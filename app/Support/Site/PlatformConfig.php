<?php

namespace App\Support\Site;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Crypt;

/**
 * Ulanish kalitlari (Payme, Eskiz) super admin sozlamalaridan o'qiladi (bazada shifrlangan).
 * `.env` faqat zaxira: bazada qiymat bo'lmasa, eski `.env` qiymati ishlaydi.
 */
class PlatformConfig
{
    /** Sozlama kaliti => [config yo'li, maxfiymi] */
    public const MAP = [
        'conn.sms_driver' => ['services.sms.driver', false],
        'conn.eskiz_email' => ['services.eskiz.email', false],
        'conn.eskiz_password' => ['services.eskiz.password', true],
        'conn.eskiz_from' => ['services.eskiz.from', false],
        'conn.eskiz_callback_url' => ['services.eskiz.callback_url', false],
        'conn.payme_merchant_id' => ['services.payme.merchant_id', false],
        'conn.payme_key' => ['services.payme.key', true],
        'conn.payme_test_key' => ['services.payme.test_key', true],
        'conn.payme_test_mode' => ['services.payme.test_mode', false],
        'conn.payme_allowed_ips' => ['services.payme.allowed_ips', false],
    ];

    public static function apply(): void
    {
        try {
            foreach (self::MAP as $key => [$path, $secret]) {
                $raw = PlatformSetting::get($key);
                if ($raw === null || $raw === '') {
                    continue;
                }
                $val = $secret ? self::decrypt($raw) : $raw;
                if ($val === null) {
                    continue;
                }
                if ($key === 'conn.payme_test_mode') {
                    $val = $val === '1';
                } elseif ($key === 'conn.payme_allowed_ips') {
                    $val = array_values(array_filter(array_map('trim', explode(',', $val))));
                }
                config([$path => $val]);
            }
        } catch (\Throwable) {
            // Jadval hali yo'q (migratsiya) — .env qiymatlari ishlaydi
        }
    }

    public static function put(string $key, ?string $value): void
    {
        $secret = self::MAP[$key][1] ?? false;
        PlatformSetting::put($key, $value === null || $value === '' ? null : ($secret ? Crypt::encryptString($value) : $value));
    }

    public static function has(string $key): bool
    {
        $raw = PlatformSetting::get($key);
        if ($raw === null || $raw === '') {
            return filled(config(self::MAP[$key][0]));
        }

        return true;
    }

    private static function decrypt(string $raw): ?string
    {
        try {
            return Crypt::decryptString($raw);
        } catch (\Throwable) {
            return null;
        }
    }
}
