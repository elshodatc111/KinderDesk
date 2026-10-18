<?php

namespace App\Services\Security;

use App\Models\PlatformSetting;
use App\Models\TelegramBot;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Telegram-kod orqali ikki bosqichli tasdiq (direktor va super admin).
 * Faqat platforma boti sozlangan va yoqilgan bo'lsagina majburiy — shunda mahalliy ishlab chiqish va testlar buzilmaydi.
 */
class TwoFactor
{
    public const CODE_TTL = 300;
    public const MAX_TRIES = 3;
    public const TRUST_DAYS = 30;

    public function bot(): ?TelegramBot
    {
        return TelegramBot::platform();
    }

    public function enabled(): bool
    {
        if (! config('kinderdesk.enforce_security') && ! $this->forced) {
            return false;
        }
        $bot = $this->bot();

        return $bot && $bot->status === 'ok' && filled($bot->token) && PlatformSetting::get('tg.2fa', '1') === '1';
    }

    public bool $forced = false;

    public function applies(User $user): bool
    {
        return $user->is_super_admin || $user->is_owner;
    }

    /** Middleware: kerak bo'lsa yo'naltirish qaytaradi. */
    public function gate(Request $request, User $user)
    {
        if (! $this->enabled() || ! $this->applies($user)) {
            return null;
        }
        if ($request->session()->get('2fa_ok') === $user->id) {
            return null;
        }
        if ($this->trustedByCookie($request, $user)) {
            $request->session()->put('2fa_ok', $user->id);

            return null;
        }
        if (! $request->expectsJson() && $request->isMethod('GET')) {
            $request->session()->put('url.intended', $request->fullUrl());
        }
        if ($request->expectsJson()) {
            abort(403, '2FA kerak');
        }

        return redirect()->route($user->tg_chat_id ? 'twofa.challenge' : 'twofa.setup');
    }

    /* ---------- Ulash ---------- */

    /** Start-havola yaratadi (15 daqiqa amal qiladi). */
    public function startLink(User $user): ?string
    {
        $bot = $this->bot();
        if (! $bot || ! $bot->username) {
            return null;
        }
        $token = Str::random(20);
        $user->forceFill(['tg_link_hash' => hash('sha256', $token), 'tg_link_expires' => now()->addMinutes(15)])->saveQuietly();

        return $bot->link('u_'.$token);
    }

    /** Platforma boti /start u_<token> olganda. */
    public function bindFromBot(string $token, int $chatId, ?string $username): ?User
    {
        $user = User::where('tg_link_hash', hash('sha256', $token))->where('tg_link_expires', '>', now())->first();
        if (! $user) {
            return null;
        }
        // Bitta Telegram — bitta foydalanuvchi
        User::where('tg_chat_id', $chatId)->where('id', '!=', $user->id)->update(['tg_chat_id' => null, 'tg_username' => null]);
        $user->forceFill(['tg_chat_id' => $chatId, 'tg_username' => $username, 'tg_linked_at' => now(), 'tg_link_hash' => null, 'tg_link_expires' => null])->saveQuietly();

        return $user;
    }

    public function unlink(User $user): void
    {
        $user->forceFill(['tg_chat_id' => null, 'tg_username' => null, 'tg_linked_at' => null, 'tg_link_hash' => null, 'tg_link_expires' => null, 'tg_recovery' => null])->saveQuietly();
        DB::table('trusted_devices')->where('user_id', $user->id)->delete();
    }

    /** 8 ta bir martalik zaxira kod; faqat heshi saqlanadi, ochiq ko'rinishi bir marta qaytariladi. */
    public function makeRecoveryCodes(User $user): array
    {
        $codes = collect(range(1, 8))->map(fn () => strtoupper(Str::random(4).'-'.Str::random(4)))->all();
        $user->forceFill(['tg_recovery' => json_encode(array_map(fn ($c) => hash('sha256', $c), $codes))])->saveQuietly();

        return $codes;
    }

    public function recoveryLeft(User $user): int
    {
        return count(json_decode((string) $user->tg_recovery, true) ?: []);
    }

    /* ---------- Kod yuborish va tekshirish ---------- */

    public function sendCode(Request $request, User $user): bool
    {
        $bot = $this->bot();
        if (! $bot || ! $user->tg_chat_id) {
            return false;
        }
        $code = (string) random_int(100000, 999999);
        $r = $bot->api()->send($user->tg_chat_id, "🔐 KinderDesk kirish kodi: {$code}\nKod 5 daqiqa amal qiladi. Uni hech kimga bermang.", ['protect_content' => true]);
        if (! ($r['ok'] ?? false)) {
            return false;
        }
        $request->session()->put('2fa.pending', ['hash' => hash_hmac('sha256', $code, config('app.key')), 'exp' => time() + self::CODE_TTL, 'tries' => 0, 'sent' => time()]);

        return true;
    }

    public function pending(Request $request): ?array
    {
        $p = $request->session()->get('2fa.pending');

        return $p && $p['exp'] > time() ? $p : null;
    }

    /** @return string ok|wrong|locked|expired */
    public function verify(Request $request, User $user, string $input): string
    {
        $input = trim($input);
        // Zaxira kod
        if (preg_match('/^[A-Za-z0-9]{4}-[A-Za-z0-9]{4}$/', $input)) {
            $list = json_decode((string) $user->tg_recovery, true) ?: [];
            $h = hash('sha256', strtoupper($input));
            if (($i = array_search($h, $list, true)) !== false) {
                unset($list[$i]);
                $user->forceFill(['tg_recovery' => json_encode(array_values($list))])->saveQuietly();

                return 'ok';
            }

            return 'wrong';
        }
        $p = $this->pending($request);
        if (! $p) {
            return 'expired';
        }
        if ($p['tries'] >= self::MAX_TRIES) {
            return 'locked';
        }
        if (hash_equals($p['hash'], hash_hmac('sha256', preg_replace('/\D/', '', $input), config('app.key')))) {
            $request->session()->forget('2fa.pending');

            return 'ok';
        }
        $p['tries']++;
        $request->session()->put('2fa.pending', $p);

        return $p['tries'] >= self::MAX_TRIES ? 'locked' : 'wrong';
    }

    public function pass(Request $request, User $user, bool $remember): void
    {
        $request->session()->put('2fa_ok', $user->id);
        $request->session()->forget('2fa.pending');
        if ($remember) {
            $token = Str::random(40);
            DB::table('trusted_devices')->insert([
                'user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 250),
                'expires_at' => now()->addDays(self::TRUST_DAYS), 'created_at' => now(), 'updated_at' => now(),
            ]);
            Cookie::queue(Cookie::make('kd_td', $user->id.'|'.$token, self::TRUST_DAYS * 1440, null, null, $request->isSecure(), true, false, 'lax'));
        }
    }

    private function trustedByCookie(Request $request, User $user): bool
    {
        $raw = (string) $request->cookie('kd_td');
        if (! str_contains($raw, '|')) {
            return false;
        }
        [$uid, $token] = explode('|', $raw, 2);

        return (int) $uid === $user->id && DB::table('trusted_devices')->where('user_id', $user->id)
            ->where('token_hash', hash('sha256', $token))->where('expires_at', '>', now())->exists();
    }
}
