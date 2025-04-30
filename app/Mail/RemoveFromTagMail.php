<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RemoveFromTagMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $tag;
    public $ownerName;
    public $keepInTasks;
    public $senderEmail;
    public $senderName;

    /**
     * Create a new message instance.
     */
    public function __construct($senderEmail, $senderName, $tag, $keepInTasks = false)
    {
        $this->senderEmail = $senderEmail;
        $this->senderName = $senderName;
        $this->tag = $tag;
        $this->keepInTasks = $keepInTasks;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->from($this->senderEmail, $this->senderName)
            ->subject('Thông báo: Bạn đã bị xóa khỏi Tag')
            ->view('emails.remove-from-tag')
            ->with([
                'tag' => $this->tag,
                'keepInTasks' => $this->keepInTasks,
            ]);
    }
}