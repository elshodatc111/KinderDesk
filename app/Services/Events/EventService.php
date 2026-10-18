<?php

namespace App\Services\Events;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Event;
use App\Models\EventKid;
use App\Models\GroupEmployee;
use App\Models\GroupEvent;
use App\Models\Kid;
use App\Models\PayslipItem;
use App\Models\User;
use App\Services\Finance\FinanceService;
use Illuminate\Support\Carbon;

class EventService
{
    public function __construct(private FinanceService $finance)
    {
    }

    public static function chargeKey(Event $e): string
    {
        return 'ev:'.$e->id;
    }

    /** Bitta bola uchun narx: jami / bolalar, yuqoriga yaxlitlanadi + ustama foiz. */
    public function computePrice(int $total, int $kids): int
    {
        if ($kids <= 0 || $total <= 0) {
            return 0;
        }
        $raw = $total / $kids;
        $raw *= 1 + max(0, (int) tenant()->setting('event.markup_percent', 0)) / 100;
        $step = max(1, (int) tenant()->setting('event.round_to', 1000));

        return (int) (ceil($raw / $step) * $step);
    }

    public function currentPrice(Event $e): int
    {
        $e->loadMissing('costs', 'staff', 'kids');

        return $e->price ?: $this->computePrice($e->plannedTotal(), $e->kids->count());
    }

    public function submit(Event $e): void
    {
        $e->load('costs', 'staff');
        if ($e->status !== 'draft' || $e->plannedTotal() <= 0) {
            throw new \DomainException("Avval xarajatlar smetasini kiriting");
        }
        $e->update(['status' => 'pending']);
    }

    public function approve(Event $e, User $by): void
    {
        if ($e->status !== 'pending') {
            throw new \DomainException("Tadbir tasdiqlashni kutmayapti");
        }
        $e->load('costs', 'staff');
        $first = $e->approved_at === null;
        $e->update(['status' => $e->locked_at ? 'locked' : 'registration', 'approved_total' => $e->plannedTotal(), 'reapproval' => false,
            'approved_by' => $by->id, 'approved_at' => now()]);
        AuditLog::record($first ? 'event.approved' : 'event.reapproved', $e);
    }

    public function reject(Event $e): void
    {
        if ($e->status === 'pending') {
            $e->update(['status' => 'draft']);
        }
    }

    /** Smeta o'zgardi: tasdiqlangandan keyin summa oshsa, qayta tasdiqlash kerak. */
    public function afterCostsChanged(Event $e): void
    {
        $e->load('costs', 'staff');
        if (in_array($e->status, ['registration', 'locked'], true) && $e->approved_total !== null && $e->plannedTotal() > $e->approved_total) {
            $e->update(['status' => 'pending', 'reapproval' => true]);
        }
    }

    public function register(Event $e, Kid $kid): void
    {
        if ($e->status !== 'registration') {
            throw new \DomainException("Ro'yxatga olish yopiq");
        }
        EventKid::firstOrCreate(['event_id' => $e->id, 'kid_id' => $kid->id], ['tenant_id' => $e->tenant_id]);
    }

    public function unregister(Event $e, Kid $kid): void
    {
        if ($e->status !== 'registration') {
            throw new \DomainException("Ro'yxatga olish yopiq");
        }
        EventKid::where('event_id', $e->id)->where('kid_id', $kid->id)->delete();
    }

    /** Ro'yxat yopiladi, narx qotiriladi. */
    public function lock(Event $e): void
    {
        if ($e->status !== 'registration') {
            throw new \DomainException("Tadbir ro'yxatga olish bosqichida emas");
        }
        $e->load('costs', 'staff', 'kids.kid');
        $count = $e->kids->count();
        if ($count === 0) {
            throw new \DomainException("Hech bir bola ro'yxatga olinmagan");
        }
        $e->update(['status' => 'locked', 'price' => $this->computePrice($e->plannedTotal(), $count), 'locked_at' => now()]);
        if (tenant()->setting('event.collect_before', false)) {
            foreach ($e->kids as $row) {
                $this->charge($e, $row);
            }
        }
    }

    private function charge(Event $e, EventKid $row): void
    {
        if ($row->kid && $e->price > 0) {
            $this->finance->charge($row->kid, Carbon::parse($e->date), -$e->price, self::chargeKey($e), null, "Tadbir: {$e->title}");
            $row->update(['charged' => true]);
        }
    }

    public function mark(Event $e, Kid $kid, string $status): void
    {
        if (! in_array($e->status, ['locked', 'held'], true)) {
            throw new \DomainException("Davomat hali olinmaydi");
        }
        $row = EventKid::where('event_id', $e->id)->where('kid_id', $kid->id)->firstOrFail();
        $row->update(['attended' => $status]);
        if (tenant()->setting('event.collect_before', false)) {
            return;
        }
        if ($status === 'present') {
            $this->charge($e, $row->fresh('kid'));
        } else {
            $this->finance->cancelCharge($kid, self::chargeKey($e));
            $row->update(['charged' => false]);
        }
    }

    public function hold(Event $e): void
    {
        if ($e->status === 'locked') {
            $e->update(['status' => 'held']);
        }
    }

    /** Yopish: haqiqiy xarajatlar kiritiladi, xodimlar kunlik haqi ish haqiga o'tadi. */
    public function close(Event $e): void
    {
        if ($e->status !== 'held') {
            throw new \DomainException("Avval tadbirni o'tkazilgan deb belgilang");
        }
        $e->load('staff', 'costs');
        $month = Carbon::parse($e->date)->startOfMonth();
        foreach ($e->staff as $s) {
            PayslipItem::where('employee_id', $s->employee_id)->where('source', 'ev:'.$e->id)->delete();
            if ($s->pay > 0) {
                PayslipItem::create(['tenant_id' => $e->tenant_id, 'employee_id' => $s->employee_id, 'period' => $month, 'type' => 'event_bonus',
                    'amount' => $s->pay, 'reason' => "Tadbir: {$e->title}", 'source' => 'ev:'.$e->id]);
            }
        }
        $e->update(['status' => 'closed', 'closed_at' => now()]);
    }

    public function cancel(Event $e): void
    {
        if (in_array($e->status, ['closed', 'cancelled'], true)) {
            return;
        }
        foreach ($e->kids()->with('kid')->get() as $row) {
            if ($row->kid) {
                $this->finance->cancelCharge($row->kid, self::chargeKey($e));
            }
        }
        $e->update(['status' => 'cancelled']);
    }

    /** @return array{planned:int, actual:int, collected:int, variance:int, kids:int, paying:int} */
    public function variance(Event $e): array
    {
        $e->load('costs', 'staff', 'kids');
        $planned = $e->plannedTotal();
        $actual = (int) $e->costs->sum(fn ($c) => $c->actual ?? $c->planned) + (int) $e->staff->sum('pay');
        $paying = $e->kids->where('charged', true)->count();
        $collected = $paying * (int) $e->price;

        return ['planned' => $planned, 'actual' => $actual, 'collected' => $collected, 'variance' => $collected - $actual,
            'kids' => $e->kids->count(), 'paying' => $paying];
    }

    /* ---------------- Guruh tadbiri bonusi ---------------- */

    public function approveGroupEvent(GroupEvent $g, User $by): void
    {
        $g->load('staff');
        $date = Carbon::parse($g->date);
        $month = $date->copy()->startOfMonth();
        PayslipItem::where('source', 'ge:'.$g->id)->delete();
        foreach ($g->staff as $emp) {
            $role = GroupEmployee::where('group_id', $g->group_id)->where('employee_id', $emp->id)
                ->whereDate('started_on', '<=', $date)->where(fn ($q) => $q->whereNull('ended_on')->orWhereDate('ended_on', '>=', $date))->value('role_in_group');
            if (! $role) {
                continue; // bonus faqat shu guruh xodimlariga
            }
            $amount = (int) tenant()->setting($role === 'assistant' ? 'event.bonus_assistant' : 'event.bonus_teacher', $role === 'assistant' ? 25000 : 50000);
            if ($amount > 0) {
                PayslipItem::create(['tenant_id' => $g->tenant_id, 'employee_id' => $emp->id, 'period' => $month, 'type' => 'event_bonus',
                    'amount' => $amount, 'reason' => "Guruh tadbiri: {$g->title}", 'source' => 'ge:'.$g->id, 'created_by' => $by->id]);
            }
        }
        $g->update(['status' => 'approved', 'approved_by' => $by->id, 'approved_at' => now()]);
    }

    public function rejectGroupEvent(GroupEvent $g, User $by): void
    {
        PayslipItem::where('source', 'ge:'.$g->id)->delete();
        $g->update(['status' => 'rejected', 'approved_by' => $by->id, 'approved_at' => now()]);
    }

    /**
     * KPI «tadbirlarda ishtirok»: xodim guruhida oy davomida tasdiqlangan tadbir bo'lmagan bo'lsa — bajarilgan hisoblanadi;
     * bo'lgan bo'lsa, kamida bittasida qatnashgan bo'lishi kerak.
     */
    public function participated(Employee $e, Carbon $period): bool
    {
        $start = $period->copy()->startOfMonth();
        $end = $period->copy()->endOfMonth();
        $groupIds = GroupEmployee::where('employee_id', $e->id)->whereDate('started_on', '<=', $end)
            ->where(fn ($q) => $q->whereNull('ended_on')->orWhereDate('ended_on', '>=', $start))->pluck('group_id');
        $events = GroupEvent::whereIn('group_id', $groupIds)->where('status', 'approved')->whereBetween('date', [$start->toDateString(), $end->toDateString()])->pluck('id');
        if ($events->isEmpty()) {
            return true;
        }

        return \DB::table('group_event_staff')->whereIn('group_event_id', $events)->where('employee_id', $e->id)->exists();
    }
}
