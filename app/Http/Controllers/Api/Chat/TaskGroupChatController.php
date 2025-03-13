<?php

namespace App\Http\Controllers\Api\Chat;

use Illuminate\Http\Request;
use App\Models\TaskGroup;
use App\Models\TaskGroupMember;
use App\Models\TaskGroupMessage;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;
use App\Events\NewTaskGroupChatMessages;
use App\Http\Controllers\Controller;

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
        // Kiểm tra xem user đã có trong nhóm chưa
        $exists = TaskGroupMember::where('group_id', $taskGroupId)
            ->where('user_id', $userId)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'User is already a member of the group',
                'group_id' => $taskGroupId,
                'user_id' => $userId,
            ], 400);
        }

        // Thêm thành viên mới với quyền mặc định là "member"
        $member = TaskGroupMember::create([
            'group_id' => $taskGroupId,
            'user_id'       => $userId,
            'role'          => 'member',
        ]);

        return response()->json([
            'message' => 'Member has been successfully added to the group',
            'data'    => $member,
        ], 201);
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
            'file' => 'nullable|file|max:5120', // Tối đa 5MB
        ]);

        $group = TaskGroup::findOrFail($request->group_id);

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
        ]);

        // Load thông tin người gửi (name, avatar)
        $message->load('user:id,name,avatar');

        // 🔥 Gửi event real-time qua Pusher
        broadcast(new NewTaskGroupChatMessages($message))->toOthers();

        return response()->json([
            'message' => 'Message sent successfully!',
            'data' => [
                'id' => $message->id,
                'group_id' => $message->group_id, // Đã sửa tên đúng
                'user_id' => $message->user_id,
                'message' => $message->message,
                'file' => $message->file ? asset('storage/' . $message->file) : null,
                'created_at' => $message->created_at->toDateTimeString(),
                'user' => [
                    'name' => $message->user->name,
                    'avatar' => $message->user->avatar ? asset('storage/' . $message->user->avatar) : null,
                ],
            ]
        ], 201);
    }



    // Get all messages in the group
    public function getMessages($groupId)
    {
        if (!TaskGroupMember::where(['group_id' => $groupId, 'user_id' => Auth::id()])->exists()) {
            return response()->json(['message' => 'You are not a member of this group!'], 403);
        }

        $messages = TaskGroupMessage::where('group_id', $groupId)
            ->with([
                'user:id,name,avatar' // Lấy thêm tên và avatar của user
            ])
            ->latest()
            ->paginate(20);

        return response()->json($messages);
    }
}
