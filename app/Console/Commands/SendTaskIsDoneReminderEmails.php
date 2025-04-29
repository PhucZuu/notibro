<?php

namespace App\Console\Commands;

use App\Services\ForgotDoneTaskEmailReminder;
use Illuminate\Console\Command;

use Illuminate\Support\Facades\Log;

class SendTaskIsDoneReminderEmails extends Command
{
    protected $signature = 'task:send-is-done-reminder-emails';
    protected $description = 'Gửi email nhắc nhở sau khi sự kiện kết thúc nhưng chưa được đánh dấu là hoàn thành';
    protected $ForgotDoneTaskEmailReminder;

    public function __construct(ForgotDoneTaskEmailReminder $ForgotDoneTaskEmailReminder)
    {
        parent::__construct();
        $this->ForgotDoneTaskEmailReminder = $ForgotDoneTaskEmailReminder;
    }

    public function handle()
    {
        try {
            $this->ForgotDoneTaskEmailReminder->handle();
            $this->info('Task is done reminder emails sent successfully.');
        } catch (\Exception $e) {
            Log::error('Error sending task is done reminder emails: ' . $e->getMessage());
            $this->error('Error sending task is done reminder emails. Check logs for details.');
        }
    }
}
