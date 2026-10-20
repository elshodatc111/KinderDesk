<?php

namespace App\Services\Auth;

use App\Models\OtpCode;
use App\Services\Sms\SmsService;

class OtpService
{
    public function __construct(private SmsService $sms)
    {
    }

    /**
     * Tasdiqlash kodini SMS orqali yuboradi.
     *
     * @return array{ok: bool, message: string, wait?: int, dev_code?: string}
     */
    public function send(string $phone, string $purpose, ?string $ip = null): array
    {
        $issued = $this->issue($phone, $purpose, $ip, 'sms');
        if (! $issued['ok']) {
            return $issued;
        }
        $phone = $issued['phone'];
        $code = $issued['code'];

        $log = $this->sms->sendPlatform($phone, 'otp', ['kod' => $code]);
        if ($log && $log->status === 'failed') {
            return ['ok' => false, 'message' => "SMS yuborib bo'lmadi. Birozdan keyin qayta urinib ko'ring"];
        }

        $result = ['ok' => true, 'message' => 'Tasdiqlash kodi yuborildi'];
        // Faqat mahalliy sinov rejimida (SMS yuborilmaganda) kodni ko'rsatamiz
        if (config('services.sms.driver') === 'log' && config('app.debug')) {
            $result['dev_code'] = $code;
        }

        return $result;
    }

    /**
     * Yangi kod yaratadi va saqlaydi (yuborishni chaqiruvchi bajaradi: SMS yoki Telegram).
     * Eski kodlar bekor qilinadi. Kanallar uchun umumiy tezlik cheklovi.
     *
     * @return array{ok: true, phone: string, code: string}|array{ok: false, message: string, wait?: int}
     */
    public function issue(string $phone, string $purpose, ?string $ip = null, string $channel = 'sms'): array
    {
        $phone = normalize_phone($phone);
        if (! $phone) {
            return ['ok' => false, 'message' => "Telefon raqam noto'g'ri. Format: 90 123 45 67"];
        }

        $cfg = config('kinderdesk.otp');

        // Telegram — SMS kelmaganda zaxira kanal: SMS'dan keyin qisqa kutish yetarli
        $gap = $channel === 'sms' ? $cfg['resend_seconds'] : ($cfg['telegram_after_seconds'] ?? 15);
        $last = OtpCode::where('phone', $phone)->where('purpose', $purpose)->latest('id')->first();
        if ($last && $last->created_at->gt(now()->subSeconds($gap))) {
            $wait = $gap - (int) $last->created_at->diffInSeconds(now());

            return ['ok' => false, 'message' => __('Kodni qayta yuborish uchun :n soniya kuting', ['n' => max(1, $wait)]), 'wait' => max(1, $wait)];
        }

        // Soatiga 6 tadan ko'p kod yubormaymiz (xarajat va suiiste'moldan himoya; SMS va Telegram birga hisoblanadi)
        $hourly = OtpCode::where('phone', $phone)->where('created_at', '>', now()->subHour())->count();
        if ($hourly >= 6) {
            return ['ok' => false, 'message' => "Juda ko'p urinish. Bir soatdan keyin qayta urinib ko'ring"];
        }

        $code = str_pad((string) random_int(0, 10 ** $cfg['length'] - 1), $cfg['length'], '0', STR_PAD_LEFT);

        // Eski kodlarni bekor qilamiz
        OtpCode::where('phone', $phone)->where('purpose', $purpose)->whereNull('used_at')->update(['used_at' => now()]);

        OtpCode::create([
            'phone' => $phone,
            'purpose' => $purpose,
            'channel' => $channel,
            'code_hash' => $this->hash($phone, $purpose, $code),
            'expires_at' => now()->addMinutes($cfg['ttl_minutes']),
            'ip' => $ip,
        ]);

        return ['ok' => true, 'phone' => $phone, 'code' => $code];
    }

    /** Kodni tekshiradi. To'g'ri bo'lsa kod ishlatilgan deb belgilanadi. */
    public function verify(string $phone, string $purpose, string $code): bool
    {
        $phone = normalize_phone($phone);
        if (! $phone) {
            return false;
        }

        $otp = OtpCode::where('phone', $phone)->where('purpose', $purpose)
            ->whereNull('used_at')->where('expires_at', '>', now())->latest('id')->first();
        if (! $otp) {
            return false;
        }

        if ($otp->attempts >= config('kinderdesk.otp.max_attempts')) {
            $otp->update(['used_at' => now()]);

            return false;
        }

        $otp->increment('attempts');

        if (! hash_equals($otp->code_hash, $this->hash($phone, $purpose, trim($code)))) {
            return false;
        }

        $otp->update(['used_at' => now()]);

        return true;
    }

    private function hash(string $phone, string $purpose, string $code): string
    {
        return hash_hmac('sha256', "$phone|$purpose|$code", config('app.key'));
    }
}
