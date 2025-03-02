<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TaskReminderService;
use Illuminate\Support\Facades\Log;

class SendTaskReminderEmails extends Command
{
    protected $signature = 'task:send-reminder-emails';
    protected $description = 'Gửi email nhắc nhở trước khi task diễn ra';
    protected $taskReminderService;

    public function __construct(TaskReminderService $taskReminderService)
    {
        parent::__construct();
        $this->taskReminderService = $taskReminderService;
    }

    public function handle()
    {
        try {
            $this->taskReminderService->sendReminders();
            $this->info('Task reminder emails sent successfully.');
        } catch (\Exception $e) {
            Log::error('Error sending task reminder emails: ' . $e->getMessage());
            $this->error('Error sending task reminder emails. Check logs for details.');
        }
    }
}
