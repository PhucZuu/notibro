<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use App\Mail\TaskReminderMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TaskReminderService
{
    public function taskMailRemindSchedule()
    {
        Log::info('Bắt đầu taskMailRemindSchedule');
        $now = Carbon::now();
        $next24Hours = $now->copy()->addHours(24);

        // Lấy danh sách task có nhắc nhở và nằm trong khoảng thời gian hợp lệ
        $tasks = Task::where('is_reminder', true)
            ->whereNotNull('reminder')
            ->where(function ($query) use ($now, $next24Hours) {
                $query->whereBetween('start_time', [$now, $next24Hours])
                    ->orWhere(function ($q) use ($now, $next24Hours) {
                        $q->where('is_repeat', 1)
                            ->where(function ($subQuery) use ($now, $next24Hours) {
                                $subQuery->where('until', '>=', $now)
                                    ->orWhereNull('until');
                            });
                    });
            })->get();

        Log::info('Số lượng task cần xử lý: ' . $tasks->count());

        foreach ($tasks as $task) {
            Log::info("Xử lý task ID: {$task->id}");
            foreach ($task->reminder as $reminder) {
                if ($reminder['type'] === 'email') {
                    Log::info("Xử lý nhắc nhở email cho task ID: {$task->id}");
                    $this->processEmailReminder($task, $reminder, $now);
                }
            }
        }
    }

    private function processEmailReminder($task, $reminder, $now)
    {
        Log::info("Bắt đầu processEmailReminder cho task ID: {$task->id}");
        $nextOccurrence = $this->getNextOccurrence($task, $now);
        if (!$nextOccurrence) {
            Log::info("Không có lần xuất hiện tiếp theo cho task ID: {$task->id}");
            return;
        }

        $reminderTime = Carbon::parse($nextOccurrence)->subMinutes($reminder['set_time']);
        if (!$now->isSameMinute($reminderTime)) {
            Log::info("Không phải thời gian nhắc nhở cho task ID: {$task->id},{$reminderTime},{$now}");
            return;
        }

        foreach ($task->getAttendees() as $userID) {
            Log::info("Gửi nhắc nhở email cho user ID: {$userID}");
            $this->sendEmailReminder($task, $userID, $reminderTime);
        }
    }

    private function sendEmailReminder($task, $userID, $reminderTime)
    {
        Log::info("Bắt đầu sendEmailReminder cho task ID: {$task->id}, user ID: {$userID}");
        $user = User::find($userID);
        if (!$user) {
            Log::warning("Không tìm thấy user ID {$userID} cho task {$task->id}");
            return;
        }

        $cacheKey = "task_notified_{$task->id}_{$user->id}_{$reminderTime->timestamp}";
        if (Cache::has($cacheKey)) {
            Log::warning("Đã gửi email trước đó: {$cacheKey}");
            return;
        }

        // Gửi email
        try {
            Mail::to($user->email)->send(new TaskReminderMail($user, $task, $reminderTime));
            Log::info("Đã gửi email nhắc nhở task {$task->id} đến user {$user->id}");
            Cache::put($cacheKey, true, now()->addHours(24));
        } catch (\Exception $e) {
            Log::error("Lỗi khi gửi email task {$task->id} đến user {$user->id}: " . $e->getMessage());
        }
    }

    protected function getNextOccurrence($task, $now)
    {
        Log::info("Bắt đầu getNextOccurrence cho task ID: {$task->id}");
        if (!$task->is_repeat) {
            return Carbon::parse($task->start_time);
        }

        $until = $task->until ? Carbon::parse($task->until) : null;
        if ($until && $now->greaterThan($until)) {
            return null;
        }

        switch ($task->freq) {
            case 'daily':
                return $this->getNextInterval($task, $now, 'days');

            case 'weekly':
                return $this->getNextWeeklyOccurrence($task, $now);

            case 'monthly':
                return $this->getNextMonthlyOccurrence($task, $now);

            case 'yearly':
                return $this->getNextInterval($task, $now, 'years');

            default:
                return null;
        }
    }

    protected function getNextInterval($task, $now, $unit)
    {
        Log::info("Bắt đầu getNextInterval cho task ID: {$task->id}, unit: {$unit}");
        $interval = $task->interval ?? 1;
        $startTime = Carbon::parse($task->start_time);
        $untilTime = $task->until ? Carbon::parse($task->until) : null;
        $maxCount = $task->count;
        $occurrenceCount = 0;

        while ($startTime->lessThan($now) && (!$untilTime || $startTime->lessThan($untilTime))) {
            $startTime->add($unit, $interval);
            if ($maxCount !== null && ++$occurrenceCount > $maxCount) {
                return null;
            }
        }

        return $startTime;
    }

    protected function getNextWeeklyOccurrence($task, $now)
    {
        Log::info("Bắt đầu getNextWeeklyOccurrence cho task ID: {$task->id}");
        $weekdays = $task->byweekday ?? [];
        $startTime = Carbon::parse($task->start_time);
        $validDays = array_map(fn($d) => ['SU' => 0, 'MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6][$d] ?? null, $weekdays);
        $validDays = array_filter($validDays);

        $nextOccurrence = Carbon::now();
        $maxCount = $task->count;
        $occurrenceCount = 0;

        while (!in_array($nextOccurrence->dayOfWeek, $validDays)) {
            $nextOccurrence->addDay();
            if ($maxCount !== null && ++$occurrenceCount > $maxCount) {
                return null;
            }
        }

        return $nextOccurrence;
    }

    protected function getNextMonthlyOccurrence($task, $now)
    {
        Log::info("Bắt đầu getNextMonthlyOccurrence cho task ID: {$task->id}");
        $startTime = Carbon::parse($task->start_time);
        $interval = $task->interval ?? 1;
        $count = $task->count ?? null;
        $monthDays = $task->bymonthday ?? [$startTime->day];

        $nextOccurrence = $startTime->copy();
        $occurrenceCount = 0;

        while (true) {
            if ($nextOccurrence->greaterThan($now) && in_array($nextOccurrence->day, $monthDays)) {
                break;
            }

            $nextOccurrence->addMonths($interval);

            if ($count && ++$occurrenceCount > $count) {
                Log::error("Số lần xuất hiện vượt quá giới hạn cho task ID: {$task->id}");
                return null;
            }
        }

        return $nextOccurrence;
    }
}
