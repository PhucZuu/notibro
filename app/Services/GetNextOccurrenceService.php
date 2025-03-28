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
        $until = $task->until ? Carbon::parse($task->end_time) : null;
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

            if ($until && $nextOccurrence->greaterThan($until)) {
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
