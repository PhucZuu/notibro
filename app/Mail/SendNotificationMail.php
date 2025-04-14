<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $task;

    public $type;

    public function __construct($user, $task, $type)
    {
        $this->user = $user;
        $this->task = $task;
        $this->type = $type;
    }

    public function build()
    {
        if ($this->type == 'update') {
            return $this->subject("Notification: {$this->task->title} has been updated!")
            ->view('emails.task_notification')
            ->with([
                'user' => $this->user,
                'task' => $this->task,
                'type' => $this->type,
            ]);
        }else if ($this->type == 'delete') {
            return $this->subject("Notification'): {$this->task->title} has been deleted!")
            ->view('emails.task_notification')
            ->with([
                'user' => $this->user,
                'task' => $this->task,
                'type' => $this->type,
            ]);
        }
    }
}
