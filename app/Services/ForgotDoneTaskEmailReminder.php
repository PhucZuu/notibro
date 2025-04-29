<?php

namespace App\Services;

use App\Mail\TaskIsDoneReminderMail;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;

class ForgotDoneTaskEmailReminder
{
    protected const CACHE_PREFIX = 'task_is_done_reminder_sent:';
    protected const GRACE_PERIOD_MINUTES = 5;
    protected const CACHE_TTL_HOURS = 24;

    public function handle()
    {
        Log::info('[BẮT ĐẦU] Đang xử lý các email nhắc nhở công việc');

        $now = Carbon::now();
        $yesterday = $now->copy()->addDay();

        // Lấy danh sách các công việc cần nhắc nhở
        $tasks = Task::where('type', 'task')
            ->where('is_done', 0)
            ->where('is_reminder', true)
            ->whereNotNull('reminder')
            ->get();

        Log::info("Tìm thấy {$tasks->count()} công việc cần kiểm tra nhắc nhở");

        $filteredTasks = $tasks->filter(function ($task) use ($now, $yesterday) {
            $nextEndTime = $this->getNextEndTime($task, $now);
            return $nextEndTime && $nextEndTime->between($yesterday, $now);
        });

        Log::info("Đã lọc ra {$filteredTasks->count()} công việc cần gửi nhắc nhở");

        $filteredTasks->each(function ($task) use ($now) {
            $this->processTaskReminder($task, $now);
        });

        Log::info('[KẾT THÚC] Đã xử lý xong việc gửi email nhắc nhở công việc');
    }

    protected function processTaskReminder(Task $task, Carbon $now): void
    {
        Log::info("Đang xử lý công việc ID {$task->id}");

        // Lấy thời gian kết thúc gốc của công việc (không phải lần tái lập kế tiếp)
        $originalEndTime = Carbon::parse($task->end_time);
        $gracePeriod = $originalEndTime->copy()->addMinutes(self::GRACE_PERIOD_MINUTES);

        // Kiểm tra xem công việc đã quá hạn + thời gian ân hạn hay chưa
        if ($now->greaterThanOrEqualTo($gracePeriod)) {
            Log::notice("Công việc ID {$task->id} đã quá hạn, gửi email nhắc nhở");

            $attendees = $task->getAttendees();

            foreach ($attendees as $userId) {
                $this->sendEmailReminder($task, $userId, $originalEndTime);
            }
        } else {
            Log::debug("Công việc ID {$task->id} vẫn trong thời gian ân hạn");
        }

        // Kiểm tra các lần tái lập công việc nếu công việc là lặp lại
        if ($task->is_repeat) {
            $this->checkMissedRecurrences($task, $now, $originalEndTime);
        }
    }

    protected function checkMissedRecurrences(Task $task, Carbon $now, Carbon $originalEndTime): void
    {
        $nextEndTime = $this->getNextEndTime($task, $now);

        if ($nextEndTime && $now->greaterThanOrEqualTo($nextEndTime)) {
            Log::notice("Công việc ID {$task->id} đã bỏ lỡ một lần tái lập, gửi nhắc nhở thêm");

            $attendees = $task->getAttendees();
            foreach ($attendees as $userId) {
                $this->sendEmailReminder($task, $userId, $nextEndTime);
            }
        }
    }

    protected function sendEmailReminder(Task $task, int $userId, Carbon $endTime): void
    {
        $user = User::find($userId);
        if (!$user) {
            Log::error("Không tìm thấy người dùng ID {$userId} cho công việc {$task->id}");
            return;
        }

        $cacheKey = self::CACHE_PREFIX . "{$task->id}:{$user->id}:{$endTime->timestamp}";

        if (Cache::has($cacheKey)) {
            Log::debug("Đã gửi email nhắc nhở cho công việc {$task->id} tới người dùng {$user->id}");
            return;
        }

        try {
            Mail::to($user->email)->queue(new TaskIsDoneReminderMail($user, $task, $endTime));
            Log::notice("Gửi thành công email nhắc nhở cho công việc {$task->id} tới {$user->email}");
            Cache::put($cacheKey, true, now()->addHours(self::CACHE_TTL_HOURS));
        } catch (\Exception $e) {
            Log::error("Lỗi khi gửi email cho công việc {$task->id} tới {$user->email}: " . $e->getMessage());
        }
    }

    protected function getNextEndTime(Task $task, Carbon $now): ?Carbon
    {
        if (!$task->is_repeat) {
            Log::debug("Công việc ID {$task->id} không lặp lại, sử dụng thời gian kết thúc cố định");
            return $task->end_time ? Carbon::parse($task->end_time) : null;
        }

        Log::debug("Công việc ID {$task->id} lặp lại, tính toán thời gian kết thúc lần tái lập tiếp theo");
        return $this->calculateNextRecurringEndTime($task, $now);
    }

    protected function calculateNextRecurringEndTime(Task $task, Carbon $now): ?Carbon
    {
        $endTime = Carbon::parse($task->end_time);
        $until = $task->until ? Carbon::parse($task->until) : null;
        $interval = $task->interval ?? 1;
        $maxCount = $task->count;

        Log::debug("Tính toán lần tái lập cho công việc ID {$task->id}, tần suất {$task->freq}, khoảng thời gian {$interval}");

        switch ($task->freq) {
            case 'daily':
                return $this->calculateDailyRecurrence($endTime, $now, $until, $interval, $maxCount);

            case 'weekly':
                return $this->calculateWeeklyRecurrence($task, $endTime, $now, $until, $interval, $maxCount);

            case 'monthly':
                return $this->calculateMonthlyRecurrence($endTime, $now, $until, $interval, $maxCount);

            case 'yearly':
                return $this->calculateYearlyRecurrence($endTime, $now, $until, $interval, $maxCount);

            default:
                Log::warning("Công việc ID {$task->id} có tần suất không hợp lệ: {$task->freq}");
                return null;
        }
    }

    protected function calculateDailyRecurrence(
        Carbon $endTime,
        Carbon $now,
        ?Carbon $until,
        int $interval,
        ?int $maxCount
    ): ?Carbon {
        $occurrenceCount = 0;

        while ($endTime->lessThan($now)) {
            $endTime->addDays($interval);
            $occurrenceCount++;

            if ($this->exceedsLimits($endTime, $until, $occurrenceCount, $maxCount)) {
                return null;
            }
        }

        return $endTime;
    }

    protected function calculateWeeklyRecurrence(
        Task $task,
        Carbon $endTime,
        Carbon $now,
        ?Carbon $until,
        int $interval,
        ?int $maxCount
    ): ?Carbon {
        $weekdays = $task->byweekday ?? [];
        $dayMapping = [
            'SU' => Carbon::SUNDAY,
            'MO' => Carbon::MONDAY,
            'TU' => Carbon::TUESDAY,
            'WE' => Carbon::WEDNESDAY,
            'TH' => Carbon::THURSDAY,
            'FR' => Carbon::FRIDAY,
            'SA' => Carbon::SATURDAY,
        ];

        $validDays = array_filter(array_map(fn($d) => $dayMapping[$d] ?? null, $weekdays));

        if (empty($validDays)) {
            while ($endTime->lessThan($now)) {
                $endTime->addWeeks($interval);

                if ($this->exceedsLimits($endTime, $until, 0, $maxCount)) {
                    return null;
                }
            }
            return $endTime;
        }

        while (true) {
            if (in_array($endTime->dayOfWeek, $validDays) && $endTime->greaterThanOrEqualTo($now)) {
                return $endTime;
            }

            $endTime->addDay();

            if ($until && $endTime->greaterThan($until)) {
                return null;
            }
        }
    }

    protected function calculateMonthlyRecurrence(
        Carbon $endTime,
        Carbon $now,
        ?Carbon $until,
        int $interval,
        ?int $maxCount
    ): ?Carbon {
        $occurrenceCount = 0;

        while ($endTime->lessThan($now)) {
            $endTime->addMonths($interval);
            $occurrenceCount++;

            if ($this->exceedsLimits($endTime, $until, $occurrenceCount, $maxCount)) {
                return null;
            }
        }

        return $endTime;
    }

    protected function calculateYearlyRecurrence(
        Carbon $endTime,
        Carbon $now,
        ?Carbon $until,
        int $interval,
        ?int $maxCount
    ): ?Carbon {
        $occurrenceCount = 0;

        while ($endTime->lessThan($now)) {
            $endTime->addYears($interval);
            $occurrenceCount++;

            if ($this->exceedsLimits($endTime, $until, $occurrenceCount, $maxCount)) {
                return null;
            }
        }

        return $endTime;
    }

    protected function exceedsLimits(Carbon $endTime, ?Carbon $until, int $occurrenceCount, ?int $maxCount): bool
    {
        return ($until && $endTime->greaterThan($until)) || ($maxCount !== null && $occurrenceCount > $maxCount);
    }
}
