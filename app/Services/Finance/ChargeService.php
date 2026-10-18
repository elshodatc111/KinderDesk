<?php

namespace App\Services\Finance;

use App\Models\Club;
use App\Models\ClubAttendance;
use App\Models\ClubKid;
use App\Models\Group;
use App\Models\GroupKid;
use App\Models\Kid;
use App\Models\Tenant;
use App\Services\Calendar\WorkCalendar;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;

/**
 * Bolalarga oylik to'lovni hisoblash. Takroriy ishga tushirishdan xavfsiz (charge_key noyob).
 * Asosiy guruh: qat'iy oylik to'lov (bola kelsa ham, kelmasa ham guruh narxi to'liq). Faqat yangi kelgan bolaning
 * birinchi oyi sozlamaga ko'ra kalendardagi ishchi kunlarga proporsional bo'lishi mumkin (billing.newcomer_mode).
 */
class ChargeService
{
    public function __construct(private FinanceService $finance, private TenantContext $context, private DiscountService $discounts, private WorkCalendar $calendar)
    {
    }

    /** Bog'cha uchun joriy davrni hisoblaydi. @return array{charged:int, skipped:int} */
    public function runForTenant(Tenant $tenant, ?Carbon $today = null, bool $force = false): array
    {
        $today ??= today();

        return $this->context->run($tenant, function () use ($tenant, $today, $force) {
            $stats = ['charged' => 0, 'skipped' => 0];
            if (! $force && (! $tenant->setting('billing.auto_charge', true) || $tenant->isReadOnly())) {
                return $stats;
            }
            $chargeDay = (int) $tenant->setting('billing.charge_day', 1);

            if ($today->day >= $chargeDay || $force) {
                $period = $today->copy()->startOfMonth();
                Kid::active()->each(function (Kid $kid) use ($period, &$stats) {
                    $this->chargeKid($kid, $period) ? $stats['charged']++ : $stats['skipped']++;
                });
            }

            // To'garaklarning oylik to'lovi (har oyning 1-sanasidan, hisob kunidan qat'i nazar)
            if (feature('clubs') && ($tenant->setting('billing.auto_charge', true) || $force)) {
                ClubKid::with('club', 'kid')->whereNull('ended_on')->get()->each(function (ClubKid $link) use ($today, &$stats) {
                    if ($link->club && $link->club->status === 'active' && ! $link->club->isPerSession() && $link->kid?->status === 'active') {
                        $this->chargeClubMonth($link->kid, $link->club, $today->copy()->startOfMonth()) ? $stats['charged']++ : $stats['skipped']++;
                    }
                });
            }

            return $stats;
        });
    }

    /** Yangi qabul qilingan bola uchun (joriy oy hisob kuni o'tgan bo'lsa). */
    public function chargeOnEnroll(Kid $kid): void
    {
        $t = tenant();
        if (! $t->setting('billing.auto_charge', true)) {
            return;
        }
        if (today()->day >= (int) $t->setting('billing.charge_day', 1)) {
            $this->chargeKid($kid, today()->startOfMonth());
        }
    }

    /** Bitta bola, bitta davr. Hisoblangan bo'lsa true. */
    public function chargeKid(Kid $kid, Carbon $period, ?string $unused = null): bool
    {
        $period = $period->copy()->startOfMonth();
        $end = $period->copy()->endOfMonth();
        $links = GroupKid::with('group')->where('kid_id', $kid->id)
            ->whereHas('group', fn ($q) => $q->where('type', 'main'))
            ->whereDate('started_on', '<=', $end)
            ->where(fn ($q) => $q->whereNull('ended_on')->orWhereDate('ended_on', '>=', $period))
            ->orderBy('started_on')->get();
        if ($links->isEmpty()) {
            return false;
        }

        $link = $links->last();
        $fee = (int) ($kid->custom_fee ?? $link->group->monthly_fee);
        $gross = $fee;
        $desc = $link->group->name.': '.$this->label($period);

        // Yangi kelgan bolaning birinchi oyi: sozlama bo'yicha kalendar kunlariga proporsional
        $first = ! \App\Models\KidLedger::where('kid_id', $kid->id)->where('type', 'charge')->whereNull('club_id')->whereDate('period', '<', $period)->exists();
        if ($first && tenant()->setting('billing.newcomer_mode', 'full') === 'prorated' && $link->started_on->gt($period)) {
            $days = collect($this->calendar->workingDays($period))->filter(fn ($d) => $d->gte($link->started_on))->count();
            $gross = (int) round($fee * $days / $this->calendar->countWorking($period));
            $desc .= ' ('.$days.' kun)';
        }
        if ($gross <= 0) {
            return false;
        }

        $key = $period->format('Y-m').':m';
        if (! $this->finance->charge($kid, $period, -$gross, $key, $link->group_id, $desc)) {
            return false;
        }
        $this->discounts->postMain($kid, $period, $gross, $link->group_id);

        return true;
    }

    /* ---------------- Kunlik guruhlar (shanba / kechki) ---------------- */

    /**
     * Shanba va kechki guruh: faqat belgilangan ("keldi") kun uchun bolaning hisobidan yechiladi.
     * Keyin "kelmadi"ga o'zgartirilsa — o'sha kun uchun yechim bekor qilinadi.
     */
    public function syncDaily(Kid $kid, Group $group, Carbon $date, string $status): void
    {
        if (! $group->isDaily() || $group->daily_fee <= 0) {
            return;
        }
        $key = 'd:'.$date->toDateString().':g'.$group->id;
        if ($status === 'present') {
            $label = $group->name.': '.$date->format('d.m.Y');
            $this->finance->charge($kid, $date, -$group->daily_fee, $key, $group->id, $label);
            $this->discounts->postDaily($kid, $date, (int) $group->daily_fee, $group->id);
        } else {
            $this->finance->cancelCharge($kid, $key);
            $this->finance->cancelCharge($kid, 'sib:'.$date->toDateString().':k'.$kid->id.':g'.$group->id);
        }
    }

    /* ---------------- To'garaklar ---------------- */

    public function chargeClubMonth(Kid $kid, Club $club, Carbon $period): bool
    {
        if ($club->fee <= 0) {
            return false;
        }

        $ok = $this->finance->charge($kid, $period, -$club->fee, $period->format('Y-m').':c'.$club->id, null,
            "To'garak: {$club->name} ({$this->label($period)})", 'charge', $club->id);
        if ($ok) {
            $this->discounts->postClub($kid, $period, (int) $club->fee, $club->id);
        }

        return $ok;
    }

    /** To'garak mashg'ulotiga qatnashgan kun uchun (per_session) yechim. */
    public function syncClubSession(Kid $kid, Club $club, Carbon $date, string $status): void
    {
        if (! $club->isPerSession() || $club->fee <= 0) {
            return;
        }
        $key = 'cs:'.$date->toDateString().':c'.$club->id;
        if ($status === 'present') {
            $this->finance->charge($kid, $date, -$club->fee, $key, null, "To'garak: {$club->name} — ".$date->format('d.m.Y'), 'charge', $club->id);
        } else {
            $this->finance->cancelCharge($kid, $key);
        }
    }

    /** @return Carbon[] oydagi ish kunlari (ish kalendaridan) */
    public function workingDays(Carbon $period): array
    {
        return $this->calendar->workingDays($period);
    }

    private function label(Carbon $p): string
    {
        return $p->format('m.Y');
    }
}
