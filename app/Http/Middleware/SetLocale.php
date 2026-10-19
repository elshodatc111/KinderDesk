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

        $locale = session('locale') ?? $request->user()?->locale ?? 'ru';
        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = 'ru';
        }
        app()->setLocale($locale);
        \Carbon\Carbon::setLocale($locale === 'uz' ? 'uz_Latn' : $locale);

        return $next($request);
    }
}
