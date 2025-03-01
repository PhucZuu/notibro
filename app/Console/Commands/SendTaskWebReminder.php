<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TaskWebReminderService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;

class SendTaskWebReminder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'task:send-web-reminder';

    protected $taskWebReminderService;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send web reminders for tasks';

    public function __construct(TaskWebReminderService $taskWebReminderService)
    {
        parent::__construct();
        $this->taskWebReminderService = $taskWebReminderService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::info("Task web reminder bắt đầu chạy...");
        $this->taskWebReminderService->taskWebSchedule();
        Log::info("Task web reminder đã chạy xong...");
    }
}
