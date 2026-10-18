<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Edition;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Topup;
use App\Services\Billing\InsufficientBalance;
use App\Services\Billing\SubscriptionService;
use App\Services\Billing\TopupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SubscriptionController extends Controller
{
    public function __construct(private SubscriptionService $subs, private TopupService $topups)
    {
    }

    public function index()
    {
        $tenant = tenant();

        return view('app.subscription', [
            'editions' => Edition::where('is_active', true)->orderBy('sort')->with(['plans' => fn ($q) => $q->where('is_active', true)->orderBy('months')])->get(),
            'features' => config('kinderdesk.features'),
            'subscription' => $tenant->currentSubscription(),
            'history' => $tenant->subscriptions()->latest('id')->limit(10)->get(),
            'topups' => Topup::where('tenant_id', $tenant->id)->latest('id')->limit(10)->get(),
            'transactions' => $tenant->walletTransactions()->latest('id')->limit(15)->get(),
            'card' => [
                'number' => PlatformSetting::get('payment.card_number'),
                'holder' => PlatformSetting::get('payment.card_holder'),
                'bank' => PlatformSetting::get('payment.card_bank'),
                'note' => kd_desc(PlatformSetting::get('payment.note')),
            ],
            'paymeEnabled' => $this->topups->paymeEnabled(),
            'minTopup' => $this->topups->minAmount(),
        ]);
    }

    public function buy(Request $request)
    {
        $data = $request->validate(['plan_id' => 'required|exists:plans,id']);
        $plan = Plan::with('edition')->findOrFail($data['plan_id']);
        $tenant = tenant();

        try {
            $sub = $this->subs->purchase($tenant, $plan, 'balance', $request->user()->id);
        } catch (InsufficientBalance $e) {
            $need = $plan->price - $tenant->balance;

            return back()->with('error', __("Balans yetarli emas. Yana :sum so'm kerak — avval balansni to'ldiring.", ['sum' => money($need)]));
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Obuna faollashtirildi: ".$sub->ends_on->format('d.m.Y')." gacha");
    }

    public function topupManual(Request $request)
    {
        $data = $request->validate([
            'amount' => 'required|integer|min:1000',
            'receipt' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:6144',
            'paid_on' => 'nullable|date|before_or_equal:today',
            'sender_card' => 'nullable|string|max:30',
            'note' => 'nullable|string|max:300',
        ], ['receipt.required' => 'To\'lov chekini yuklang', 'receipt.mimes' => 'Chek rasm (JPG, PNG) yoki PDF bo\'lishi kerak', 'receipt.max' => 'Fayl hajmi 6 MB dan oshmasin']);

        try {
            $this->topups->createManual(tenant(), $request->user(), (int) $data['amount'], $request->file('receipt'), $data['paid_on'] ?? null, $data['sender_card'] ?? null, $data['note'] ?? null);
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', "Chek yuborildi. Tasdiqlangach to'liq summa balansingizga tushadi.");
    }

    public function topupPayme(Request $request)
    {
        $data = $request->validate(['amount' => 'required|integer|min:1000']);
        try {
            [, $url] = $this->topups->createPayme(tenant(), $request->user(), (int) $data['amount']);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->away($url);
    }

    /** O'z chekini ko'rish (faqat shu bog'cha). */
    public function receipt(Topup $topup)
    {
        abort_unless($topup->tenant_id === tenant()->id, 404);
        $path = $this->topups->receiptUrl($topup);
        abort_unless($path, 404);

        return Storage::disk('local')->response($path);
    }
}
