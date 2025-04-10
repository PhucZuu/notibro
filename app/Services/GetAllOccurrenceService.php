<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class GetAllOccurrenceService
{
    public function getAllOccurrences($task)
    {
        if (!$task->is_repeat) {
            Log::info("Task không lặp lại - Task đến hạn tại: {$task->start_time}");
            return [Carbon::parse($task->start_time)];
        }

        $startTime = Carbon::parse($task->start_time);
        $until = $task->until ? Carbon::parse($task->until) : $startTime->copy()->addYears(value: 2);
        Log::info("Task đã nhận {$startTime} và {$until} của {$task->id} {$task->title}");
        $occurrences = [];

        switch ($task->freq) {
            case 'daily':
                $occurrences = $this->getAllIntervals($task, $startTime, $until, 'days');
                break;

            case 'weekly':
                $occurrences = $this->getAllWeeklyOccurrences($task, $startTime, $until);
                break;

            case 'monthly':
                $occurrences = $this->getAllMonthlyOccurrences($task, $startTime, $until);
                break;

            case 'yearly':
                $occurrences = $this->getAllIntervals($task, $startTime, $until, 'years');
                break;

            default:
                Log::warning("Tần suất không hợp lệ: {$task->freq}");
                return [];
        }

        // Loại bỏ các thời điểm bị exclude nếu có
        if (!empty($task->exclude_time)) {
            $excludedDates = is_array($task->exclude_time)
                ? $task->exclude_time
                : json_decode($task->exclude_time, true);

            $occurrences = array_filter($occurrences, function ($occurrence) use ($excludedDates) {
                return !in_array($occurrence->toDateString(), $excludedDates);
            });
        }

        return array_values($occurrences); // reset keys
    }

    protected function getAllIntervals($task, $startTime, $untilTime, $unit)
    {
        $interval = $task->interval ?? 1;
        $occurrences = [];
        $maxCount = $task->count;

        while ($startTime->lessThanOrEqualTo($untilTime)) {
            $occurrences[] = $startTime->copy();

            if ($maxCount && count($occurrences) >= $maxCount) {
                break;
            }

            $startTime->add($unit, $interval);
        }

        return $occurrences;
    }

    protected function getAllWeeklyOccurrences($task, $startTime, $untilTime)
    {
        $interval = $task->interval ?? 1;
        $weekdays = $task->byweekday ?? []; // ví dụ: ['MO', 'WE', 'FR']
        $dayMapping = [
            'SU' => 0, 'MO' => 1, 'TU' => 2,
            'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6,
        ];
        $validDays = array_filter(array_map(fn($d) => $dayMapping[$d] ?? null, $weekdays));
        $occurrences = [];
        $maxCount = $task->count;

        $currentWeekStart = $startTime->copy()->startOfWeek();

        while ($currentWeekStart->lessThanOrEqualTo($untilTime)) {
            foreach ($validDays as $dayOfWeek) {
                $occurrence = $currentWeekStart->copy()->addDays($dayOfWeek);
                if ($occurrence->between($startTime, $untilTime)) {
                    $occurrences[] = $occurrence->copy();

                    if ($maxCount && count($occurrences) >= $maxCount) {
                        return $occurrences;
                    }
                }
            }

            $currentWeekStart->addWeeks($interval);
        }

        return $occurrences;
    }

    protected function getAllMonthlyOccurrences($task, $startTime, $untilTime)
    {
        $interval = $task->interval ?? 1;
        $monthDays = $task->bymonthday ?? [$startTime->day];
        $occurrences = [];
        $maxCount = $task->count;

        $current = $startTime->copy();

        while ($current->lessThanOrEqualTo($untilTime)) {
            foreach ($monthDays as $day) {
                try {
                    $occurrence = Carbon::create($current->year, $current->month, $day, $current->hour, $current->minute, $current->second);
                    if ($occurrence->between($startTime, $untilTime)) {
                        $occurrences[] = $occurrence;

                        if ($maxCount && count($occurrences) >= $maxCount) {
                            return $occurrences;
                        }
                    }
                } catch (\Exception $e) {
                    continue; // bỏ qua ngày không hợp lệ như 31/2
                }
            }

            $current->addMonths($interval);
        }

        return $occurrences;
    }
}
