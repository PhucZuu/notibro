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

                    $nextOccurrence = Carbon::parse($nextOccurrence)->subMinutes($reminder['set_time']);

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

                                $startTime = Carbon::parse($task->start_time)->format('H:i');
                                $combinedDateTime = $nextOccurrence->toDateString() . ' ' . $startTime;
                                $notifiTime = Carbon::createFromFormat('Y-m-d H:i', $combinedDateTime, 'UTC')
                                                    ->setTimezone($task->timezone_code);
                                //Send notification
                                $user->notify(new TaskNotification($task, $user->id, $notifiTime));
                                Log::info("Task {$task->id} đã thông báo đến user {$user->id}, bỏ qua... ->Cache key: {$cacheKey}<-");

                                //Set cache to prevent duplicate notification
                                Cache::put($cacheKey, true, now()->addHours(24));
                            } else {
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
            Log::info("Task không lặp lại thời điểm task đến hạn {$task->start_time}");
            return Carbon::parse($task->start_time);
        }

        $startTime = Carbon::parse($task->start_time);
        $until = $task->until ? Carbon::parse($task->until) : Carbon::parse($task->start_time)->addYears(3);

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
        $untilTime = $task->until ? Carbon::parse($task->until) : null;

        $maxCount = $task->count;

        $occurrenceCount = 0;

        while ($startTime->lessThan($now) && $startTime->lessThan($untilTime)) {
            $startTime = $startTime->add($unit, $interval);

            if ($maxCount != null) {
                $occurrenceCount++;

                if ($occurrenceCount > $maxCount) {
                    return null;
                }
            }
        }

        Log::info("Task đã trả vể giá trị chạy tiếp theo {$startTime}");

        return $startTime;
    }

    protected function getNextMonthlyOccurence($task, $now)
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

    protected function getNextWeeklyOccurence($task, $now)
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
            $validDays = array_values(array_filter(array_map(function($d) use ($dayMapping) {  
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

                    if($nextOccurrence->greaterThan($now)) {
                        return $nextOccurrence; // Trả về ngày tiếp theo nếu nó lớn hơn thời điểm hiện tại  
                    }
                }

                // Tăng thêm một ngày  
                $nextOccurrence->addDay();
            }
        }
    }
}
