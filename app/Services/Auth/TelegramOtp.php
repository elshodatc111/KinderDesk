<?php

namespace App\Services\Auth;

use App\Models\OtpTelegramLink;
use App\Models\OtpTelegramRequest;
use App\Models\PlatformSetting;
use App\Models\TelegramBot;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * SMS kod kelmasa — tasdiqlash kodini platforma boti orqali Telegramga yuborish.
 *
 * Oqim:
 *  1) Sayt `request()` chaqiradi. Telefon avval tasdiqlangan bo'lsa — kod darhol Telegramga ketadi.
 *     Aks holda start-havola (t.me/bot?start=o_TOKEN) qaytadi.
 *  2) Foydalanuvchi botda /start bosadi → bot «Raqamni ulashish» tugmasini ko'rsatadi (handleStart).
 *  3) Kontakt kelganda (handleContact): kontakt shu foydalanuvchining o'ziniki va kiritilgan raqamga teng
 *     bo'lsa — bog'lanish saqlanadi va kod yuboriladi. Kodni tekshirish — odatdagi OtpService::verify().
 */
class TelegramOtp
{
    public const PURPOSES = ['login', 'register', 'reset'];

    public function __construct(private OtpService $otp)
    {
    }

    public function bot(): ?TelegramBot
    {
        return TelegramBot::platform();
    }

    public function available(): bool
    {
        $bot = $this->bot();

        return $bot && $bot->status === 'ok' && filled($bot->token) && filled($bot->username)
            && PlatformSetting::get('tg.otp', '1') === '1';
    }

    /**
     * Saytdan so'rov.
     *
     * @return array{ok: bool, message: string, sent?: bool, link?: string, token?: string, wait?: int}
     */
    public function request(string $phone, string $purpose, ?string $ip): array
    {
        $phone = normalize_phone($phone);
        if (! $phone || ! in_array($purpose, self::PURPOSES, true)) {
            return ['ok' => false, 'message' => "Telefon raqam noto'g'ri. Format: 90 123 45 67"];
        }
        if (! $this->available()) {
            return ['ok' => false, 'message' => "Telegram orqali kod hozircha mavjud emas. SMS dan foydalaning."];
        }

        // Telefon avval tasdiqlangan bo'lsa — kodni darhol yuboramiz (faqat mavjud bo'lsa va yaroqli bo'lsa)
        if ($chat = $this->knownChat($phone)) {
            $res = $this->deliver($phone, $purpose, $chat, $ip);
            if ($res['ok']) {
                return ['ok' => true, 'sent' => true, 'message' => 'Kod Telegramga yuborildi. Telegramni oching.'];
            }
            if (isset($res['wait'])) {
                return ['ok' => false, 'message' => $res['message'], 'wait' => $res['wait']];
            }
            // Yuborib bo'lmadi (bot bloklangan va h.k.) — start-havola orqali qayta ulaymiz
        }

        $token = Str::random(24);
        OtpTelegramRequest::where('phone', $phone)->whereIn('state', ['pending', 'awaiting_contact'])->update(['state' => 'failed', 'note' => 'yangisi bilan almashtirildi']);
        OtpTelegramRequest::create([
            'phone' => $phone,
            'purpose' => $purpose,
            'token_hash' => hash('sha256', $token),
            'ip' => $ip,
            'expires_at' => now()->addMinutes((int) config('kinderdesk.otp.telegram_request_minutes', 10)),
        ]);

        return [
            'ok' => true,
            'sent' => false,
            'token' => $token,
            'link' => $this->bot()->link('o_'.$token),
            'message' => "Telegramni oching, «Start» va «Raqamni ulashish» tugmalarini bosing — kod o'sha yerga keladi.",
        ];
    }

    /** Saytning holat so'rovi (polling). */
    public function status(string $token): array
    {
        $req = $this->find($token, includeFinished: true);
        if (! $req) {
            return ['state' => 'expired', 'message' => "Havola eskirgan. Qayta urinib ko'ring."];
        }

        return match ($req->state) {
            'sent' => ['state' => 'sent', 'message' => 'Kod Telegramga yuborildi. Uni pastdagi maydonga kiriting.'],
            'failed' => ['state' => 'failed', 'message' => $req->note ?: "Kod yuborilmadi. Qayta urinib ko'ring."],
            'awaiting_contact' => ['state' => 'awaiting_contact', 'message' => "Botda «Raqamni ulashish» tugmasini bosing."],
            default => ['state' => 'pending', 'message' => "Telegramda botni oching va «Start» bosing."],
        };
    }

    /* ---------------- Bot tomoni ---------------- */

    /** /start o_<token> */
    public function handleStart(TelegramBot $bot, string $token, int $chat, array $from): void
    {
        $api = $bot->api();
        $req = $this->find($token);
        if (! $req) {
            $api->send($chat, "Havola eskirgan yoki ishlatilgan. Saytda «Kodni Telegramdan olish» tugmasini qayta bosing.");

            return;
        }

        // Shu raqam shu chatga avval tasdiqlangan bo'lsa — kontakt so'ramaymiz
        if ($this->knownChat($req->phone) === $chat) {
            $this->finish($bot, $req, $chat);

            return;
        }

        $req->update(['chat_id' => $chat, 'state' => 'awaiting_contact']);
        $api->send($chat, "KinderDesk tasdiqlash kodi.\n\nRaqamingiz ".$this->mask($req->phone)." ekanini tasdiqlash uchun pastdagi tugmani bosing va o'z raqamingizni ulashing.", [
            'reply_markup' => [
                'keyboard' => [[['text' => '📱 Raqamni ulashish', 'request_contact' => true]]],
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
            ],
        ]);
    }

    /** Foydalanuvchi «Raqamni ulashish» tugmasi orqali kontakt yubordi. */
    public function handleContact(TelegramBot $bot, array $message): void
    {
        $api = $bot->api();
        $chat = (int) $message['chat']['id'];
        $contact = $message['contact'] ?? [];
        $fromId = (int) ($message['from']['id'] ?? 0);

        $req = OtpTelegramRequest::where('chat_id', $chat)->where('state', 'awaiting_contact')
            ->where('expires_at', '>', now())->latest('id')->first();
        if (! $req) {
            $api->send($chat, "Faol so'rov topilmadi. Saytda «Kodni Telegramdan olish» tugmasini bosing.", ['reply_markup' => ['remove_keyboard' => true]]);

            return;
        }

        // Faqat o'zining kontakti: boshqa odamning kontaktini yuborib bo'lmaydi
        if ((int) ($contact['user_id'] ?? 0) !== $fromId || $fromId === 0) {
            $api->send($chat, "Faqat o'zingizning raqamingizni ulashing: pastdagi «Raqamni ulashish» tugmasini bosing.");

            return;
        }

        $sent = normalize_phone((string) ($contact['phone_number'] ?? ''));
        if ($sent !== $req->phone) {
            $req->update(['state' => 'failed', 'note' => "Telegramdagi raqam kiritilgan raqamga mos kelmadi."]);
            $api->send($chat, "Telegramdagi raqamingiz saytda kiritilgan raqamga mos kelmadi. Saytda to'g'ri raqamni kiriting va qayta urinib ko'ring.", ['reply_markup' => ['remove_keyboard' => true]]);

            return;
        }

        OtpTelegramLink::updateOrCreate(['phone' => $req->phone], [
            'chat_id' => $chat,
            'tg_user_id' => $fromId,
            'username' => isset($message['from']['username']) ? mb_substr((string) $message['from']['username'], 0, 64) : null,
            'verified_at' => now(),
        ]);

        $this->finish($bot, $req, $chat);
    }

    /** Foydalanuvchi so'rovdan keyin boshqa matn yozsa — yo'l ko'rsatamiz. Qaytaradi: javob berildimi. */
    public function hint(TelegramBot $bot, int $chat): bool
    {
        $req = OtpTelegramRequest::where('chat_id', $chat)->where('state', 'awaiting_contact')->where('expires_at', '>', now())->exists();
        if ($req) {
            $bot->api()->send($chat, "Kod olish uchun pastdagi «📱 Raqamni ulashish» tugmasini bosing.");
        }

        return $req;
    }

    /* ---------------- Ichki ---------------- */

    private function finish(TelegramBot $bot, OtpTelegramRequest $req, int $chat): void
    {
        $res = $this->deliver($req->phone, $req->purpose, $chat, $req->ip);
        $remove = ['reply_markup' => ['remove_keyboard' => true]];
        if ($res['ok']) {
            $req->update(['state' => 'sent', 'note' => null]);

            return; // kod xabari deliver() ichida yuborilgan
        }
        $req->update(['state' => 'failed', 'note' => mb_substr($res['message'], 0, 250)]);
        $bot->api()->send($chat, $res['message'], $remove);
    }

    /**
     * Kodni yaratadi va chatga yuboradi. Maqsadga mos hisob holatini ham tekshiradi.
     *
     * @return array{ok: bool, message: string, wait?: int}
     */
    private function deliver(string $phone, string $purpose, int $chat, ?string $ip): array
    {
        $bot = $this->bot();
        if (! $bot) {
            return ['ok' => false, 'message' => 'Telegram bot sozlanmagan.'];
        }

        $exists = User::where('phone', $phone)->where('status', 'active')->exists();
        if ($purpose === 'register' && User::where('phone', $phone)->exists()) {
            return ['ok' => false, 'message' => "Bu raqam allaqachon ro'yxatdan o'tgan. Saytdagi «Kirish» sahifasidan foydalaning."];
        }
        if (in_array($purpose, ['login', 'reset'], true) && ! $exists) {
            return ['ok' => false, 'message' => "Bu raqam bilan faol hisob topilmadi. Avval ro'yxatdan o'ting."];
        }

        $issued = $this->otp->issue($phone, $purpose, $ip, 'telegram');
        if (! $issued['ok']) {
            return $issued;
        }

        $r = $bot->api()->send($chat, "🔐 KinderDesk tasdiqlash kodi: {$issued['code']}\n\nKod ".config('kinderdesk.otp.ttl_minutes')." daqiqa amal qiladi. Uni hech kimga bermang.", [
            'protect_content' => true,
            'reply_markup' => ['remove_keyboard' => true],
        ]);
        if (! ($r['ok'] ?? false)) {
            // Chat yaroqsiz (bot bloklangan) — bog'lanishni o'chiramiz, keyingi safar qayta tasdiqlanadi
            OtpTelegramLink::where('phone', $phone)->where('chat_id', $chat)->delete();

            return ['ok' => false, 'message' => "Kodni Telegramga yuborib bo'lmadi. Botni qayta ishga tushiring (/start) yoki SMS dan foydalaning."];
        }

        return ['ok' => true, 'message' => 'Kod yuborildi'];
    }

    /** Telefonga tasdiqlangan chat: avval Telegram-OTP bog'lanishi, so'ng 2FA ulangan hisob. */
    private function knownChat(string $phone): ?int
    {
        $days = (int) config('kinderdesk.otp.telegram_link_days', 180);
        $link = OtpTelegramLink::where('phone', $phone)->where('verified_at', '>', now()->subDays($days))->first();
        if ($link) {
            return (int) $link->chat_id;
        }
        $user = User::where('phone', $phone)->whereNotNull('tg_chat_id')->first();

        return $user ? (int) $user->tg_chat_id : null;
    }

    private function find(string $token, bool $includeFinished = false): ?OtpTelegramRequest
    {
        $q = OtpTelegramRequest::where('token_hash', hash('sha256', $token))->where('expires_at', '>', now());
        if (! $includeFinished) {
            $q->whereIn('state', ['pending', 'awaiting_contact']);
        }

        return $q->first();
    }

    private function mask(string $phone): string
    {
        return '+'.substr($phone, 0, 3).' ** *** ** '.substr($phone, -2);
    }
}
