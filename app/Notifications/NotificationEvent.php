<?php

namespace App\Notifications;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class NotificationEvent extends Notification implements ShouldBroadcast
{
    use Queueable;

    protected $userID;

    protected $message;

    /**
     * Create a new notification instance.
     */
    public function __construct($userID, $message)
    {
        $this->userID   = $userID;
        $this->message  = $message;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['broadcast', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->line('The introduction to the notification.')
                    ->action('Notification Action', url('/'))
                    ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message'       => $this->message,
            'link'          => '',
            'user_id'       => $notifiable->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage 
    {
        Log::info('🔔 Đang gửi notification đến Pusher', ['user_id' => $notifiable->id]);

        return new BroadcastMessage ([
            'message'       => $this->message,
            'link'          => '',
            'user_id'       => $notifiable->id,
        ]);
    }

    public function broadcastOn()
    {
        Log::info("📡 Broadcast to Pusher", [
            'channel' => 'App.Models.User.' . $this->userID
        ]);

        return new PrivateChannel('App.Models.User.' . $this->userID);
    }

    public function broadcastAs()
    {
        return 'notification';
    }
}
