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
     * Tasdiqlash kodini yuboradi.
     *
     * @return array{ok: bool, message: string, wait?: int, dev_code?: string}
     */
    public function send(string $phone, string $purpose, ?string $ip = null): array
    {
        $phone = normalize_phone($phone);
        if (! $phone) {
            return ['ok' => false, 'message' => "Telefon raqam noto'g'ri. Format: 90 123 45 67"];
        }

        $cfg = config('kinderdesk.otp');

        $last = OtpCode::where('phone', $phone)->where('purpose', $purpose)->latest('id')->first();
        if ($last && $last->created_at->gt(now()->subSeconds($cfg['resend_seconds']))) {
            $wait = $cfg['resend_seconds'] - (int) $last->created_at->diffInSeconds(now());

            return ['ok' => false, 'message' => __('Kodni qayta yuborish uchun :n soniya kuting', ['n' => $wait]), 'wait' => max(1, $wait)];
        }

        // Soatiga 6 tadan ko'p kod yubormaymiz (SMS xarajati va suiiste'moldan himoya)
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
            'code_hash' => $this->hash($phone, $purpose, $code),
            'expires_at' => now()->addMinutes($cfg['ttl_minutes']),
            'ip' => $ip,
        ]);

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
