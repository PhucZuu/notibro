<?php

namespace App\Events\Task;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TaskUpdatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public $task;
    public $action;
    public $recipients;

    /**
     * Create a new event instance.
     */
    public function __construct($task, $action, $recipients)
    {
        $task = collect($task);

        $this->task = $task->map(fn($task) => $task->formatedReturnData())->toArray();
        $this->action = $action;
        $this->recipients = $recipients;
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
