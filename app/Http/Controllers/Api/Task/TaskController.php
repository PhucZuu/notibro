<?php

namespace App\Http\Controllers\Api\Task;

use App\Http\Controllers\Controller;
use App\Models\Reminder;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Task;
use App\Models\Timezone;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TaskController extends Controller
{
    protected function handleJsonStringData($data)
    {
        // Handling reminder if it's a JSON string
        if (!empty($data['reminder']) && is_string($data['reminder'])) {
            $data['reminder'] = json_decode($data['reminder'], true);
        }

        // Handling attendees if it's a JSON string
        if (!empty($data['attendees']) && is_string($data['attendees'])) {
            $data['attendees'] = json_decode($data['attendees'], true);
        }

        // Handling exclude_time if it's a JSON string
        if (!empty($data['exclude_time']) && is_string($data['exclude_time'])) {
            $data['exclude_time'] = json_decode($data['exclude_time'], true);
        }

        // Handling byweekday if it's a JSON string
        if (!empty($data['byweekday']) && is_string($data['byweekday'])) {
            $data['byweekday'] = json_decode($data['byweekday'], true);
        }

        // Handling bymonthday if it's a JSON string
        if (!empty($data['bymonthday']) && is_string($data['bymonthday'])) {
            $data['bymonthday'] = json_decode($data['bymonthday'], true);
        }

        // Handling bymonth if it's a JSON string
        if (!empty($data['bymonth']) && is_string($data['bymonth'])) {
            $data['bymonth'] = json_decode($data['bymonth'], true);
        }

        // Handling bysetpos if it's a JSON string
        if (!empty($data['bysetpos']) && is_string($data['bysetpos'])) {
            $data['bysetpos'] = json_decode($data['bysetpos'], true);
        }
        return $data;
    }

    protected function handleLogicData($data)
    {
        // if is_all_day = 1, set start_time to 00:00:00 and end_time to 23:59:59
        if (!empty($data['is_all_day'] && $data['is_all_day'] == 1)) {
            $data['start_time'] = date('Y-m-d 00:00:00', strtotime($data['start_time']));
            $data['end_time'] = date('Y-m-d 23:59:59', strtotime($data['end_time']));
        }

        return $data;
    }

    public function index()
    {
        if (Auth::check()) {
            $user_id = Auth::user()->id;
        }

        $setting = Setting::select('timezone_code')
            ->where('user_id', '=', $user_id)
            ->first();

        $tasks = Task::select('*')
            ->where(function ($query) use ($user_id) {
                $query->where('user_id', $user_id)
                    ->orWhereRaw("
                    EXISTS (
                        SELECT 1 FROM JSON_TABLE(
                            attendees, '$[*]' COLUMNS (
                                user_id INT PATH '$.user_id',
                                status VARCHAR(20) PATH '$.status'
                            )
                        ) AS jt
                        WHERE jt.user_id = ? AND jt.status = 'yes'
                    )
                ", [$user_id]);
            })
            ->get();

        foreach ($tasks as $task) {
            $timezone_code = $task->timezone_code;

            $task->rrule = [
                'freq'              => $task->freq,
                'interval'          => $task->interval,
                'until'             => Carbon::parse($task->until, 'UTC'),
                // 'set_until'         => Carbon::parse($task->until, 'UTC')->setTimezone($setting->timezone_code),
                'count'             => $task->count,
                'byweekday'         => $task->byweekday,
                'bymonthday'        => $task->bymonthday,
                'bymonth'           => $task->bymonth,
                'bysetpos'          => $task->bysetpos,
            ];

            $task->start_time   = Carbon::parse($task->start_time, 'UTC');
            $task->end_time     = Carbon::parse($task->end_time, 'UTC');

            // $task->set_start_time   = Carbon::parse($task->start_time, 'UTC')->setTimezone($setting->timezone_code);
            // $task->set_end_time     = Carbon::parse($task->end_time, 'UTC')->setTimezone($setting->timezone_code);

            if ($task->exclude_time && count($task->exclude_time) > 0) {
                $cal_exclude_time = array_map(function ($date) use($timezone_code) {
                    return Carbon::parse($date, 'UTC');
                    // ->setTimezone($timezone_code);
                }, $task->exclude_time);
                
                $task->exclude_time = $cal_exclude_time;
            }

            if ($task->attendees) {  
                foreach ($task->attendees as $attendee) {  
                    $user = User::select('first_name', 'last_name', 'email', 'avatar')  
                        ->where('id', $attendee['user_id'])  
                        ->first();  
    
                    if ($user) {  
                        $attendeesDetails[] = [  
                            'user_id'    => $attendee['user_id'],  
                            'first_name' => $user->first_name,  
                            'last_name'  => $user->last_name,  
                            'email'      => $user->email,  
                            'avatar'     => $user->avatar,  
                            'status'     => $attendee['status'],  
                            'role'       => $attendee['role'], 
                        ];  
                    }  
                }  

                $task->attendees = $attendeesDetails;
    
                $attendeesDetails = [];
            }
            
            unset(
                $task->freq,
                $task->interval,
                $task->until,
                $task->count,
                $task->byweekday,
                $task->bymonthday,
                $task->bymonth,
                $task->bysetpos
            );
        }

        // Trả về view và truyền dữ liệu vào
        return response()->json([
            'code'      =>  200,
            'message'   =>  'Fetching Data successfully',
            'data'      =>  $tasks,
        ], 200);
    }

    public function updateTask(Request $request, $id)
    {
        $code = $request->code;

        $data = $request->validate([
            'color_code'        => 'required',
            'timezone_code'     => 'required',
            'title'             => 'required|max:255',
            'description'       => 'nullable',
            'start_time'        => 'required|date_format:Y-m-d H:i:s',
            'end_time'          => 'nullable|date_format:Y-m-d H:i:s',
            'is_reminder'       => 'nullable|boolean',
            'reminder'          => 'nullable', //JSON
            'is_done'           => 'nullable|boolean',
            'attendees'         => 'nullable', //JSON
            'location'          => 'nullable|string|max:255',
            'type'              => 'required',
            'is_all_day'        => 'nullable|boolean',
            'is_repeat'         => 'nullable|boolean',
            'is_busy'           => 'nullable|boolean',
            'path'              => 'nullable',
            'freq'              => ['nullable', Rule::in('SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA')],
            'interval'          => 'nullable|numeric',
            'until'             => 'nullable|date_format:Y-m-d H:i:s',
            'count'             => 'nullable|numeric',
            'byweekday'         => 'nullable', //JSON
            'bymonthday'        => 'nullable', //JSON
            'bymonth'           => 'nullable', //JSON
            'bysetpos'           => 'nullable', //JSON
            'exclude_time'      => 'nullable', //JSON
            'parent_id'         => 'nullable',
        ]);

        $data['user_id'] = Auth::id();

        $data = $this->handleJsonStringData($data);

        $data = $this->handleLogicData($data);

        $task = Task::find($id);

        //Kiểm tra xem có tìm được task với id truyền vào không
        if (!$task) {
            return response()->json([
                'code'    => 404,
                'message' => 'Failed to get task',
                'error'   => 'Cannot get task',
            ], 404);
        }

        switch ($code) {
                //Update when event dont have reapet
            case 'EDIT_N':
                try {
                    $task->update($data);

                    return response()->json([
                        'code'    => 200,
                        'message' => 'Task updated successfully',
                        'data'    => $task,
                    ], 200);
                } catch (\Exception $e) {
                    Log::error($e->getMessage());

                    return response()->json([
                        'code'    => 500,
                        'message' => 'Failed to updated task',
                    ], 500);
                }

            case 'EDIT_1':
                try {
                    //Add new task for 1 day change
                    $data->parent_id = $id;
                    $new_task = Task::create($data);

                    //Push enddate to exclude_time array of task
                    $endDate = Carbon::parse($new_task->start_time)->subDay()->endOfDay();
                    $task->exclude_time[] = $endDate;
                    $task->save();

                    return response()->json([
                        'code'    => 200,
                        'message' => 'Task updated successfully',
                        'data'    => $new_task,
                    ], 200);
                } catch (\Exception $e) {
                    Log::error($e->getMessage());

                    return response()->json([
                        'code'    => 500,
                        'message' => 'Failed to updated task',
                    ], 500);
                }

            case 'EDIT_1B':
                try {
                    //Add new task for following change
                    $new_task = Task::create($data);

                    $task->until = Carbon::parse($new_task->start_time)->subDay()->endOfDay();
                    $task->save();

                    //Delete all task that have parent_id = $task->id and start_time > $ta
                    $relatedTasks = Task::where('parent_id', $task->id)
                        ->where('start_time', '>=', $task->until)
                        ->get();
                    foreach ($relatedTasks as $relatedTask) {
                        $relatedTask->update($data);
                    }

                    return response()->json([
                        'code'    => 200,
                        'message' => 'Task updated successfully',
                        'data'    => $task,
                    ], 200);
                } catch (\Exception $e) {
                    Log::error($e->getMessage());

                    return response()->json([
                        'code'    => 500,
                        'message' => 'Failed to updated task',
                    ], 500);
                }

            case 'EDIT_A':
                try {
                    //Update current Task
                    $task->update($data);

                    // Update all child Task
                    $relatedTasks = Task::where('parent_id', $task->id)
                        ->orWhere('parent_id', $task->parent_id)
                        ->get();
                    foreach ($relatedTasks as $relatedTask) {
                        $relatedTask->update($data);
                    }

                    // Update all parent task
                    $parentTask = Task::find(id: $task->parent_id);
                    if ($parentTask) {
                        $parentTask->update($data);
                    }

                    return response()->json([
                        'code'    => 200,
                        'message' => 'Task updated successfully',
                        'data'    => $task,
                    ], 200);
                } catch (\Exception $e) {
                    Log::error($e->getMessage());

                    return response()->json([
                        'code'    => 500,
                        'message' => 'Failed to updated task',
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

    public function show($id)
    {
        $task = Task::with('user')
            ->select('id', 'user_id', 'title', 'description', 'start_time', 'end_time', 'location', 'timezone_code', 'attendees')
            ->where('type', 'event')
            ->where(function ($query) use ($id) {
                $query->where('id', $id)
                    ->orWhere('parent_id', $id);
            })
            ->first();

        if (!$task) {
            return response()->json([
                'code' => 404,
                'message' => 'The event owner may have been removed',
            ]);
        }

        $attendees = collect($task->attendees);

        $userIds = $attendees->pluck('user_id')->toArray();

        $users = User::whereIn('id', $userIds)->get();

        if ($users) {
            $attendeesInfo = $attendees->map(function ($attendee) use ($users) {
                $user = $users->firstWhere('id', $attendee['user_id']);
                return [
                    'user_id' => $attendee['user_id'],
                    'name'  => $user->first_name . ' ' . $user->last_name,
                    'email' => $user->email,
                    'avatar' => $user->avatar ?? null,
                ];
            });
        }

        return response()->json([
            'code'   => 200,
            'message' => 'Success',
            'data'   => [
                'task' => $task,
                'attendees' => $attendeesInfo,
                'quantityAttendee' => count($attendeesInfo),
            ]
        ]);
    }

    public function store(Request $request)
    {

        //validate request
        $data = $request->validate([
            'color_code'        => 'required',
            'timezone_code'     => 'required',
            'title'             => 'required|max:255',
            'description'       => 'nullable',
            'start_time'        => 'required|date_format:Y-m-d H:i:s',
            'end_time'          => 'nullable|date_format:Y-m-d H:i:s',
            'is_reminder'       => 'nullable|boolean',
            'reminder'          => 'nullable', //JSON
            'is_done'           => 'nullable|boolean',
            'attendees'         => 'nullable', //JSON
            'location'          => 'nullable|string|max:255',
            'type'              => 'required',
            'is_all_day'        => 'nullable|boolean',
            'is_repeat'         => 'nullable|boolean',
            'is_busy'           => 'nullable|boolean',
            'path'              => 'nullable',
            'freq'              => ['nullable', Rule::in('su', 'mo', 'tu', 'we', 'th', 'fr', 'sa')],
            'interval'          => 'nullable|numeric',
            'until'             => 'nullable|date_format:Y-m-d H:i:s',
            'count'             => 'nullable|numeric',
            'byweekday'         => 'nullable', //JSON
            'bymonthday'        => 'nullable', //JSON
            'bymonth'           => 'nullable', //JSON
            'bysetpos'           => 'nullable', //JSON
            'exclude_time'      => 'nullable', //JSON
            'parent_id'         => 'nullable',
        ]);

        try {
            $data['user_id'] = Auth::id();

            $data = $this->handleJsonStringData($data);

            $data = $this->handleLogicData($data);

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

    public function destroy(Request $request, $id)
    {
        $code = $request->code;

        $task = Task::find($id);

        if (!$task) {
            return response()->json([
                'code'    => 404,
                'message' => 'Task not found',
            ], 404);
        }

        $attendees = collect($task->attendees);
        $attendee = $attendees->firstWhere('user_id', Auth::id());

        if ($task->user_id  === Auth::id() || ($attendee && $attendee['role'] === 'edit')) {
            switch ($code) {
                case 'DEL_N':
                    try {
                        $task->delete();
                        return response()->json([
                            'code'    => 200,
                            'message' => 'Delete task successfully',
                        ], 200);
                    } catch (\Exception $e) {
                        Log::error($e->getMessage());

                        return response()->json([
                            'code'    => 500,
                            'message' => 'Failed to delete task',
                        ], 500);
                    }
                case 'DEL_1':
                    try {
                        // select day deleted task
                        $currentDate = $request->date;

                        // select exclude_time of task
                        if($task->exclude_time) {
                            $excludeTime = $task->exclude_time;
                        } else {
                            $excludeTime = [];
                        }

                        // add current date to exclude_time
                        if (!in_array($currentDate, $excludeTime)) {
                            $excludeTime[] = $currentDate;
                        }

                        // Encode back to JSON before saving
                        $task->exclude_time = $excludeTime;
                        $task->save();

                        return response()->json([
                            'code'=> 200,
                            'message' => 'Delete task successfully 1'
                        ], 200);
                    } catch (\Exception $e) {
                        Log::error($e->getMessage());

                        return response()->json([
                            'code'    => 500,
                            'message' => 'Failed to delete task',
                        ], 500);
                    }
                case 'DEL_1B':
                    try {
                        $currentDate = $request->date;

                        $task->until = $currentDate;
                        
                        $task->save();

                        // Xoá các task liên quan về sau
                        $tasksChild = Task::where('start_time', '>', $currentDate)
                                            ->where('id', $task->id)
                                            ->orWhere('parent_id', $task->id)
                                            ->get(); 

                        if(!$tasksChild->isEmpty()) {
                            foreach($tasksChild as $task) {
                                $task->delete();
                            }
                        }

                        return response()->json([
                            'code'=> 200,
                            'message' => 'Delete tasks and following tasks successfully'
                        ], 200);
                    } catch (\Exception $e) {
                        Log::error($e->getMessage());

                        return response()->json([
                            'code'    => 500,
                            'message' => 'Failed to delete task and following tasks',
                        ], 500);
                    }
                case 'DEL_A':
                    // select all task with path first char is the same as the first char of the task to delete
                    try {
                        // delete all tasks
                        Task::where('id', $task->id)
                            ->orWhere('parent_id',   $task->id)
                            ->delete();

                        return response()->json([
                            'code'=> 200,
                            'message' => 'Delete all tasks successfully'
                        ], 200);
                    } catch (\Exception $e) {
                        Log::error($e->getMessage());

                        return response()->json([
                            'code'    => 500,
                            'message' => 'Failed to delete all task',
                        ], 500);
                    }

                default:
                    return response()->json([
                        'code'      =>  400,
                        'message'   =>  'Invalid code',
                        'data'      =>  ''
                    ]);
            }
        } else {
            return response()->json([
                'code'=> 401,
                'message'=> 'You do not have permission to edit this event',
            ]);
        }
    }

    public function acceptInvite(Request $request, $id)
    {
        $user = auth()->user();

        $task = Task::with('user.setting')
            ->where('type', 'event')
            ->where(function ($query) use ($id) {
                $query->where('id', $id)
                    ->orWhere('parent_id', $id);
            })
            ->first();

        if (!$task) {
            return response()->json([
                'code' => 404,
                'message' => 'The event owner may have been removed',
            ]);
        }

        if ($user->id == $task->user_id) {
            return response()->json([
                'code' => 400,
                'message' => 'Can not invite yourself',
            ]);
        }

        DB::beginTransaction();

        try {
            // Kiểm tra người dùng đã tồn tại trong attendees chưa
            if (is_array($task->attendees) && in_array($user->id, array_column($task->attendees, 'user_id'))) {
                return response()->json([
                    'code'    => 409,
                    'message' => 'You have already accepted this event',
                ]);
            } else {
                $attendee = [
                    'role' => 'viewer',
                    'status' => 'yes',
                    'user_id' => $user->id
                ];

                if(!is_array($task->attendees)) {
                    $attendees = [];    
                } else {
                    $attendees = $task->attendees;
                }
                array_push($attendees, $attendee);
                $task->attendees = $attendees;

                Reminder::insert([
                    'title'   => 'Event notification',
                    'user_id' => $task->user_id,
                    'message' => 'User ' . $user->first_name . ' ' . $user->last_name . ' has accepted to participate in your event: ' . $task->title,
                    'type'    => 'event',
                    'sent_at' => Carbon::now($task->user->setting->timezone_code),
                ]);
            }

            $task->save();

            DB::commit();

            return response()->json([
                'code' => 200,
                'message' => 'Participate in the event successfully',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred',
            ], 500);
        }
    }

    public function refuseInvite(Request $request, $id)
    {
        $user = auth()->user();

        $task = Task::with('user.setting')
            ->where('type', 'event')
            ->where(function ($query) use ($id) {
                $query->where('id', $id)
                    ->orWhere('parent_id', $id);
            })
            ->first();

        if (!$task) {
            return response()->json([
                'code'    => 404,
                'message' => 'Event not found',
            ], 404);
        }

        DB::beginTransaction();

        try {
            // Kiểm tra người dùng đã tồn tại trong attendees chưa
            if (in_array($user->id, array_column($task->attendees, 'user_id'))) {
                $task->attendees = array_filter($task->attendees, function ($attendee) use ($user) {
                    return $attendee['user_id'] !== $user->id;
                });

                $task->save();
            }

            DB::commit();

            return response()->json([
                'code' => 200,
                'message' => 'You have refused to participate in this event',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred',
            ]);
        }
    }
}
