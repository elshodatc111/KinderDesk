<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\TelegramBot;
use App\Services\Telegram\TelegramUpdates;
use Illuminate\Http\Request;

class TelegramController extends Controller
{
    public function __invoke(Request $request, int $bot, TelegramUpdates $updates)
    {
        $b = TelegramBot::find($bot);
        $secret = (string) $request->header('X-Telegram-Bot-Api-Secret-Token');
        // Maxfiy sarlavha mos kelmasa — so'rov Telegramdan emas
        if (! $b || ! $b->secret || ! hash_equals($b->secret, $secret)) {
            abort(403);
        }
        $update = $request->json()->all();
        if ($update) {
            $updates->handle($b, $update);
        }

        return response()->json(['ok' => true]);
    }
}
