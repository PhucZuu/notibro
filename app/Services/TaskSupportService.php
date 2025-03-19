<?php

namespace App\Services;

use App\Events\Task\TaskUpdatedEvent;
use App\Http\Controllers\Api\Chat\TaskGroupChatController;
use App\Mail\InviteGuestMail;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use App\Notifications\NotificationEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class TaskSupportService
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

    protected function sendMail($mailOwner, $emails, $data)
    {
        $nameOwner = Auth::user()->first_name . ' ' . Auth::user()->last_name;
        foreach ($emails as $email) {
            Mail::to($email)->queue(new InviteGuestMail($mailOwner, $nameOwner, $data));
        }
    }

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

    public function store($data)
    {
        $data['user_id'] = Auth::id();

        $data = $this->handleJsonStringData($data);

        $data = $this->handleLogicData($data);

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

        if (!empty($data['tag_id'])) {
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


        return $task;
    }
}
