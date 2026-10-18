<?php

namespace App\Http\Middleware;

use App\Services\Security\ConsentService;
use Closure;
use Illuminate\Http\Request;

/**
 * Kirgan foydalanuvchi uchun majburiy shartlar:
 *  1) oferta/maxfiylik/shartlarga (joriy versiya) rozilik;
 *  2) Telegram-kod orqali ikki bosqichli tasdiq (direktor va super admin).
 * Super admin «kirib turgan» (impersonation) sessiyada ikkalasi ham o'tkazib yuboriladi.
 */
class EnsureSecurity
{
    public function __construct(private ConsentService $consent)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user || $request->session()->has('impersonator_id')) {
            return $next($request);
        }

        if ($this->consent->needs($user)) {
            if (! $request->expectsJson() && $request->isMethod('GET')) {
                $request->session()->put('url.intended', $request->fullUrl());
            }

            return $request->expectsJson() ? abort(403, 'Rozilik kerak') : redirect()->route('consent.show');
        }

        if ($redirect = app(\App\Services\Security\TwoFactor::class)->gate($request, $user)) {
            return $redirect;
        }

        return $next($request);
    }
}
