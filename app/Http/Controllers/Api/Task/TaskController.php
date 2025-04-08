<?php

namespace App\Http\Controllers\Api\Task;

use App\Events\Task\TaskUpdatedEvent;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Chat\TaskGroupChatController;
use App\Mail\InviteGuestMail;
use App\Models\Reminder;
use App\Models\Setting;
use App\Models\Tag;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Task;
use App\Models\User;
use App\Notifications\NotificationEvent;
use App\Services\GetAllOccurrenceService;
use App\Services\GetNextOccurrenceService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TaskController extends Controller
{
    protected $serviceNextOcc;

    protected $serviceGetAllOcc;

    public $URL_FRONTEND;

    public function __construct()
    {
        $this->serviceNextOcc = new GetNextOccurrenceService();
        $this->serviceGetAllOcc = new GetAllOccurrenceService();
        $this->URL_FRONTEND = config('app.frontend_url');
    }

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
        // if is_all_day = 1, set start_time to 00:00:00 and end_time to 00:00:00
        if (!empty($data['is_all_day']) && $data['is_all_day'] == 1) {
            $data['start_time'] = date('Y-m-d 00:00:00', strtotime($data['start_time']));
            $data['end_time'] = date('Y-m-d 00:00:00', strtotime($data['end_time']));
        }

        if (!empty($data['start_time']) && !empty($data['timezone_code'])) {
            $data['start_time'] = Carbon::createFromFormat('Y-m-d H:i:s', $data['start_time'], $data['timezone_code'])->setTimezone('UTC');
        }

        if (!empty($data['end_time']) && !empty($data['timezone_code'])) {
            $data['end_time'] = Carbon::createFromFormat('Y-m-d H:i:s', $data['end_time'], $data['timezone_code'])->setTimezone('UTC');
            Log::info("Start time: {$data['end_time']}");
        }

        if (!empty($data['until']) && !empty($data['timezone_code'])) {
            $data['until'] = Carbon::createFromFormat('Y-m-d H:i:s', $data['until'], $data['timezone_code'])->setTimezone('UTC');
        }

        if (!empty($data['updated_date']) && !empty($data['timezone_code'])) {
            $data['updated_date'] = Carbon::createFromFormat('Y-m-d H:i:s', $data['updated_date'], $data['timezone_code'])->setTimezone('UTC');
        }

        if (!empty($data['exclude_time']) && !empty($data['timezone_code'])) {
            $startHour = $data['start_time']->hour;
            $startMinute = $data['start_time']->minute;

            $data['exclude_time'] = array_map(function ($date) use ($data, $startHour, $startMinute) {
                $excludeCarbon = Carbon::createFromFormat('Y-m-d H:i:s', $date, $data['timezone_code'])->setTimezone('UTC');

                $excludeCarbon->hour = $startHour;
                $excludeCarbon->minute = $startMinute;

                return $excludeCarbon;
            }, $data['exclude_time']);
        }

        return $data;
    }

    //Put in array in array data to get all attendees
    public function getRecipients($data)
    {
        $recipients = collect($data)
            ->flatMap(fn($task) => $task->getAttendeesForRealTime()) // Lấy tất cả attendees
            ->unique() // Loại bỏ user trùng
            ->values() // Reset key của mảng
            ->toArray();

        return $recipients;
    }

    public function sendRealTimeUpdate($data, $action)
    {
        $recipients = $this->getRecipients($data);

        event(new TaskUpdatedEvent($data, $action, $recipients));
    }

    public function index()
    {
        if (Auth::check()) {
            $user_id = Auth::user()->id;
        }

        $setting = Setting::select('timezone_code')
            ->where('user_id', '=', $user_id)
            ->first();

        $tasks = Task::select('tasks.*', 'tags.name as tag_name', 'tags.color_code as tag_color_code')
            ->leftJoin('tags', 'tasks.tag_id', '=', 'tags.id')
            ->where(function ($query) use ($user_id) {
                $query->where('tasks.user_id', $user_id)
                    ->orWhereRaw("
                    EXISTS (
                        SELECT 1 FROM JSON_TABLE(
                            attendees, '$[*]' COLUMNS (
                                user_id INT PATH '$.user_id',
                                status VARCHAR(20) PATH '$.status'
                            )
                        ) AS jt
                        WHERE jt.user_id = ?
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
                'count'             => $task->count,
                'byweekday'         => $task->byweekday,
                'bymonthday'        => $task->bymonthday,
                'bymonth'           => $task->bymonth,
                'bysetpos'          => $task->bysetpos,
            ];

            $task->start_time   = Carbon::parse($task->start_time, 'UTC');
            $task->end_time     = Carbon::parse($task->end_time, 'UTC');

            if ($task->exclude_time && count($task->exclude_time) > 0) {
                $cal_exclude_time = array_map(function ($date) use ($timezone_code) {
                    return Carbon::parse($date, 'UTC');
                }, $task->exclude_time);

                $task->exclude_time = $cal_exclude_time;
            }

            if ($task->attendees) {
                foreach ($task->attendees as $attendee) {
                    $user = User::select('first_name', 'last_name', 'email', 'avatar')
                        ->where('id', $attendee['user_id'])
                        ->first();

                    if ($user->avatar && !Str::startsWith($user->avatar, ['http://', 'https://'])) {
                        $user->avatar = Storage::url($user->avatar);
                    }

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
            'parent_id'         => 'nullable',
            'updated_date'      => 'nullable',
            'tag_id'            => 'nullable',
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
            'freq'              => ['nullable', Rule::in('daily', 'weekly', 'monthly', 'yearly')],
            'interval'          => 'nullable|numeric',
            'until'             => 'nullable|date_format:Y-m-d H:i:s',
            'count'             => 'nullable|numeric',
            'byweekday'         => ['nullable', 'array'],
            'byweekday.*'       => [Rule::in(['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA'])],
            'bymonthday'        => 'nullable', //JSON
            'bymonth'           => 'nullable', //JSON
            'bysetpos'           => 'nullable', //JSON
            'exclude_time'      => 'nullable', //JSON
            'link'              => 'nullable',
            'is_private'        => 'nullable',
            'default_permission' => 'nullable',
        ]);

        // $data['user_id'] = Auth::id();

        $data = $this->handleJsonStringData($data);

        $data = $this->handleLogicData($data);

        $task = Task::find($id);

        $data['user_id'] = $task->user_id;

        //Kiểm tra xem có tìm được task với id truyền vào không
        if (!$task) {
            return response()->json([
                'code'    => 404,
                'message' => 'Failed to get task',
                'error'   => 'Cannot get task',
            ], 404);
        }

        $attendees = collect($task->attendees);
        $attendee = $attendees->firstWhere('user_id', Auth::id());

        if (!empty($task->tag_id)) {
            $tag = Tag::where('id', $task->tag_id)->first();
            $sharedUsers = collect($tag->shared_user);
            $currentUser = $sharedUsers->firstWhere('user_id', Auth::id());
        }

        if ($task->user_id  === Auth::id() || ($attendee && $attendee['role'] === 'editor') || ($currentUser && $currentUser['role'] == "editor")) {
            switch ($code) {
                //Update when event dont have reapet
                case 'EDIT_N':
                    try {
                        $task->update($data);

                        //Send REALTIME
                        $returnTask[] = $task;

                        $this->sendRealTimeUpdate($returnTask, 'update');

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
                        if (!$task->parent_id) {
                            $data['parent_id'] = $task->id;
                        } else {
                            $data['parent_id'] = $task->parent_id;
                        }

                        //Add new task for 1 day change
                        $new_task = Task::create([
                            'parent_id'     => $data['parent_id'],
                            'start_time'    => $data['start_time'],
                            'end_time'      => $data['end_time'],
                            'title'         => $data['title'],
                            'description'   => $data['description'],
                            'user_id'       => $data['user_id'],
                            'timezone_code' => $data['timezone_code'],
                            'color_code'    => $data['color_code'],
                            'tag_id'        => $data['tag_id'] ?? null,
                            'attendees'     => $data['attendees'],
                            'location'      => $data['location'],
                            'type'          => $data['type'],
                            'is_all_day'    => $data['is_all_day'],
                            'is_busy'       => $data['is_busy'],
                            'is_reminder'   => $data['is_reminder'],
                            'reminder'      => $data['reminder'],
                            'link'          => $data['link'],
                            'is_private'    => $data['is_private'],
                            'is_done'       => $data['is_done'],
                            'default_permission' => $data['default_permission'] ?? 'viewer',
                        ]);

                        //Send REALTIME
                        $returnTask[] = $new_task;

                        $this->sendRealTimeUpdate($returnTask, 'create');

                        //Push updated_date to exclude_time array of task
                        $exclude_time = $task->exclude_time ?? [];

                        if (!is_array($exclude_time)) {
                            $exclude_time = json_decode($exclude_time, true) ?? [];
                        }

                        // $exclude_time[] = Carbon::createFromFormat('Y-m-d H:i:s', $data['updated_date'], $data['timezone_code'])->setTimezone('UTC')->setTime($data['start_time']->hour, $data['start_time']->minute, $data['start_time']->second);
                        $exclude_time[] = $data['updated_date'];

                        $task->exclude_time = $exclude_time;
                        $task->save();

                        //Send REALTIME
                        $returnTaskUpdate[] = $task;

                        // update task -> create new group
                        app(TaskGroupChatController::class)->createGroup($new_task->id, $new_task->user_id);

                        $this->sendRealTimeUpdate($returnTaskUpdate, 'update');

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
                        $new_task = Task::create($data);
                        Log::info("New task created with ID: " . $new_task->id);

                        // $task->until = Carbon::parse($data['updated_date'])->setTime(0, 0, 0)->subDay();
                        $task->until = Carbon::parse($data['updated_date'])->subDay();

                        $task->save();

                        //Send REALTIME
                        $returnTaskUpdate[] = $task;

                        // $this->sendRealTimeUpdate($returnTaskUpdate, 'update');

                        //Delete all task that have parent_id = $task->id and start_time > $ta
                        $relatedTasks = Task::where(function ($query) use ($task) {
                            $query->where('parent_id', $task->id);
                            // Kiểm tra nếu parent_id của task hiện tại không phải là null  
                            if ($task->parent_id !== null) {
                                $query->orWhere('parent_id', $task->parent_id);
                            }
                        })
                            ->where('start_time', '>=', $task->until)
                            ->where('id', '!=', $new_task->id)
                            ->get();

                        $allExcludeTimes = $task->exclude_time ?? [];

                        $allAttendees = $task->attendees ?? [];

                        $maxUntil = $new_task->until;

                        foreach ($relatedTasks as $relatedTask) {
                            $updatedStartTime = Carbon::parse($relatedTask->start_time)->setTime($data['start_time']->hour, $data['start_time']->minute, $data['start_time']->second);
                            $updatedEndTime = Carbon::parse($relatedTask->end_time)->setTime($data['end_time']->hour, $data['end_time']->minute, $data['end_time']->second);

                            if ($relatedTask->is_repeat) {
                                $relatedExcludeTimes = $relatedTask->exclude_time ?? [];
                                $allExcludeTimes = array_merge($allExcludeTimes, $relatedExcludeTimes);

                                //Add all attendees to new task
                                $allAttendees = array_merge($allAttendees, $relatedTask->attendees ?? []);
                                $allAttendees = array_values(array_reduce($allAttendees, function ($carry, $attendee) {
                                    $carry[$attendee['user_id']] = $attendee; // Ghi đè để giữ bản ghi cuối cùng
                                    return $carry;
                                }, []));

                                if ($relatedTask->until && (!$maxUntil || Carbon::parse($relatedTask->until)->greaterThan(Carbon::parse($maxUntil)))) {
                                    $maxUntil = $relatedTask->until;
                                }

                                $relatedTask->forceDelete();
                            } else {
                                $relatedTask->update([
                                    'start_time'    => $updatedStartTime,
                                    'end_time'      => $updatedEndTime,
                                    'title'         => $data['title'],
                                    'description'   => $data['description'],
                                    'user_id'       => $data['user_id'],
                                    'timezone_code' => $data['timezone_code'],
                                    'color_code'    => $data['color_code'],
                                    'tag_id'        => $data['tag_id'] ?? null,
                                    'attendees'     => $data['attendees'],
                                    'location'      => $data['location'],
                                    'type'          => $data['type'],
                                    'is_all_day'    => $data['is_all_day'],
                                    'is_busy'       => $data['is_busy'],
                                    'is_reminder'   => $data['is_reminder'],
                                    'reminder'      => $data['reminder'],
                                    'link'          => $data['link'],
                                    'is_private'    => $data['is_private'],
                                    'is_done'       => $data['is_done'],
                                    'default_permission' => $data['default_permission'] ?? 'viewer',
                                ]);
                            }

                            $returnTaskUpdate[] = $relatedTask;
                        }

                        //Send REALTIME
                        if (!$returnTaskUpdate) {
                            $this->sendRealTimeUpdate($returnTaskUpdate, 'update');
                        }

                        // create new task -> create new group
                        // app(TaskGroupChatController::class)->createGroup($new_task->id, $new_task->user_id);

                        // Remove same excude time and save it to new task
                        $allExcludeTimes = array_unique($allExcludeTimes);
                        $new_task->exclude_time = array_values($allExcludeTimes);

                        $new_task->until = $maxUntil;

                        $new_task->parent_id = $task->parent_id ?? $task->id;
                        $new_task->save();

                        //Send REALTIME
                        $returnTask[] = $new_task;

                        $this->sendRealTimeUpdate($returnTask, 'create');

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
                        $parentTask = Task::find($task->parent_id);

                        Log::info("Đây là task cha {$parentTask}");

                        $duration = Carbon::parse($data['start_time'])->diff(Carbon::parse(time: $data['end_time']));

                        if ($parentTask) {
                            $relatedTasks = Task::where('parent_id', $parentTask->id)
                                ->orWhere('parent_id', $task->parent_id)
                                ->get();

                            foreach ($relatedTasks as $relatedTask) {
                                $updatedStartTime = Carbon::parse($relatedTask->start_time)->setTime($data['start_time']->hour, $data['start_time']->minute, $data['start_time']->second);
                                $updatedEndTime = Carbon::parse($updatedStartTime)->copy()->add($duration);

                                if ($relatedTask->is_repeat) {
                                    $relatedTask->update([
                                        'parent_id'     => $parentTask->id,
                                        'title'         => $data['title'],
                                        'description'   => $data['description'],
                                        'user_id'       => $data['user_id'],
                                        'timezone_code' => $data['timezone_code'],
                                        'color_code'    => $data['color_code'],
                                        'tag_id'        => $data['tag_id'] ?? null,
                                        'attendees'     => $data['attendees'],
                                        'location'      => $data['location'],
                                        'type'          => $data['type'],
                                        'is_all_day'    => $data['is_all_day'],
                                        'is_busy'       => $data['is_busy'],
                                        'is_reminder'   => $data['is_reminder'],
                                        'reminder'      => $data['reminder'],
                                        'is_repeat'     => $data['is_repeat'],
                                        'freq'          => $data['freq'],
                                        'interval'      => $data['interval'],
                                        // 'until'         => !empty($data['until']) ? $data['until'] : $relatedTask->until,
                                        'count'         => $data['count'],
                                        'byweekday'     => $data['byweekday'],
                                        'bymonthday'    => $data['bymonthday'],
                                        'bymonth'       => $data['bymonth'],
                                        'link'          => $data['link'],
                                        'is_private'    => $data['is_private'],
                                        'is_done'       => $data['is_done'],
                                        'default_permission' => $data['default_permission'] ?? 'viewer',
                                        'start_time'    => $updatedStartTime,
                                        'end_time'      => $updatedEndTime,
                                        'exclude_time' => $relatedTask->exclude_time,
                                    ]);
                                } else {
                                    $relatedTask->update([
                                        'parent_id'     => $parentTask->id,
                                        'title'         => $data['title'],
                                        'description'   => $data['description'],
                                        'user_id'       => $data['user_id'],
                                        'timezone_code' => $data['timezone_code'],
                                        'color_code'    => $data['color_code'],
                                        'tag_id'        => $data['tag_id'] ?? null,
                                        'attendees'     => $data['attendees'],
                                        'location'      => $data['location'],
                                        'type'          => $data['type'],
                                        'is_all_day'    => $data['is_all_day'],
                                        'is_busy'       => $data['is_busy'],
                                        'is_reminder'   => $data['is_reminder'],
                                        'reminder'      => $data['reminder'],
                                        'link'          => $data['link'],
                                        'is_private'    => $data['is_private'],
                                        'is_done'       => $data['is_done'],
                                        'default_permission' => $data['default_permission'] ?? 'viewer',
                                        'start_time'    => $updatedStartTime,
                                        'end_time'      => $updatedEndTime,
                                    ]);
                                }

                                $returnTask[] = $relatedTask;
                            }

                            $parentStartTime = is_string($parentTask->start_time)
                                ? Carbon::parse($parentTask->start_time)
                                : $parentTask->start_time;

                            $parentEndTime = is_string($parentTask->end_time)
                                ? Carbon::parse($parentTask->end_time)
                                : $parentTask->end_time;

                            $data['start_time'] = Carbon::parse($data['start_time'])->setDate($parentStartTime->year, $parentStartTime->month, $parentStartTime->day);
                            $data['end_time'] = Carbon::parse($data['start_time'])->copy()->add($duration);

                            unset($data['parent_id'], $data['until']);
                            $data['exclude_time'] = $parentTask->exclude_time;
                            $parentTask->update($data);
                            $returnTask[] = $parentTask;
                        } else {
                            // Update all child Task
                            $relatedTasks = Task::where(function ($query) use ($task) {
                                $query->where('parent_id', $task->id);
                                // Kiểm tra nếu parent_id của task hiện tại không phải là null  
                                if ($task->parent_id !== null) {
                                    $query->orWhere('parent_id', $task->parent_id);
                                }
                            })
                                ->get();

                            foreach ($relatedTasks as $relatedTask) {
                                $updatedStartTime = Carbon::parse($relatedTask->start_time)->setTime($data['start_time']->hour, $data['start_time']->minute, $data['start_time']->second);
                                $updatedEndTime = Carbon::parse($updatedStartTime)->copy()->add($duration);

                                if ($relatedTask->is_repeat) {
                                    $relatedTask->update([
                                        'parent_id'     => $task->id,
                                        'title'         => $data['title'],
                                        'description'   => $data['description'],
                                        'user_id'       => $data['user_id'],
                                        'timezone_code' => $data['timezone_code'],
                                        'color_code'    => $data['color_code'],
                                        'tag_id'        => $data['tag_id'] ?? null,
                                        'attendees'     => $data['attendees'],
                                        'location'      => $data['location'],
                                        'type'          => $data['type'],
                                        'is_all_day'    => $data['is_all_day'],
                                        'is_busy'       => $data['is_busy'],
                                        'is_reminder'   => $data['is_reminder'],
                                        'reminder'      => $data['reminder'],
                                        'is_repeat'     => $data['is_repeat'],
                                        'is_private'    => $data['is_private'],
                                        'is_done'       => $data['is_done'],
                                        'link'          => $data['link'],
                                        'default_permission' => $data['default_permission'] ?? 'viewer',
                                        'freq'          => $data['freq'],
                                        'interval'      => $data['interval'],
                                        // 'until'         => !empty($data['until']) ? $data['until'] : $relatedTask->until,
                                        'count'         => $data['count'],
                                        'byweekday'     => $data['byweekday'],
                                        'bymonthday'    => $data['bymonthday'],
                                        'bymonth'       => $data['bymonth'],
                                        'start_time'    => $updatedStartTime,
                                        'end_time'      => $updatedEndTime,
                                    ]);
                                } else {
                                    $relatedTask->update([
                                        'parent_id'     => $task->id,
                                        'title'         => $data['title'],
                                        'description'   => $data['description'],
                                        'user_id'       => $data['user_id'],
                                        'timezone_code' => $data['timezone_code'],
                                        'color_code'    => $data['color_code'],
                                        'tag_id'        => $data['tag_id'] ?? null,
                                        'attendees'     => $data['attendees'],
                                        'location'      => $data['location'],
                                        'type'          => $data['type'],
                                        'is_all_day'    => $data['is_all_day'],
                                        'is_busy'       => $data['is_busy'],
                                        'is_reminder'   => $data['is_reminder'],
                                        'reminder'      => $data['reminder'],
                                        'is_private'    => $data['is_private'],
                                        'is_done'       => $data['is_done'],
                                        'link'          => $data['link'],
                                        'default_permission' => $data['default_permission'] ?? 'viewer',
                                        'start_time'    => $updatedStartTime,
                                        'end_time'      => $updatedEndTime,
                                    ]);
                                }
                                $returnTask[] = $relatedTask;
                            }

                            //Update current Task
                            $parentStartTime = is_string($task->start_time)
                                ? Carbon::parse($task->start_time)
                                : $task->start_time;

                            $parentEndTime = is_string($task->end_time)
                                ? Carbon::parse($task->end_time)
                                : $task->end_time;

                            $data['start_time'] = Carbon::parse($data['start_time'])->setDate($parentStartTime->year, $parentStartTime->month, $parentStartTime->day);
                            $data['end_time'] = Carbon::parse($data['start_time'])->copy()->add($duration);

                            unset($data['parent_id'], $data['until']);
                            $data['exclude_time'] = $task->exclude_time;
                            $task->update($data);

                            $returnTask[] = $task;
                        }

                        //Send API
                        $this->sendRealTimeUpdate($returnTask, 'update');

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
        } else {
            return response()->json([
                'code' => 401,
                'message' => 'You do not have permission to edit this event',
            ]);
        }
    }

    public function updateTaskOnDrag(Request $request, $id)
    {
        $code = $request->code;

        $data = $request->validate([
            'updated_date'      => 'nullable',
            'timezone_code'     => 'required',
            'start_time'        => 'required|date_format:Y-m-d H:i:s',
            'end_time'          => 'nullable|date_format:Y-m-d H:i:s',
            'is_all_day'        => 'nullable|boolean',
        ]);

        $data = $this->handleLogicData($data);

        $task = Task::find($id);

        $data['user_id'] = $task->user_id;

        //Kiểm tra xem có tìm được task với id truyền vào không
        if (!$task) {
            return response()->json([
                'code'    => 404,
                'message' => 'Failed to get task',
                'error'   => 'Cannot get task',
            ], 404);
        }

        $attendees = collect($task->attendees);
        $attendee = $attendees->firstWhere('user_id', Auth::id());

        if (!empty($task->tag_id)) {
            $tag = Tag::where('id', $task->tag_id)->first();
            $sharedUsers = collect($tag->shared_user);
            $currentUser = $sharedUsers->firstWhere('user_id', Auth::id());
        }


        if ($task->user_id  === Auth::id() || ($currentUser && $currentUser['role'] == "editor")) {
            // if ($task->user_id  === Auth::id() || ($attendee && $attendee['role'] === 'editor') || ($currentUser && $currentUser['role'] == "editor")) {
            switch ($code) {
                //Update when event dont have reapet
                case 'EDIT_N':
                    try {
                        $task->update($data);

                        //Send REALTIME
                        $returnTask[] = $task;

                        $this->sendRealTimeUpdate($returnTask, 'update');

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
                        $data['parent_id'] = $task->parent_id ?? $task->id;

                        $new_task = Task::create([
                            'parent_id'     => $data['parent_id'],
                            'start_time'    => $data['start_time'],
                            'end_time'      => $data['end_time'],
                            'title'         => $task->title,
                            'description'   => $task->description,
                            'user_id'       => $task->user_id,
                            'timezone_code' => $task->timezone_code,
                            'color_code'    => $task->color_code,
                            'tag_id'        => $task->tag_id,
                            'attendees'     => $task->attendees,
                            'location'      => $task->location,
                            'type'          => $task->type,
                            'is_all_day'    => $data['is_all_day'] ?? $task->is_all_day,
                            'is_busy'       => $task->is_busy,
                            'is_reminder'   => $task->is_reminder,
                            'is_private'    => $task->is_private,
                            'is_done'       => $task->is_done,
                            'link'          => $task->link,
                            'reminder'      => $task->reminder,
                            'default_permission' => $task->default_permission ?? 'viewer',
                        ]);

                        //Send REALTIME
                        $returnTaskCre[] = $new_task;

                        $this->sendRealTimeUpdate($returnTaskCre, 'create');

                        $exclude_time = $task->exclude_time ?? [];

                        if (!is_array($exclude_time)) {
                            $exclude_time = json_decode($exclude_time, true) ?? [];
                        }

                        // $exclude_time[] = Carbon::createFromFormat('Y-m-d H:i:s', $data['updated_date'], $data['timezone_code'])->setTimezone('UTC');
                        $exclude_time[] = $data['updated_date'];

                        $task->exclude_time = $exclude_time;
                        $task->save();

                        //Send REALTIME
                        $returnTask[] = $task;

                        $this->sendRealTimeUpdate($returnTask, 'update');

                        app(TaskGroupChatController::class)->createGroup($new_task->id, $new_task->user_id);


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
                        $preNewTask = $task->replicate();

                        $preNewTask->start_time = $data['start_time'];
                        $preNewTask->end_time = $data['end_time'];

                        unset($preNewTask->uuid);

                        //Add new task for following change
                        $new_task = Task::create($preNewTask->toArray());
                        Log::info($new_task);

                        if ($data['start_time']->isSameDay($data['updated_date'])) {
                            $task->until = Carbon::parse($data['updated_date'])->setTime(0, 0, 0);
                        } else {
                            $task->until = Carbon::parse($data['updated_date'])->subDay();
                        }

                        $task->save();

                        //Send REALTIME
                        $returnTaskUpdate[] = $task;

                        // $this->sendRealTimeUpdate($returnTaskUpdate, 'update');

                        // Delete all task that have parent_id = $task->id and start_time > $ta
                        $relatedTasks = Task::where(function ($query) use ($task) {
                            $query->where('parent_id', $task->id);
                            // Kiểm tra nếu parent_id của task hiện tại không phải là null  
                            if ($task->parent_id !== null) {
                                $query->orWhere('parent_id', $task->parent_id);
                            }
                        })
                            ->where('start_time', '>=', $task->until)
                            ->where('id', '!=', $new_task->id)
                            ->get();

                        //Send REALTIME
                        // $returnTaskDel = $relatedTasks;

                        $allExcludeTimes = $new_task->exclude_time ?? [];

                        $allAttendees = $new_task->attendees ?? [];

                        $maxUntil = $new_task->until;

                        foreach ($relatedTasks as $relatedTask) {
                            $updatedStartTime = Carbon::parse($relatedTask->start_time)->setTime($data['start_time']->hour, $data['start_time']->minute, $data['start_time']->second);
                            $updatedEndTime = Carbon::parse($relatedTask->end_time)->setTime($data['end_time']->hour, $data['end_time']->minute, $data['end_time']->second);

                            if ($relatedTask->is_repeat) {

                                $relatedExcludeTimes = $relatedTask->exclude_time ?? [];
                                $allExcludeTimes = array_merge($allExcludeTimes, $relatedExcludeTimes);

                                //Add all attendees to new task
                                $allAttendees = array_merge($allAttendees, $relatedTask->attendees ?? []);
                                $allAttendees = array_values(array_reduce($allAttendees, function ($carry, $attendee) {
                                    $carry[$attendee['user_id']] = $attendee; // Ghi đè để giữ bản ghi cuối cùng
                                    return $carry;
                                }, []));

                                if ($relatedTask->until && (!$maxUntil || Carbon::parse($relatedTask->until)->greaterThan(Carbon::parse($maxUntil)))) {
                                    $maxUntil = $relatedTask->until;
                                }

                                $relatedTask->forceDelete();
                            } else {
                                $relatedTask->update([
                                    'start_time'    => $updatedStartTime,
                                    'end_time'      => $updatedEndTime,
                                    'is_all_day'    => $data['is_all_day'] ?? $relatedTask->is_all_day,
                                ]);

                                $returnTaskUpdate[] = $relatedTask;
                            }
                        }

                        if (!$returnTaskUpdate) {
                            $this->sendRealTimeUpdate($returnTaskUpdate, 'update');
                        }

                        // app(TaskGroupChatController::class)->createGroup($new_task->id, $new_task->user_id);

                        $allExcludeTimes = array_unique($allExcludeTimes);
                        $new_task->exclude_time = array_values($allExcludeTimes);

                        $new_task->until = $maxUntil;

                        $new_task->parent_id = $task->parent_id ?? $task->id;
                        $new_task->save();

                        //Send REALTIME
                        $returnTask[] = $new_task;

                        $this->sendRealTimeUpdate($returnTask, 'create');

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

                case 'EDIT_A':
                    try {
                        $parentTask = Task::find($task->parent_id);

                        Log::info("Đây là task cha {$parentTask}");

                        $duration = Carbon::parse($data['start_time'])->diff(Carbon::parse(time: $data['end_time']));

                        if ($parentTask) {
                            $relatedTasks = Task::where('parent_id', $parentTask->id)
                                ->orWhere('parent_id', $task->parent_id)
                                ->get();

                            foreach ($relatedTasks as $relatedTask) {
                                $updatedStartTime = Carbon::parse($relatedTask->start_time)->setTime($data['start_time']->hour, $data['start_time']->minute, $data['start_time']->second);
                                $updatedEndTime = Carbon::parse($updatedStartTime)->copy()->add($duration);

                                if ($relatedTask->is_repeat) {
                                    $relatedTask->update([
                                        'title'         => $parentTask->title,
                                        'description'   => $parentTask->description,
                                        'user_id'       => $parentTask->user_id,
                                        'timezone_code' => $parentTask->timezone_code,
                                        'color_code'    => $parentTask->color_code,
                                        'tag_id'        => $parentTask->tag_id ?? null,
                                        'attendees'     => $parentTask->attendees,
                                        'location'      => $parentTask->location,
                                        'type'          => $parentTask->type,
                                        'is_all_day'    => $parentTask->is_all_day,
                                        'is_busy'       => $parentTask->is_busy,
                                        'is_reminder'   => $parentTask->is_reminder,
                                        'reminder'      => $parentTask->reminder,
                                        'is_repeat'     => $parentTask->is_repeat,
                                        'is_private'    => $parentTask->is_private,
                                        'is_done'       => $parentTask->is_done,
                                        'link'          => $parentTask->link,
                                        'default_permission' => $parentTask->default_permission ?? 'viewer',
                                        'freq'          => $parentTask->freq,
                                        'interval'      => $parentTask->interval,
                                        'until'         => $parentTask->until,
                                        'count'         => $parentTask->count,
                                        'byweekday'     => $parentTask->byweekday,
                                        'bymonthday'    => $parentTask->bymonthday,
                                        'bymonth'       => $parentTask->bymonth,
                                        'start_time'    => $updatedStartTime,
                                        'end_time'      => $updatedEndTime,
                                    ]);
                                } else {
                                    $relatedTask->update([
                                        'parent_id'     => $parentTask->id,
                                        'title'         => $parentTask->title,
                                        'description'   => $parentTask->description,
                                        'user_id'       => $parentTask->user_id,
                                        'timezone_code' => $parentTask->timezone_code,
                                        'color_code'    => $parentTask->color_code,
                                        'tag_id'        => $parentTask->tag_id ?? null,
                                        'attendees'     => $parentTask->attendees,
                                        'location'      => $parentTask->location,
                                        'type'          => $parentTask->type,
                                        'is_all_day'    => $parentTask->is_all_day,
                                        'is_busy'       => $parentTask->is_busy,
                                        'is_reminder'   => $parentTask->is_reminder,
                                        'is_done'       => $parentTask->is_done,
                                        'link'          => $parentTask->link,
                                        'reminder'      => $parentTask->reminder,
                                        'is_repeat'     => $parentTask->is_repeat,
                                        'default_permission' => $parentTask->default_permission ?? 'viewer',
                                        'start_time'    => $updatedStartTime,
                                        'end_time'      => $updatedEndTime,
                                    ]);
                                }

                                $returnTask[] = $relatedTask;
                            }

                            $parentStartTime = is_string($parentTask->start_time)
                                ? Carbon::parse($parentTask->start_time)
                                : $parentTask->start_time;

                            $parentEndTime = is_string($parentTask->end_time)
                                ? Carbon::parse($parentTask->end_time)
                                : $parentTask->end_time;

                            $data['start_time'] = Carbon::parse($data['start_time'])->setDate($parentStartTime->year, $parentStartTime->month, $parentStartTime->day);
                            $data['end_time'] = Carbon::parse($data['start_time'])->copy()->add($duration);

                            $parentTask->update($data);
                            $returnTask[] = $parentTask;

                            $returnTask[] = $task;
                        } else {
                            // Update all child Task
                            $relatedTasks = Task::where('parent_id', $task->id)
                                ->get();

                            foreach ($relatedTasks as $relatedTask) {
                                $updatedStartTime = Carbon::parse($relatedTask->start_time)->setTime($data['start_time']->hour, $data['start_time']->minute, $data['start_time']->second);
                                $updatedEndTime = Carbon::parse($updatedStartTime)->copy()->add($duration);

                                if ($relatedTask->is_repeat) {
                                    $relatedTask->update([
                                        'parent_id'     => $task->id,
                                        'title'         => $task->title,
                                        'description'   => $task->description,
                                        'user_id'       => $task->user_id,
                                        'timezone_code' => $task->timezone_code,
                                        'color_code'    => $task->color_code,
                                        'tag_id'        => $task->tag_id ?? null,
                                        'attendees'     => $task->attendees,
                                        'location'      => $task->location,
                                        'type'          => $task->type,
                                        'is_all_day'    => $task->is_all_day,
                                        'is_busy'       => $task->is_busy,
                                        'is_reminder'   => $task->is_reminder,
                                        'reminder'      => $task->reminder,
                                        'is_repeat'     => $task->is_repeat,
                                        'is_private'    => $task->is_private,
                                        'is_done'       => $task->is_done,
                                        'link'          => $task->link,
                                        'default_permission' => $task->default_permission ?? 'viewer',
                                        'freq'          => $task->freq,
                                        'interval'      => $task->interval,
                                        'until'         => $task->until,
                                        'count'         => $task->count,
                                        'byweekday'     => $task->byweekday,
                                        'bymonthday'    => $task->bymonthday,
                                        'bymonth'       => $task->bymonth,
                                        'start_time'    => $updatedStartTime,
                                        'end_time'      => $updatedEndTime,
                                    ]);
                                } else {
                                    $relatedTask->update([
                                        'parent_id'     => $task->id,
                                        'title'         => $task->title,
                                        'description'   => $task->description,
                                        'user_id'       => $task->user_id,
                                        'timezone_code' => $task->timezone_code,
                                        'color_code'    => $task->color_code,
                                        'tag_id'        => $task->tag_id ?? null,
                                        'attendees'     => $task->attendees,
                                        'location'      => $task->location,
                                        'type'          => $task->type,
                                        'is_all_day'    => $task->is_all_day,
                                        'is_busy'       => $task->is_busy,
                                        'is_reminder'   => $task->is_reminder,
                                        'reminder'      => $task->reminder,
                                        'is_repeat'     => $task->is_repeat,
                                        'is_private'    => $task->is_private,
                                        'is_done'       => $task->is_done,
                                        'link'          => $task->link,
                                        'default_permission' => $task->default_permission ?? 'viewer',
                                        'start_time'    => $updatedStartTime,
                                        'end_time'      => $updatedEndTime,
                                    ]);
                                }

                                $returnTask[] = $relatedTask;
                            }

                            $parentStartTime = is_string($task->start_time)
                                ? Carbon::parse($task->start_time)
                                : $task->start_time;

                            $parentEndTime = is_string($task->end_time)
                                ? Carbon::parse($task->end_time)
                                : $task->end_time;

                            $data['start_time'] = Carbon::parse($data['start_time'])->setDate($parentStartTime->year, $parentStartTime->month, $parentStartTime->day);
                            $data['end_time'] = Carbon::parse($data['start_time'])->copy()->add($duration);

                            //Update current Task
                            $task->update($data);

                            $returnTask[] = $task;
                        }

                        //Send API
                        $this->sendRealTimeUpdate($returnTask, 'update');

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
        } else {
            return response()->json([
                'code' => 401,
                'message' => 'You do not have permission to edit this event',
            ]);
        }
    }

    public function attendeeLeaveTask(Request $request, $id)
    {
        $code = $request->code;

        $data = $request->validate([
            'updated_date'      => 'nullable',
            'atteendee_id'      => 'required|integer',
            'timezone_code'     => 'required',
            'start_time'        => 'required|date_format:Y-m-d H:i:s',
            'end_time'          => 'nullable|date_format:Y-m-d H:i:s',
        ]);

        Log::info($data);

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

        // Lấy danh sách attendees hiện tại từ JSON  
        $attendees = $task->attendees ?? [];

        // Loại bỏ attendee theo id  
        $attendees = array_values(array_filter($attendees, function ($attendee) use ($data) {
            return isset($attendee['user_id']) && $attendee['user_id'] != $data['atteendee_id'];
        }));

        // Dữ liệu dùng gửi thông báo
        $user = auth()->user();
        $owner = User::find($task->user_id);

        switch ($code) {
            //Update when event dont have reapet
            case 'EDIT_N':
                try {
                    Log::info($attendees);

                    $task->update(['attendees' => $attendees]);

                    //Send REALTIME
                    $returnTask[] = $task;

                    $this->sendRealTimeUpdate($returnTask, 'update');

                    Log::info($task->attendees);

                    $owner->notify(new NotificationEvent(
                        $task->user_id,
                        "Tài khoản {$user->first_name} {$user->last_name} vừa mới rời khỏi {$task->type} {$task->title} của bạn",
                        "",
                        "leave_task"
                    ));

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
                    $new_task = Task::create([
                        'parent_id'     => $task->parent_id ?? $task->id,
                        'start_time'    => $data['start_time'],
                        'end_time'      => $data['end_time'],
                        'title'         => $task->title,
                        'description'   => $task->description,
                        'user_id'       => $task->user_id,
                        'timezone_code' => $task->timezone_code,
                        'color_code'    => $task->color_code,
                        'tag_id'        => $task->tag_id,
                        'attendees'     => $attendees,
                        'location'      => $task->location,
                        'type'          => $task->type,
                        'is_all_day'    => $task->is_all_day,
                        'is_busy'       => $task->is_busy,
                        'is_reminder'   => $task->is_reminder,
                        'reminder'      => $task->reminder,
                        'is_done'       => $task->is_done,
                        'link'          => $task->link,
                        'is_private'    => $task->is_private,
                        'default_permission' => $task->default_permission ?? 'viewer',
                    ]);

                    //Send REALTIME
                    $returnTaskCre[] = $new_task;

                    $this->sendRealTimeUpdate($returnTaskCre, 'create');

                    $exclude_time = $task->exclude_time ?? [];

                    if (!is_array($exclude_time)) {
                        $exclude_time = $exclude_time ?? [];
                    }

                    Log::info($data['updated_date']);

                    $exclude_time[] = $data['updated_date'];
                    $exclude_time = array_unique($exclude_time);

                    Log::info($exclude_time);

                    $task->exclude_time = $exclude_time;
                    $task->save();

                    //Send REALTIME
                    $returnTask[] = $task;

                    $this->sendRealTimeUpdate($returnTask, 'update');

                    $owner->notify(new NotificationEvent(
                        $task->user_id,
                        "Tài khoản {$user->first_name} {$user->last_name} vừa mới rời khỏi ngày {$data['updated_date']} {$task->type} {$task->title} của bạn",
                        "",
                        "leave_task"
                    ));

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
                    $preNewTask = $task->replicate();

                    unset($preNewTask->uuid);
                    $preNewTask->atteedees = $attendees;

                    //Add new task for following change
                    $new_task = Task::create($preNewTask->toArray());

                    $task->until = Carbon::parse($data['updated_date'])->setTime(0, 0, 0);

                    $task->save();

                    //Send REALTIME
                    $returnTaskUpdate[] = $task;

                    // Delete all task that have parent_id = $task->id and start_time > $ta
                    $relatedTasks = Task::where(function ($query) use ($task) {
                        $query->where('parent_id', $task->id);
                        // Kiểm tra nếu parent_id của task hiện tại không phải là null  
                        if ($task->parent_id !== null) {
                            $query->orWhere('parent_id', $task->parent_id);
                        }
                    })
                        ->where('start_time', '>=', $task->until)
                        ->where('id', '!=', $new_task->id)
                        ->get();

                    //Send REALTIME

                    $allExcludeTimes = $new_task->exclude_time ?? [];

                    $maxUntil = $new_task->until;

                    $nearestTask = null;
                    $nearestStartTimeDiff = null;

                    foreach ($relatedTasks as $relatedTask) {
                        if ($relatedTask->is_repeat) {
                            Log::info('relatedTask->start_time' . $relatedTask->start_time);

                            $relatedExcludeTimes = $relatedTask->exclude_time ?? [];
                            $allExcludeTimes = array_merge($allExcludeTimes, $relatedExcludeTimes);

                            $diff = abs(Carbon::parse($relatedTask->start_time)->diffInMinutes(Carbon::parse($new_task->start_time)));
                            log::info('diff' . $diff);

                            if (is_null($nearestStartTimeDiff) || $diff < $nearestStartTimeDiff) {
                                $nearestStartTimeDiff = $diff;
                                $nearestTask = $relatedTask;
                            }

                            if ($relatedTask->until && (!$maxUntil || Carbon::parse($relatedTask->until)->greaterThan(Carbon::parse($maxUntil)))) {
                                $maxUntil = $relatedTask->until;
                                Log::info('maxUntil' . $maxUntil);
                            }

                            $relatedTask->update([
                                'attendees' => $attendees,
                            ]);

                            $returnTaskUpdate[] = $relatedTask;
                        } else {
                            $relatedTask->update([
                                'attendees' => $attendees,
                            ]);

                            $returnTaskUpdate[] = $relatedTask;
                        }
                    }

                    if (!$returnTaskUpdate) {
                        $this->sendRealTimeUpdate($returnTaskUpdate, 'update');
                    }

                    $allExcludeTimes = array_unique($allExcludeTimes);
                    $new_task->exclude_time = array_values($allExcludeTimes);

                    if ($nearestTask) {
                        $new_task->until = Carbon::parse($nearestTask->start_time)->subDay();
                    } else {
                        $new_task->until = $maxUntil;
                    }

                    $new_task->parent_id = $task->parent_id ?? $task->id;
                    $new_task->start_time = $data['start_time'];
                    $new_task->end_time = $data['end_time'];
                    $new_task->attendees = $attendees;
                    $new_task->save();

                    //Send REALTIME
                    $returnTask[] = $new_task;

                    $this->sendRealTimeUpdate($returnTask, 'create');

                    $owner->notify(new NotificationEvent(
                        $task->user_id,
                        "Tài khoản {$user->first_name} {$user->last_name} vừa mới rời khỏi {$task->type} {$task->title} của bạn từ ngày {$data['updated_date']}",
                        "",
                        "leave_task"
                    ));

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

            case 'EDIT_A':
                try {
                    $parentTask = Task::find($task->parent_id);

                    if ($parentTask) {
                        $relatedTasks = Task::where('parent_id', $parentTask->id)
                            ->orWhere('parent_id', $task->parent_id)
                            ->get();

                        foreach ($relatedTasks as $relatedTask) {
                            $relatedAttendees = $relatedTask->attendees ?? [];

                            // Loại bỏ attendee theo id  
                            $relatedAttendees = array_values(array_filter($relatedAttendees, function ($attendee) use ($data) {
                                return isset($attendee['user_id']) && $attendee['user_id'] != $data['atteendee_id'];
                            }));

                            if ($relatedTask->is_repeat) {
                                $relatedTask->update([
                                    'attendees'     => $relatedAttendees
                                ]);
                            } else {
                                $relatedTask->update([
                                    'attendees'     => $relatedAttendees
                                ]);
                            }

                            $returnTask[] = $relatedTask;
                        }

                        $parentAttendees = $parentTask->attendees ?? [];

                        // Loại bỏ attendee theo id  
                        $parentAttendees = array_values(array_filter($relatedAttendees, function ($attendee) use ($data) {
                            return isset($attendee['user_id']) && $attendee['user_id'] != $data['atteendee_id'];
                        }));

                        $parentTask->update(['attendees'     => $parentAttendees]);
                        $returnTask[] = $parentTask;

                        $returnTask[] = $task;
                    } else {
                        // Update all child Task
                        $relatedTasks = Task::where('parent_id', $task->id)
                            ->get();

                        foreach ($relatedTasks as $relatedTask) {
                            $relatedAttendees = $relatedTask->attendees ?? [];

                            // Loại bỏ attendee theo id  
                            $relatedAttendees = array_values(array_filter($relatedAttendees, function ($attendee) use ($data) {
                                return isset($attendee['user_id']) && $attendee['user_id'] != $data['atteendee_id'];
                            }));

                            if ($relatedTask->is_repeat) {
                                $relatedTask->update([
                                    'attendees'     => $relatedAttendees
                                ]);
                            } else {
                                $relatedTask->update([
                                    'attendees'     => $relatedAttendees
                                ]);
                            }

                            $returnTask[] = $relatedTask;
                        }

                        //Update current Task
                        $task->update(['attendees'     => $attendees]);

                        $returnTask[] = $task;
                    }

                    //Send API
                    $this->sendRealTimeUpdate($returnTask, 'update');

                    $owner->notify(new NotificationEvent(
                        $task->user_id,
                        "Tài khoản {$user->first_name} {$user->last_name} vừa mới rời khỏi chuỗi {$task->type} {$task->title} của bạn",
                        "",
                        "leave_task"
                    ));

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

    public function show($uuid)
    {
        $task = Task::with('user')
            ->select('id', 'user_id', 'title', 'description', 'start_time', 'end_time', 'location', 'timezone_code', 'attendees')
            ->where('type', 'event')
            ->where('uuid', $uuid)
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

    public function showOne($id)
    {
        if (Auth::check()) {
            $user_id = Auth::user()->id;
        } else {
            return response()->json([
                'code'    => 401,
                'message' => 'Unauthorized',
            ], 401);
        }

        // Retrieve the task by its ID  
        $task = Task::select('*')
            ->where('id', $id)
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
                    WHERE jt.user_id = ?  
                )  
            ", [$user_id]);
            })
            ->first();

        // If the task is not found  
        if (!$task) {
            return response()->json([
                'code'    => 404,
                'message' => 'Task not found',
            ], 404);
        }

        $timezone_code = $task->timezone_code;

        $task->rrule = [
            'freq'              => $task->freq,
            'interval'          => $task->interval,
            'until'             => Carbon::parse($task->until, 'UTC'),
            'count'             => $task->count,
            'byweekday'         => $task->byweekday,
            'bymonthday'        => $task->bymonthday,
            'bymonth'           => $task->bymonth,
            'bysetpos'          => $task->bysetpos,
        ];

        $task->start_time   = Carbon::parse($task->start_time, 'UTC');
        $task->end_time     = Carbon::parse($task->end_time, 'UTC');

        if ($task->exclude_time && count($task->exclude_time) > 0) {
            $cal_exclude_time = array_map(function ($date) use ($timezone_code) {
                return Carbon::parse($date, 'UTC');
            }, $task->exclude_time);

            $task->exclude_time = $cal_exclude_time;
        }

        if ($task->attendees) {
            $attendeesDetails = [];
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
        }

        // Remove unnecessary fields  
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

        // Return the response in a similar format as index  
        return response()->json([
            'code'    => 200,
            'message' => 'Fetching Data successfully',
            'data'    => $task,
        ], 200);
    }

    public function checkPossibleStartTime(Request $request)
    {
        //validate request
        $data = $request->validate([
            'start_time' => 'required',
            'end_time'   => 'required',
            'timezone_code' => 'required',
        ]);

        $data = $this->handleLogicData($data);

        $user_id = Auth::id();

        $tasks = Task::where('is_all_day', '!=', 1)
            ->where(function ($query) use ($data) {
                $query->where('start_time', '<=', $data['start_time'])
                    ->orWhere(function ($q) use ($data) {
                        $q->where('is_repeat', true)
                            ->where(function ($subQuery) use ($data) {
                                $subQuery->where('until', '>', $data['start_time'])
                                    ->orWhereNull('until');
                            });
                    });
            })
            ->where(function ($query) use ($user_id) {
                $query->where('tasks.user_id', $user_id)
                    ->orWhereRaw("
                    EXISTS (
                        SELECT 1 FROM JSON_TABLE(
                            attendees, '$[*]' COLUMNS (
                                user_id INT PATH '$.user_id',
                                status VARCHAR(20) PATH '$.status'
                            )
                        ) AS jt
                        WHERE jt.user_id = ?
                    )
                ", [$user_id]);
            })
            ->get();

        foreach ($tasks as $task) {
            $occurrences = $this->serviceGetAllOcc->getAllOccurrences($task);
            
            foreach ($occurrences as $occurrence) {
                if ($occurrence->equalTo($data['start_time'])) {
                    return response()->json([
                        'code'    => 477,
                        'message' => 'Trùng thời gian với một task khác'
                    ], 200);
                }
            }
        }

        return response()->json([
            'code'    => 200,
            'message' => 'Valid time range',
        ], 200);
    }

    public function store(Request $request)
    {

        //validate request
        $data = $request->validate([
            'tag_id'            => 'nullable',
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
            'freq'              => ['nullable', Rule::in('daily', 'weekly', 'monthly', 'yearly')],
            'interval'          => 'nullable|numeric',
            'until'             => 'nullable|date_format:Y-m-d H:i:s',
            'count'             => 'nullable|numeric',
            'byweekday'         => ['nullable', 'array'],
            'byweekday.*'       => [Rule::in(['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA'])],
            'bymonthday'        => 'nullable', //JSON
            'bymonth'           => 'nullable', //JSON
            'bysetpos'          => 'nullable', //JSON
            'exclude_time'      => 'nullable', //JSON
            'parent_id'         => 'nullable',
            'sendMail'          => 'nullable',
            'link'              => 'nullable',
            'is_private'        => 'nullable',
        ]);

        try {
            $data['user_id'] = Auth::id();

            $data = $this->handleJsonStringData($data);

            $data = $this->handleLogicData($data);

            $data['is_private'] = $data['is_private'] ?? 0;

            if (!empty($data['tag_id'])) {
                $tag = Tag::where('id', $data['tag_id'])->first();
                $sharedUsers = collect($tag->shared_user);
                $currentUser = $sharedUsers->firstWhere('user_id', Auth::id());

                if ($tag->user_id == Auth::id() || ($currentUser && $currentUser['role'] == "editor")) {
                    $task = Task::create($data);
                } else {
                    return response()->json([
                        'code' => 401,
                        'message' => 'You do not have permission to create new event with this tag',
                    ]);
                }
            } else {
                $task = Task::create($data);
            }

            $attendees = is_array($task->attendees) ? $task->attendees : json_decode($task->attendees, true);
            $users = User::whereIn('id', collect($attendees)->pluck('user_id'))->get();

            // create group chat after created tasks
            app(TaskGroupChatController::class)->createGroup($task->id, $task->user_id);

            if (isset($data['sendMail']) && $data['sendMail'] == 'yes') {
                $userIds = collect($task->attendees)->pluck('user_id');
                $emailGuests = User::select('email')->whereIn('id', $userIds)->get();
                $this->sendMail(Auth::user()->email, $emailGuests, $task);
            }

            foreach ($users as $user) {
                $user->notify(new NotificationEvent(
                    $user->id,
                    "Bạn có 1 lời mời tham gia {$task->type} {$task->title}",
                    "{$this->URL_FRONTEND}/calendar/event/{$task->uuid}/invite",
                    "invite_to_task"
                ));
            }

            if (!empty($data['tag_id']) && $task->type == 'event') {
                // $tag = Tag::where('id', $data['tag_id'])->first();
                $users = User::whereIn('id', collect($tag->shared_user)->pluck('user_id'))->get();

                $attendees = $task->attendees ?? [];
                $existingUserIds = collect($attendees)->pluck('user_id')->toArray();

                foreach ($users as $user) {
                    if (!in_array($user->id, $existingUserIds)) {
                        $attendees[] = [
                            'role'      =>  'viewer',
                            'status'    =>  'yes',
                            'user_id'   =>  $user->id,
                        ];
                    }

                    $user->notify(new NotificationEvent(
                        $user->id,
                        "Vừa có {$task->type} {$task->title} được thêm vào trong {$tag->name}",
                        "",
                        "new_task_in_tag"
                    ));
                }
            }

            $task->attendees = $attendees;

            //Send REALTIME
            $returnTask[] = $task;

            $this->sendRealTimeUpdate($returnTask, 'create');


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

        if (!empty($task->tag_id)) {
            $tag = Tag::where('id', $task->tag_id)->first();
            $sharedUsers = collect($tag->shared_user);
            $currentUser = $sharedUsers->firstWhere('user_id', Auth::id());
        }

        $setting = Setting::select('timezone_code')
            ->where('user_id', '=', Auth::id())
            ->first();

        if ($task->user_id  === Auth::id() || ($currentUser && $currentUser['role'] == "editor")) {
            switch ($code) {
                case 'DEL_N':
                    try {
                        $returnTask[] = $task;

                        $task->delete();

                        // delete all tasks -> delete group chats
                        // app(TaskGroupChatController::class)->deleteGroup($task->id);

                        //Send REALTIME        
                        $this->sendRealTimeUpdate($returnTask, 'delete');

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
                        if ($task->exclude_time) {
                            $excludeTime = $task->exclude_time;
                        } else {
                            $excludeTime = [];
                        }

                        // add current date to exclude_time
                        if (!in_array($currentDate, $excludeTime)) {
                            $excludeTime[] = Carbon::parse($currentDate, $setting->timezone_code)->setTimezone('UTC');
                        }

                        $taskStartTime = Carbon::parse($task->start_time);
                        $taskEndTime = Carbon::parse($task->end_time);

                        $updatedStartTime = Carbon::parse($currentDate, $setting->timezone_code)
                            ->setTime($taskStartTime->hour, $taskStartTime->minute, $taskStartTime->second)
                            ->setTimezone('UTC');
                        $updatedEndTime = Carbon::parse($currentDate, $setting->timezone_code)
                            ->setTime($taskEndTime->hour, $taskEndTime->minute, $taskEndTime->second)
                            ->setTimezone('UTC');

                        $new_task = Task::create([
                            'start_time'    => $updatedStartTime,
                            'end_time'      => $updatedEndTime,
                            'title'         => $task->title,
                            'description'   => $task->description,
                            'user_id'       => $task->user_id,
                            'timezone_code' => $task->timezone_code,
                            'color_code'    => $task->color_code,
                            'tag_id'        => $task->tag_id ?? null,
                            'attendees'     => $task->attendees,
                            'location'      => $task->location,
                            'type'          => $task->type,
                            'is_all_day'    => $task->is_all_day,
                            'is_busy'       => $task->is_busy,
                            'is_reminder'   => $task->is_reminder,
                            'reminder'      => $task->reminder,
                            'link'          => $task->link,
                            'is_private'    => $task->is_private,
                            'parent_id'     => $task->parent_id ?? $task->id,
                        ])->delete();

                        // $returnTaskDel[] = $new_task;

                        // Encode back to JSON before saving
                        $task->exclude_time = $excludeTime;
                        $task->save();

                        $returnTask[] = $task;

                        //Send REALTIME        
                        $this->sendRealTimeUpdate($returnTask, 'update');

                        return response()->json([
                            'code' => 200,
                            'message' => 'Delete task successfully'
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
                        $preUntil = $task->until;

                        // Giảm đi 1 ngày để xóa đc task
                        $task->until = Carbon::parse($request->date)->subDay();

                        $task->save();

                        $returnTask[] = $task;

                        //Send REALTIME        
                        $this->sendRealTimeUpdate($returnTask, 'update');

                        // Xoá các task liên quan về sau
                        // $tasksChild = Task::where('start_time', '>', $request->date)
                        //     ->where('id', $task->id)
                        //     ->orWhere('parent_id', $task->id)
                        //     ->get();

                        // $returnTaskDel[] = $tasksChild;

                        // if (!$tasksChild->isEmpty()) {
                        //     foreach ($tasksChild as $task) {
                        //         $task->delete();
                        //     }
                        // }

                        $relatedTasks = Task::where(function ($query) use ($task) {
                            $query->where('parent_id', $task->id);
                            // Kiểm tra nếu parent_id của task hiện tại không phải là null  
                            if ($task->parent_id !== null) {
                                $query->orWhere('parent_id', $task->parent_id);
                            }
                        })
                            ->where('start_time', '>=', $request->date)
                            ->get();

                        $returnTaskDel = [];

                        foreach ($relatedTasks as $relatedTask) {
                            // if ($relatedTask->is_repeat) {
                            //     $relatedTask->create([
                            //         'parent_id'     => $task->parent_id ?? $task->id,
                            //         'title'         => $task->title,
                            //         'description'   => $task->description,
                            //         'user_id'       => $task->user_id,
                            //         'timezone_code' => $task->timezone_code,
                            //         'color_code'    => $task->color_code,
                            //         'tag_id'        => $task->tag_id ?? null,
                            //         'attendees'     => $task->attendees,
                            //         'location'      => $task->location,
                            //         'type'          => $task->type,
                            //         'is_all_day'    => $task->is_all_day,
                            //         'is_busy'       => $task->is_busy,
                            //         'is_reminder'   => $task->is_reminder,
                            //         'reminder'      => $task->reminder,
                            //         'is_repeat'     => $task->is_repeat,
                            //         'freq'          => $task->freq,
                            //         'interval'      => $task->interval,
                            //         'until'         => $preUntil,
                            //         'count'         => $task->count,
                            //         'byweekday'     => $task->byweekday,
                            //         'bymonthday'    => $task->bymonthday,
                            //         'bymonth'       => $task->bymonth,
                            //         'link'          => $task->link,
                            //         'is_private'    => $task->is_private,
                            //         'start_time'    => $relatedTask->start_time,
                            //         'end_time'      => $relatedTask->end_time,
                            //     ]);
                            // } else {
                            //     $relatedTask->create([
                            //         'parent_id'     => $task->parent_id ?? $task->id,
                            //         'title'         => $task->title,
                            //         'description'   => $task->description,
                            //         'user_id'       => $task->user_id,
                            //         'timezone_code' => $task->timezone_code,
                            //         'color_code'    => $task->color_code,
                            //         'tag_id'        => $task->tag_id ?? null,
                            //         'attendees'     => $task->attendees,
                            //         'location'      => $task->location,
                            //         'type'          => $task->type,
                            //         'is_all_day'    => $task->is_all_day,
                            //         'is_busy'       => $task->is_busy,
                            //         'is_reminder'   => $task->is_reminder,
                            //         'reminder'      => $task->reminder,
                            //         'link'          => $task->link,
                            //         'is_private'    => $task->is_private,
                            //     ]);
                            // }

                            $relatedTask->forceDelete();

                            $returnTaskDel[] = $relatedTask;
                        }

                        //Send REALTIME
                        if (!$returnTaskDel) {
                            $this->sendRealTimeUpdate($returnTaskDel, 'delete');
                        }

                        return response()->json([
                            'code' => 200,
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
                        $deleteTasks = Task::where('id', $task->id)
                            ->orWhere('parent_id',   $task->id)
                            ->orWhere('id',   $task->parent_id)
                            ->orWhere(function ($query) use ($task) {
                                // Nếu có parent_id, lấy thêm các task con của task cha  
                                if ($task->parent_id !== null) {
                                    $query->where('parent_id', $task->parent_id);
                                }
                            })
                            ->get();

                        if ($deleteTasks->isNotEmpty()) {
                            // Gửi Realtime  
                            $this->sendRealTimeUpdate($deleteTasks, 'delete');

                            // Xóa Task
                            $deleteTasks->each->delete();
                        }

                        // delete all tasks -> delete group chats
                        app(TaskGroupChatController::class)->deleteGroup($task->id);

                        return response()->json([
                            'code' => 200,
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
                'code' => 401,
                'message' => 'You do not have permission to edit this event',
            ]);
        }
    }

    public function forceDestroy(Request $request)
    {
        // Validate input  
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:tasks,id',
        ]);

        $ids = $request->input('ids');

        $canDelete = false;

        try {
            $tasks = Task::withTrashed()->whereIn('id', $ids)->get();

            foreach ($tasks as $task) {
                if (!empty($task->tag_id)) {
                    $tag = Tag::where('id', $task->tag_id)->first();
                    $sharedUsers = collect($tag->shared_user);
                    $currentUser = $sharedUsers->firstWhere('user_id', Auth::id());

                    if ($task->user_id === Auth::id() || ($currentUser && $currentUser['role'] == "editor")) {
                        $canDelete = true;
                    } else {
                        return response()->json([
                            'code' => 401,
                            'message' => 'You do not have permission to delete ' . $task->title . ' event',
                        ], 401);
                    }
                } else {
                    if ($task->user_id === Auth::id()) {
                        $canDelete = true;
                    } else {
                        return response()->json([
                            'code' => 401,
                            'message' => 'You do not have permission to delete this event',
                        ], 401);
                    }
                }
            }

            if ($canDelete) {
                Task::whereIn('id', $ids)->forceDelete();

                // Return response  
                return response()->json([
                    'code' => 200,
                    'message' => 'Force delete tasks successfully',
                ], 200);
            }
        } catch (\Throwable $th) {
            // Xử lý ngoại lệ  
            return response()->json([
                'code' => 500,
                'message' => 'An error occurred while deleting tasks',
                'error' => $th->getMessage()
            ], 500);
        }

        // Trả lại phản hồi nếu không có quyền hoặc không có task nào để xóa  
        return response()->json([
            'code' => 400,
            'message' => 'No tasks were found to delete or you do not have permission.',
        ], 400);
    }

    public function restoreTask(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:tasks,id',
        ]);

        $ids = $request->input('ids');

        try {
            $tasks = Task::onlyTrashed()->whereIn('id', $ids)->get();

            if ($tasks->isEmpty()) {
                return response()->json([
                    'code' => 404,
                    'message' => 'No tasks found to restore.',
                ], 404);
            }

            foreach ($tasks as $task) {
                $canRestore = false;

                if (!empty($task->tag_id)) {
                    $tag = Tag::where('id', $task->tag_id)->first();
                    $sharedUsers = collect($tag->shared_user);
                    $currentUser = $sharedUsers->firstWhere('user_id', Auth::id());

                    if ($task->user_id === Auth::id() || ($currentUser && $currentUser['role'] == "editor")) {
                        $canRestore = true;
                    } else {
                        return response()->json([
                            'code' => 401,
                            'message' => 'You do not have permission to restore this task.',
                        ], 401);
                    }
                } else {
                    if ($task->user_id === Auth::id()) {
                        $canRestore = true;
                    } else {
                        return response()->json([
                            'code' => 401,
                            'message' => 'You do not have permission to restore this task.',
                        ], 401);
                    }
                }
            }

            foreach ($tasks as $task) {
                $task->restore();
            }

            return response()->json([
                'code' => 200,
                'message' => 'Tasks restored successfully.',
            ], 200);
        } catch (\Throwable $th) {
            // Xử lý ngoại lệ  
            return response()->json([
                'code' => 500,
                'message' => 'An error occurred while restoring tasks.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function getTrashedTasks()
    {
        if (Auth::check()) {
            $user_id = Auth::user()->id;
        }

        $tasks = Task::select('tasks.*', 'tags.name as tag_name')
            ->leftJoin('tags', 'tasks.tag_id', '=', 'tags.id')
            ->onlyTrashed()
            ->get();

        foreach ($tasks as $task) {
            $timezone_code = $task->timezone_code;

            $task->rrule = [
                'freq'              => $task->freq,
                'interval'          => $task->interval,
                'until'             => Carbon::parse($task->until, 'UTC'),
                'count'             => $task->count,
                'byweekday'         => $task->byweekday,
                'bymonthday'        => $task->bymonthday,
                'bymonth'           => $task->bymonth,
                'bysetpos'          => $task->bysetpos,
            ];

            $task->start_time   = Carbon::parse($task->start_time, 'UTC');
            $task->end_time     = Carbon::parse($task->end_time, 'UTC');

            if ($task->exclude_time && count($task->exclude_time) > 0) {
                $cal_exclude_time = array_map(function ($date) use ($timezone_code) {
                    return Carbon::parse($date, 'UTC');
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

    public function acceptInvite(Request $request, $uuid)
    {
        $user = auth()->user();

        $task = Task::with('user.setting')
            ->where('type', 'event')
            ->where('uuid', $uuid)
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
            // Kiểm tra nếu attendees là mảng
            $attendees = is_array($task->attendees) ? $task->attendees : [];

            // Kiểm tra người dùng đã tồn tại trong attendees chưa
            $attendeeIndex = array_search($user->id, array_column($attendees, 'user_id'));

            if ($attendeeIndex !== false) {
                if ($attendees[$attendeeIndex]['status'] === 'yes') {
                    return response()->json([
                        'code'    => 409,
                        'message' => 'You have already accepted this event',
                    ]);
                } else {
                    // Cập nhật trạng thái trong biến tạm
                    $attendees[$attendeeIndex]['status'] = 'yes';
                }
            } else {
                // Nếu chưa có, thêm mới người dùng vào danh sách attendees
                $attendees[] = [
                    'role'    => 'viewer',
                    'status'  => 'yes',
                    'user_id' => $user->id
                ];
            }

            // Gán lại danh sách attendees vào model
            $task->attendees = $attendees;
            $task->save(); // Lưu thay đổi vào database

            $returnTask[] = $task;
            $this->sendRealTimeUpdate($returnTask, 'update');

            //Send Notification
            $owner = User::find($task->user_id);

            $owner->notify(new NotificationEvent(
                $task->user_id,
                "Tài khoản {$user->first_name} {$user->last_name} vừa mới đồng ý tham gia {$task->type} {$task->title} của bạn",
                "",
                "accept_invite"
            ));

            // Thêm thông báo
            Reminder::insert([
                'title'   => 'Event notification',
                'user_id' => $task->user_id,
                'message' => 'User ' . $user->first_name . ' ' . $user->last_name . ' has accepted to participate in your event: ' . $task->title,
                'type'    => 'event',
                'sent_at' => Carbon::now($task->user->setting->timezone_code),
            ]);

            // add member to group chat after member accepts invitation
            app(TaskGroupChatController::class)->addMember($task->id, $user->id);

            DB::commit();

            return response()->json([
                'code'    => 200,
                'message' => 'You have successfully accepted the event',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred',
            ], 500);
        }
    }

    public function refuseInvite(Request $request, $uuid)
    {
        $user = auth()->user();

        $task = Task::with('user.setting')
            ->where('type', 'event')
            ->where('uuid', $uuid)
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
            Log::info('before', [$task->attendees]);

            if (in_array($user->id, array_column($task->attendees, 'user_id'))) {
                $task->attendees = array_filter($task->attendees, function ($attendee) use ($user) {
                    return $attendee['user_id'] != $user->id;
                });
                Log::info('after', [$task->attendees]);
                $task->save();

                $returnTask[] = $task;
                $this->sendRealTimeUpdate($returnTask, 'update');
            }

            DB::commit();

            //Send Notification
            $owner = User::find($task->user_id);

            $owner->notify(new NotificationEvent(
                $task->user_id,
                "Tài khoản {$user->first_name} {$user->last_name} vừa từ chối tham gia {$task->type} {$task->title} của bạn",
                "",
                "refuse_invite"
            ));

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

    protected function sendMail($mailOwner, $emails, $data)
    {
        $nameOwner = Auth::user()->first_name . ' ' . Auth::user()->last_name;
        foreach ($emails as $email) {
            Mail::to($email)->queue(new InviteGuestMail($mailOwner, $nameOwner, $data));
        }
    }

    public function search(Request $request)
    {
        $user_id = auth()->user()->id;
        $title      = $request->query('title');
        $tag        = $request->query('tag');
        $start      = $request->query('start');
        $end        = $request->query('end');
        $location   = $request->query('location');

        $query = Task::select('*')
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
                    WHERE jt.user_id = ?
                )
            ", [$user_id]);
            });

        if (!empty($title)) {
            $query->where('title', 'like', "%$title%");
        }

        if (!empty($tag)) {
            $query->where('tag_id', $tag);
        }

        if (!empty($location)) {
            $query->where('location', 'like', "%$location%");
        }

        if (!empty($start) && !empty($end)) {
            $query->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_time', ["$start 00:00:00", "$end 23:59:59"])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->where('is_repeat', true)
                            ->where(function ($q3) use ($start, $end) {
                                $q3->whereNull('until')->orWhere('until', '>=', "$start 00:00:00");
                            });
                    });
            });
        }

        $tasks = $query->get();

        $expandedTasks = [];

        foreach ($tasks as $task) {
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

            if (!$task->is_repeat || !$task->freq) {
                $expandedTasks[] = $task;
                continue;
            }

            // Xác định thời gian lặp
            $repeatStart = Carbon::parse($task->start_time);
            $repeatUntil = $task->until ? Carbon::parse($task->until) : null;
            $interval = $task->interval ?? 1;
            $count = $task->count ?? 1000;
            $occurrences = 0;

            // Nếu không có ngày `$start` và `$end`, lấy toàn bộ trong 5 năm để tránh vô hạn
            if (empty($start) || empty($end)) {
                $searchStart = $repeatStart;
                $searchEnd = $repeatUntil ?? Carbon::now()->addYears(3);
            } else {
                $searchStart = Carbon::parse($start);
                $searchEnd = Carbon::parse($end);
            }

            while ($repeatStart <= $searchEnd && $occurrences < $count) {
                $formattedDate = $repeatStart->format('Y-m-d H:i:s');

                // Bỏ qua nếu ngày nằm trong danh sách loại trừ
                if (in_array($formattedDate, $task->exclude_time ?? [])) {
                    $repeatStart = $this->nextRepeatDate($repeatStart, $task->freq, $interval);
                    continue;
                }

                // Kiểm tra logic lặp lại theo từng kiểu tần suất
                if ($task->freq == 'weekly' && !empty($task->byweekday)) {
                    while (!in_array(strtoupper(substr($repeatStart->format('D'), 0, 2)), $task->byweekday)) {
                        $repeatStart = $repeatStart->modify("+1 day");;
                    }
                }

                if ($task->freq == 'monthly' && !empty($task->bymonthday)) {
                    // Lưu lại tháng hiện tại để lặp qua tất cả các ngày trong bymonthday
                    $currentMonth = $repeatStart->format('m');

                    foreach ($task->bymonthday as $day) {
                        // Tạo bản sao của $repeatStart để không làm thay đổi ngày gốc
                        $tempDate = clone $repeatStart;
                        $tempDate->setDate($tempDate->format('Y'), $currentMonth, $day);

                        // Chỉ thêm vào danh sách nếu nằm trong khoảng tìm kiếm
                        if ($tempDate >= $searchStart && $tempDate <= $searchEnd) {
                            $newTask = clone $task;
                            $newTask->start_time = $tempDate->format('Y-m-d H:i:s');
                            $expandedTasks[] = $newTask;
                        }
                    }

                    // Sau khi xử lý hết ngày trong tháng, chuyển sang tháng tiếp theo
                    $repeatStart = $this->nextRepeatDate($repeatStart, $task->freq, $interval);
                }

                if ($task->freq == 'yearly' && !empty($task->bymonth)) {
                    if (!in_array($repeatStart->month, $task->bymonth)) {
                        $repeatStart = $this->nextRepeatDate($repeatStart, $task->freq, $interval);
                        continue;
                    }
                }

                // Chỉ thêm task nếu nó nằm trong phạm vi tìm kiếm
                if ($repeatStart >= $searchStart && $repeatStart <= $searchEnd) {
                    $newTask = clone $task;
                    $newTask->start_time = $repeatStart->format('Y-m-d H:i:s');
                    $expandedTasks[] = $newTask;
                }

                // Chuyển đến ngày tiếp theo
                $repeatStart = $this->nextRepeatDate($repeatStart, $task->freq, $interval);
                $occurrences++;
            }
        }

        return response()->json([
            'code'    => 200,
            'message' => 'Success',
            'data'    => $expandedTasks,
        ]);
    }

    private function nextRepeatDate($date, $freq, $interval)
    {
        return match ($freq) {
            'daily' => $date->addDays($interval),
            'weekly' => $date->addWeeks($interval),
            'monthly' => $date->addMonths($interval),
            'yearly' => $date->addYears($interval),
            default => $date,
        };
    }

    public function getUpComingTasks()
    {
        if (Auth::check()) {
            $user_id = Auth::user()->id;
        }

        $now = Carbon::now();
        $next24Hours = $now->copy()->addDay();

        $tasks = Task::with('tag:id,name')
            ->whereNotNull('reminder')
            ->where(function ($query) use ($now, $next24Hours) {
                $query->whereBetween('start_time', [$now, $next24Hours])
                    ->orWhere(function ($q) use ($now, $next24Hours) {
                        $q->where('is_repeat', 1)
                            ->where(function ($subQuery) use ($now, $next24Hours) {
                                $subQuery->where('until', '>=', $now) // Nếu until có giá trị, chỉ lấy các bản ghi chưa hết hạn
                                    ->orWhereNull('until'); // Nếu until là NULL, nó lặp vô hạn
                            });
                    })
                    ->orWhere(function ($q) use ($now, $next24Hours) {
                        $q->where('is_repeat', 0) // Đưa điều kiện này vào để lấy bản ghi có is_repeat = 0  
                            ->whereBetween('start_time', [$now, $next24Hours]); // Thời gian cũng được kiểm tra  
                    });
            })->where(function ($query) use ($user_id) {
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
            ->get()
            ->map(function ($task) {
                $task->tag_name = optional($task->tag)->name; // Lấy `name`, nếu null thì không lỗi
                unset($task->tag); // Xóa object `tag`, chỉ giữ lại `tag_name`
                return $task;
            });

        $validTasks = [];

        foreach ($tasks as $task) {
            if ($task->is_repeat) {
                $nextOccurrence = $this->serviceNextOcc->getNextOccurrence($task, $now);
                Log::info('nextOccurrence', [$nextOccurrence]);

                if ($nextOccurrence && $nextOccurrence->greaterThanOrEqualTo($now) && $nextOccurrence->lessThanOrEqualTo($next24Hours)) {
                    // $timezone = $task->timezone_code ?? 'UTC';
                    // $task->start_time = $nextOccurrence->copy()->tz($timezone)->toDateTimeString();

                    Log::info('nextOccurrence', [$nextOccurrence, $now, $next24Hours]);

                    $task->start_time = $nextOccurrence->copy()->tz('UTC');
                    $task->end_time = Carbon::parse($task->end_time)
                        ->setDate($nextOccurrence->year, $nextOccurrence->month, $nextOccurrence->day)
                        ->tz('UTC');

                    $task->rrule = [
                        'freq'              => $task->freq,
                        'interval'          => $task->interval,
                        'until'             => Carbon::parse($task->until, 'UTC'),
                        'count'             => $task->count,
                        'byweekday'         => $task->byweekday,
                        'bymonthday'        => $task->bymonthday,
                        'bymonth'           => $task->bymonth,
                        'bysetpos'          => $task->bysetpos,
                    ];

                    unset(
                        $task->freq,
                        $task->interval,
                        $task->until,
                        $task->count,
                        $task->byweekday,
                        $task->bymonthday,
                        $task->bymonth,
                        $task->bysetpos,
                        $task->reminder,
                        $task->attendees,
                        $task->path,
                        $task->parent_id,
                        $task->deleted_at,
                        $task->created_at,
                        $task->updated_at,
                        $task->exclude_time,
                    );

                    $validTasks[] = $task;

                    Log::info('validTasks', [$validTasks]);
                }
            } else {
                $task->start_time = Carbon::parse($task->start_time)->tz('UTC');
                $task->end_time = Carbon::parse($task->end_time)->tz('UTC');

                if ($task->start_time->greaterThanOrEqualTo($now) && $task->start_time->lessThanOrEqualTo($next24Hours)) {
                    $validTasks[] = $task; // Chỉ thêm task hợp lệ vào mảng  
                }
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
        }

        if (empty($validTasks)) {
            $validTasks = []; // Trả về mảng rỗng nếu không có giá trị  
        } else {
            usort($validTasks, function ($a, $b) {
                return Carbon::parse($a->start_time)->timestamp <=> Carbon::parse($b->start_time)->timestamp;
            });
        }

        // Trả về view và truyền dữ liệu vào
        return response()->json([
            'code'      =>  200,
            'message'   =>  'Fetching Data successfully',
            'data'      =>  $validTasks,
        ], 200);
    }
}
