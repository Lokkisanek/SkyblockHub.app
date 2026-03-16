<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MarketManipulationDetected implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @var array<int, array<string, mixed>> */
    public array $alerts;

    /**
     * @param array<int, array<string, mixed>> $alerts
     */
    public function __construct(array $alerts)
    {
        $this->alerts = $alerts;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('bazaar-intelligence');
    }

    public function broadcastAs(): string
    {
        return 'manipulation.detected';
    }
}
