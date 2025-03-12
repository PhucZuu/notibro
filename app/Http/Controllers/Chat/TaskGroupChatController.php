<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TaskGroup;
use App\Models\TaskGroupMember;
use App\Models\TaskGroupMessage;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;
use App\Events\NewTaskGroupChatMessages;

class TaskGroupChatController extends Controller
{
    // Create a chat group for a Task
    public function createGroup(Request $request)
    {
        $request->validate(['task_id' => 'required|exists:tasks,id']);

        if (TaskGroup::where('task_id', $request->task_id)->exists()) {
            return response()->json(['message' => 'Chat group already exists for this Task'], 400);
        }

        $group = TaskGroup::create([
            'task_id' => $request->task_id,
            'owner_id' => Auth::id(),
        ]);

        TaskGroupMember::create([
            'task_group_id' => $group->id,
            'user_id' => Auth::id(),
        ]);

        return response()->json(['message' => 'Chat group has been created', 'group' => $group], 201);
    }

    // Add a member to the group
    public function addMember(Request $request)
    {
        $request->validate([
            'task_group_id' => 'required|exists:task_groups,id',
            'user_id' => 'required|exists:users,id',
        ]);

        if (TaskGroupMember::where('task_group_id', $request->task_group_id)->where('user_id', $request->user_id)->exists()) {
            return response()->json(['message' => 'User is already in the group'], 400);
        }

        TaskGroupMember::create([
            'task_group_id' => $request->task_group_id,
            'user_id' => $request->user_id,
        ]);

        return response()->json(['message' => 'Member has been added to the group'], 201);
    }

    // Send a message in the group (Dispatch Event)
    public function sendMessage(Request $request)
    {
        $request->validate([
            'task_group_id' => 'required|exists:task_groups,id',
            'message' => 'required|string',
        ]);

        $group = TaskGroup::findOrFail($request->task_group_id);

        if (!TaskGroupMember::where('task_group_id', $group->id)->where('user_id', Auth::id())->exists()) {
            return response()->json(['message' => 'You are not a member of this group!'], 403);
        }

        $message = TaskGroupMessage::create([
            'task_group_id' => $group->id,
            'user_id' => Auth::id(),
            'message' => $request->message,
        ]);

        // 🔥 Dispatch Event for real-time messaging
        broadcast(new NewTaskGroupChatMessages($message))->toOthers();

        return response()->json(['message' => 'Message sent successfully!', 'data' => $message], 201);
    }

    // Leave a chat group
    public function leaveGroup($groupId)
    {
        $userId = Auth::id();
        $member = TaskGroupMember::where('task_group_id', $groupId)->where('user_id', $userId)->first();

        if (!$member) {
            return response()->json(['message' => 'You are not a member of this group!'], 403);
        }

        $member->delete();

        return response()->json(['message' => 'You have left the group']);
    }

    // Delete task and chat group (Only owner can delete)
    // public function deleteTaskAndGroup($taskId)
    // {
    //     $task = Task::findOrFail($taskId);
    //     $group = TaskGroup::where('task_id', $taskId)->first();

    //     if (!$group) {
    //         return response()->json(['message' => 'No chat group found for this Task'], 404);
    //     }

    //     if ($group->owner_id !== Auth::id()) {
    //         return response()->json(['message' => 'You do not have permission to delete this chat group!'], 403);
    //     }

    //     TaskGroupMember::where('task_group_id', $group->id)->delete();
    //     TaskGroupMessage::where('task_group_id', $group->id)->delete();
    //     $group->delete();
    //     $task->delete();

    //     return response()->json(['message' => 'Task and chat group have been deleted']);
    // }
}
