<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BazaarPricesUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    
    public array $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('bazaar-intelligence');
    }

    public function broadcastAs(): string
    {
        return 'prices.updated';
    }
}
