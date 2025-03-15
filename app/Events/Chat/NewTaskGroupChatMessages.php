<?php

namespace App\Events;

use App\Models\TaskGroupMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewTaskGroupChatMessages implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    public function __construct(TaskGroupMessage $message)
    {
        // Load thêm thông tin user và tin nhắn được reply (nếu có)
        $this->message = $message->load(['user:id,name,avatar', 'replyMessage.user:id,name,avatar']);
    }

    public function broadcastOn()
    {
        return new PrivateChannel('task-group.' . $this->message->group_id);
    }

    public function broadcastWith()
    {
        return [
            'id' => $this->message->id,
            'group_id' => $this->message->group_id,
            'user_id' => $this->message->user_id,
            'message' => $this->message->message,
            'file' => $this->message->file ? asset('storage/' . $this->message->file) : null,
            'created_at' => $this->message->created_at->toDateTimeString(),
            'user' => [
                'name' => $this->message->user->name,
                'avatar' => $this->message->user->avatar ? asset('storage/' . $this->message->user->avatar) : null,
            ],
            'reply_message' => $this->message->replyMessage ? [
                'id' => $this->message->replyMessage->id,
                'message' => $this->message->replyMessage->message,
                'user' => [
                    'id' => $this->message->replyMessage->user->id,
                    'name' => $this->message->replyMessage->user->name,
                    'avatar' => $this->message->replyMessage->user->avatar 
                        ? asset('storage/' . $this->message->replyMessage->user->avatar) 
                        : null,
                ],
            ] : null,
        ];
    }
}
