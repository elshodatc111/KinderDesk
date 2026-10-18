<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }
        // Bog'cha kabinetida «kirib turgan» super admin /admin ga o'tmoqchi bo'lsa — o'z hisobiga qaytariladi (403 o'rniga)
        if (! $user->is_super_admin && ($adminId = $request->session()->get('impersonator_id')) && $request->isMethod('GET')) {
            $request->session()->forget('impersonator_id');
            \Illuminate\Support\Facades\Auth::loginUsingId($adminId);
            $request->session()->regenerate();

            return redirect($request->fullUrl());
        }
        if (! $user->is_super_admin || ! $user->isActive()) {
            abort(403);
        }

        return $next($request);
    }
}
