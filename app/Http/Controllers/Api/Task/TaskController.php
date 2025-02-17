<?php

namespace App\Http\Controllers\api\Task;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Task;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    protected function handleJsonStringData($data){
        // Handling reminder if it's a JSON string
        if (!empty($data['reminder']) && is_string($data['reminder'])) {
            $data['reminder'] = json_decode($data['reminder'], true);
        }

        // Handling user_ids if it's a JSON string
        if (!empty($data['user_ids']) && is_string($data['user_ids'])) {
            $data['user_ids'] = json_decode($data['user_ids'], true);
        }

        // Handling exclude_time if it's a JSON string
        if (!empty($data['exclude_time']) && is_string($data['exclude_time'])) {
            $data['exclude_time'] = json_decode($data['exclude_time'], true);
        }

        // Handling day_of_week if it's a JSON string
        if (!empty($data['day_of_week']) && is_string($data['day_of_week'])) {
            $data['day_of_week'] = json_decode($data['day_of_week'], true);
        }

        // Handling day_of_month if it's a JSON string
        if (!empty($data['day_of_month']) && is_string($data['day_of_month'])) {
            $data['day_of_month'] = json_decode($data['day_of_month'], true);
        }

        // Handling by_month if it's a JSON string
        if (!empty($data['by_month']) && is_string($data['by_month'])) {
            $data['by_month'] = json_decode($data['by_month'], true);
        }

        // if is_all_day = 1, set start_time to 00:00:00 and end_time to 23:59:59
        if (!empty($data['is_all_day'] && $data['is_all_day'] == 1)) {
            $data['start_time'] = date('Y-m-d 00:00:00', strtotime($data['start_time']));
            $data['end_time'] = date('Y-m-d 23:59:59', strtotime($data['end_time']));
        }

        return $data;
    }

    public function index()
    {
        //Check is user logged in
        if (Auth::check()) {
            $user_id = Auth::user()->id;
        }

        $tasks = Task::select(
            'tasks.*', 
            'colors.code as color_code', 
            'timezones.name as timezone_code'
        )
        ->leftJoin('colors', 'tasks.color_id', '=', 'colors.id')
        ->leftJoin('timezones', 'tasks.timezone_id', '=', 'timezones.id')
        ->where(function ($query) use ($user_id) {
            $query->where('tasks.user_id', $user_id)
                ->orWhereRaw("
                    EXISTS (
                        SELECT 1 FROM JSON_TABLE(
                            tasks.user_ids, '$[*]' COLUMNS (
                                user_id INT PATH '$.user_id',
                                status INT PATH '$.status'
                            )
                        ) AS jt
                        WHERE jt.user_id = ? AND jt.status = 1
                    )
                ", [$user_id]);
        })
        ->get();
        

        // Trả về view và truyền dữ liệu vào

        return response()->json([
            'code'      =>  200,
            'message'   =>  'Fetching Data successfully',
            'data'      =>  $tasks
        ], 200);
    }

    public function updateTask(Request $request, $id)
    {
        $code = $request->code;

        $data = $request->validate([
            'color_id'          => 'required',
            'timezone_id'       => 'required',
            'title'             => 'required|max:255',
            'description'       => 'nullable',
            'start_time'        => 'required|date_format:Y-m-d H:i:s',
            'end_time'          => 'nullable|date_format:Y-m-d H:i:s',
            'is_reminder'       => 'nullable|boolean',
            'reminder'          => 'nullable', //JSON
            'is_done'           => 'nullable|boolean',
            'user_ids'          => 'nullable', //JSON
            'location'          => 'nullable|string|max:255',
            'type'              => 'required',
            'is_all_day'        => 'nullable|boolean',
            'is_repeat'         => 'nullable|boolean',
            'is_busy'           => 'nullable|boolean',
            'path'              => 'nullable',
            'date_space'        => ['nullable', Rule::in('su', 'mo', 'tu', 'we', 'th', 'fr', 'sa')],
            'repeat_space'      => 'nullable|numeric',
            'end_repeat'        => 'nullable|date_format:Y-m-d H:i:s',
            'total_repeat_time' => 'nullable|numeric',
            'day_of_week'       => 'nullable', //JSON
            'day_of_month'      => 'nullable', //JSON
            'by_month'          => 'nullable', //JSON
            'exclude_time'      => 'nullable', //JSON
        ]);

        $data['user_id'] = Auth::id();

        $task = Task::find($id);

        //Kiểm tra xem có tìm được task với id truyền vào không
        if(!$task){
            return response()->json([
                'code'    => 500,
                'message' => 'Failed to get task',
                'error'   => 'Cannot get task',
            ], 500);
        }

        switch ($code) {
            //Update when event dont have reapet
            case 'EDIT_N':
                try {
                    $data = $this->handleJsonStringData($data);
                    
                    $task->update($data);
        
                    return response()->json([
                        'code'    => 200,
                        'message' => 'Task updated successfully',
                        'data'    => $task,
                    ], 200);
                } catch (\Exception $e) {
                    return response()->json([
                        'code'    => 500,
                        'message' => 'Failed to updated task',
                        'error'   => $e->getMessage(),
                    ], 500);
                }

            case 'EDIT_1':
                try {
                    $parent_path =  $task['path'];

                    $data = $this->handleJsonStringData($data);
                    
                    $new_task = Task::create($data);

                    //Save new path for last insert task
                    $new_path = $parent_path . $new_task->id . '/';
                    $new_task->path = $new_path;
                    $new_task->save();
        
                    return response()->json([
                        'code'    => 200,
                        'message' => 'Task updated successfully',
                        'data'    => $new_task,
                    ], 200);
                } catch (\Exception $e) {
                    return response()->json([
                        'code'    => 500,
                        'message' => 'Failed to updated task',
                        'error'   => $e->getMessage(),
                    ], 500);
                }

            case 'EDIT_1B':
                try {
                    $data = $this->handleJsonStringData($data);
                    
                    $task->update($data);
        
                    return response()->json([
                        'code'    => 200,
                        'message' => 'Task updated successfully',
                        'data'    => $task,
                    ], 200);
                } catch (\Exception $e) {
                    return response()->json([
                        'code'    => 500,
                        'message' => 'Failed to updated task',
                        'error'   => $e->getMessage(),
                    ], 500);
                }

            case 'EDIT_A':
                try {
                    $data = $this->handleJsonStringData($data);
                    
                    $task->update($data);
        
                    return response()->json([
                        'code'    => 200,
                        'message' => 'Task updated successfully',
                        'data'    => $task,
                    ], 200);
                } catch (\Exception $e) {
                    return response()->json([
                        'code'    => 500,
                        'message' => 'Failed to updated task',
                        'error'   => $e->getMessage(),
                    ], 500);
                }

            default:    
                return response()->json([
                    'code'      =>  400,
                    'message'   =>  'Invalid code',
                    'data'      =>  ''
                ]);
            
        }
    }

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
