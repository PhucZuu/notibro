<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TaskUpdatedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $task;
    public $action;
    public $recipients;

    /**
     * Create a new event instance.
     */
    public function __construct($task, $action, )
    {
        $this->task = $task;
        $this->action = $action;
        $this->recipients = $task->getAttendees();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return collect($this->recipients)->map(function ($userId) {
            return new PrivateChannel('App.Models.User.' . $userId);
        })->toArray();
    }

    public function broadcastAs()
    {
        return 'task.listUpdated';
    }
}
