<?php

namespace App\Console\Commands;

use App\Models\Kid;
use App\Models\Tenant;
use App\Services\Sms\SmsService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

class SendBirthdays extends Command
{
    protected $signature = 'kids:birthdays';

    protected $description = "Bugun tug'ilgan bolalarning ota-onasiga tabrik SMS (sozlamada yoqilgan bo'lsa)";

    public function handle(SmsService $sms, TenantContext $ctx): int
    {
        $n = 0;
        Tenant::where('status', 'active')->each(function (Tenant $t) use ($sms, $ctx, &$n) {
            if (! $t->hasFeature('sms') || $t->isReadOnly()) {
                return;
            }
            $ctx->run($t, function () use ($t, $sms, &$n) {
                Kid::active()->whereMonth('birth_date', today()->month)->whereDay('birth_date', today()->day)->each(function (Kid $k) use ($t, $sms, &$n) {
                    $log = $sms->sendTenant($t, $k->parent_phone, 'birthday', ['bola' => $k->full_name]);
                    if ($log && $log->status !== 'blocked') {
                        $n++;
                    }
                });
            });
        });
        $this->info("Tabrik yuborildi: $n");

        return self::SUCCESS;
    }
}
