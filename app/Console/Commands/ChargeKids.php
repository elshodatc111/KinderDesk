<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Finance\ChargeService;
use Illuminate\Console\Command;

class ChargeKids extends Command
{
    protected $signature = 'kids:charge';

    protected $description = "Barcha bog'chalarda bolalarning oylik to'lovini hisoblaydi (takrorlanmaydi)";

    public function handle(ChargeService $charges): int
    {
        $total = 0;
        Tenant::where('status', 'active')->with('edition')->each(function (Tenant $t) use ($charges, &$total) {
            $total += $charges->runForTenant($t)['charged'];
        });
        $this->info("Hisoblandi: $total ta bola");

        return self::SUCCESS;
    }
}
