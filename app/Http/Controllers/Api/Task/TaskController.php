<?php

namespace App\Http\Controllers\api\Task;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    public function index() {}

    public function show($id) {}

    public function store(Request $request)
    {

        //validate request
        $data = $request->validate([
            'color_id'     => 'required',
            'timezone_id'  => 'required',
            'title'        => 'required|max_length:255',
            'description'  => 'nullable',
            'start_time'   => 'required|date_format:Y-m-d H:i:s',
            'end_time'     => 'nullable|date_format:Y-m-d H:i:s',
            'is_reminder'  => 'required|boolean',
            'reminder'     => 'nullable',
            'is_done'      => 'required',
            'user_ids'     => 'nullable',
            'location'     => 'nullable|string|max:255',
            'type'         => 'required',
            'is_all_day'   => 'required|boolean',
            'is_repeat'    => 'required|boolean',
            'is_busy'      => 'required|boolean',
            'path'         => 'nullable',
            'frequency'    => 'nullable',
            'date_space'   => 'nullable',
            'repeat_space' => 'nullable',
            'end_repeat'   => 'nullable|date_format:Y-m-d H:i:s',
            'day_of_week'  => 'nullable',
            'exclude_time' => 'nullable',
        ]);

        try {
            $data['user_id'] = Auth::id();

            // Handling reminder if it's a JSON string
            if (!empty($data['reminder']) && is_string($data['reminder'])) {
                $data['reminder'] = json_decode($data['reminder'], true);
            }

            // Handling user_id if it's a JSON string
            // if (!empty($data['user_ids']) && is_string($data['user_ids'])) {
            //     $data['user_ids'] = json_decode($data['user_ids'], true);
            // }

            // Handling exclude_time if it's a JSON string
            if (!empty($data['exclude_time']) && is_string($data['exclude_time'])) {
                $data['exclude_time'] = json_decode($data['exclude_time'], true);
            }

            // if is_all_day = 1, set start_time to 00:00:00 and end_time to 23:59:59
            if (!empty($data['is_all_day'] && $data['is_all_day'] == 1)) {
                $data['start_time'] = date('Y-m-d 00:00:00', strtotime($data['start_time']));
                $data['end_time'] = date('Y-m-d 23:59:59', strtotime($data['end_time']));
            }
    
            $task = Task::create($data);

            return response()->json([
                'code'    => 200,
                'message' => 'Task created successfully',
                'data'    => $task,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'code'    => 500,
                'message' => 'Failed to create task',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id) {}

    public function destroy($id)
    {
        try {
            $task = Task::findOrFail($id);

            // Lấy danh sách user_ids hiện tại
            $userIds = $task->user_ids ?? [];

            // Kiểm tra nếu người dùng hiện tại có tham gia
            if (!in_array(Auth::id(), $userIds)) {
                return response()->json([
                    'code'    => 404,
                    'message' => 'You are not a participant of this task.',
                ], 404);
            }

            // Xóa user khỏi danh sách
            $userIds = array_filter($userIds, function ($userId) {
                return $userId !== Auth::id();
            });

            // Cập nhật lại danh sách user_ids
            $task->user_ids = array_values($userIds);
            $task->save();

            return response()->json([
                'code'    => 200,
                'message' => 'You have left the task.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'code'    => 500,
                'message' => 'Failed to leave the task.',
                'data'    => $e->getMessage(),
            ], 500);
        }
    }
}
