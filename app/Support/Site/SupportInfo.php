<?php

namespace App\Support\Site;

use App\Models\PlatformSetting;

/** Qo'llab-quvvatlash ish vaqti: super admin sozlaydi, sayt va kabinetda «hozir ishlayapmiz» holati hisoblanadi (Toshkent vaqti). */
class SupportInfo
{
    public const DAYS = [1 => 'Du', 2 => 'Se', 3 => 'Ch', 4 => 'Pa', 5 => 'Ju', 6 => 'Sh', 7 => 'Ya'];

    public static function days(): array
    {
        return collect(explode(',', (string) PlatformSetting::get('support.days')))->map(fn ($d) => (int) trim($d))->filter(fn ($d) => $d >= 1 && $d <= 7)->unique()->values()->all();
    }

    public static function from(): string
    {
        return (string) PlatformSetting::get('support.from');
    }

    public static function to(): string
    {
        return (string) PlatformSetting::get('support.to');
    }

    public static function response(): string
    {
        return trim((string) PlatformSetting::get('support.response'));
    }

    public static function configured(): bool
    {
        return self::from() !== '' && self::to() !== '' && self::days() !== [];
    }

    public static function isOpen(?\DateTimeInterface $at = null): bool
    {
        if (! self::configured()) {
            return false;
        }
        $now = \Illuminate\Support\Carbon::instance($at ?? now())->setTimezone('Asia/Tashkent');
        if (! in_array($now->dayOfWeekIso, self::days(), true)) {
            return false;
        }
        $t = $now->format('H:i');

        return $t >= self::from() && $t < self::to();
    }

    /** Masalan: «Du–Ju, 09:00–18:00». */
    public static function scheduleLabel(): string
    {
        if (! self::configured()) {
            return '';
        }
        $days = self::days();
        $first = $days[0];
        $last = end($days);
        $consecutive = count($days) > 2 && ($last - $first + 1) === count($days);
        $names = array_map(fn ($d) => __(self::DAYS[$d]), $days);
        $d = $consecutive ? __(self::DAYS[$first]).'–'.__(self::DAYS[$last]) : implode(', ', $names);

        return $d.', '.self::from().'–'.self::to();
    }
}
