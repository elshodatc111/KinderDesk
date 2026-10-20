<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public const SUPPORTED = ['uz', 'ru'];

    public function handle(Request $request, Closure $next)
    {
        // ?lang=uz|ru: har bir til alohida manzilga ega bo'lishi uchun (qidiruv tizimlari va ulashilgan havolalar)
        $asked = $request->query('lang');
        if ($request->isMethod('GET') && is_string($asked) && in_array($asked, self::SUPPORTED, true)) {
            session(['locale' => $asked]);
        }

        // Til tanlanmagan mehmon uchun: brauzer tilidan (uz/ru), aniqlanmasa — o'zbekcha (asosiy auditoriya).
        // Qidiruv botlari Accept-Language yubormaydi, shuning uchun ular ham o'zbekcha sahifani oladi.
        $locale = session('locale') ?? $request->user()?->locale ?? $this->preferred($request);
        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = 'uz';
        }
        app()->setLocale($locale);
        \Carbon\Carbon::setLocale($locale === 'uz' ? 'uz_Latn' : $locale);

        return $next($request);
    }

    /** Accept-Language'dan eng mos til: "ru-RU,ru;q=0.9,uz;q=0.8" → ru. */
    private function preferred(Request $request): string
    {
        foreach (explode(',', (string) $request->header('Accept-Language')) as $part) {
            $code = strtolower(substr(trim(explode(';', $part)[0]), 0, 2));
            if (in_array($code, self::SUPPORTED, true)) {
                return $code;
            }
        }

        return 'uz';
    }
}
