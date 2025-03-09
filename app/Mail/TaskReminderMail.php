<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TaskReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $user;
    public $task;
    public $occurrenceTime;

    public function __construct($user, $task, $occurrenceTime = null)
    {
        $this->user = $user;
        $this->task = $task;
        $this->occurrenceTime = $occurrenceTime;
    }

    public function build()
    {
        return $this->subject("Reminder: {$this->task->title} is coming up!")
            ->view('emails.task_reminder')
            ->with([
                'user' => $this->user,
                'task' => $this->task,
                'occurrenceTime' => $this->occurrenceTime
            ]);
    }
}
