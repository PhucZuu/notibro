<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InviteGuestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $data;
    public $senderEmail;
    public $senderName;

    /**
     * Create a new message instance.
     */
    public function __construct($senderEmail, $senderName, $data)
    {
        $this->senderEmail = $senderEmail;
        $this->senderName = $senderName;
        $this->data = $data;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->from($this->senderEmail, $this->senderName)
            ->subject('Thư mời tham gia sự kiện')
            ->view('emails.invite-guest') // Đường dẫn view email
            ->with([
                'data' => $this->data,
                'ownerEmail'=> $this->senderEmail,
                'ownerName'=> $this->senderName
            ]);
    }
}
