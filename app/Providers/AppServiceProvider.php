<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Sms\SmsManager;
use App\Support\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->singleton(SmsManager::class);
    }

    public function boot(): void
    {
        \App\Support\Site\PlatformConfig::apply();

        // Ruxsatlar: can('kids.view') — foydalanuvchi roli bo'yicha
        Gate::before(function (User $user, string $ability) {
            if (array_key_exists($ability, config('kinderdesk.permissions'))) {
                return $user->hasPermission($ability);
            }

            return null;
        });

        // Matnlarni avtomatik tarjima uchun o'rash (kalit — o'zbekcha matn, lang/ru.json)
        Blade::prepareStringsForCompilationUsing(fn (string $tpl) => \App\Support\I18n\BladeTranslator::transform($tpl));

        // Blade: @feature('sms') ... @endfeature
        Blade::if('feature', fn (string $key) => feature($key));

        RateLimiter::for('otp', fn (Request $r) => [
            Limit::perMinute(5)->by($r->ip()),
            Limit::perHour(20)->by($r->ip()),
        ]);
        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(8)->by($r->ip().'|'.$r->input('phone')));
        RateLimiter::for('public-lead', fn (Request $r) => Limit::perMinute(6)->by($r->ip()));
        RateLimiter::for('post-comment', fn (Request $r) => [Limit::perMinute(3)->by($r->ip()), Limit::perHour(15)->by($r->ip())]);
        RateLimiter::for('backup', fn (Request $r) => Limit::perMinute(20)->by($r->ip()));
        RateLimiter::for('webhooks', fn (Request $r) => Limit::perMinute(600)->by($r->ip()));

        Vite::prefetch(concurrency: 3);
    }
}
