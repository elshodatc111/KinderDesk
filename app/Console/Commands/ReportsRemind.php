<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\ReportSubmission;
use App\Models\Tenant;
use App\Services\Calendar\WorkCalendar;
use App\Services\NotificationService;
use App\Services\Reports\ReportChain;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

class ReportsRemind extends Command
{
    protected $signature = 'reports:remind {--mode=remind : remind (muddatdan 2 soat oldin xodimlarga) | overdue (muddat o\'tgach direktorga)}';

    protected $description = 'Hisobot zanjiri: eslatmalar va kechikkanlar haqida xabar';

    public function handle(TenantContext $context, NotificationService $notify): int
    {
        $overdue = $this->option('mode') === 'overdue';
        Tenant::where('status', 'active')->with('edition')->each(function (Tenant $t) use ($context, $notify, $overdue) {
            $context->run($t, function () use ($t, $notify, $overdue) {
                if (! $t->hasFeature('report_chain') || $t->isReadOnly()) {
                    return;
                }
                $day = $overdue ? today()->subDay() : today();
                if (! app(WorkCalendar::class)->isWorking($day)) {
                    return;
                }
                $chain = app(ReportChain::class);
                $missing = [];
                foreach (Employee::active()->with('role', 'user')->get() as $e) {
                    foreach ($chain->typesFor($e)->where('frequency', 'daily') as $type) {
                        if ($e->hired_on && $e->hired_on->gt($day) || $type->created_at->startOfDay()->gt($day)) {
                            continue;
                        }
                        $s = ReportSubmission::where('type_id', $type->id)->where('employee_id', $e->id)->whereDate('date', $day)->first();
                        if ($s && in_array($s->status, ['submitted', 'accepted'], true)) {
                            continue;
                        }
                        if ($overdue) {
                            $missing[] = $e->full_name.' — '.$type->name;
                        } elseif ($e->user) {
                            $notify->toUser($e->user, __('Hisobot muddati yaqin'), __(':name hisobotini :time gacha topshiring.', ['name' => $type->name, 'time' => $type->deadline_time]),
                                route('app.chain.index'), 'warn', 'report');
                        }
                    }
                }
                if ($overdue && $missing) {
                    $notify->toOwner($t, __('Hisobotlar topshirilmadi'), $day->format('d.m.Y').': '.implode('; ', array_slice($missing, 0, 10)).(count($missing) > 10 ? ' …' : ''),
                        route('app.chain.index'), 'warn', 'report', 'reports.view');
                }
            });
        });

        return self::SUCCESS;
    }
}
