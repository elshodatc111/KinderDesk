<?php

namespace App\Services\Telegram;

use App\Models\TelegramBot;
use App\Models\TgContact;
use App\Models\TgOutbox;

/** Yuborish navbati: tezlik cheklovi, qayta urinish, bloklangan foydalanuvchini belgilash. */
class TelegramOutbox
{
    private static bool $kicked = false;

    public function queue(TelegramBot $bot, int $chatId, string $method, array $payload, ?int $postId = null, ?int $contactId = null, ?int $tenantId = null): TgOutbox
    {
        $payload['chat_id'] = $chatId;

        return TgOutbox::create([
            'tenant_id' => $tenantId ?? $bot->tenant_id, 'bot_id' => $bot->id, 'post_id' => $postId, 'contact_id' => $contactId,
            'chat_id' => $chatId, 'method' => $method, 'payload' => $payload, 'status' => 'pending', 'available_at' => now(),
        ]);
    }

    /** So'rov tugagach kichik partiyani yuboradi (worker ishlamasa ham xabarlar ketaveradi). */
    public function kick(): void
    {
        if (self::$kicked || app()->runningUnitTests()) {
            return;
        }
        self::$kicked = true;
        app()->terminating(function () {
            try {
                $this->pump(30, 8);
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    /** @return int yuborilganlar soni */
    public function pump(int $limit = 50, float $maxSeconds = 20): int
    {
        $started = microtime(true);
        $sent = 0;
        // Uzilib qolgan «yuborilmoqda» yozuvlarini qaytaramiz
        TgOutbox::where('status', 'sending')->where('updated_at', '<', now()->subMinutes(3))->update(['status' => 'pending']);
        $items = TgOutbox::where('status', 'pending')->where(fn ($q) => $q->whereNull('available_at')->orWhere('available_at', '<=', now()))
            ->orderBy('id')->limit($limit)->get();
        $bots = [];
        $gap = (int) (1_000_000 / max(1, (int) config('kinderdesk.telegram.rate_per_second')));

        foreach ($items as $item) {
            if ((microtime(true) - $started) > $maxSeconds) {
                break;
            }
            // Bir nechta worker bir xabarni ikki marta yubormasligi uchun «band qilamiz»
            if (TgOutbox::where('id', $item->id)->where('status', 'pending')->update(['status' => 'sending']) !== 1) {
                continue;
            }
            $item->setRawAttributes(array_merge($item->getAttributes(), ['status' => 'sending']), true); // xotiradagi holat bazaga mos bo'lsin
            $bot = $bots[$item->bot_id] ??= TelegramBot::find($item->bot_id);
            if (! $bot || ! $bot->token) {
                $item->update(['status' => 'failed', 'error' => 'Bot topilmadi']);

                continue;
            }
            $r = $bot->api()->call($item->method, $item->payload);
            if ($r['ok'] ?? false) {
                $res = $r['result'] ?? null;
                $ids = [];
                if (is_array($res)) {
                    $ids = isset($res['message_id']) ? [$res['message_id']] : collect($res)->pluck('message_id')->filter()->values()->all();
                }
                $item->update(['status' => 'sent', 'message_ids' => $ids, 'sent_at' => now(), 'error' => null]);
                $sent++;
            } else {
                $this->fail($item, $r);
            }
            if (! app()->runningUnitTests()) {
                usleep($gap);
            }
        }

        return $sent;
    }

    private function fail(TgOutbox $item, array $r): void
    {
        $code = (int) ($r['error_code'] ?? 0);
        $desc = mb_substr((string) ($r['description'] ?? ''), 0, 250);
        if ($code === 429) {
            $item->update(['status' => 'pending', 'available_at' => now()->addSeconds((int) ($r['retry_after'] ?? 5) + 1), 'error' => $desc]);

            return;
        }
        if (in_array($code, [403, 400], true) && $item->contact_id !== null && $code === 403) {
            // Foydalanuvchi botni bloklagan yoki chatni o'chirgan
            TgContact::withoutGlobalScopes()->where('id', $item->contact_id)->update(['blocked' => true]);
            $item->update(['status' => 'failed', 'error' => $desc, 'attempts' => $item->attempts + 1]);

            return;
        }
        $attempts = $item->attempts + 1;
        if ($code === 400 || $attempts >= 3) {
            $item->update(['status' => 'failed', 'attempts' => $attempts, 'error' => $desc]);
        } else {
            $item->update(['status' => 'pending', 'attempts' => $attempts, 'available_at' => now()->addSeconds(30 * $attempts), 'error' => $desc]);
        }
    }
}
