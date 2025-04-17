<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ChatWithGuest extends Mailable
{
    use Queueable, SerializesModels;

    public $title;
    
    public $content;

    public $userSend;

    public $task;

    public $isOwner;

    /**
     * Create a new message instance.
     */
    public function __construct($title, $content, $userSend, $task, $isOwner)
    {
        $this->title = $title;
        $this->content = $content;
        $this->userSend = $userSend;
        $this->task = $task;
        $this->isOwner = $isOwner;
    }

    /**
     * Get the message envelope.
     */
    public function build()
    {
        $fullName = "{$this->userSend->first_name} {$this->userSend->last_name}";

        return $this->from($this->userSend->email, $fullName)
                    ->replyTo($this->userSend->email, $fullName)
                    ->subject('Tin nhắn từ người trong sự kiện bạn tham gia')
                    ->view('emails.chat-with-guest')
                    ->with([
                        'title' => $this->title,
                        'content' => $this->content,
                        'user' => $this->userSend,
                        'task' => $this->task,
                        'isOwner' => $this->isOwner,
                    ]);
    }
}
