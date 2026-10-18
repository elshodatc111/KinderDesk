<?php

namespace App\Services\Telegram;

use App\Models\Employee;
use App\Models\KidGuardian;
use App\Models\TelegramBot;
use Illuminate\Support\Str;

/** Shaxsiy start-havolalar: har bir vasiy (1/2-o'rin) va har bir xodim uchun alohida. */
class TelegramLinks
{
    public function guardianLink(TelegramBot $bot, KidGuardian $g): string
    {
        if (! $g->tg_token) {
            $g->forceFill(['tg_token' => Str::random(24)])->saveQuietly();
        }

        return $bot->link('g_'.$g->tg_token);
    }

    public function employeeLink(TelegramBot $bot, Employee $e): string
    {
        if (! $e->tg_token) {
            $e->forceFill(['tg_token' => Str::random(24)])->saveQuietly();
        }

        return $bot->link('e_'.$e->tg_token);
    }

    /** Eski havola ishlamasligi uchun yangi token (ulangan akkauntlar saqlanadi). */
    public function regenerate(KidGuardian|Employee $who): void
    {
        $who->forceFill(['tg_token' => Str::random(24)])->saveQuietly();
    }
}
