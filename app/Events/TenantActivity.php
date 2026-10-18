<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** Bog'cha ichidagi jonli o'zgarishlar: davomad, to'lov, yangi ariza, obuna holati... */
class TenantActivity implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $tenantId, public string $kind, public array $data = [])
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('tenant.'.$this->tenantId)];
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
