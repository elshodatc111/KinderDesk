<?php

namespace App\Services\Telegram;

use App\Models\PlatformSetting;
use App\Models\TelegramBot;
use Illuminate\Support\Facades\Cache;

/** getUpdates rejimi (XAMPP / webhook yo'q joyda) va umumiy ish sikli (worker). */
class TelegramPoller
{
    public function __construct(private TelegramUpdates $updates, private BotManager $manager, private TelegramOutbox $outbox)
    {
    }

    public function pollBot(TelegramBot $bot, int $timeout = 0): int
    {
        if (! $bot->token) {
            return 0;
        }
        $lock = Cache::lock('tg.poll.'.$bot->id, 20);
        if (! $lock->get()) {
            return 0;
        }
        try {
            return $this->doPoll($bot, $timeout);
        } finally {
            $lock->release();
        }
    }

    private function doPoll(TelegramBot $bot, int $timeout): int
    {
        $bot->refresh();
        $r = $bot->api()->getUpdates($bot->last_update_id + 1, $timeout);
        if (! ($r['ok'] ?? false)) {
            $code = (int) ($r['error_code'] ?? 0);
            if ($code === 409) {
                $bot->api()->call('deleteWebhook'); // webhook qolib ketgan bo'lsa, polling uchun olib tashlaymiz
            }
            $bot->forceFill(['status' => $code === 401 ? 'error' : $bot->status, 'last_error' => mb_substr((string) ($r['description'] ?? ''), 0, 250)])->saveQuietly();

            return 0;
        }
        $n = 0;
        foreach ($r['result'] ?? [] as $u) {
            $this->updates->handle($bot, $u);
            $bot->last_update_id = max($bot->last_update_id, (int) $u['update_id']);
            $n++;
        }
        $bot->forceFill(['status' => 'ok', 'last_error' => null, 'last_ok_at' => now()])->saveQuietly();

        return $n;
    }

    /** 2 bosqichli ulash sahifasi: worker ishlamasa ham /start ni qabul qilish uchun. */
    public function touchPlatform(): void
    {
        if ($this->manager->mode() !== 'polling') {
            return;
        }
        $bot = TelegramBot::platform();
        if ($bot && $bot->token && Cache::add('tg.platform.poll', 1, 3)) {
            $this->pollBot($bot);
        }
    }

    public function heartbeat(): void
    {
        PlatformSetting::put('tg.heartbeat', (string) time());
    }

    public function heartbeatAge(): ?int
    {
        $h = (int) PlatformSetting::get('tg.heartbeat', '0');

        return $h ? time() - $h : null;
    }

    /** Bitta aylanish: yangilanishlarni olish (polling) + navbatni yuborish. */
    public function tick(int $pollTimeout = 0): array
    {
        $got = 0;
        if ($this->manager->mode() === 'polling') {
            foreach (TelegramBot::whereNotNull('token')->where('status', '!=', 'error')->get() as $bot) {
                $got += $this->pollBot($bot, $pollTimeout);
            }
        }
        $sent = $this->outbox->pump(100, 15);
        $this->heartbeat();

        return ['updates' => $got, 'sent' => $sent];
    }
}
