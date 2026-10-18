<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Models\TelegramBot;
use App\Models\TgOutbox;
use App\Services\Telegram\BotManager;
use App\Services\Telegram\TelegramPoller;
use Illuminate\Http\Request;

/** Super admin: platforma boti (2 bosqichli tasdiq), ishlash rejimi (polling / webhook), holat. */
class TelegramController extends Controller
{
    public function index(BotManager $manager, TelegramPoller $poller)
    {
        $bot = TelegramBot::platform();
        $age = $poller->heartbeatAge();

        return view('admin.telegram', [
            'bot' => $bot, 'mode' => $manager->mode(), 'twofa' => PlatformSetting::get('tg.2fa', '1') === '1',
            'heartbeatAge' => $age, 'workerAlive' => $age !== null && $age < 30,
            'tenantBots' => TelegramBot::where('is_platform', false)->count(),
            'tenantBotsErr' => TelegramBot::where('is_platform', false)->where('status', 'error')->count(),
            'pending' => TgOutbox::where('status', 'pending')->count(), 'failed' => TgOutbox::where('status', 'failed')->where('created_at', '>', now()->subDay())->count(),
            'webhookUrl' => $bot ? route('tg.webhook', ['bot' => $bot->id]) : route('tg.webhook', ['bot' => 1]),
        ]);
    }

    public function update(Request $request, BotManager $manager)
    {
        $data = $request->validate(['token' => 'nullable|string|max:200', 'mode' => 'required|in:polling,webhook', 'twofa' => 'nullable|boolean']);
        PlatformSetting::put('tg.2fa', $request->boolean('twofa') ? '1' : '0');
        $modeChanged = PlatformSetting::get('tg.mode', 'polling') !== $data['mode'];
        PlatformSetting::put('tg.mode', $data['mode']);

        $bot = TelegramBot::platform() ?? new TelegramBot(['is_platform' => true]);
        try {
            if (filled($data['token'] ?? null)) {
                $manager->connect($bot, $data['token']);
            } elseif ($bot->exists && $modeChanged) {
                $manager->deliveryFor($bot);
            }
            if ($modeChanged) {
                $errors = $manager->applyModeToAll();
                if ($errors) {
                    return back()->with('error', 'Ba\'zi botlarda rejim o\'rnatilmadi: '.implode('; ', array_map(fn ($k, $v) => "$k — $v", array_keys($errors), $errors)));
                }
            }
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Saqlandi');
    }

    /** Barcha botlarga joriy rejimni qayta qo'llash (masalan, yangi serverga ko'chganda webhook uchun). */
    public function apply(BotManager $manager)
    {
        $errors = $manager->applyModeToAll();

        return $errors
            ? back()->with('error', implode('; ', array_map(fn ($k, $v) => "$k — $v", array_keys($errors), $errors)))
            : back()->with('success', 'Rejim barcha botlarga qo\'llandi');
    }

    public function forgetToken()
    {
        TelegramBot::platform()?->delete();

        return back()->with('success', 'Platforma boti olib tashlandi. 2 bosqichli tasdiq o\'chdi.');
    }
}
