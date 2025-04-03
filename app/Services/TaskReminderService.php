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

        // Lấy danh sách task có nhắc nhở
        $tasks = Task::where('is_reminder', true)
            ->whereNotNull('reminder')
            ->get() // Lấy tất cả trước, rồi lọc lại
            ->filter(function ($task) use ($now, $next24Hours) {
                if (!$task->is_repeat) {
                    // Nếu task không lặp lại, kiểm tra start_time trực tiếp
                    return Carbon::parse($task->start_time)->between($now, $next24Hours);
                }

                // Nếu task có lặp lại, tìm lần xuất hiện tiếp theo
                $nextOccurrence = $this->getNextOccurrence($task, $now);
                return $nextOccurrence && $nextOccurrence->between($now, $next24Hours);
            });

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
        //$nextOccurrence = Carbon::parse($nextOccurrence)->setTimezone(config('app.timezone'));
        if (!$nextOccurrence) {
            Log::info("Không có lần xuất hiện tiếp theo cho task ID: {$task->id}");
            return;
        }

        $reminderTime = Carbon::parse($nextOccurrence)->subMinutes($reminder['set_time']);
        if (!$now->isSameMinute($reminderTime)) {
            Log::info("Không phải thời gian nhắc nhở cho task ID: {$task->id}, Remind time:{$reminderTime},Now:{$now}");
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
            Mail::to($user->email)->queue(new TaskReminderMail($user, $task, $reminderTime));
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
        $weekdays = $task->byweekday ?? [];

        $startTime = Carbon::parse($task->start_time);

        $maxCount = $task->count;

        $until = isset($task->until) ? Carbon::parse($task->until) : null;

        $dayMapping = [
            'SU' => 0,
            'MO' => 1,
            'TU' => 2,
            'WE' => 3,
            'TH' => 4,
            'FR' => 5,
            'SA' => 6
        ];

        if (empty($weekdays)) {
            return $this->getNextInterval($task, $now, 'weeks');
        } else {
            // Chuyển đổi các ngày từ ký hiệu sang số thứ tự  
            $validDays = array_map(fn($d) => $dayMapping[$d] ?? null, $weekdays);
            Log::info("Task đã nhận đưuọc danh sách ngày hợp lệ: " . implode(',', $validDays));
            $validDays = array_values(array_filter(array_map(function ($d) use ($dayMapping) {
                return $dayMapping[$d] ?? null;
            }, $weekdays))); // Loại bỏ null nếu có  
            Log::info("Task đã nhận đưuọc danh sách ngày hợp lệ: " . implode(',', $validDays));

            // Khởi tạo ngày tiếp theo từ thời điểm hiện tại  
            $nextOccurrence = $startTime->copy();

            $maxCount = $task->count;

            $occurrenceCount = 0;

            // Tìm ngày tiếp theo cho tới khi nó nằm trong danh sách ngày hợp lệ hoặc vượt quá maxCount nếu có  
            while (true) {
                if ($until && $nextOccurrence->greaterThan($until)) {
                    return null; // Không có ngày hợp lệ trước khi đến until  
                }

                // Tìm chỉ số của ngày trong tuần  
                $currentDayOfWeek = $nextOccurrence->dayOfWeek;

                // Nếu ngày hiện tại trong danh sách hợp lệ, thì tăng số lần lặp lại  
                if (in_array($currentDayOfWeek, $validDays)) {
                    $occurrenceCount++;

                    // Nếu đã đạt giới hạn và maxCount không phải null thì thoát ra  
                    if ($maxCount !== null && $occurrenceCount >= $maxCount) {
                        return null; // Hoặc có thể trả về thông báo hoặc giá trị nào đó nếu cần   
                    }

                    if ($nextOccurrence->greaterThan($now)) {
                        return $nextOccurrence; // Trả về ngày tiếp theo nếu nó lớn hơn thời điểm hiện tại  
                    }
                }

                // Tăng thêm một ngày  
                $nextOccurrence->addDay();
            }
        }
    }

    protected function getNextMonthlyOccurrence($task, $now)
    {
        $startTime = Carbon::parse($task->start_time);
        $until = $task->until ? Carbon::parse($task->until) : null;
        $interval = $task->interval ?? 1;
        $count = $task->count ?? null;
        $monthDays = $task->bymonthday ?? [$startTime->day];

        // Sắp xếp để đảm bảo ngày trong tháng được kiểm tra theo thứ tự  
        sort($monthDays);

        // Đặt nextOccurrence là thời gian bắt đầu  
        $nextOccurrence = $startTime->copy();
        $occurrenceCount = 0;

        while ($occurrenceCount === 0 || $nextOccurrence->lessThanOrEqualTo($now)) {
            // Thiết lập cờ để kiểm tra nếu ngày hợp lệ đã được tìm thấy  
            $validDayFound = false;

            // Kiểm tra từng ngày trong $monthDays  
            foreach ($monthDays as $day) {
                $testDate = $nextOccurrence->copy()->day($day);
                if ($testDate->greaterThan($now) && $testDate->greaterThanOrEqualTo($startTime)) {
                    $nextOccurrence = $testDate;
                    $validDayFound = true; // Ngày hợp lệ đã tìm thấy  
                    break; // Thoát khỏi vòng lặp nếu tìm thấy ngày hợp lệ  
                }
            }

            // Nếu không tìm thấy ngày hợp lệ, chuyển sang tháng tiếp theo  
            if (!$validDayFound) {
                $nextOccurrence->addMonths($interval);
                continue; // Tiếp tục vòng lặp  
            }

            // Kiểm tra điều kiện 'until'  
            if ($until && $nextOccurrence->greaterThan($until)) {
                return null;
            }

            // Kiểm tra số lần lặp  
            $occurrenceCount++;
            if ($count && $occurrenceCount > $count) {
                return null;
            }

            // Log::info(" {$nextOccurrence} - case Monthly");
        }

        return $nextOccurrence;
    }
}
