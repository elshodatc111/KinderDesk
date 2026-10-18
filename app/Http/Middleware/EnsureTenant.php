<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** /app sohasi: kirgan foydalanuvchining bog'chasini aniqlaydi va ma'lumotlarni shu bog'chaga cheklaydi. */
class EnsureTenant
{
    public function __construct(private TenantContext $context)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }
        if ($user->is_super_admin) {
            return redirect()->route('admin.dashboard');
        }
        if (! $user->isActive() || ! $user->tenant_id) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['phone' => "Hisobingiz faol emas. Bog'cha direktoriga murojaat qiling."]);
        }

        $tenant = $user->tenant;
        $this->context->set($tenant, true);
        view()->share('tenant', $tenant);

        if (! $tenant->last_active_at || $tenant->last_active_at->lt(now()->subMinutes(10))) {
            $tenant->forceFill(['last_active_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
