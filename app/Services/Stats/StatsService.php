<?php

namespace App\Services\Stats;

use App\Models\EmployeeAttendance;
use App\Models\FinanceTransaction;
use App\Models\Group;
use App\Models\Kid;
use App\Models\KidAttendance;
use App\Models\Lead;
use App\Models\Subsidy;
use Illuminate\Support\Carbon;

/**
 * Statistika: kunlik / haftalik / oylik / yillik kesimda diagrammalar uchun ma'lumot.
 * Faqat yig'ma sonlar — shaxsiy ma'lumot (ism, telefon) chiqmaydi.
 */
class StatsService
{
    public const PERIODS = ['day', 'week', 'month', 'year'];

    /** @return array<int, array{label:string, from:Carbon, to:Carbon}> */
    public function buckets(string $period, ?Carbon $now = null): array
    {
        $now = ($now ?? today())->copy()->startOfDay();
        $out = [];
        switch ($period) {
            case 'week':
                for ($i = 11; $i >= 0; $i--) {
                    $from = $now->copy()->startOfWeek()->subWeeks($i);
                    $out[] = ['label' => $from->format('d.m'), 'from' => $from, 'to' => $from->copy()->endOfWeek()];
                }
                break;
            case 'month':
                for ($i = 11; $i >= 0; $i--) {
                    $from = $now->copy()->startOfMonth()->subMonthsNoOverflow($i);
                    $out[] = ['label' => $from->format('m.Y'), 'from' => $from, 'to' => $from->copy()->endOfMonth()];
                }
                break;
            case 'year':
                for ($i = 4; $i >= 0; $i--) {
                    $from = $now->copy()->startOfYear()->subYears($i);
                    $out[] = ['label' => (string) $from->year, 'from' => $from, 'to' => $from->copy()->endOfYear()];
                }
                break;
            default:
                for ($i = 29; $i >= 0; $i--) {
                    $d = $now->copy()->subDays($i);
                    $out[] = ['label' => $d->format('d.m'), 'from' => $d, 'to' => $d->copy()->endOfDay()];
                }
        }

        return $out;
    }

    /** sana → kovak indeksi */
    private function map(array $buckets): array
    {
        $map = [];
        foreach ($buckets as $i => $b) {
            for ($d = $b['from']->copy(); $d->lte($b['to']); $d->addDay()) {
                $map[$d->toDateString()] = $i;
            }
        }

        return $map;
    }

    private function zeros(int $n): array
    {
        return array_fill(0, $n, 0);
    }

    public function build(string $period, bool $finance, bool $staff, bool $leads, bool $subsidy, ?Carbon $now = null): array
    {
        $period = in_array($period, self::PERIODS, true) ? $period : 'day';
        $now = ($now ?? today())->copy()->startOfDay();
        $buckets = $this->buckets($period, $now);
        $n = count($buckets);
        $map = $this->map($buckets);
        $from = $buckets[0]['from']->toDateString();
        $to = $buckets[$n - 1]['to']->toDateString();
        $labels = array_column($buckets, 'label');

        $out = ['period' => $period, 'labels' => $labels, 'from' => $from, 'to' => $to];

        // ----- Bolalar davomadi (asosiy guruhlar) -----
        $mainIds = Group::where('type', 'main')->pluck('id');
        $att = ['present' => $this->zeros($n), 'absent' => $this->zeros($n), 'sick' => $this->zeros($n)];
        $rows = KidAttendance::whereIn('group_id', $mainIds)->whereBetween('date', [$from, $to])->selectRaw('date, status, count(*) c')->groupBy('date', 'status')->get();
        foreach ($rows as $r) {
            $i = $map[Carbon::parse($r->date)->toDateString()] ?? null;
            if ($i !== null && isset($att[$r->status])) {
                $att[$r->status][$i] += (int) $r->c;
            }
        }
        $rate = [];
        foreach (range(0, $n - 1) as $i) {
            $tot = $att['present'][$i] + $att['absent'][$i] + $att['sick'][$i];
            $rate[] = $tot ? round($att['present'][$i] / $tot * 100, 1) : null;
        }
        $out['attendance'] = $att + ['rate' => $rate];

        // ----- Bolalar harakati -----
        $new = $this->zeros($n);
        $left = $this->zeros($n);
        foreach (Kid::whereBetween('enrolled_on', [$from, $to])->get(['enrolled_on']) as $k) {
            $new[$map[$k->enrolled_on->toDateString()] ?? 0]++;
        }
        foreach (Kid::whereBetween('archived_on', [$from, $to])->get(['archived_on']) as $k) {
            $left[$map[$k->archived_on->toDateString()] ?? 0]++;
        }
        $out['kids'] = ['new' => $new, 'left' => $left, 'active' => Kid::active()->count()];

        // ----- Guruhlar to'lishi (hozirgi holat) -----
        $groups = Group::active()->withCount('activeKidLinks')->orderBy('name')->get();
        $out['groups'] = ['labels' => $groups->pluck('name')->all(), 'kids' => $groups->pluck('active_kid_links_count')->map(fn ($v) => (int) $v)->all(), 'capacity' => $groups->pluck('capacity')->map(fn ($v) => (int) $v)->all()];

        // ----- Moliya -----
        $totalIncome = $totalExpense = 0;
        if ($finance) {
            $inc = $this->zeros($n);
            $exp = $this->zeros($n);
            $methods = [];
            $cats = [];
            $tx = FinanceTransaction::where('status', 'confirmed')->whereBetween('occurred_on', [$from, $to])
                ->whereIn('type', ['kid_payment', 'income', 'expense', 'salary'])
                ->selectRaw('occurred_on d, type, account, category, sum(amount) s')->groupBy('occurred_on', 'type', 'account', 'category')->get();
            foreach ($tx as $t) {
                $i = $map[Carbon::parse($t->d)->toDateString()] ?? null;
                if ($i === null) {
                    continue;
                }
                $v = (int) $t->s;
                if (in_array($t->type, ['kid_payment', 'income'], true)) {
                    $inc[$i] += $v;
                    if ($t->type === 'kid_payment') {
                        $methods[$t->account] = ($methods[$t->account] ?? 0) + $v;
                    }
                } else {
                    $exp[$i] += abs($v);
                    $c = $t->type === 'salary' ? __('Ish haqi') : ($t->category ? __($t->category) : __('Boshqa'));
                    $cats[$c] = ($cats[$c] ?? 0) + abs($v);
                }
            }
            arsort($cats);
            $labelsM = ['cash' => __('Naqd'), 'card' => __('Karta'), 'bank' => __('Bank')];
            $out['finance'] = [
                'income' => $inc, 'expense' => $exp, 'net' => array_map(fn ($a, $b) => $a - $b, $inc, $exp),
                'methods' => ['labels' => array_map(fn ($k) => $labelsM[$k] ?? $k, array_keys($methods)), 'values' => array_values($methods)],
                'categories' => ['labels' => array_slice(array_keys($cats), 0, 8), 'values' => array_slice(array_values($cats), 0, 8)],
            ];
            $totalIncome = array_sum($inc);
            $totalExpense = array_sum($exp);
            $out['debt'] = ['amount' => abs((int) Kid::active()->where('balance', '<', 0)->sum('balance')), 'kids' => Kid::active()->where('balance', '<', 0)->count()];
        }

        // ----- Xodimlar davomadi -----
        if ($staff) {
            $st = ['present' => $this->zeros($n), 'late' => $this->zeros($n), 'absent' => $this->zeros($n), 'leave' => $this->zeros($n)];
            $rows = EmployeeAttendance::whereBetween('date', [$from, $to])->selectRaw('date, status, count(*) c')->groupBy('date', 'status')->get();
            foreach ($rows as $r) {
                $i = $map[Carbon::parse($r->date)->toDateString()] ?? null;
                if ($i !== null && isset($st[$r->status])) {
                    $st[$r->status][$i] += (int) $r->c;
                }
            }
            $out['staff'] = $st;
        }

        // ----- Arizalar -----
        if ($leads) {
            $ln = $this->zeros($n);
            $lw = $this->zeros($n);
            foreach (Lead::whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])->get(['created_at', 'result']) as $l) {
                $i = $map[$l->created_at->toDateString()] ?? null;
                if ($i !== null) {
                    $ln[$i]++;
                    if ($l->result === 'won') {
                        $lw[$i]++;
                    }
                }
            }
            $out['leads'] = ['new' => $ln, 'won' => $lw];
        }

        // ----- Subsidiya -----
        if ($subsidy) {
            $sr = $this->zeros($n);
            foreach (Subsidy::where('status', 'received')->whereBetween('received_on', [$from, $to])->get(['received_on', 'received']) as $s) {
                $i = $map[$s->received_on->toDateString()] ?? null;
                if ($i !== null) {
                    $sr[$i] += $s->received;
                }
            }
            $out['subsidy'] = ['received' => $sr, 'expected' => (int) Subsidy::where('status', 'expected')->sum('expected')];
        }

        // ----- Umumiy ko'rsatkichlar -----
        $present = array_sum($att['present']);
        $tot = $present + array_sum($att['absent']) + array_sum($att['sick']);
        $out['kpi'] = [
            'attendance' => $tot ? round($present / $tot * 100, 1) : null,
            'income' => $totalIncome, 'expense' => $totalExpense, 'net' => $totalIncome - $totalExpense,
            'new_kids' => array_sum($new), 'left_kids' => array_sum($left),
        ];

        return $out;
    }
}
