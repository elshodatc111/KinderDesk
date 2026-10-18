<?php

namespace App\Support;

use App\Events\PlatformActivity;
use App\Events\TenantActivity;
use App\Events\UserNotified;
use Illuminate\Support\Facades\Log;

/**
 * Jonli (real-time) xabarlar. Reverb ishlamayotgan bo'lsa ham asosiy ish buzilmaydi:
 * xato jimgina yutiladi, brauzer esa polling orqali yangilanib turadi.
 */
class Realtime
{
    public static function tenant(int $tenantId, string $kind, array $data = []): void
    {
        self::safe(fn () => event(new TenantActivity($tenantId, $kind, $data)));
    }

    public static function platform(string $kind, array $data = []): void
    {
        self::safe(fn () => event(new PlatformActivity($kind, $data)));
    }

    public static function user(int $userId, array $payload): void
    {
        self::safe(fn () => event(new UserNotified($userId, $payload)));
    }

    private static function safe(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            Log::debug('Realtime yuborilmadi: '.$e->getMessage());
        }
    }
}
