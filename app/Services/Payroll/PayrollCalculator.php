<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\GroupKid;
use App\Models\PayslipItem;
use App\Services\Finance\ChargeService;
use Illuminate\Support\Carbon;

/**
 * Ish haqi hisoblash. Usul bog'cha sozlamasidan (yoki xodimning shaxsiy sozlamasidan) olinadi:
 *  fixed      — belgilangan oylik
 *  attendance — ishlagan kunlarga qarab (kunlik stavka × kelgan kunlar)
 *  hybrid     — asosiy oylik + guruhdagi bolalar soniga qo'shimcha (KPI)
 * Jarima (sababsiz kechikish va kelmaslik, xodimlar davomadi yoqilgan bo'lsa) barcha usullarga qo'llanadi; summalarni direktor sozlamada belgilaydi.
 */
class PayrollCalculator
{
    public function __construct(private ChargeService $charges, private SchemePayroll $scheme)
    {
    }

    public function strategyFor(Employee $e): string
    {
        $t = tenant();
        if ($t->hasFeature('pay_schemes') && $this->scheme->kindOf($e)) {
            return 'scheme';
        }
        $wanted = $e->salary_type ?: $t->setting('payroll.strategy', 'fixed');
        if ($wanted === 'attendance' && ! $t->hasFeature('payroll_attendance')) {
            return 'fixed';
        }
        if ($wanted === 'hybrid' && ! $t->hasFeature('payroll_hybrid')) {
            return 'fixed';
        }

        return $wanted;
    }

    /** @return array{strategy:string, base_amount:int, worked_days:int, working_days:int, late_count:int, kids_count:int, kpi_amount:int, penalty:int, total:int} */
    public function calculate(Employee $e, Carbon $period, ?array $periodData = null, bool $persist = false): array
    {
        $t = tenant();
        $period = $period->copy()->startOfMonth();
        $end = $period->copy()->endOfMonth();
        $strategy = $this->strategyFor($e);

        $workingDays = (int) $t->setting('payroll.working_days', 0) ?: count($this->charges->workingDays($period));
        $workingDays = max(1, $workingDays);

        $worked = 0;
        $late = 0;
        $lateFines = 0;
        $absentFines = 0;
        if ($t->hasFeature('staff_attendance')) {
            $rows = EmployeeAttendance::where('employee_id', $e->id)->whereBetween('date', [$period->toDateString(), $end->toDateString()])->get(['status', 'excuse']);
            $worked = $rows->filter(fn ($r) => in_array($r->status, ['present', 'late'], true))->count();
            $late = $rows->filter(fn ($r) => $r->status === 'late')->count();
            // Jarima faqat administrator «sababsiz» deb belgilagan kunlar uchun
            $lateFines = $rows->filter(fn ($r) => $r->status === 'late' && $r->excuse === 'unexcused')->count();
            $absentFines = $rows->filter(fn ($r) => $r->status === 'absent' && $r->excuse === 'unexcused')->count();
        }

        $base = 0;
        $kids = 0;
        $kpi = 0;
        $detail = null;
        switch ($strategy) {
            case 'scheme':
                [$base, $kpi, $kids, $detail] = $this->schemeAmounts($e, $period, $periodData, $persist);
                break;
            case 'attendance':
                $daily = $e->daily_rate ?: (int) round($e->base_salary / $workingDays);
                $base = $daily * $worked;
                break;
            case 'hybrid':
                $base = $e->base_salary;
                $groupIds = $e->groupLinks()->where(fn ($q) => $q->whereNull('ended_on')->orWhereDate('ended_on', '>=', $period))->whereDate('started_on', '<=', $end)->pluck('group_id');
                $kids = $groupIds->isEmpty() ? 0 : GroupKid::whereIn('group_id', $groupIds)->whereDate('started_on', '<=', $end)
                    ->where(fn ($q) => $q->whereNull('ended_on')->orWhereDate('ended_on', '>=', $end))->distinct('kid_id')->count('kid_id');
                $kpi = $kids * $e->kpi_per_kid;
                break;
            default:
                $base = $e->base_salary;
        }

        $penalty = $t->hasFeature('staff_attendance')
            ? $lateFines * (int) $t->setting('attendance.fine_late', 0) + $absentFines * (int) $t->setting('attendance.fine_absent', 0)
            : 0;

        $bonus = 0;
        $advance = 0;
        if ($t->hasFeature('pay_schemes')) {
            // Jarima usuli: "shartli bonusni qo'shmaslik" bo'lsa, sababsiz holatda pul ushlanmaydi, bonus qismi qo'shilmaydi
            if ($penalty > 0 && $t->setting('pay.penalty_mode', 'withhold') === 'conditional_bonus') {
                if ($detail !== null) {
                    $detail['bonus_cancelled'] = $kpi;
                }
                $kpi = 0;
                $penalty = 0;
            }
            $items = PayslipItem::where('employee_id', $e->id)->whereDate('period', $period)->get()->groupBy('type');
            $bonus = (int) ($items['premium'] ?? collect())->sum('amount') + (int) ($items['event_bonus'] ?? collect())->sum('amount');
            $penalty += (int) ($items['fine'] ?? collect())->sum('amount') + (int) ($items['deduction'] ?? collect())->sum('amount');
            $advance = (int) ($items['advance'] ?? collect())->sum('amount');
            if ($detail !== null) {
                $detail['substitution'] = $detail['substitution'] ?? 0;
            }
        }
        $total = max(0, $base + $kpi + $bonus - $penalty - $advance);

        return ['strategy' => $strategy, 'base_amount' => (int) $base, 'worked_days' => $worked, 'working_days' => $workingDays,
            'late_count' => $late, 'kids_count' => $kids, 'kpi_amount' => (int) $kpi, 'bonus' => $bonus, 'penalty' => (int) $penalty,
            'advance' => $advance, 'total' => (int) $total, 'detail' => $detail];
    }

    /**
     * Sxema bo'yicha: [asos, kpi/bonus qismi, bolalar soni, tafsilot].
     * @return array{0:int,1:int,2:int,3:array}
     */
    private function schemeAmounts(Employee $e, Carbon $period, ?array $periodData, bool $persist): array
    {
        $t = tenant();
        $kind = $this->scheme->kindOf($e);
        $detail = ['kind' => $kind];

        if ($kind === 'pedagog') {
            $p = app(\App\Services\Subjects\LessonPay::class)->forEmployee($e, $period);
            $detail['lessons'] = $p['rows'];

            return [$p['amount'], 0, 0, $detail];
        }

        if ($kind === 'admin') {
            $a = $this->scheme->admin($period, $persist);
            $allowed = PayHooks::bonusesAllowed($e, $period);
            $bonuses = $allowed ? $a['new_kids_amount'] + $a['full_amount'] : 0;
            $detail += $a + ['bonuses_allowed' => $allowed];

            return [$a['fixed'], $bonuses, $a['active_kids'], $detail];
        }

        $data = $periodData ?? $this->scheme->period($period);
        $mine = $data['employees'][$e->id] ?? null;
        $base = $mine ? (int) round($mine['amount']) : 0;
        $detail['W'] = $data['W'];
        $detail['rows'] = array_values(array_map(fn ($r) => [
            'group' => $r['group'], 'role' => $r['role'], 'n' => $r['n'], 'T' => $r['T'], 'r' => round($r['r'], 2),
            'days' => $r['days'], 'amount' => (int) round($r['amount']), 'sub_amount' => (int) round($r['sub_amount']),
        ], $mine['rows'] ?? []));
        $detail['substitution'] = (int) round(collect($mine['rows'] ?? [])->sum('sub_amount'));

        $kpi = 0;
        if ($kind === 'group') {
            $cond = $this->scheme->kpiConditions($e, $period);
            $allowed = PayHooks::bonusesAllowed($e, $period);
            $unit = (int) $t->setting('pay.kpi_amount', 100000);
            $kpi = $allowed ? $unit * count(array_filter($cond)) : 0;
            $detail['kpi'] = $cond + ['unit' => $unit, 'bonuses_allowed' => $allowed];
        }
        $kids = (int) collect($mine['rows'] ?? [])->max('n');

        return [$base, $kpi, $kids, $detail];
    }
}
