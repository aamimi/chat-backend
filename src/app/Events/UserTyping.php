<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserTyping implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int    $conversationId,
        public int    $userId,
        public string $userName,
        public bool   $isTyping = true
    )
    {
    }

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel('conversation.' . $this->conversationId);
    }
}
