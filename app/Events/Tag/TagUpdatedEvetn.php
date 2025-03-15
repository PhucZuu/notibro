<?php

namespace App\Events\Tag;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TagUpdatedEvetn implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $tag;
    public $action;
    public $recipients;

    /**
     * Create a new event instance.
     */
    public function __construct($tag, $action, $recipients)
    {
        $tag = collect($tag);

        $this->tag = $tag->toArray();
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
        Log::info("Pusher chạy đến broadcastOn");

        return collect($this->recipients)->map(function ($userId) {
            return new PrivateChannel('App.Models.User.' . $userId);
        })->toArray();
    }

    public function broadcastAs()
    {
        return 'tag.listTagUpdated';
    }
}
