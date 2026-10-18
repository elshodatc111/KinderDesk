<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** Super adminlar uchun jonli xabarlar: yangi chek, yangi bog'cha... */
class PlatformActivity implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public string $kind, public array $data = [])
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('platform')];
    }

    public function broadcastAs(): string
    {
        return 'activity';
    }

    public function broadcastWith(): array
    {
        return ['kind' => $this->kind, 'data' => $this->data, 'at' => now()->toIso8601String()];
    }
}
