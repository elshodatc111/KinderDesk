<?php

namespace App\Services\Payroll;

use App\Models\AdminBonusEvent;
use App\Models\Complaint;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\FinanceTransaction;
use App\Models\Group;
use App\Models\GroupEmployee;
use App\Models\Kid;
use App\Models\KidAttendance;
use App\Models\Substitution;
use App\Services\Calendar\WorkCalendar;
use Illuminate\Support\Carbon;

/**
 * Premium ish haqi: bolalar soniga (plus) asoslangan sxemalar.
 *
 *  - n = oy davomida guruhning o'rtacha kelgan bolalari (jami plus / W)
 *  - r = T(n) / (n × W) — bitta plusning narxi
 *  - kun ulushi = o'sha kungi plus × r; kun davomida ishlagan xodimga (yoki o'rindoshga) yoziladi
 */
class SchemePayroll
{
    public function __construct(private WorkCalendar $calendar)
    {
    }

    /** Xodim qaysi hisob turiga kiradi: group (tarbiyachi/yordamchi) | cook | admin | null (qat'iy oylik). */
    public function kindOf(Employee $e): ?string
    {
        if ($e->scheme_key === 'fixed') {
            return null;
        }
        $key = $e->scheme_key ?: ($e->role?->key ?? $e->position);

        return match ($key) {
            'teacher', 'assistant', 'group' => 'group',
            'cook' => 'cook',
            'admin' => 'admin',
            'pedagog' => tenant()->hasFeature('subjects') ? 'pedagog' : null,
            default => null,
        };
    }

    /**
     * Butun oy uchun plus asosidagi ulushlar.
     * @return array{W:int, employees: array<int, array{amount:float, rows: array<int, array>}>}
     */
    public function period(Carbon $period): array
    {
        $period = $period->copy()->startOfMonth();
        $end = $period->copy()->endOfMonth();
        $W = $this->calendar->countWorking($period);
        $days = collect($this->calendar->workingDays($period))->map(fn ($d) => $d->toDateString())->all();
        $groups = Group::where('type', 'main')->get();

        // plus: guruh -> sana -> son
        $pluses = [];
        KidAttendance::whereIn('group_id', $groups->pluck('id'))->where('status', 'present')
            ->whereBetween('date', [$period->toDateString(), $end->toDateString()])
            ->selectRaw('group_id, date, count(*) c')->groupBy('group_id', 'date')->get()
            ->each(function ($r) use (&$pluses, $days) {
                $d = Carbon::parse($r->date)->toDateString();
                if (in_array($d, $days, true)) {
                    $pluses[$r->group_id][$d] = (int) $r->c;
                }
            });

        $absent = [];
        if (tenant()->hasFeature('staff_attendance')) {
            EmployeeAttendance::whereBetween('date', [$period->toDateString(), $end->toDateString()])->whereIn('status', ['absent', 'leave'])
                ->get(['employee_id', 'date'])->each(function ($r) use (&$absent) {
                    $absent[$r->employee_id.'|'.Carbon::parse($r->date)->toDateString()] = true;
                });
        }
        $subs = [];
        if (tenant()->hasFeature('substitution')) {
            Substitution::whereBetween('date', [$period->toDateString(), $end->toDateString()])->get()->each(function ($s) use (&$subs) {
                $subs[$s->group_id.'|'.$s->date->toDateString().'|'.$s->absent_employee_id] = $s->substitute_employee_id;
            });
        }
        $links = GroupEmployee::whereDate('started_on', '<=', $end)
            ->where(fn ($q) => $q->whereNull('ended_on')->orWhereDate('ended_on', '>=', $period))->get();

        $out = [];
        $add = function (int $empId, array $row) use (&$out) {
            $out[$empId]['amount'] = ($out[$empId]['amount'] ?? 0) + $row['amount'];
            $key = $row['group_id'].'|'.$row['role'];
            if (! isset($out[$empId]['rows'][$key])) {
                $out[$empId]['rows'][$key] = $row;
            } else {
                $out[$empId]['rows'][$key]['amount'] += $row['amount'];
                $out[$empId]['rows'][$key]['sub_amount'] += $row['sub_amount'];
                $out[$empId]['rows'][$key]['days'] += $row['days'];
            }
        };

        // 1) Tarbiyachi va yordamchi — o'z guruhlari bo'yicha
        foreach ($groups as $g) {
            $perDay = $pluses[$g->id] ?? [];
            $total = array_sum($perDay);
            if ($total === 0) {
                continue;
            }
            $n = (int) round($total / $W);
            foreach (['teacher', 'assistant'] as $role) {
                $scheme = PayScheme::for($role.'_'.($g->size ?: 'large'));
                if (! $scheme) {
                    continue;
                }
                $T = $scheme->tier($n);
                $r = $scheme->rate($n, $W);
                foreach ($perDay as $date => $p) {
                    $share = $p * $r;
                    $workers = [];
                    $viaSub = [];
                    foreach ($links as $l) {
                        if ($l->group_id !== $g->id || $l->role_in_group !== $role || $l->started_on->toDateString() > $date || ($l->ended_on && $l->ended_on->toDateString() < $date)) {
                            continue;
                        }
                        if (isset($absent[$l->employee_id.'|'.$date])) {
                            $sub = $subs[$g->id.'|'.$date.'|'.$l->employee_id] ?? null;
                            if ($sub) {
                                $workers[$sub] = true;
                                $viaSub[$sub] = true;
                            }
                        } else {
                            $workers[$l->employee_id] = true;
                        }
                    }
                    if (! $workers) {
                        continue; // ishlagan xodim yo'q — shu kun hech kimga yozilmaydi
                    }
                    $part = $share / count($workers);
                    foreach (array_keys($workers) as $empId) {
                        $add($empId, ['group_id' => $g->id, 'group' => $g->name, 'role' => $role, 'scheme' => $scheme->key, 'n' => $n, 'T' => $T, 'r' => $r,
                            'pluses' => 0, 'days' => 1, 'amount' => $part, 'sub_amount' => isset($viaSub[$empId]) ? $part : 0]);
                    }
                }
            }
        }

        // 2) Oshpaz — butun bog'cha bo'yicha
        $kitchen = [];
        foreach ($pluses as $perDay) {
            foreach ($perDay as $d => $p) {
                $kitchen[$d] = ($kitchen[$d] ?? 0) + $p;
            }
        }
        $cookScheme = PayScheme::for('cook');
        if ($cookScheme && $kitchen) {
            $n = (int) round(array_sum($kitchen) / $W);
            $T = $cookScheme->tier($n);
            $r = $cookScheme->rate($n, $W);
            $cooks = Employee::with('role')->get()->filter(fn ($e) => $this->kindOf($e) === 'cook');
            foreach ($kitchen as $date => $p) {
                $present = $cooks->filter(fn ($e) => (! $e->hired_on || $e->hired_on->toDateString() <= $date) && (! $e->left_on || $e->left_on->toDateString() >= $date)
                    && ! isset($absent[$e->id.'|'.$date]));
                if ($present->isEmpty()) {
                    continue;
                }
                $part = $p * $r / $present->count();
                foreach ($present as $e) {
                    $add($e->id, ['group_id' => 0, 'group' => "Butun bog'cha", 'role' => 'cook', 'scheme' => 'cook', 'n' => $n, 'T' => $T, 'r' => $r,
                        'pluses' => 0, 'days' => 1, 'amount' => $part, 'sub_amount' => 0]);
                }
            }
        }

        return ['W' => $W, 'employees' => $out];
    }

    /** Administrator: belgilangan oylik + yangi bolalar + faol bolalar soni. */
    public function admin(Carbon $period, bool $persist = false): array
    {
        $period = $period->copy()->startOfMonth();
        $end = $period->copy()->endOfMonth();
        $t = tenant();
        $admins = Employee::with('role')->active()->get()->filter(fn ($e) => $this->kindOf($e) === 'admin')->count();
        $admins = max(1, $admins);

        // Birinchi to'lovi shu oyga to'g'ri kelgan va hali hisoblanmagan bolalar
        $first = FinanceTransaction::where('type', 'kid_payment')->where('status', 'confirmed')->whereNotNull('kid_id')
            ->selectRaw('kid_id, min(occurred_on) first_on')->groupBy('kid_id')->get()
            ->filter(fn ($r) => Carbon::parse($r->first_on)->between($period, $end))->pluck('kid_id');
        $existing = AdminBonusEvent::whereDate('period', $period)->pluck('kid_id');
        $counted = AdminBonusEvent::pluck('kid_id');
        $fresh = $first->reject(fn ($id) => $counted->contains($id) && ! $existing->contains($id))->values();
        $newKids = $existing->merge($fresh)->unique()->values();
        $unit = (int) $t->setting('pay.admin_new_kid', 100000);
        $bonus = $t->hasFeature('admin_bonus');
        if ($persist && $bonus) {
            foreach ($fresh as $kidId) {
                AdminBonusEvent::firstOrCreate(['kid_id' => $kidId], ['tenant_id' => $t->id, 'period' => $period->toDateString(), 'amount' => $unit]);
            }
        }

        $active = Kid::where(fn ($q) => $q->whereNull('enrolled_on')->orWhereDate('enrolled_on', '<=', $end))
            ->where(fn ($q) => $q->where('status', 'active')->orWhereDate('archived_on', '>', $end))->count();

        return [
            'fixed' => (int) $t->setting('pay.admin_fixed', 3000000),
            'new_kids' => $newKids->count(),
            'new_kids_amount' => $bonus ? (int) round($newKids->count() * $unit / $admins) : 0,
            'active_kids' => $active,
            'full_amount' => $bonus ? (int) round($active * (int) $t->setting('pay.admin_per_kid', 25000) / $admins) : 0,
            'admins' => $admins,
        ];
    }

    /** Tarbiyachi/yordamchi KPI shartlari. @return array{parent_report:bool, satisfied:bool, events:bool} */
    public function kpiConditions(Employee $e, Carbon $period): array
    {
        $period = $period->copy()->startOfMonth();
        $end = $period->copy()->endOfMonth();
        $groupIds = GroupEmployee::where('employee_id', $e->id)->whereDate('started_on', '<=', $end)
            ->where(fn ($q) => $q->whereNull('ended_on')->orWhereDate('ended_on', '>=', $period))->pluck('group_id');
        $complaint = Complaint::whereBetween('date', [$period->toDateString(), $end->toDateString()])
            ->where(fn ($q) => $q->where('employee_id', $e->id)->orWhere(fn ($w) => $w->whereNull('employee_id')->whereIn('group_id', $groupIds)))->exists();

        return [
            'parent_report' => PayHooks::parentReportMet($e, $period),
            'satisfied' => ! $complaint,
            'events' => PayHooks::eventsMet($e, $period),
        ];
    }
}
