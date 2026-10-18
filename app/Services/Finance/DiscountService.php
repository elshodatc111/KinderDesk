<?php

namespace App\Services\Finance;

use App\Models\Family;
use App\Models\Kid;
use App\Models\KidExcuse;
use App\Models\User;
use App\Services\Calendar\WorkCalendar;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Chegirmalar: aka-uka (oila) chegirmasi va sababli kelmaganlik chegirmasi.
 * Hamma foizlar bog'cha sozlamalaridan olinadi (direktor belgilaydi).
 */
class DiscountService
{
    public function __construct(private FinanceService $finance, private WorkCalendar $calendar)
    {
    }

    /* ---------------- Aka-uka chegirmasi ---------------- */

    /** Chegirma qaysi to'lov turlariga tegishli: main | club | evening */
    public function appliesTo(string $scope): bool
    {
        return in_array($scope, (array) tenant()->setting('sibling.apply_to', ['main', 'club', 'evening']), true);
    }

    /** Bolaning asosiy guruh bo'yicha oylik narxi (shaxsiy narx yoki guruh narxi). */
    public function mainFee(Kid $kid): int
    {
        $link = $kid->relationLoaded('currentLink') ? $kid->currentLink : $kid->currentLink()->with('group')->first();

        return (int) ($kid->custom_fee ?? $link?->group?->monthly_fee ?? 0);
    }

    /**
     * Oiladagi tartib raqami (1 = eng qimmat guruh, to'liq to'laydi). Oila tasdiqlanmagan yoki
     * kuchga kirmagan (keyingi oydan) bo'lsa — 1.
     */
    public function siblingRank(Kid $kid, Carbon $period): int
    {
        if (! tenant()->hasFeature('family_discount') || ! $kid->family_id) {
            return 1;
        }
        $family = Family::find($kid->family_id);
        if (! $family || ! $family->confirmed_at || $family->effective_from->gt($period->copy()->startOfMonth())) {
            return 1;
        }
        $members = Kid::where('family_id', $family->id)->where('status', 'active')->get();
        if ($members->count() < 2) {
            return 1;
        }
        $sorted = $members->sortBy([fn ($a, $b) => $this->mainFee($b) <=> $this->mainFee($a), fn ($a, $b) => $a->id <=> $b->id])->values();
        $pos = $sorted->search(fn ($k) => $k->id === $kid->id);

        return $pos === false ? 1 : $pos + 1;
    }

    /** Chegirma summasi ($gross — shu to'lov turi bo'yicha asosiy summa). 0 — chegirma yo'q. */
    public function siblingAmount(Kid $kid, Carbon $period, string $scope, int $gross): int
    {
        if ($gross <= 0 || $kid->custom_fee !== null || ! $this->appliesTo($scope)) {
            return 0;
        }
        $rank = $this->siblingRank($kid, $period);
        if ($rank < 2) {
            return 0;
        }
        $t = tenant();
        $tier = $rank === 2 ? 'second' : 'third';
        if ($t->setting('sibling.mode', 'percent') === 'amount') {
            $sum = (int) $t->setting("sibling.{$tier}_amount", 0);

            return min($gross, max(0, $sum));
        }
        $pct = (int) $t->setting("sibling.{$tier}_percent", 0);
        if ($pct === 0 && $rank === 2) { // eski sozlama bilan moslik
            $pct = (int) $t->setting('billing.sibling_discount', 0);
        }

        return (int) round($gross * min(100, max(0, $pct)) / 100);
    }

    /** Asosiy oylik to'lovga chegirma yozadi. */
    public function postMain(Kid $kid, Carbon $period, int $gross, ?int $groupId): void
    {
        $amount = $this->capped($gross, $this->siblingAmount($kid, $period, 'main', $gross));
        if ($amount > 0) {
            $this->finance->charge($kid, $period, $amount, 'sib:'.$period->format('Y-m').':k'.$kid->id, $groupId,
                "Aka-uka chegirmasi", 'discount');
        }
    }

    /** Kunlik (kechki) guruhga chegirma. */
    public function postDaily(Kid $kid, Carbon $date, int $gross, int $groupId): void
    {
        $amount = $this->capped($gross, $this->siblingAmount($kid, $date, 'evening', $gross));
        if ($amount > 0) {
            $this->finance->charge($kid, $date, $amount, 'sib:'.$date->toDateString().':k'.$kid->id.':g'.$groupId, $groupId,
                "Aka-uka chegirmasi", 'discount');
        }
    }

    /** To'garak to'loviga chegirma. */
    public function postClub(Kid $kid, Carbon $period, int $gross, int $clubId): void
    {
        $amount = $this->capped($gross, $this->siblingAmount($kid, $period, 'club', $gross));
        if ($amount > 0) {
            $this->finance->charge($kid, $period, $amount, 'sib:'.$period->format('Y-m').':k'.$kid->id.':c'.$clubId, null,
                "Aka-uka chegirmasi", 'discount', $clubId);
        }
    }

    private function capped(int $gross, int $amount): int
    {
        $max = (int) tenant()->setting('discount.max_percent', 100);

        return min($amount, (int) floor($gross * $max / 100));
    }

    /* ---------------- Oila bog'lamasi ---------------- */

    /**
     * Bir telefon bo'yicha o'xshash, hali bog'lanmagan bolalar guruhlari (administrator tasdiqlaydi).
     * @return Collection<int, Collection<int, Kid>>
     */
    public function suggestions(): Collection
    {
        $kids = Kid::where('status', 'active')->get();
        $groups = [];
        foreach ($kids as $k) {
            foreach (array_filter([$k->parent_phone, $k->parent_phone2]) as $ph) {
                $groups[$ph][$k->id] = $k;
            }
        }
        $seen = [];
        $out = collect();
        foreach ($groups as $members) {
            if (count($members) < 2) {
                continue;
            }
            $ids = collect($members)->keys()->sort()->values()->all();
            $sig = implode('-', $ids);
            if (isset($seen[$sig])) {
                continue;
            }
            $seen[$sig] = true;
            $coll = collect($members)->values();
            // Hammasi allaqachon bitta oilada bo'lsa — taklif emas
            if ($coll->pluck('family_id')->unique()->count() === 1 && $coll->first()->family_id) {
                continue;
            }
            $out->push($coll);
        }

        return $out;
    }

    /** Bolalarni bitta oilaga bog'laydi. Chegirma keyingi oyning 1-kunidan kuchga kiradi. */
    public function linkFamily(array $kidIds, User $by): Family
    {
        $kids = Kid::whereIn('id', $kidIds)->get();
        $existing = $kids->pluck('family_id')->filter()->first();
        $family = $existing ? Family::find($existing) : null;
        if (! $family) {
            $family = Family::create([
                'tenant_id' => tenant()->id, 'effective_from' => today()->addMonthNoOverflow()->startOfMonth(),
                'confirmed_by' => $by->id, 'confirmed_at' => now(),
            ]);
        }
        Kid::whereIn('id', $kids->pluck('id'))->update(['family_id' => $family->id]);
        // Eski oilalar bo'sh qolsa tozalanadi
        Family::whereDoesntHave('kids')->delete();

        return $family;
    }

    public function unlinkKid(Kid $kid): void
    {
        $fid = $kid->family_id;
        $kid->update(['family_id' => null]);
        if ($fid && Kid::where('family_id', $fid)->count() < 2) {
            Kid::where('family_id', $fid)->update(['family_id' => null]);
            Family::where('id', $fid)->delete();
        }
    }

    /* ---------------- Sababli kelmagan chegirmasi ---------------- */

    /** Chegirma summasi = (oylik narx − aka-uka chegirmasi) / W × sababli kunlar. */
    public function excuseAmount(Kid $kid, Carbon $period, int $days): int
    {
        $fee = $this->mainFee($kid);
        if ($fee <= 0 || $days <= 0) {
            return 0;
        }
        $net = $fee - $this->siblingAmount($kid, $period, 'main', $fee);
        $w = $this->calendar->countWorking($period);
        $amount = (int) round($net / $w * min($days, $w));
        $max = (int) tenant()->setting('discount.max_percent', 100);
        $already = abs((int) \App\Models\KidLedger::where('kid_id', $kid->id)->where('type', 'discount')->where('status', 'posted')
            ->whereDate('period', $period->copy()->startOfMonth())->whereNull('club_id')->sum('amount'));
        $room = max(0, (int) floor($fee * $max / 100) - $already);

        return min($amount, $room);
    }

    public function enterExcuse(Kid $kid, Carbon $period, int $days, string $reason, User $by): KidExcuse
    {
        return KidExcuse::create([
            'tenant_id' => tenant()->id, 'kid_id' => $kid->id, 'period' => $period->copy()->startOfMonth(),
            'days' => $days, 'reason' => $reason, 'status' => 'pending', 'entered_by' => $by->id,
        ]);
    }

    public function approveExcuse(KidExcuse $e, User $by): bool
    {
        if ($e->status !== 'pending') {
            return false;
        }
        $kid = $e->kid;
        $amount = $this->excuseAmount($kid, $e->period, $e->days);
        $e->update(['status' => 'approved', 'amount' => $amount, 'decided_by' => $by->id, 'decided_at' => now()]);
        if ($amount > 0) {
            $this->finance->charge($kid, $e->period, $amount, 'exc:'.$e->period->format('Y-m').':e'.$e->id, null,
                "Sababli kelmagan chegirmasi ({$e->days} kun)", 'discount');
        }

        return true;
    }

    public function rejectExcuse(KidExcuse $e, User $by): bool
    {
        if ($e->status !== 'pending') {
            return false;
        }

        return $e->update(['status' => 'rejected', 'decided_by' => $by->id, 'decided_at' => now()]);
    }
}
