<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class GetAllOccurrenceService
{
    public function getAllOccurrences($task)
    {
        if (!$task->is_repeat) {
            Log::info("Task không lặp lại thời điểm task đến hạn {$task->start_time}");
            return [Carbon::parse($task->start_time)];
        }

        $startTime = Carbon::parse($task->start_time);
        $until = $task->until ? Carbon::parse($task->until) : null;

        Log::info("Task đã nhận {$startTime} và {$until} của {$task->id} {$task->title}");

        $occurrences = [];

        switch ($task->freq) {
            case 'daily':
                $occurrences = $this->getAllIntervals($task, $startTime, $until, 'days');
                Log::info("Task đã nhận đưuọc tất cả các lần lặp lại - case Daily");
                break;

            case 'weekly':
                $occurrences = $this->getAllWeeklyOccurrences($task, $startTime, $until);
                Log::info("Task đã nhận đưuọc tất cả các lần lặp lại - case Weekly");
                break;

            case 'monthly':
                $occurrences = $this->getAllMonthlyOccurrences($task, $startTime, $until);
                Log::info("Task đã nhận đưuọc tất cả các lần lặp lại - case Monthly");
                break;

            case 'yearly':
                $occurrences = $this->getAllIntervals($task, $startTime, $until, 'years');
                Log::info("Task đã nhận đưuọc tất cả các lần lặp lại - case Yearly");
                break;

            default:
                return [];
        }

        // Loại bỏ các thời điểm bị loại trừ nếu có  
        if ($task->exclude_time) {
            $occurrences = array_filter($occurrences, function ($occurrence) use ($task) {
                return !in_array($occurrence->toDateString(), $task->exclude_time);
            });
        }

        return $occurrences;
    }

    protected function getAllIntervals($task, $startTime, $untilTime, $unit)
    {
        $interval = $task->interval ?? 1;
        $occurrences = [];
        $maxCount = $task->count;

        while ($untilTime === null || $startTime->lessThanOrEqualTo($untilTime)) {
            $occurrences[] = $startTime->copy();

            if ($maxCount !== null && count($occurrences) >= $maxCount) {
                break;
            }

            $startTime->add($unit, $interval);
        }

        return $occurrences;
    }

    protected function getAllMonthlyOccurrences($task, $startTime, $untilTime)
    {
        $interval = $task->interval ?? 1;
        $monthDays = $task->bymonthday ?? [$startTime->day];
        $occurrences = [];

        $nextOccurrence = $startTime->copy();

        while ($untilTime === null || $nextOccurrence->lessThanOrEqualTo($untilTime)) {
            if (in_array($nextOccurrence->day, $monthDays)) {
                $occurrences[] = $nextOccurrence->copy();
            }
            $nextOccurrence->addMonths($interval);
        }

        return $occurrences;
    }

    protected function getAllWeeklyOccurrences($task, $startTime, $untilTime)
    {
        $weekdays = $task->byweekday ?? [];
        $occurrences = [];
        $dayMapping = [
            'SU' => 0,
            'MO' => 1,
            'TU' => 2,
            'WE' => 3,
            'TH' => 4,
            'FR' => 5,
            'SA' => 6
        ];

        $validDays = array_map(fn($d) => $dayMapping[$d] ?? null, $weekdays);
        $validDays = array_filter($validDays);

        $nextOccurrence = $startTime->copy();

        while ($untilTime === null || $nextOccurrence->lessThanOrEqualTo($untilTime)) {  
            if (in_array($nextOccurrence->dayOfWeek, $validDays)) {
                $occurrences[] = $nextOccurrence->copy();
            }
  
            $nextOccurrence->addWeek();
        }

        return $occurrences;
    }
}
