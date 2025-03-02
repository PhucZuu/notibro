<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\TaskReminderMail;

class TaskReminderService
{
    public function sendReminders()
    {
        $now = Carbon::now();
        Log::info("Starting to send reminders at {$now}");

        try {
            // Lấy tất cả task có is_reminder = true và có dữ liệu reminder
            $tasks = Task::where('is_reminder', 1)
                ->whereNotNull('reminder')
                ->get();

            Log::info("Found {$tasks->count()} tasks with reminders.");

            foreach ($tasks as $task) {
                $this->processTaskReminder($task, $now);
            }

            Log::info("Finished sending reminders at " . Carbon::now());
        } catch (\Exception $e) {
            Log::error("Error in sendReminders: " . $e->getMessage());
        }
    }

    private function processTaskReminder($task, $now)
    {
        try {
            Log::info("Processing Task ID {$task->id} - Repeat: {$task->repeat}");

            // Giải mã JSON reminder
            $reminders = is_string($task->reminder) ? json_decode($task->reminder, true) : $task->reminder;

            if (!is_array($reminders)) {
                Log::warning("Task ID {$task->id} has invalid reminder format: " . json_encode($task->reminder));
                return;
            }

            foreach ($reminders as $reminder) {
                Log::info("Task ID {$task->id} - Checking reminder: " . json_encode($reminder));

                if ($reminder['type'] === 'email') {
                    $setTime = $reminder['set_time'];
                    $user = User::find($task->user_id);

                    if (!$user || !$user->email) {
                        Log::warning("User ID {$task->user_id} not found or has no email.");
                        continue;
                    }

                    $sendTime = Carbon::parse($task->start_time)->subHours($setTime);
                    Log::info("Task ID {$task->id} - Send time: {$sendTime} (Now: {$now})");

                    if ($now->greaterThanOrEqualTo($sendTime) && $now->lessThan($task->start_time)) {
                        Log::info("Task ID {$task->id} - Sending email to {$user->email}");
                        $this->sendEmailReminder($user, $task);
                    } else {
                        Log::info("Task ID {$task->id} - Not yet time to send email.");
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Error in processTaskReminder for task ID {$task->id}: " . $e->getMessage());
        }
    }

    private function processRepeatingTask($task, $user, $setTime, $now)
    {
        try {
            $startTime = Carbon::parse($task->start_time);
            $until = $task->until ? Carbon::parse($task->until) : null;
            $repeatInterval = $task->interval ?? 1; // Mặc định khoảng cách giữa các lần lặp = 1

            // Nếu không có until, mặc định kiểm tra trong 1 năm
            $endCheckTime = $until ?? $now->copy()->addYear();

            while ($startTime->lessThanOrEqualTo($endCheckTime)) {
                $sendTime = $startTime->copy()->subHours($setTime);

                if ($now->greaterThanOrEqualTo($sendTime) && $now->lessThan($startTime)) {
                    $this->sendEmailReminder($user, $task, $startTime);
                }

                // Xác định thời điểm lặp tiếp theo dựa vào repeat
                switch ($task->repeat) {
                    case 'daily':
                        $startTime->addDays($repeatInterval);
                        break;
                    case 'weekly':
                        $startTime->addWeeks($repeatInterval);
                        break;
                    case 'monthly':
                        $startTime->addMonths($repeatInterval);
                        break;
                    case 'yearly':
                        $startTime->addYears($repeatInterval);
                        break;
                }
            }
        } catch (\Exception $e) {
            Log::error("Error in processRepeatingTask for task ID {$task->id}: " . $e->getMessage());
        }
    }

    private function sendEmailReminder($user, $task, $occurrenceTime = null)
    {
        try {
            Mail::to($user->email)->send(new TaskReminderMail($user, $task, $occurrenceTime));
            Log::info("Sent reminder email to {$user->email} for task ID {$task->id}");
        } catch (\Exception $e) {
            Log::error("Error sending email reminder for task ID {$task->id} to {$user->email}: " . $e->getMessage());
        }
    }
}
