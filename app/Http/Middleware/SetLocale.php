<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public const SUPPORTED = ['uz', 'ru'];

    public function handle(Request $request, Closure $next)
    {
        $locale = session('locale') ?? $request->user()?->locale ?? 'ru';
        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = 'ru';
        }
        app()->setLocale($locale);
        \Carbon\Carbon::setLocale($locale === 'uz' ? 'uz_Latn' : $locale);

        return $next($request);
    }
}
