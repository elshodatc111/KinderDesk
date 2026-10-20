<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Edition;
use App\Models\User;
use App\Services\Auth\OtpService;
use App\Services\Auth\TelegramOtp;
use App\Services\Telegram\TelegramPoller;
use App\Services\Tenancy\TenantProvisioner;
use App\Services\Tenancy\TrialAlreadyUsed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function __construct(private OtpService $otp, private TenantProvisioner $provisioner, private TelegramOtp $tgOtp)
    {
    }

    /* ---------- Kirish ---------- */

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['phone' => 'required|string', 'password' => 'required|string']);
        $phone = normalize_phone($data['phone']);

        if (! $phone || ! Auth::attempt(['phone' => $phone, 'password' => $data['password']], $request->boolean('remember'))) {
            return back()->withInput($request->only('phone'))->withErrors(['phone' => "Telefon raqam yoki parol noto'g'ri"]);
        }

        return $this->finishLogin($request);
    }

    public function loginOtpSend(Request $request): JsonResponse
    {
        $request->validate(['phone' => 'required|string']);
        $phone = normalize_phone($request->input('phone'));
        // Raqam bazada bor-yo'qligini oshkor qilmaymiz, lekin yo'q raqamga SMS ham yubormaymiz
        $exists = $phone && User::where('phone', $phone)->where('status', 'active')->exists();
        if (! $exists) {
            return response()->json(['ok' => true, 'message' => "Agar raqam ro'yxatdan o'tgan bo'lsa, kod yuboriladi"]);
        }

        return response()->json($this->otp->send($phone, 'login', $request->ip()));
    }

    public function loginOtp(Request $request)
    {
        $data = $request->validate(['phone' => 'required|string', 'code' => 'required|string']);
        $phone = normalize_phone($data['phone']);
        $user = $phone ? User::where('phone', $phone)->first() : null;

        if (! $user || ! $this->otp->verify($phone, 'login', $data['code'])) {
            return back()->withInput($request->only('phone'))->withErrors(['code' => "Kod noto'g'ri yoki muddati o'tgan"]);
        }
        Auth::login($user, true);

        return $this->finishLogin($request);
    }

    private function finishLogin(Request $request)
    {
        $request->session()->regenerate();
        $request->session()->forget(['2fa_ok', '2fa.pending']);
        $user = $request->user();
        if (! $user->isActive()) {
            Auth::logout();

            return redirect()->route('login')->withErrors(['phone' => "Hisobingiz faol emas. Bog'cha direktoriga murojaat qiling."]);
        }
        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        if ($user->locale) {
            session(['locale' => $user->locale]);
        }

        return redirect()->intended($user->is_super_admin ? route('admin.dashboard') : route('app.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    /* ---------- Ro'yxatdan o'tish ---------- */

    public function showRegister(Request $request)
    {
        return view('auth.register', [
            'editions' => Edition::where('is_active', true)->orderBy('sort')->get(),
            'selected' => $request->query('edition', 'standard'),
        ]);
    }

    public function registerOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => 'required|string',
            'email' => 'nullable|email',
            'tax_id' => 'nullable|string|max:20',
        ]);
        $phone = normalize_phone($data['phone']);
        if ($fail = $this->registerPrecheck($phone, $data['email'] ?? null, $data['tax_id'] ?? null)) {
            return $fail;
        }

        return response()->json($this->otp->send($phone, 'register', $request->ip()));
    }

    /** Ro'yxatdan o'tish uchun kod so'raldanda umumiy tekshiruvlar (SMS va Telegram). */
    private function registerPrecheck(?string $phone, ?string $email, ?string $taxId): ?JsonResponse
    {
        if (! $phone) {
            return response()->json(['ok' => false, 'message' => "Telefon raqam noto'g'ri. Format: 90 123 45 67"], 422);
        }
        if (User::where('phone', $phone)->exists()) {
            return response()->json(['ok' => false, 'message' => "Bu raqam ro'yxatdan o'tgan. Kirish sahifasidan foydalaning."], 422);
        }
        // Xarajatni tejash uchun: sinovdan foydalangan bo'lsa kod yubormaymiz
        if ($used = $this->provisioner->trialUsedBefore($phone, $email, $taxId)) {
            return response()->json(['ok' => false, 'message' => __('Bu :used bilan sinov muddatidan avval foydalanilgan. Obuna xarid qilish uchun mavjud hisobingizga kiring.', ['used' => __($used)])], 422);
        }

        return null;
    }

    /* ---------- Telegram orqali kod (SMS kelmasa) ---------- */

    public function telegramOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => 'required|string',
            'purpose' => 'required|in:login,register,reset',
            'email' => 'nullable|email',
            'tax_id' => 'nullable|string|max:20',
        ]);
        $phone = normalize_phone($data['phone']);
        if (! $phone) {
            return response()->json(['ok' => false, 'message' => "Telefon raqam noto'g'ri. Format: 90 123 45 67"], 422);
        }
        if ($data['purpose'] === 'register' && ($fail = $this->registerPrecheck($phone, $data['email'] ?? null, $data['tax_id'] ?? null))) {
            return $fail;
        }
        // Login/reset: raqam bazada bor-yo'qligini oshkor qilmaymiz — havola baribir qaytadi,
        // hisob tekshiruvi botda (raqam egasi tasdiqlagandan keyin) bajariladi.

        return response()->json($this->tgOtp->request($phone, $data['purpose'], $request->ip()));
    }

    public function telegramOtpStatus(Request $request, TelegramPoller $poller): JsonResponse
    {
        $request->validate(['token' => 'required|string|max:64']);
        try {
            $poller->touchPlatform(); // polling rejimida worker bo'lmasa ham /start ni qabul qilish uchun
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json($this->tgOtp->status($request->input('token')));
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'owner_name' => 'required|string|max:120',
            'phone' => 'required|string',
            'email' => 'nullable|email|max:150',
            'tax_id' => 'nullable|string|max:20',
            'region' => 'nullable|string|max:80',
            'city' => 'nullable|string|max:80',
            'edition' => 'required|exists:editions,key',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'code' => 'required|string',
            'agree' => 'accepted',
        ], [
            'agree.accepted' => 'Davom etish uchun shartlarga rozilik bering',
            'password.confirmed' => 'Parollar bir xil emas',
        ]);

        $phone = normalize_phone($data['phone']);
        if (! $phone) {
            return back()->withInput()->withErrors(['phone' => "Telefon raqam noto'g'ri"]);
        }
        if (! $this->otp->verify($phone, 'register', $data['code'])) {
            return back()->withInput($request->except('password', 'password_confirmation'))->withErrors(['code' => "Kod noto'g'ri yoki muddati o'tgan"]);
        }

        try {
            $tenant = $this->provisioner->register([...$data, 'phone' => $phone]);
        } catch (TrialAlreadyUsed $e) {
            return back()->withInput($request->except('password', 'password_confirmation'))->withErrors(['phone' => $e->getMessage()]);
        }

        Auth::login($tenant->owner, true);
        $request->session()->regenerate();
        app(\App\Services\Security\ConsentService::class)->record($tenant->owner, $request);

        return redirect()->route('app.dashboard')->with('success', __("Xush kelibsiz! :n kunlik bepul sinov boshlandi.", ['n' => config('kinderdesk.trial_days')]));
    }

    /* ---------- Parolni tiklash ---------- */

    public function showForgot()
    {
        return view('auth.forgot');
    }

    public function forgotOtp(Request $request): JsonResponse
    {
        $request->validate(['phone' => 'required|string']);
        $phone = normalize_phone($request->input('phone'));
        if ($phone && User::where('phone', $phone)->where('status', 'active')->exists()) {
            return response()->json($this->otp->send($phone, 'reset', $request->ip()));
        }

        return response()->json(['ok' => true, 'message' => "Agar raqam ro'yxatdan o'tgan bo'lsa, kod yuboriladi"]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|string',
            'code' => 'required|string',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], ['password.confirmed' => 'Parollar bir xil emas']);
        $phone = normalize_phone($data['phone']);
        $user = $phone ? User::where('phone', $phone)->first() : null;

        if (! $user || ! $this->otp->verify($phone, 'reset', $data['code'])) {
            return back()->withInput($request->only('phone'))->withErrors(['code' => "Kod noto'g'ri yoki muddati o'tgan"]);
        }
        $user->update(['password' => $data['password']]);

        return redirect()->route('login')->with('success', "Parol yangilandi. Yangi parol bilan kiring.");
    }
}
