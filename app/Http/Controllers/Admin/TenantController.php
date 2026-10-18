<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Edition;
use App\Models\Tenant;
use App\Models\TenantFeatureOverride;
use App\Services\Billing\SubscriptionService;
use App\Services\Billing\WalletService;
use App\Services\NotificationService;
use App\Services\Tenancy\TenantDeleter;
use App\Services\Tenancy\TenantProvisioner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class TenantController extends Controller
{
    public function index(Request $request)
    {
        $q = Tenant::with('edition')->latest('id');
        if ($s = trim((string) $request->query('q'))) {
            $digits = preg_replace('/\D/', '', $s);
            $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")
                ->when($digits, fn ($x) => $x->orWhere('phone', 'like', "%$digits%")->orWhere('tax_id', 'like', "%$digits%")));
        }
        if ($e = $request->query('edition')) {
            $q->whereHas('edition', fn ($x) => $x->where('key', $e));
        }
        $all = $q->get();
        if ($st = $request->query('state')) {
            $all = $all->filter(fn ($t) => $t->accessState() === $st || ($st === 'delete' && $t->accessState() === 'read_only' && $t->daysSinceExpiry() >= config('kinderdesk.delete_after_days')))->values();
        }

        return view('admin.tenants.index', ['tenants' => $all, 'editions' => Edition::orderBy('sort')->get()]);
    }

    public function create()
    {
        return view('admin.tenants.create', ['editions' => Edition::orderBy('sort')->get()]);
    }

    public function store(Request $request, TenantProvisioner $provisioner)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120', 'owner_name' => 'required|string|max:120', 'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:120', 'password' => 'required|string|min:8|max:100', 'edition_id' => 'required|exists:editions,id',
            'lifetime' => 'nullable|boolean', 'months' => 'nullable|integer|min:0|max:36', 'tax_id' => 'nullable|string|max:20',
        ]);
        $phone = normalize_phone($data['phone']);
        if (\App\Models\User::where('phone', $phone)->exists()) {
            return back()->withErrors(['phone' => "Bu telefon raqam bilan foydalanuvchi allaqachon bor"])->withInput();
        }
        $data['lifetime'] = $request->boolean('lifetime');
        if (! $data['lifetime'] && (int) ($data['months'] ?? 0) < 1) {
            return back()->withErrors(['months' => "Boshlang'ich muddatni kiriting (yoki «Umrbod»ni tanlang)"])->withInput();
        }
        $tenant = $provisioner->createByAdmin($data, $request->user());

        return redirect()->route('admin.tenants.show', $tenant)->with('success', "Bog'cha yaratildi (demo ma'lumotsiz)");
    }

    public function readonly(Request $request, Tenant $tenant)
    {
        $data = $request->validate(['reason' => 'required|string|min:3|max:300', 'until' => 'nullable|date|after:today']);
        $tenant->update(['manual_readonly_reason' => trim($data['reason']), 'manual_readonly_until' => ! empty($data['until']) ? \Illuminate\Support\Carbon::parse($data['until'])->endOfDay() : null,
            'manual_readonly_at' => now(), 'manual_readonly_by' => $request->user()->id]);
        AuditLog::record('tenant.manual_readonly_on', $tenant, ['reason' => $data['reason'], 'until' => $data['until'] ?? null], $tenant->id);
        app(NotificationService::class)->toOwner($tenant, __("Tizim vaqtincha faqat ko'rish rejimida"), $data['reason'], route('app.dashboard'), 'danger', 'lock');

        return back()->with('success', "Qo'lda «faqat ko'rish» yoqildi");
    }

    public function readonlyOff(Request $request, Tenant $tenant)
    {
        $tenant->update(['manual_readonly_reason' => null, 'manual_readonly_until' => null, 'manual_readonly_at' => null, 'manual_readonly_by' => null]);
        AuditLog::record('tenant.manual_readonly_off', $tenant, ['reason' => (string) $request->input('reason', '')], $tenant->id);
        app(NotificationService::class)->toOwner($tenant, __("Faqat ko'rish rejimi olib tashlandi"), '', route('app.dashboard'), 'success', 'check');

        return back()->with('success', "Qo'lda «faqat ko'rish» olib tashlandi");
    }

    public function lifetime(Request $request, Tenant $tenant)
    {
        $on = ! $tenant->lifetime;
        $request->validate(['reason' => 'required|string|min:3|max:300']);
        $tenant->update(['lifetime' => $on]);
        $tenant->forgetCaches();
        AuditLog::record($on ? 'tenant.lifetime_on' : 'tenant.lifetime_off', $tenant, ['reason' => $request->input('reason')], $tenant->id);

        return back()->with('success', $on ? "Umrbod rejim yoqildi" : "Umrbod rejim o'chirildi");
    }

    public function show(Tenant $tenant)
    {
        $tenant->load('edition', 'owner');

        return view('admin.tenants.show', [
            'tenant' => $tenant,
            'editions' => Edition::orderBy('sort')->get(),
            'features' => config('kinderdesk.features'),
            'overrides' => $tenant->featureOverrides()->pluck('enabled', 'feature_key')->all(),
            'subscriptions' => $tenant->subscriptions()->latest('id')->limit(8)->get(),
            'transactions' => $tenant->walletTransactions()->latest('id')->limit(10)->get(),
            'counts' => [
                'users' => $tenant->users()->count(),
                'kids' => \DB::table('kids')->where('tenant_id', $tenant->id)->count(),
                'employees' => \DB::table('employees')->where('tenant_id', $tenant->id)->count(),
                'groups' => \DB::table('groups')->where('tenant_id', $tenant->id)->count(),
            ],
        ]);
    }

    public function grant(Request $request, Tenant $tenant, SubscriptionService $subs)
    {
        $data = $request->validate(['edition_id' => 'required|exists:editions,id', 'months' => 'required|integer|min:1|max:36']);
        $subs->grant($tenant, Edition::findOrFail($data['edition_id']), (int) $data['months'], $request->user()->id);

        return back()->with('success', __(':months oy bepul muddat berildi', ['months' => $data['months']]));
    }

    public function edition(Request $request, Tenant $tenant)
    {
        $data = $request->validate(['edition_id' => 'required|exists:editions,id']);
        $tenant->update(['edition_id' => $data['edition_id']]);
        $tenant->forgetCaches();
        AuditLog::record('tenant.edition_changed', $tenant, ['edition' => $data['edition_id']], $tenant->id);

        return back()->with('success', 'Nashr o\'zgartirildi');
    }

    public function features(Request $request, Tenant $tenant)
    {
        $state = $request->input('f', []); // feature => default|on|off
        foreach (config('kinderdesk.features') as $key => $def) {
            if (! empty($def['core'])) {
                continue;
            }
            $v = $state[$key] ?? 'default';
            if ($v === 'default') {
                TenantFeatureOverride::where('tenant_id', $tenant->id)->where('feature_key', $key)->delete();
            } else {
                TenantFeatureOverride::updateOrCreate(['tenant_id' => $tenant->id, 'feature_key' => $key], ['enabled' => $v === 'on']);
            }
        }
        $tenant->forgetCaches();
        AuditLog::record('tenant.features_changed', $tenant, [], $tenant->id);

        return back()->with('success', 'Imkoniyatlar saqlandi');
    }

    public function suspend(Request $request, Tenant $tenant)
    {
        $suspend = $tenant->status !== 'suspended';
        $tenant->update(['status' => $suspend ? 'suspended' : 'active']);
        AuditLog::record($suspend ? 'tenant.suspended' : 'tenant.unsuspended', $tenant, [], $tenant->id);

        return back()->with('success', $suspend ? 'Bog\'cha bloklandi' : 'Blok olib tashlandi');
    }

    public function wallet(Request $request, Tenant $tenant, WalletService $wallet)
    {
        $data = $request->validate(['amount' => 'required|integer|not_in:0', 'note' => 'required|string|max:200']);
        try {
            $amount = (int) $data['amount'];
            $amount > 0
                ? $wallet->credit($tenant, $amount, 'adjustment', $data['note'], null, $request->user()->id)
                : $wallet->debit($tenant, abs($amount), 'adjustment', $data['note'], null, $request->user()->id);
        } catch (\Throwable $e) {
            return back()->with('error', 'Bajarilmadi: '.$e->getMessage());
        }
        AuditLog::record('tenant.wallet_adjusted', $tenant, ['amount' => $data['amount'], 'note' => $data['note']], $tenant->id);

        return back()->with('success', 'Balans yangilandi');
    }

    public function message(Request $request, Tenant $tenant, NotificationService $notify)
    {
        $data = $request->validate(['title' => 'required|string|max:120', 'body' => 'nullable|string|max:400']);
        $notify->toOwner($tenant, $data['title'], $data['body'] ?? '', route('app.dashboard'), 'info', 'bell');

        return back()->with('success', 'Xabar direktorga yuborildi');
    }

    /** Direktor telefonini yo'qotsa: Telegram ulanishini tozalash (keyingi kirishda qayta ulaydi). */
    public function twofaReset(Tenant $tenant, \App\Services\Security\TwoFactor $tf)
    {
        abort_unless($tenant->owner, 404);
        $tf->unlink($tenant->owner);
        AuditLog::record('tenant.2fa_reset', $tenant, [], $tenant->id);

        return back()->with('success', 'Direktorning Telegram ulanishi tiklandi');
    }

    public function impersonate(Request $request, Tenant $tenant)
    {
        abort_unless($tenant->owner, 404);
        AuditLog::record('tenant.impersonated', $tenant, [], $tenant->id);
        $adminId = $request->user()->id;
        Auth::login($tenant->owner);
        $request->session()->regenerate();
        $request->session()->put('impersonator_id', $adminId);

        return redirect()->route('app.dashboard');
    }

    public function destroy(Request $request, Tenant $tenant, TenantDeleter $deleter)
    {
        $data = $request->validate([
            'confirm_name' => 'required|string',
            'password' => 'required|string',
            'reason' => 'nullable|string|max:300',
        ]);
        if (trim($data['confirm_name']) !== $tenant->name) {
            return back()->withErrors(['confirm_name' => "Bog'cha nomi mos kelmadi"]);
        }
        if (! Hash::check($data['password'], $request->user()->password)) {
            return back()->withErrors(['password' => "Parol noto'g'ri"]);
        }
        $deleter->delete($tenant, $request->user(), $data['reason'] ?? null);

        return redirect()->route('admin.tenants.index')->with('success', __('«:tenant» butunlay o\'chirildi', ['tenant' => $tenant->name]));
    }
}
