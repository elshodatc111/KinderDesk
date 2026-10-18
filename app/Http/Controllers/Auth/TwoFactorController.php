<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Security\TwoFactor;
use App\Services\Telegram\TelegramPoller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TwoFactorController extends Controller
{
    public function __construct(private TwoFactor $tf)
    {
    }

    private function home(Request $request): string
    {
        return $request->user()->is_super_admin ? route('admin.dashboard') : route('app.dashboard');
    }

    private function passed(Request $request): bool
    {
        return $request->session()->get('2fa_ok') === $request->user()->id;
    }

    /* ---------- Ulash / boshqarish ---------- */

    public function setup(Request $request)
    {
        $user = $request->user();
        if (! $this->tf->applies($user) || ! $this->tf->enabled()) {
            return redirect($this->home($request));
        }
        // Ulangan bo'lsa — faqat tasdiqdan o'tgan foydalanuvchi boshqara oladi
        if ($user->tg_chat_id && ! $this->passed($request)) {
            return redirect()->route('twofa.challenge');
        }
        $link = ($user->tg_chat_id && ! $request->boolean('change')) ? null : $this->tf->startLink($user);

        return view('auth.twofa-setup', [
            'user' => $user, 'link' => $link, 'bot' => $this->tf->bot(), 'left' => $this->tf->recoveryLeft($user),
            'devices' => DB::table('trusted_devices')->where('user_id', $user->id)->where('expires_at', '>', now())->count(),
            'forced' => ! $this->passed($request),
        ]);
    }

    public function status(Request $request, TelegramPoller $poller)
    {
        $user = $request->user();
        $poller->touchPlatform();
        $user->refresh();

        return response()->json(['linked' => (bool) $user->tg_chat_id, 'username' => $user->tg_username]);
    }

    public function confirm(Request $request)
    {
        $user = $request->user()->refresh();
        abort_unless($this->tf->applies($user) && $user->tg_chat_id, 403);
        $request->session()->put('2fa_ok', $user->id);
        if ($this->tf->recoveryLeft($user) === 0 || $request->boolean('new_codes')) {
            return view('auth.twofa-codes', ['codes' => $this->tf->makeRecoveryCodes($user), 'next' => $this->home($request)]);
        }

        return redirect()->intended($this->home($request));
    }

    public function forgetDevices(Request $request)
    {
        abort_unless($this->passed($request), 403);
        DB::table('trusted_devices')->where('user_id', $request->user()->id)->delete();

        return back()->with('success', "Eslab qolingan qurilmalar o'chirildi");
    }

    /* ---------- Kirishda kod ---------- */

    public function challenge(Request $request)
    {
        $user = $request->user();
        if (! $this->tf->enabled() || ! $this->tf->applies($user) || $this->passed($request)) {
            return redirect()->intended($this->home($request));
        }
        if (! $user->tg_chat_id) {
            return redirect()->route('twofa.setup');
        }
        $error = null;
        if (! $this->tf->pending($request)) {
            if (! $this->tf->sendCode($request, $user)) {
                $error = "Kod Telegramga yuborilmadi. Zaxira koddan foydalaning yoki keyinroq urinib ko'ring.";
            }
        }

        return view('auth.twofa-challenge', ['error' => $error, 'left' => $this->tf->recoveryLeft($user)]);
    }

    public function verify(Request $request)
    {
        $data = $request->validate(['code' => 'required|string|max:20']);
        $user = $request->user();
        $res = $this->tf->verify($request, $user, $data['code']);
        if ($res === 'ok') {
            $this->tf->pass($request, $user, $request->boolean('remember_device'));
            \App\Models\AuditLog::create(['user_id' => $user->id, 'tenant_id' => $user->tenant_id, 'action' => 'auth.2fa', 'ip' => $request->ip()]);

            return redirect()->intended($this->home($request));
        }
        $msg = match ($res) {
            'locked' => "3 marta xato kiritildi. Yangi kod so'rang.",
            'expired' => "Kod muddati o'tgan. Yangi kod so'rang.",
            default => "Kod noto'g'ri",
        };

        return back()->withErrors(['code' => $msg]);
    }

    public function resend(Request $request)
    {
        $user = $request->user();
        $p = $this->tf->pending($request);
        if ($p && time() - ($p['sent'] ?? 0) < 30 && $p['tries'] < TwoFactor::MAX_TRIES) {
            return back()->withErrors(['code' => "Yangi kodni 30 soniyadan keyin so'rang"]);
        }
        $request->session()->forget('2fa.pending');

        return $this->tf->sendCode($request, $user)
            ? back()->with('success', 'Yangi kod yuborildi')
            : back()->withErrors(['code' => 'Kod yuborilmadi']);
    }
}
