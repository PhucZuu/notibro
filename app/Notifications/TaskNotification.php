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

class TaskNotification extends Notification implements ShouldBroadcast
{
    use Queueable;
    protected $task;

    /**
     * Create a new notification instance.
     */
    public function __construct($task)
    {
        $this->task = $task;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        Log::info("📢 Notification via called for user", ['user_id' => $notifiable->id]);
        return ['broadcast'];
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
            //
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage 
    {
        Log::info('🔔 Đang gửi thông báo đến Pusher', ['user_id' => $notifiable->id]);

        return new BroadcastMessage ([
            'message'       => "Event' {$this->task->title}.' is coming up at {$this->task->start_time}",
            'task_id'       => $this->task->id,
            'start_time'    => $this->task->start_time,
            'user_id'       => $notifiable->id,
        ]);
    }

    // public function broadcastOn()
    // {
    //     Log::info("📡 Broadcast to Pusher", ['channel' => 'App.Models.User.' . $this->notifiable->id]);

    //     return new PrivateChannel('App.Models.User.' . $this->notifiable->id);
    // }

    // public function broadcastAs()
    // {
    //     return 'task.reminder';
    // }
}
