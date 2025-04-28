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
        $link = config('app.frontend_url') . '/calendar/tag/' . $this->tag->uuid .'/invite';

        return $this->from($this->senderEmail, $this->senderName)
            ->subject('Lời mời tham gia Tag')
            ->view('emails.invite-to-tag')
            ->with([
                'tag'        => $this->tag,
                'ownerEmail' => $this->senderEmail,
                'ownerName'  => $this->senderName,
                'link'       => $link, // <- thêm dòng này
            ]);
    }

}
