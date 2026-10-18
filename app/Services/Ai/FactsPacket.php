<?php

namespace App\Services\Ai;

use App\Models\DeskHandover;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\Event;
use App\Models\FinanceTransaction;
use App\Models\Group;
use App\Models\GroupEvent;
use App\Models\Kid;
use App\Models\KidAttendance;
use App\Models\KidExcuse;
use App\Models\KidLedger;
use App\Models\Lead;
use App\Models\Payslip;
use App\Models\ReportSubmission;
use App\Models\Substitution;
use App\Models\User;
use App\Services\Calendar\WorkCalendar;
use App\Services\Payroll\PayScheme;
use App\Services\Reports\ReportChain;
use Illuminate\Support\Carbon;

/**
 * AI'ga beriladigan FAKTLAR PAKETI. Raqamlarni kod (SQL) hisoblaydi, AI faqat izohlaydi.
 * Paketda: sonlar, foizlar, guruh nomi, lavozim (rol). Ism, telefon, manzil, hujjat, erkin matn YO'Q.
 * Xodimlar "Xodim-{id}" belgisi bilan ko'rsatiladi (moslik jadvali faqat o'z serverda).
 */
class FactsPacket
{
    public function __construct(private WorkCalendar $calendar)
    {
    }

    public static function label(Employee $e): string
    {
        return 'Xodim-'.$e->id;
    }

    /** @return array<string, mixed> */
    public function build(Carbon $date, string $period = 'daily'): array
    {
        $from = $period === 'weekly' ? $date->copy()->subDays(6) : $date->copy();
        $t = tenant();
        $facts = [
            'meta' => ['date' => $date->toDateString(), 'period' => $period, 'from' => $from->toDateString(), 'working_day' => $this->calendar->isWorking($date)],
        ];
        $sections = [
            'kindergarten' => fn () => $this->kindergarten(),
            'attendance' => fn () => $this->attendance($date, $from),
            'staff' => fn () => $this->staff($date),
            'money' => fn () => $this->money($date, $from),
            'admissions' => fn () => $this->admissions($date),
            'approvals' => fn () => $this->approvals(),
            'discipline' => fn () => $this->discipline(),
        ];
        if ($t->hasFeature('report_chain')) {
            $sections['reports'] = fn () => $this->reportDiscipline($date);
        }
        if ($t->hasFeature('pay_schemes')) {
            $sections['payroll'] = fn () => $this->payroll($date);
        }
        if ($t->hasFeature('events')) {
            $sections['events'] = fn () => $this->events($date);
        }
        foreach ($sections as $name => $fn) {
            try {
                $facts[$name] = $fn();
            } catch (\Throwable $e) {
                report($e);
                $facts[$name] = null;
            }
        }

        return $facts;
    }

    /** Dot-yo'llar bilan tekis xarita (tekshirish uchun). @return array<string, scalar|null> */
    public static function flatten(array $facts, string $prefix = ''): array
    {
        $out = [];
        foreach ($facts as $k => $v) {
            $key = $prefix === '' ? (string) $k : $prefix.'.'.$k;
            if (is_array($v)) {
                $out += self::flatten($v, $key);
            } else {
                $out[$key] = $v;
            }
        }

        return $out;
    }

    private function pct(int $a, int $b): ?float
    {
        return $b > 0 ? round($a * 100 / $b, 1) : null;
    }

    private function kindergarten(): array
    {
        $groups = Group::main()->active()->get();
        $active = Kid::active()->count();
        $cap = (int) $groups->sum('capacity');
        $vacant = max(0, $cap - $active);
        $avgFee = (int) round((float) $groups->avg('monthly_fee'));

        return ['active_kids' => $active, 'groups' => $groups->count(), 'capacity' => $cap, 'vacant_seats' => $vacant, 'lost_monthly_income' => $vacant * $avgFee];
    }

    private function attendance(Carbon $date, Carbon $from): array
    {
        $rate = function (Carbon $a, Carbon $b, ?int $groupId = null) {
            $q = KidAttendance::whereBetween('date', [$a->toDateString(), $b->toDateString()])->when($groupId, fn ($x) => $x->where('group_id', $groupId));
            $present = (clone $q)->where('status', 'present')->count();
            $total = (clone $q)->count();

            return $this->pct($present, $total);
        };
        $groups = [];
        foreach (Group::main()->active()->orderBy('name')->get() as $g) {
            $groups[] = [
                'group' => $g->name, 'kids' => $g->activeKidLinks()->count(),
                'percent_period' => $rate($from, $date, $g->id), 'percent_7d' => $rate($date->copy()->subDays(6), $date, $g->id), 'percent_30d' => $rate($date->copy()->subDays(29), $date, $g->id),
            ];
        }
        // 3+ ketma-ket ishchi kun kelmagan bolalar soni
        $streak = 0;
        $days = [];
        for ($d = $date->copy(); count($days) < 3 && $d->gte($date->copy()->subDays(14)); $d->subDay()) {
            if ($this->calendar->isWorking($d)) {
                $days[] = $d->toDateString();
            }
        }
        if (count($days) === 3) {
            $absentSets = collect($days)->map(fn ($day) => KidAttendance::whereDate('date', $day)->whereIn('status', ['absent', 'sick'])->pluck('kid_id')->all());
            $streak = count(array_intersect($absentSets[0], $absentSets[1], $absentSets[2]));
        }
        // haftaning qaysi kuni kam keladi (30 kun)
        $byDay = [];
        $names = [1 => 'Du', 2 => 'Se', 3 => 'Chor', 4 => 'Pay', 5 => 'Ju', 6 => 'Sha'];
        $rows = KidAttendance::whereBetween('date', [$date->copy()->subDays(29)->toDateString(), $date->toDateString()])->get(['date', 'status']);
        foreach ($rows->groupBy(fn ($r) => Carbon::parse($r->date)->dayOfWeekIso) as $iso => $list) {
            if (isset($names[$iso])) {
                $byDay[$names[$iso]] = $this->pct($list->where('status', 'present')->count(), $list->count());
            }
        }

        return ['percent_period' => $rate($from, $date), 'percent_7d' => $rate($date->copy()->subDays(6), $date), 'percent_30d' => $rate($date->copy()->subDays(29), $date),
            'groups' => $groups, 'kids_absent_3plus_days' => $streak, 'by_weekday_30d' => $byDay];
    }

    private function staff(Carbon $date): array
    {
        $out = ['unexcused_late_7d' => 0, 'unexcused_absent_7d' => 0, 'unexcused_late_30d' => 0, 'unexcused_absent_30d' => 0, 'top_unexcused_30d' => [], 'substitutions_30d_by_group' => []];
        if (tenant()->hasFeature('staff_attendance')) {
            $rows = EmployeeAttendance::with('employee.role')->whereBetween('date', [$date->copy()->subDays(29)->toDateString(), $date->toDateString()])->where('excuse', 'unexcused')->get();
            $in7 = fn ($r) => Carbon::parse($r->date)->gte($date->copy()->subDays(6));
            $out['unexcused_late_7d'] = $rows->where('status', 'late')->filter($in7)->count();
            $out['unexcused_absent_7d'] = $rows->where('status', 'absent')->filter($in7)->count();
            $out['unexcused_late_30d'] = $rows->where('status', 'late')->count();
            $out['unexcused_absent_30d'] = $rows->where('status', 'absent')->count();
            $out['top_unexcused_30d'] = $rows->groupBy('employee_id')->map(fn ($g) => ['staff' => $g->first()->employee ? self::label($g->first()->employee) : '—',
                'role' => $g->first()->employee?->role?->name ?? '—', 'count' => $g->count()])->sortByDesc('count')->take(3)->values()->all();
        }
        if (tenant()->hasFeature('substitution')) {
            $groups = Group::pluck('name', 'id');
            $out['substitutions_30d_by_group'] = Substitution::whereBetween('date', [$date->copy()->subDays(29)->toDateString(), $date->toDateString()])->get()
                ->groupBy('group_id')->map(fn ($g, $id) => ['group' => $groups[$id] ?? '—', 'count' => $g->count()])->sortByDesc('count')->values()->all();
        }

        return $out;
    }

    private function money(Carbon $date, Carbon $from): array
    {
        $paid = fn (Carbon $a, Carbon $b) => (int) FinanceTransaction::where('type', 'kid_payment')->where('status', 'confirmed')
            ->whereBetween('occurred_on', [$a->toDateString(), $b->toDateString()])->sum('amount');
        $monthStart = $date->copy()->startOfMonth();
        $charges = -(int) KidLedger::where('status', 'posted')->where('type', 'charge')->whereDate('period', $monthStart)->sum('amount');
        $discounts = (int) KidLedger::where('status', 'posted')->where('type', 'discount')->whereDate('period', $monthStart)->sum('amount');
        $debtors = Kid::active()->where('balance', '<', 0);
        $oldestHandover = DeskHandover::where('status', 'pending')->min('created_at');
        $oldestPending = FinanceTransaction::where('status', 'pending')->min('created_at');

        return [
            'income_period' => $paid($from, $date), 'income_7d' => $paid($date->copy()->subDays(6), $date), 'income_30d' => $paid($date->copy()->subDays(29), $date),
            'month_charges' => $charges, 'month_paid' => $paid($monthStart, $date),
            'month_discount_percent' => $this->pct(max(0, $discounts), max(1, $charges)),
            'debtors' => (clone $debtors)->count(), 'debt_total' => -(int) (clone $debtors)->sum('balance'),
            'handover_waiting_days' => $oldestHandover ? (int) Carbon::parse($oldestHandover)->diffInDays($date) : 0,
            'pending_payments' => FinanceTransaction::where('status', 'pending')->count(),
            'pending_payment_oldest_days' => $oldestPending ? (int) Carbon::parse($oldestPending)->diffInDays($date) : 0,
        ];
    }

    private function admissions(Carbon $date): array
    {
        $from7 = $date->copy()->subDays(6)->startOfDay();
        $kid = Lead::where('type', 'kid');
        $new7 = (clone $kid)->where('created_at', '>=', $from7)->count();
        $late = (clone $kid)->where('result', 'open')->where('created_at', '<', $date->copy()->subDay()->endOfDay())
            ->whereColumn('updated_at', 'created_at')->count();
        $month = (clone $kid)->where('created_at', '>=', $date->copy()->subDays(29)->startOfDay());
        $won = (clone $month)->where('result', 'won')->count();
        $by = (clone $month)->get(['source', 'result'])->groupBy(fn ($l) => $l->source ?: 'nomalum')
            ->map(fn ($g, $s) => ['source' => $s, 'leads' => $g->count(), 'won' => $g->where('result', 'won')->count()])->sortByDesc('leads')->take(5)->values()->all();

        return ['new_leads_7d' => $new7, 'open_unanswered_24h' => $late, 'conversion_30d_percent' => $this->pct($won, (clone $month)->count()), 'by_source_30d' => $by];
    }

    private function approvals(): array
    {
        return [
            'excuses_pending' => KidExcuse::where('status', 'pending')->count(),
            'payslips_draft' => Payslip::where('status', 'draft')->count(),
            'events_pending' => tenant()->hasFeature('events') ? Event::where('status', 'pending')->count() : 0,
            'group_events_pending' => tenant()->hasFeature('events') ? GroupEvent::where('status', 'pending')->count() : 0,
        ];
    }

    private function discipline(): array
    {
        $users = User::where('tenant_id', tenant()->id)->where('status', 'active')->with('role')->get();
        $idle = $users->filter(fn ($u) => ! $u->last_login_at || $u->last_login_at->lt(now()->subDays(14)));

        return ['users' => $users->count(), 'not_logged_in_14d' => $idle->count(), 'not_logged_in_roles' => $idle->groupBy(fn ($u) => $u->role?->name ?? '—')->map->count()->all()];
    }

    private function reportDiscipline(Carbon $date): array
    {
        $chain = app(ReportChain::class);
        $month = $date->copy()->startOfMonth();
        $risk = [];
        $byRole = [];
        $total = 0;
        foreach (Employee::active()->with('role')->get() as $e) {
            $missing = $chain->missing($e, $month, false, $date->copy()->endOfDay());
            if ($missing) {
                $role = $e->role?->name ?? '—';
                $byRole[$role] = ($byRole[$role] ?? 0) + count($missing);
                $total += count($missing);
                $risk[] = ['staff' => self::label($e), 'role' => $role, 'missed' => count($missing)];
            }
        }
        usort($risk, fn ($a, $b) => $b['missed'] <=> $a['missed']);

        return ['month_missed_total' => $total, 'missed_by_role' => $byRole, 'bonus_at_risk_staff' => count($risk), 'top_at_risk' => array_slice($risk, 0, 5),
            'unreviewed_submissions' => ReportSubmission::where('status', 'submitted')->count()];
    }

    private function payroll(Carbon $date): array
    {
        $out = ['payroll_to_income_percent' => null, 'groups_near_tier' => []];
        $prev = $date->copy()->subMonthNoOverflow()->startOfMonth();
        $pay = (int) Payslip::whereDate('period', $prev)->sum('total');
        $income = (int) FinanceTransaction::where('type', 'kid_payment')->where('status', 'confirmed')
            ->whereBetween('occurred_on', [$prev->toDateString(), $prev->copy()->endOfMonth()->toDateString()])->sum('amount');
        $out['payroll_to_income_percent'] = $income > 0 ? $this->pct($pay, $income) : null;
        foreach (Group::main()->active()->get() as $g) {
            $n = $g->activeKidLinks()->count();
            $scheme = PayScheme::for('teacher_'.($g->size ?: 'large'));
            if (! $scheme) {
                continue;
            }
            $gain = $scheme->tier($n + 1) - $scheme->tier($n);
            if ($gain >= 2 * $scheme->perKid() && $gain > 0) {
                $out['groups_near_tier'][] = ['group' => $g->name, 'kids' => $n, 'extra_cost_for_next_kid' => $gain];
            }
        }

        return $out;
    }

    private function events(Carbon $date): array
    {
        $closed = Event::where('status', 'closed')->where('closed_at', '>=', $date->copy()->subDays(30))->with('costs', 'staff', 'kids')->get();
        $svc = app(\App\Services\Events\EventService::class);
        $var = $closed->map(fn ($e) => $svc->variance($e));

        return ['closed_30d' => $closed->count(), 'planned_30d' => (int) $var->sum('planned'), 'actual_30d' => (int) $var->sum('actual'), 'collected_30d' => (int) $var->sum('collected'),
            'upcoming' => Event::whereIn('status', ['registration', 'locked'])->count()];
    }
}
