<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TaskNotification extends Notification implements ShouldBroadcast
{
    use Queueable, Dispatchable, InteractsWithSockets, SerializesModels;
    protected $task;
    protected $userID;

    protected $notifiTime;

    /**
     * Create a new notification instance.
     */
    public function __construct($task, $userID, $notifiTime )
    {
        $this->task = $task;
        $this->userID = $userID;
        $this->notifiTime = $notifiTime;
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
        if ($this->task->type == 'task') {
            $type = 'Việc cần làm';
        } else if ($this->task->type == 'event') {
            $type = 'Sự kiện';
        }

        return new BroadcastMessage ([
            'message'       => "{$type} {$this->task->title} sẽ diễn ra vào {$this->notifiTime}!",
            'task_id'       => $this->task->id,
            'start_time'    => $this->task->start_time,
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
        return 'task.reminder';
    }
}
