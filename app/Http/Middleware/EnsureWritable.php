<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Obuna/sinov tugagan bo'lsa tizim "faqat ko'rish" rejimiga o'tadi:
 * barcha yozish so'rovlari (POST/PUT/PATCH/DELETE) bloklanadi.
 * Faqat obuna/to'lov bo'limi, chiqish, profil va bildirishnomalar ochiq qoladi.
 */
class EnsureWritable
{
    private const ALLOWED = [
        'app.subscription.*', 'app.profile.*', 'app.notifications.*', 'logout', 'locale', 'impersonate.stop',
    ];

    public function handle(Request $request, Closure $next)
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $tenant = tenant();
        if (! $tenant || ! $tenant->isReadOnly()) {
            return $next($request);
        }

        if ($request->routeIs(...self::ALLOWED)) {
            return $next($request);
        }

        $message = $tenant->isManualReadOnly()
            ? "Tizim vaqtincha faqat ko'rish rejimida: ".$tenant->manual_readonly_reason
            : ($tenant->accessState() === 'suspended'
            ? "Hisobingiz vaqtincha bloklangan. Qo'llab-quvvatlash xizmatiga murojaat qiling."
            : "Obuna muddati tugagan: tizim faqat ko'rish rejimida. Davom etish uchun obunani yangilang.");

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'read_only' => true], 423);
        }

        return redirect()->back()->with('error', $message);
    }
}
