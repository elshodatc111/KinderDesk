<?php

namespace App\Services\Calendar;

use App\Models\CalendarDay;
use App\Models\CalendarMonth;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Bog'chaning ish kalendari. Barcha hisob-kitoblar (to'lov, davomat, ish haqi) shu yerdagi
 * ishchi kunlar soniga (W) tayanadi.
 *
 * Qoida: oy direktor tomonidan tasdiqlanmagan bo'lsa — shanba/yakshanba avtomatik dam olish
 * (sozlamadagi hafta kunlari), bayramlar sanalmaydi. Direktor alohida kunlarni o'zgartirib, oyni tasdiqlaydi.
 */
class WorkCalendar
{
    /** @var array<string, array<string, CalendarDay>> */
    private array $cache = [];

    /** Kun turlari: working | off | holiday */
    public static function kinds(): array
    {
        return ['working' => 'Ish kuni', 'off' => 'Dam olish', 'holiday' => 'Bayram'];
    }

    /** @return array<string, CalendarDay> sana => yozuv (faqat qo'lda belgilanganlar) */
    private function overrides(Carbon $month): array
    {
        $key = tenant()->id.':'.$month->format('Y-m');

        return $this->cache[$key] ??= CalendarDay::whereBetween('date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->get()->keyBy(fn ($d) => $d->date->toDateString())->all();
    }

    private function weekdays(): array
    {
        return array_map('intval', (array) tenant()->setting('general.working_days', [1, 2, 3, 4, 5]));
    }

    /** Kun turi: working | off | holiday */
    public function kindOf(Carbon $date): string
    {
        $o = $this->overrides($date)[$date->toDateString()] ?? null;
        if ($o) {
            return $o->kind;
        }

        return in_array($date->dayOfWeekIso, $this->weekdays(), true) ? 'working' : 'off';
    }

    public function isWorking(Carbon $date): bool
    {
        return $this->kindOf($date) === 'working';
    }

    /** @return Carbon[] oydagi ishchi kunlar */
    public function workingDays(Carbon $month): array
    {
        $out = [];
        for ($d = $month->copy()->startOfMonth(); $d->lte($month->copy()->endOfMonth()); $d->addDay()) {
            if ($this->isWorking($d)) {
                $out[] = $d->copy();
            }
        }

        return $out;
    }

    /** W — oydagi ishchi kunlar soni (kamida 1). */
    public function countWorking(Carbon $month): int
    {
        return max(1, count($this->workingDays($month)));
    }

    /** Oyning har bir kuni: [date, kind, title, manual] */
    public function month(Carbon $month): array
    {
        $o = $this->overrides($month);
        $out = [];
        for ($d = $month->copy()->startOfMonth(); $d->lte($month->copy()->endOfMonth()); $d->addDay()) {
            $m = $o[$d->toDateString()] ?? null;
            $out[] = ['date' => $d->copy(), 'kind' => $this->kindOf($d), 'title' => $m?->title, 'manual' => (bool) $m];
        }

        return $out;
    }

    public function setDay(Carbon $date, ?string $kind, ?string $title = null): void
    {
        if (! $kind) { // avtomatik holatga qaytarish
            CalendarDay::where('date', $date->toDateString())->delete();
        } else {
            CalendarDay::updateOrCreate(['date' => $date->toDateString()], ['tenant_id' => tenant()->id, 'kind' => $kind, 'title' => $title]);
        }
        unset($this->cache[tenant()->id.':'.$date->format('Y-m')]);
        // Kun o'zgarsa, tasdiq bekor bo'ladi — direktor qayta tasdiqlashi kerak
        CalendarMonth::where('month', $date->copy()->startOfMonth()->toDateString())->update(['confirmed_by' => null, 'confirmed_at' => null]);
    }

    public function isConfirmed(Carbon $month): bool
    {
        return CalendarMonth::where('month', $month->copy()->startOfMonth()->toDateString())->whereNotNull('confirmed_at')->exists();
    }

    public function confirm(Carbon $month, User $by): void
    {
        CalendarMonth::updateOrCreate(['month' => $month->copy()->startOfMonth()->toDateString()],
            ['tenant_id' => tenant()->id, 'confirmed_by' => $by->id, 'confirmed_at' => now()]);
    }

    public function unconfirm(Carbon $month): void
    {
        CalendarMonth::where('month', $month->copy()->startOfMonth()->toDateString())->update(['confirmed_by' => null, 'confirmed_at' => null]);
    }
}
