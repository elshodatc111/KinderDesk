<?php

namespace App\Services\Attendance;

use App\Models\Group;
use App\Models\GroupKid;
use App\Models\KidAttendance;
use App\Services\Calendar\WorkCalendar;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Bolalar davomadi statistikasi: kun bo'yicha guruhlar kesimida va oylik kalendar. Umumiy natija faqat asosiy guruhlardan. */
class AttendanceStats
{
    public function __construct(private WorkCalendar $calendar)
    {
    }

    private function blank(): array
    {
        return ['total' => 0, 'present' => 0, 'absent' => 0, 'sick' => 0, 'unmarked' => 0, 'rate' => null];
    }

    private function finish(array $r): array
    {
        $r['unmarked'] = max(0, $r['total'] - $r['present'] - $r['absent'] - $r['sick']);
        $r['rate'] = $r['total'] > 0 ? round($r['present'] / $r['total'] * 100) : null;

        return $r;
    }

    /** @return array{groups: Collection, totals: array} */
    public function day(Carbon $date, ?Collection $groups = null): array
    {
        $groups ??= Group::active()->orderByRaw("case type when 'main' then 0 when 'evening' then 1 else 2 end")->orderBy('name')->get();
        $links = GroupKid::whereIn('group_id', $groups->pluck('id'))->whereDate('started_on', '<=', $date)
            ->where(fn ($q) => $q->whereNull('ended_on')->orWhereDate('ended_on', '>=', $date))->get()->groupBy('group_id');
        $marks = KidAttendance::whereDate('date', $date)->whereIn('group_id', $groups->pluck('id'))->get()->groupBy('group_id');

        $rows = $groups->map(function (Group $g) use ($links, $marks) {
            $m = $marks->get($g->id, collect());
            $kidIds = ($links->get($g->id) ?? collect())->pluck('kid_id');
            $r = $this->blank();
            // Kunlik guruhlarda (shanba/kechki) ro'yxat doimiy emas: belgilanganlar soni ham hisobga olinadi
            $r['total'] = max($kidIds->count(), $m->count());
            foreach (['present', 'absent', 'sick'] as $s) {
                $r[$s] = $m->where('status', $s)->count();
            }

            return ['group' => $g] + $this->finish($r);
        });

        $totals = $this->blank();
        foreach ($rows->filter(fn ($r) => $r['group']->type === 'main') as $r) {
            foreach (['total', 'present', 'absent', 'sick'] as $k) {
                $totals[$k] += $r[$k];
            }
        }

        return ['groups' => $rows, 'totals' => $this->finish($totals)];
    }

    /**
     * Oy kalendari: har bir kun uchun asosiy guruhlar yig'indisi.
     *
     * @return array<string, array{date: Carbon, working: bool, future: bool, marked: bool}&array>
     */
    public function month(Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $mainIds = Group::active()->where('type', 'main')->pluck('id');
        $links = GroupKid::whereIn('group_id', $mainIds)->whereDate('started_on', '<=', $end)
            ->where(fn ($q) => $q->whereNull('ended_on')->orWhereDate('ended_on', '>=', $start))->get(['started_on', 'ended_on']);
        $marks = KidAttendance::whereIn('group_id', $mainIds)->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('date, status, count(*) c')->groupBy('date', 'status')->get()->groupBy(fn ($r) => Carbon::parse($r->date)->toDateString());

        $out = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $key = $d->toDateString();
            $r = $this->blank();
            $r['total'] = $links->filter(fn ($l) => $l->started_on->lte($d) && (! $l->ended_on || $l->ended_on->gte($d)))->count();
            $day = $marks->get($key, collect());
            foreach (['present', 'absent', 'sick'] as $s) {
                $r[$s] = (int) $day->where('status', $s)->sum('c');
            }
            $r = $this->finish($r);
            $out[$key] = $r + ['date' => $d->copy(), 'working' => $this->calendar->isWorking($d), 'future' => $d->isFuture(), 'marked' => $day->isNotEmpty()];
        }

        return $out;
    }
}
