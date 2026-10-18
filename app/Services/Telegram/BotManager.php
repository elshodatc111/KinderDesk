<?php

namespace App\Services\Telegram;

use App\Models\PlatformSetting;
use App\Models\TelegramBot;

/** Bot ulash/sozlash: token tekshiruvi (getMe), profil (nom/tavsif), webhook yoki polling rejimi. */
class BotManager
{
    public function mode(): string
    {
        return PlatformSetting::get('tg.mode', 'polling') === 'webhook' ? 'webhook' : 'polling';
    }

    /** Tokenni tekshiradi va botga bog'laydi. @throws \DomainException */
    public function connect(TelegramBot $bot, string $token): TelegramBot
    {
        $token = trim($token);
        if (! preg_match('/^\d{6,}:[A-Za-z0-9_-]{30,}$/', $token)) {
            throw new \DomainException("Token formati noto'g'ri. BotFather bergan to'liq tokenni kiriting.");
        }
        $r = (new TelegramApi($token))->getMe();
        if (! ($r['ok'] ?? false)) {
            throw new \DomainException("Token qabul qilinmadi: ".($r['description'] ?? 'Telegramga ulanib bo\'lmadi'));
        }
        // Bitta token — bitta bog'cha
        $botId = (int) $r['result']['id'];
        if (TelegramBot::where('bot_id', $botId)->where('id', '!=', $bot->id ?? 0)->exists()) {
            throw new \DomainException("Bu bot boshqa bog'chaga ulangan.");
        }
        $bot->fill([
            'token' => $token, 'bot_id' => $botId, 'username' => $r['result']['username'] ?? null,
            'name' => $bot->name ?: ($r['result']['first_name'] ?? null), 'status' => 'ok', 'last_error' => null, 'last_ok_at' => now(),
        ])->save();
        $this->deliveryFor($bot);

        return $bot;
    }

    /** @return array<string,string> xatolar (bo'sh = hammasi joyida) */
    public function applyProfile(TelegramBot $bot): array
    {
        $errors = [];
        $api = $bot->api();
        foreach ([['setMyName', ['name' => (string) $bot->name]], ['setMyDescription', ['description' => (string) $bot->description]], ['setMyShortDescription', ['short_description' => (string) $bot->short_description]]] as [$method, $params]) {
            if (($params[array_key_first($params)]) === '') {
                continue;
            }
            $r = $api->call($method, $params);
            if (! ($r['ok'] ?? false)) {
                $errors[$method] = (string) ($r['description'] ?? 'xatolik');
            }
        }

        return $errors;
    }

    /** Joriy rejimga mos yetkazish: webhook o'rnatish yoki o'chirish. */
    public function deliveryFor(TelegramBot $bot): ?string
    {
        if (! $bot->token) {
            return null;
        }
        if ($this->mode() === 'webhook') {
            $r = $bot->api()->call('setWebhook', [
                'url' => route('tg.webhook', ['bot' => $bot->id]), 'secret_token' => $bot->secret,
                'allowed_updates' => ['message', 'callback_query'], 'drop_pending_updates' => false,
            ]);
        } else {
            $r = $bot->api()->call('deleteWebhook', ['drop_pending_updates' => false]);
        }
        if (! ($r['ok'] ?? false)) {
            $err = (string) ($r['description'] ?? 'xatolik');
            $bot->forceFill(['last_error' => mb_substr($err, 0, 250)])->saveQuietly();

            return $err;
        }

        return null;
    }

    public function applyModeToAll(): array
    {
        $errors = [];
        foreach (TelegramBot::whereNotNull('token')->get() as $b) {
            if ($e = $this->deliveryFor($b)) {
                $errors[$b->username ?: ('#'.$b->id)] = $e;
            }
        }

        return $errors;
    }
}
