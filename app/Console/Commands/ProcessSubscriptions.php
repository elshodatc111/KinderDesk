<?php

namespace App\Console\Commands;

use App\Services\Billing\SubscriptionLifecycle;
use Illuminate\Console\Command;

class ProcessSubscriptions extends Command
{
    protected $signature = 'subscriptions:process';

    protected $description = "Obunalarni tekshiradi: avto-yangilash, 3 kunlik eslatma, tugash xabari, 90 kunlik o'chirish xabari";

    public function handle(SubscriptionLifecycle $lifecycle): int
    {
        $stats = $lifecycle->run();
        $this->table(array_keys($stats), [array_values($stats)]);

        return self::SUCCESS;
    }
}
