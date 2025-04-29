<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InviteToTagMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $tag;
    public $senderEmail;
    public $senderName;

    /**
     * Create a new message instance.
     */
    public function __construct($senderEmail, $senderName, $tag)
    {
        $this->senderEmail = $senderEmail;
        $this->senderName = $senderName;
        $this->tag = $tag;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->from($this->senderEmail, $this->senderName)
            ->subject('Thư mời tham gia Tag')
            ->view('emails.invite-to-tag') // Không cần build $link ở đây
            ->with([
                'tag'        => $this->tag,
                'ownerEmail' => $this->senderEmail,
                'ownerName'  => $this->senderName,
            ]);
    }
}