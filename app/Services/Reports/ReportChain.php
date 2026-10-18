<?php

namespace App\Services\Reports;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\KidAttendance;
use App\Models\Lead;
use App\Models\ReportSubmission;
use App\Models\ReportType;
use App\Models\ReportWaiver;
use App\Models\User;
use App\Services\Calendar\WorkCalendar;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Hisobot zanjiri: xodim → administrator → direktor.
 * Hisobot faqat topshirilgan VA qabul qilingan bo'lsa hisoblanadi. Bitta ishchi kun o'tkazib yuborilsa — shu oy bonuslari qo'shilmaydi
 * (direktor sabab yozib kechirishi mumkin).
 */
class ReportChain
{
    public function __construct(private WorkCalendar $calendar)
    {
    }

    public static function roleKey(Employee $e): ?string
    {
        return $e->role?->key ?? $e->position;
    }

    /** @return Collection<int, ReportType> */
    public function typesFor(Employee $e): Collection
    {
        $key = self::roleKey($e);

        return ReportType::where('active', true)->where('role_key', $key)->orderBy('level')->orderBy('name')->get();
    }

    /** Hisobotning topshirish muddati (oxirgi lahza). */
    public function deadline(ReportType $type, Carbon $key): Carbon
    {
        $end = match ($type->frequency) {
            'weekly' => $key->copy()->addDays(6),
            'monthly' => $key->copy()->endOfMonth(),
            default => $key->copy(),
        };
        [$h, $m] = array_map('intval', explode(':', $type->deadline_time ?: '23:59') + [1 => 0]);

        return $end->startOfDay()->setTime($h, $m, 59);
    }

    /**
     * Berilgan oyda muddati o'tgan (talab qilingan) kalitlar.
     * @return array<int, Carbon>
     */
    public function dueKeys(ReportType $type, Employee $e, Carbon $period, ?Carbon $now = null): array
    {
        $now = $now ?? now();
        $start = $period->copy()->startOfMonth();
        $end = $period->copy()->endOfMonth();
        $from = $start->copy()->max(Carbon::parse($type->created_at)->startOfDay());
        if ($e->hired_on) {
            $from = $from->max(Carbon::parse($e->hired_on)->startOfDay());
        }
        $keys = [];
        if ($type->frequency === 'daily') {
            for ($d = $from->copy(); $d->lte($end) && $d->lte($now); $d->addDay()) {
                if ($this->calendar->isWorking($d) && $now->gt($this->deadline($type, $d))) {
                    $keys[] = $d->copy();
                }
            }
        } elseif ($type->frequency === 'weekly') {
            for ($d = $start->copy()->startOfWeek(); $d->lte($end); $d->addWeek()) {
                if ($d->gte($start) && $d->gte($from->copy()->startOfWeek()) && $now->gt($this->deadline($type, $d))) {
                    $keys[] = $d->copy();
                }
            }
        } else {
            if ($from->lte($end) && $now->gt($this->deadline($type, $start))) {
                $keys[] = $start->copy();
            }
        }

        return $keys;
    }

    private function waived(Employee $e, Carbon $period, Carbon $key): bool
    {
        return ReportWaiver::where('employee_id', $e->id)->whereDate('period', $period->copy()->startOfMonth())
            ->where(fn ($q) => $q->whereNull('date')->orWhereDate('date', $key))->exists();
    }

    /**
     * Qabul qilinmagan (va kechirilmagan) muddati o'tgan hisobotlar.
     * @return array<int, array{type: ReportType, key: Carbon, state: string}>
     */
    public function missing(Employee $e, Carbon $period, bool $onlyParent = false, ?Carbon $now = null): array
    {
        $period = $period->copy()->startOfMonth();
        $allowLate = (bool) tenant()->setting('report.allow_late', true);
        $subs = ReportSubmission::where('employee_id', $e->id)->whereBetween('date', [$period->copy()->startOfWeek()->toDateString(), $period->copy()->endOfMonth()->toDateString()])->get()
            ->keyBy(fn ($s) => $s->type_id.'|'.$s->date->toDateString());
        $out = [];
        foreach ($this->typesFor($e) as $type) {
            if ($onlyParent ? ! $type->is_parent : ! $type->affects_bonus) {
                continue;
            }
            foreach ($this->dueKeys($type, $e, $period, $now) as $key) {
                $s = $subs[$type->id.'|'.$key->toDateString()] ?? null;
                $ok = $s && $s->status === 'accepted' && ($allowLate || ! $s->late);
                if (! $ok && ! $this->waived($e, $period, $key)) {
                    $out[] = ['type' => $type, 'key' => $key, 'state' => $s?->status ?? 'missing'];
                }
            }
        }

        return $out;
    }

    /** KPI sharti: ota-onaga hisobotlar to'liq qabul qilinganmi. */
    public function parentReportsComplete(Employee $e, Carbon $period): bool
    {
        return $this->missing($e, $period, true) === [];
    }

    /** Bonuslar qo'shiladimi (hisobot zanjiri buzilmagan). */
    public function bonusesAllowed(Employee $e, Carbon $period): bool
    {
        if ($this->missing($e, $period) !== []) {
            return false;
        }
        // Administrator: xodimlar hisobotini o'z vaqtida ko'rib chiqmasa ham bonus qo'shilmaydi
        if (self::roleKey($e) === 'admin') {
            $days = max(0, (int) tenant()->setting('report.review_days', 2));
            $p = $period->copy()->startOfMonth();
            $stale = ReportSubmission::where('status', 'submitted')->whereBetween('date', [$p->toDateString(), $p->copy()->endOfMonth()->toDateString()])
                ->whereHas('type', fn ($q) => $q->where('level', 1))
                ->where('submitted_at', '<', now()->subDays($days))->exists();
            if ($stale && ! $this->waived($e, $p, $p)) {
                return false;
            }
        }

        return true;
    }

    /** Hozir topshirilishi kerak bo'lgan (oxirgi 7 kun + bugun) hisobotlar. */
    public function todo(Employee $e): array
    {
        $out = [];
        $today = today();
        foreach ($this->typesFor($e) as $type) {
            $from = $today->copy()->subDays(7)->max(Carbon::parse($type->created_at)->startOfDay());
            $keys = [];
            if ($type->frequency === 'daily') {
                for ($d = $from->copy(); $d->lte($today); $d->addDay()) {
                    if ($this->calendar->isWorking($d)) {
                        $keys[] = $d->copy();
                    }
                }
            } elseif ($type->frequency === 'weekly') {
                $keys[] = $today->copy()->startOfWeek();
            } else {
                $keys[] = $today->copy()->startOfMonth();
            }
            foreach ($keys as $key) {
                $s = ReportSubmission::where('type_id', $type->id)->where('employee_id', $e->id)->whereDate('date', $key)->first();
                if ($s && $s->status === 'accepted') {
                    continue;
                }
                $out[] = ['type' => $type, 'key' => $key, 'submission' => $s, 'overdue' => now()->gt($this->deadline($type, $key))];
            }
        }

        return $out;
    }

    public function submit(ReportType $type, Employee $e, Carbon $key, ?string $body, array $files, ?User $by): ReportSubmission
    {
        if (! $type->active || self::roleKey($e) !== $type->role_key) {
            throw new \DomainException("Bu hisobot turi shu xodim uchun emas");
        }
        if ($key->gt(today()) || ($type->frequency === 'daily' && ! $this->calendar->isWorking($key))) {
            throw new \DomainException("Bu sana uchun hisobot topshirib bo'lmaydi (kelajak yoki dam olish kuni)");
        }
        $existing = ReportSubmission::where('type_id', $type->id)->where('employee_id', $e->id)->whereDate('date', $key)->first();
        if ($existing && $existing->status === 'accepted') {
            throw new \DomainException("Bu hisobot allaqachon qabul qilingan");
        }
        $body = trim((string) $body);
        if ($type->content === 'auto') {
            $body = $this->autoBody($key);
        } elseif (in_array($type->content, ['file', 'media'], true) && $files === [] && ! ($existing && $existing->files)) {
            throw new \DomainException("Fayl yoki foto/video biriktiring");
        } elseif ($type->content === 'text' && mb_strlen($body) < 3) {
            throw new \DomainException("Hisobot matnini yozing");
        }
        $data = [
            'tenant_id' => $type->tenant_id, 'user_id' => $by?->id, 'status' => 'submitted', 'late' => now()->gt($this->deadline($type, $key)),
            'body' => $body ?: null, 'files' => $files ?: ($existing?->files), 'submitted_at' => now(), 'reviewed_by' => null, 'reviewed_at' => null, 'note' => null,
            'meta' => $type->level === 2 ? $this->packet($key) : null,
        ];
        $sub = ReportSubmission::updateOrCreate(['type_id' => $type->id, 'employee_id' => $e->id, 'date' => $key->toDateString()], $data);

        return $sub;
    }

    public function review(ReportSubmission $s, bool $accept, ?string $note, User $by): void
    {
        if ($s->status !== 'submitted') {
            throw new \DomainException("Bu hisobot ko'rib chiqishni kutmayapti");
        }
        if (! $accept && mb_strlen(trim((string) $note)) < 3) {
            throw new \DomainException("Qaytarish sababini yozing");
        }
        $s->update(['status' => $accept ? 'accepted' : 'returned', 'reviewed_by' => $by->id, 'reviewed_at' => now(), 'note' => $note ? trim($note) : null]);
    }

    public function waive(Employee $e, Carbon $period, ?Carbon $date, string $reason, User $by): ReportWaiver
    {
        if (mb_strlen(trim($reason)) < 3) {
            throw new \DomainException("Kechirish sababini yozing");
        }
        $w = ReportWaiver::create(['tenant_id' => $e->tenant_id, 'employee_id' => $e->id, 'period' => $period->copy()->startOfMonth(),
            'date' => $date?->toDateString(), 'reason' => trim($reason), 'created_by' => $by->id]);
        AuditLog::record('report.waived', $w, ['employee_id' => $e->id, 'date' => $date?->toDateString(), 'period' => $period->format('Y-m')]);

        return $w;
    }

    /** 2-daraja paketi: shu kun 1-daraja natijalari, topshirmaganlar bilan. */
    public function packet(Carbon $date): array
    {
        $rows = [];
        $types = ReportType::where('active', true)->where('level', 1)->where('frequency', 'daily')->get();
        foreach (Employee::active()->with('role')->get() as $e) {
            foreach ($types->where('role_key', self::roleKey($e)) as $type) {
                if ($e->hired_on && Carbon::parse($e->hired_on)->gt($date) || Carbon::parse($type->created_at)->startOfDay()->gt($date)) {
                    continue;
                }
                $s = ReportSubmission::where('type_id', $type->id)->where('employee_id', $e->id)->whereDate('date', $date)->first();
                $rows[] = ['employee' => $e->full_name, 'type' => $type->name, 'status' => $s?->status ?? 'missing'];
            }
        }

        return ['date' => $date->toDateString(), 'rows' => $rows,
            'missing' => count(array_filter($rows, fn ($r) => $r['status'] === 'missing')),
            'accepted' => count(array_filter($rows, fn ($r) => $r['status'] === 'accepted'))];
    }

    /** Tizim ma'lumotidan avtomatik matn (kassa/davomat/arizalar — shaxsiy ma'lumotsiz, faqat sonlar). */
    public function autoBody(Carbon $date): string
    {
        $present = KidAttendance::whereDate('date', $date)->where('status', 'present')->count();
        $absent = KidAttendance::whereDate('date', $date)->where('status', 'absent')->count();
        $staff = EmployeeAttendance::whereDate('date', $date)->whereIn('status', ['present', 'late'])->count();
        $leads = Lead::whereDate('created_at', $date)->count();

        return "Bolalar: kelgan {$present}, kelmagan {$absent}. Xodimlar kelgan: {$staff}. Yangi arizalar: {$leads}.";
    }
}
