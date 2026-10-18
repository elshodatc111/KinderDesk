<?php

namespace App\Console\Commands;

use App\Models\AiReport;
use App\Models\Tenant;
use App\Services\Ai\AiException;
use App\Services\Ai\ReportService;
use App\Services\Calendar\WorkCalendar;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AiDaily extends Command
{
    protected $signature = 'ai:daily {--date= : YYYY-MM-DD (standart: kecha)} {--tenant= : bitta bog\'cha ID}';

    protected $description = "Direktor uchun AI hisobot: Premium — kunlik, Standard — haftalik (dushanba)";

    public function handle(TenantContext $context, ReportService $reports): int
    {
        $yesterday = $this->option('date') ? Carbon::parse($this->option('date')) : today()->subDay();
        $n = 0;
        Tenant::where('status', 'active')->when($this->option('tenant'), fn ($q, $id) => $q->where('id', $id))->with('edition')->each(function (Tenant $t) use ($context, $reports, $yesterday, &$n) {
            $context->run($t, function () use ($t, $reports, $yesterday, &$n) {
                if ($t->isReadOnly() || $t->ai_disabled) {
                    return;
                }
                $jobs = [];
                if ($t->hasFeature('ai_report_daily')) {
                    foreach ([$yesterday->copy()->subDay(), $yesterday] as $d) { // avvalgi kun ham, agar tayyor bo'lmasa
                        $jobs[] = [$d, 'daily'];
                    }
                } elseif ($t->hasFeature('ai_report_weekly') && $yesterday->isSunday()) {
                    $jobs[] = [$yesterday, 'weekly'];
                }
                $cal = app(WorkCalendar::class);
                foreach ($jobs as [$d, $period]) {
                    if ($period === 'daily' && ! $cal->isWorking($d)) {
                        continue;
                    }
                    $existing = AiReport::whereDate('date', $d)->where('period', $period)->first();
                    if ($existing && in_array($existing->status, ['ready', 'unreliable'], true)) {
                        continue;
                    }
                    try {
                        $reports->generate($t, $d, $period);
                        $n++;
                    } catch (AiException $e) {
                        $this->warn("{$t->name}: ".$e->getMessage());
                    }
                }
            });
        });
        $this->info("AI hisobotlar: $n");

        return self::SUCCESS;
    }
}
