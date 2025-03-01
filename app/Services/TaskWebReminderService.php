<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskNotification;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TaskWebReminderService
{
    public function taskWebSchedule()
    {
        $now = Carbon::now();
        $next24Hours = $now->copy()->addHours(24);
        $tasks = Task::whereNotNull('reminder')
            ->where(function ($query) use ($now, $next24Hours) {
                $query->whereBetween('start_time', [$now, $next24Hours])
                    ->orWhere(function ($q) use ($now, $next24Hours) {
                        $q->where('is_repeat', 1)
                            ->where(function ($subQuery) use ($now, $next24Hours) {
                                $subQuery->where('until', '>=', $now) // Nếu until có giá trị, chỉ lấy các bản ghi chưa hết hạn
                                    ->orWhereNull('until'); // Nếu until là NULL, nó lặp vô hạn
                            });
                    });
            })->get();
        Log::info("Task đã chạy đến f1 thông báo, bỏ qua...{$tasks}");
        foreach ($tasks as $task) {

            foreach ($task->reminder as $reminder) {
                if ($reminder['type'] == 'web') {
                    $nextOccurrence = $this->getNextOccurrence($task, $now);

                    $nextOccurrence = Carbon::parse($nextOccurrence)->subMinutes( $reminder['set_time'] );

                    if ($nextOccurrence && $now->isSameMinute($nextOccurrence)) {

                        foreach ($task->getAttendees() as $userID) {

                            $user = User::find($userID);

                            if ($user) {

                                $cacheKey = "task_notified_{$task->id}_{$user->id}_{$nextOccurrence->timestamp}";

                                //Check if the task has been notified
                                if (Cache::has($cacheKey)) {
                                    Log::warning("Đã được thông báo với->Cache key: {$cacheKey}<-");
                                    continue;
                                }

                                //Send notification
                                $user->notify(new TaskNotification($task)); 
                                Log::info("Task {$task->id} đã được thông báo, bỏ qua... ->Cache key: {$cacheKey}<-");
                                
                                //Set cache to prevent duplicate notification
                                Cache::put($cacheKey, true, now()->addHours(24));

                            }else {  
                                Log::warning("Không tìm thấy người dùng với ID {$userID} cho tác vụ {$task->id}.");  
                            }
                        }
                    } else {
                        Log::warning("Thời gian không khớp bỏ qua...");
                    }
                }
            }
        }
    }

    //Calculate function for reminder time
    protected function getNextOccurrence($task, $now)
    {
        if (!$task->is_repeat) {
            return Carbon::parse($task->start_time);
        }
        

        $startTime = Carbon::parse($task->start_time);
        $until = $task->until ? Carbon::parse($task->until) : null;

        Log::info("Task đã nhận {$startTime} và {$until} của {$task->id} {$task->title}");

        if ($until && $now->greaterThan($until)) {
            return null;
        }
        $nextOccurrence = null;

        switch ($task->freq) {
            case 'daily':
                $nextOccurrence = $this->getNextInterval($task, $now, 'days');

                Log::info("Task đã nhận đưuọc lần chạy tiếp theo Gốc: {$startTime} Next: {$nextOccurrence} - case Daily");
                break;

            case 'weekly':
                $nextOccurrence = $this->getNextWeeklyOccurence($task, $now);

                Log::info("Task đã nhận đưuọc lần chạy tiếp theo Gốc: {$startTime} Next: {$nextOccurrence} - case Weekly");
                break;

            case 'monthly':
                $nextOccurrence = $this->getNextMonthlyOccurence($task, $now);

                Log::info("Task đã nhận đưuọc lần chạy tiếp theo Gốc: {$startTime} Next: {$nextOccurrence} - case Monthly");
                break;

            case 'yearly':
                $nextOccurrence = $this->getNextInterval($task, $now, 'years');

                Log::info("Task đã nhận đưuọc lần chạy tiếp theo Gốc: {$startTime} Next: {$nextOccurrence} - case Yearly");
                break;

            default:

                break;
        }

        if ($task->exclude_time && in_array($nextOccurrence, $task->exclude_time)) {
            return null;
        }

        return $nextOccurrence;
    }


    protected function getNextInterval($task, $now, $unit)
    {
        $interval = $task->interval ?? 1;
        $startTime = Carbon::parse($task->start_time);

        while ($startTime->lessThan($now)) {
            $startTime = $startTime->add($unit, $interval);
        }

        Log::info("Task đã trả vể giá trị chạy tiếp theo {$startTime}");

        return $startTime;
    }

    protected function getNextWeeklyOccurence($task, $now)
    {
        $startTime = Carbon::parse($task->start_time);
        $weekdays = $task->byweekday ?? [$startTime->dayOfWeek];

        $dayMapping = [
            'SU' => 0, 'MO' => 1, 'TU' => 2, 'WE' => 3,
            'TH' => 4, 'FR' => 5, 'SA' => 6
        ];

        $validDays = array_map(fn($d) => $dayMapping[$d] ?? null, $weekdays);
        $validDays = array_filter($validDays); // Loại bỏ null nếu có

        $nextOccurrence = $startTime->copy();

        while ($nextOccurrence->lessThan($now) || !in_array($nextOccurrence->dayOfWeek, $weekdays)) {
            $nextOccurrence = $nextOccurrence->addWeek();
        }

        return $nextOccurrence;
    }

    protected function getNextMonthlyOccurence($task, $now)
    {
        $startTime = Carbon::parse($task->start_time);
        $monthDays = $task->bymonthday ?? [$startTime->day];

        $nextOccurrence = $startTime->copy();

        while ($nextOccurrence->lessThan($now) || !in_array($nextOccurrence->day, $monthDays)) {
            $nextOccurrence = $nextOccurrence->addMonth();
        }

        return $nextOccurrence;
    }
}
