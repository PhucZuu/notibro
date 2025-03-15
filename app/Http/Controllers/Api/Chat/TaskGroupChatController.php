<?php

namespace App\Http\Controllers\Api\Chat;

use Illuminate\Http\Request;
use App\Models\TaskGroup;
use App\Models\TaskGroupMember;
use App\Models\TaskGroupMessage;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;
use App\Events\Chat\NewTaskGroupChatMessages;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;

class TaskGroupChatController extends Controller
{
    // Create a chat group for a Task
    public function createGroup(int $taskId, int $userId)
    {
        // Kiểm tra nhóm đã tồn tại chưa
        if (TaskGroup::where('task_id', $taskId)->exists()) {
            return response()->json(['message' => 'Chat group already exists for this Task'], 400);
        }

        // Tạo nhóm chat với chủ nhóm
        $group = TaskGroup::create(attributes: [
            'task_id'  => $taskId,
            'created_by' => $userId,
            'name'     => 'Task Chat - ' . $taskId,
        ]);

        // Thêm người tạo vào nhóm với quyền ADMIN
        TaskGroupMember::create([
            'group_id' => $group->id,
            'user_id'  => $userId,
            'role'     => 'admin', // Người tạo là admin
        ]);

        return response()->json([
            'message' => 'Chat group has been created successfully',
        ], 201);
    }

    // Add a member to the group
    public function addMember(int $taskGroupId, int $userId)
    {
        try {
            // Lấy ra group theo task group id
            $group = TaskGroup::where('task_id',$taskGroupId)->first();
    
            if (!$group) {
                return response()->json(['message' => 'Group not found'], 404);
            }
            // Kiểm tra xem user đã có trong nhóm chưa
            $exists = TaskGroupMember::where('group_id', $group->id)
                ->where('user_id', $userId)
                ->exists();
    
            if ($exists) {
                return response()->json([
                    'message'  => 'User is already a member of the group',
                    'group_id' => $taskGroupId,
                    'user_id'  => $userId,
                ], 400);
            }
        
            // Thêm thành viên mới với quyền mặc định là "member"
            $member = TaskGroupMember::create([
                'task_id' => $taskGroupId,
                'group_id' => $group->id,
                'user_id' => $userId,
                'role'    => 'member',
            ]);
    
    
            return response()->json([
                'message' => 'Member has been successfully added to the group',
                'data'    => $member,
            ], 201);
    
        } catch (\Exception $e) {
            Log::error('Unexpected error when adding member', [
                'task_group_id' => $taskGroupId,
                'user_id'       => $userId,
                'error'         => $e->getMessage(),
                'trace'         => $e->getTraceAsString(),
            ]);
        }
    }

    public function removeMember($taskGroupId, $userId)
    {
        $adminId = Auth::id();

        // Kiểm tra xem người gọi API có phải là admin của nhóm không
        $admin = TaskGroupMember::where('group_id', $taskGroupId)
            ->where('user_id', $adminId)
            ->where('role', 'admin')
            ->first();

        if (!$admin) {
            return response()->json(['message' => 'Only the group admin can remove members!'], 403);
        }

        // Kiểm tra xem người cần xóa có trong nhóm không
        $member = TaskGroupMember::where('group_id', $taskGroupId)
            ->where('user_id', $userId)
            ->first();

        if (!$member) {
            return response()->json(['message' => 'User is not a member of this group!'], 404);
        }

        // Không cho phép admin tự xóa chính mình
        if ($member->role === 'admin') {
            return response()->json(['message' => 'Admin cannot remove themselves!'], 403);
        }

        // Xóa thành viên khỏi nhóm
        $member->delete();

        return response()->json(['message' => 'Member has been removed from the group'], 200);
    }


    // Send a message in the group (Dispatch Event)
    public function sendMessage(Request $request)
    {
        $request->validate([
            'group_id' => 'required|exists:task_groups,id',
            'message' => 'nullable|string',
            'file' => 'nullable|file|max:5120', // Max 5MB
            'reply_to' => 'nullable|exists:task_group_messages,id',
        ]);

        $group = TaskGroup::find($request->group_id);

        // Kiểm tra xem user có trong nhóm không
        if (!TaskGroupMember::where('group_id', $group->id)->where('user_id', Auth::id())->exists()) {
            return response()->json(['message' => 'You are not a member of this group!'], 403);
        }

        // Xử lý file
        $filePath = $request->hasFile('file')
            ? $request->file('file')->store('chat_files', 'public')
            : null;

        // Tạo tin nhắn
        $message = TaskGroupMessage::create([
            'group_id' => $group->id,
            'user_id' => Auth::id(),
            'message' => $request->message ?? null,
            'file' => $filePath,
            'reply_to' => $request->reply_to, // Lưu ID tin nhắn được trả lời
        ]);

        // Load thông tin người gửi và tin nhắn gốc (nếu có)
        $message->load(['user:id,first_name,last_name,avatar', 'replyMessage']);

        // 🔥 Gửi event real-time qua Pusher
        broadcast(new NewTaskGroupChatMessages($message))->toOthers();

        return response()->json([
            'code' => 200,
            'message' => 'Message sent successfully!',
            'data' => [
                'id' => $message->id,
                'group_id' => $message->group_id,
                'user_id' => $message->user_id,
                'message' => $message->message,
                'file' => $message->file ? asset('storage/' . $message->file) : null,
                'created_at' => $message->created_at->toDateTimeString(),
                'user' => [
                    'first_name' => $message->user->first_name,
                    'last_name' => $message->user->last_name,
                    'avatar' => $message->user->avatar ? $message->user->avatar : null,
                ],
                'reply_to' => $message->reply_to,
                'reply_message' => $message->replyMessage ? [
                    'id' => $message->replyMessage->id,
                    'message' => $message->replyMessage->message,
                    'user' => [
                        'first_name' => $message->user->first_name,
                        'last_name' => $message->user->last_name,
                    ],
                ] : null,
            ],
        ], 201);
    }

    // Get all messages in the group
    public function getMessages($taskId)
    {
        // Tìm group dựa vào task_id (giả sử group_id gắn với task_id)
        $group = TaskGroup::where('task_id', $taskId)->first();

        if (!$group) {
            return response()->json(['message' => 'Group not found for this task!'], 404);
        }

        // Kiểm tra user có trong group không
        if (!TaskGroupMember::where(['group_id' => $group->id, 'user_id' => Auth::id()])->exists()) {
            return response()->json(['message' => 'You are not a member of this group!'], 403);
        }

        // Lấy danh sách tin nhắn của nhóm
        $messages = TaskGroupMessage::where('group_id', $group->id)
            ->with([
                'user:id,first_name,last_name,avatar',
                'replyMessage:id,group_id,user_id,message,reply_to,created_at',
                'replyMessage.user:id,first_name,last_name'
            ])
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'group' => [
                'id' => $group->id,
                'task_id' => $group->task_id,
                'name' => $group->name, // Nếu group có tên
                'created_at' => $group->created_at,
            ],
            'messages' => $messages
        ]);
    }
}
