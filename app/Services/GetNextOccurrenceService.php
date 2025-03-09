<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class GetNextOccurrenceService
{
    //Calculate function for reminder time
    public function getNextOccurrence($task, $now)
    {
        if (!$task->is_repeat) {
            Log::info("Task không lặp lại thời điểm task đến hạn {$task->start_time}");
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
        $endTime = $task->end_time ? Carbon::parse($task->end_time) : null;
        $interval = $task->interval ?? 1;
        $count = $task->count ?? null;
        $monthDays = $task->bymonthday ?? [$startTime->day];

        $nextOccurrence = $startTime->copy();
        $occurrenceCount = 0;

        while (true) {
            if ($nextOccurrence->greaterThan($now) && in_array($nextOccurrence->day, $monthDays)) {
                break;
            }

            $nextOccurrence = $nextOccurrence->addMonths($interval);

            while (!in_array($nextOccurrence->day, $monthDays)) {
                $nextOccurrence = $nextOccurrence->addDays();

                if ($nextOccurrence->addDay()) {
                    $nextOccurrence = $nextOccurrence->addMonths($interval);
                }
            }

            if ($endTime && $nextOccurrence->greaterThan($endTime)) {
                return null;
            }

            $occurrenceCount++;

            if ($count && $occurrenceCount > $count) {
                return null;
            }
        }

        return $nextOccurrence;
    }

    protected function getNextWeeklyOccurence($task, $now)
    {
        $weekdays = $task->byweekday ?? [];

        $startTime = Carbon::parse($task->start_time);

        $maxCount = $task->count;
 
        $occurrenceCount = 0;

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
            $this->getNextInterval($task, $now, 'weeks');
        } else {
            // Chuyển đổi các ngày từ ký hiệu sang số thứ tự  
            $validDays = array_map(fn($d) => $dayMapping[$d] ?? null, $weekdays);
            $validDays = array_filter($validDays); // Loại bỏ null nếu có  

            // Khởi tạo ngày tiếp theo từ thời điểm hiện tại  
            $nextOccurrence = Carbon::now();

            $maxCount = $task->count;

            $occurrenceCount = 0;

            // Tìm ngày tiếp theo cho tới khi nó nằm trong danh sách ngày hợp lệ hoặc vượt quá maxCount nếu có  
            while (true) {
                // Tìm chỉ số của ngày trong tuần  
                $currentDayOfWeek = $nextOccurrence->dayOfWeek;

                // Nếu ngày hiện tại trong danh sách hợp lệ, thì tăng số lần lặp lại  
                if (in_array($currentDayOfWeek, $validDays)) {
                    $occurrenceCount++;

                    // Nếu đã đạt giới hạn và maxCount không phải null thì thoát ra  
                    if ($maxCount !== null && $occurrenceCount >= $maxCount) {
                        return null; // Hoặc có thể trả về thông báo hoặc giá trị nào đó nếu cần   
                    }
                }

                // Tăng thêm một ngày  
                $nextOccurrence->addDay();
            }

            
        }
    }
}
