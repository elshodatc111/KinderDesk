<?php

namespace App\Support\Site;

use App\Models\PlatformSetting;
use Illuminate\Support\Str;

class Legal
{
    public const PAGES = ['oferta' => 'Ommaviy oferta', 'privacy' => 'Maxfiylik siyosati', 'terms' => 'Foydalanish shartlari'];

    /** Super admin yozgan matn; bo'sh bo'lsa — namuna. */
    public static function markdown(string $page, ?string $locale = null): string
    {
        $l = $locale ?: app()->getLocale();
        $stored = PlatformSetting::get("legal.$page.$l");

        return filled($stored) ? $stored : LegalDefaults::get($page, $l);
    }

    public static function isSample(string $page, ?string $locale = null): bool
    {
        return ! filled(PlatformSetting::get('legal.'.$page.'.'.($locale ?: app()->getLocale())));
    }

    public static function html(string $page, ?string $locale = null): string
    {
        return Str::markdown(self::markdown($page, $locale), ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }
}
