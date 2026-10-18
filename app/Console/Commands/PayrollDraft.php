<?php

namespace App\Console\Commands;

use App\Http\Controllers\App\PayrollController;
use App\Models\Tenant;
use App\Services\Calendar\WorkCalendar;
use App\Services\NotificationService;
use App\Services\Payroll\PayrollCalculator;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PayrollDraft extends Command
{
    protected $signature = 'payroll:draft {--force : Birinchi ishchi kun tekshiruvisiz ishga tushirish} {--month= : YYYY-MM (standart: o\'tgan oy)}';

    protected $description = "Keyingi oyning birinchi ishchi kunida o'tgan oy uchun ish haqi qoralamasini tayyorlaydi";

    public function handle(TenantContext $context, PayrollCalculator $calc, NotificationService $notify): int
    {
        $count = 0;
        Tenant::where('status', 'active')->with('edition')->each(function (Tenant $t) use ($context, $calc, $notify, &$count) {
            $context->run($t, function () use ($t, $calc, $notify, &$count) {
                if (! $t->hasFeature('payroll') || ! $t->hasFeature('pay_schemes') || $t->isReadOnly()) {
                    return;
                }
                $today = today();
                $cal = app(WorkCalendar::class);
                if (! $this->option('force') && ! $this->isFirstWorkingDay($cal, $today)) {
                    return;
                }
                $period = $this->option('month') ? Carbon::parse($this->option('month').'-01') : $today->copy()->subMonthNoOverflow()->startOfMonth();
                $n = app(PayrollController::class)->generateFor($calc, $period, null);
                $count += $n;
                if ($n > 0) {
                    $notify->toOwner($t, __('Ish haqi qoralamasi tayyor'), __(':month uchun :n ta xodimning ish haqi qoralamasi tayyorlandi. Tekshirib, tasdiqlang.', ['month' => $period->format('m.Y'), 'n' => $n]),
                        route('app.payroll.index', ['month' => $period->format('Y-m')]), 'info', 'cash', 'payroll.approve');
                }
            });
        });
        $this->info("Qoralama: $count ta varaqa");

        return self::SUCCESS;
    }

    private function isFirstWorkingDay(WorkCalendar $cal, Carbon $today): bool
    {
        if (! $cal->isWorking($today)) {
            return false;
        }
        for ($d = $today->copy()->startOfMonth(); $d->lt($today); $d->addDay()) {
            if ($cal->isWorking($d)) {
                return false;
            }
        }

        return true;
    }
}
