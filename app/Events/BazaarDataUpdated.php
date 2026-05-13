<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BazaarDataUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $items;

    public function __construct(array $items)
    {
        $this->items = $items;
    }


    public function broadcastOn(): Channel
    {
        return new Channel('bazaar');
    }

    public function broadcastAs(): string
    {
        return 'data.updated';
    }
}
