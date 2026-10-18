<?php

namespace App\Services\Billing;

use App\Models\AuditLog;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Models\Topup;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\Realtime;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TopupService
{
    public function __construct(private WalletService $wallet, private NotificationService $notify)
    {
    }

    public function minAmount(): int
    {
        return max(1000, (int) PlatformSetting::get('payment.min_topup', '10000'));
    }

    public function paymeEnabled(): bool
    {
        $cfg = config('services.payme');

        return filled($cfg['merchant_id']) && filled($cfg['test_mode'] ? $cfg['test_key'] : $cfg['key']);
    }

    /**
     * Karta raqamiga o'tkazma + chek yuborish. Super adminga navbat bo'lib tushadi.
     *
     * @throws \DomainException
     */
    public function createManual(Tenant $tenant, User $user, int $amount, UploadedFile $receipt, ?string $paidOn, ?string $senderCard, ?string $note): Topup
    {
        if ($amount < $this->minAmount()) {
            throw new \DomainException(__("Eng kam to'lov summasi: :sum so'm", ['sum' => money($this->minAmount())]));
        }

        $hash = hash_file('sha256', $receipt->getRealPath());
        $dup = Topup::query()->where('receipt_hash', $hash)->whereIn('status', ['pending', 'approved'])->exists();
        if ($dup) {
            throw new \DomainException("Bu chek rasmi avval yuborilgan. Boshqa to'lov chekini yuklang");
        }

        $path = $receipt->store('receipts/'.$tenant->id, 'local');

        $topup = Topup::create([
            'tenant_id' => $tenant->id,
            'method' => 'manual',
            'amount' => $amount,
            'status' => 'pending',
            'receipt_path' => $path,
            'receipt_hash' => $hash,
            'paid_on' => $paidOn,
            'sender_card' => $senderCard,
            'note' => $note,
            'created_by' => $user->id,
        ]);

        AuditLog::record('topup.submitted', $topup, ['amount' => $amount], $tenant->id);

        $this->notify->toSuperAdmins(
            "Yangi to'lov cheki: {$tenant->name}",
            money($amount)." so'm — tasdiqlashni kutmoqda",
            route('admin.topups.index'),
            'warning',
            'receipt'
        );
        Realtime::platform('topup.pending', ['tenant' => $tenant->name, 'amount' => $amount, 'id' => $topup->id]);

        return $topup;
    }

    /** Super admin chekni tasdiqlaydi: to'liq summa bog'chaning balansiga tushadi. */
    public function approve(Topup $topup, User $admin): Topup
    {
        $done = DB::transaction(function () use ($topup, $admin) {
            $locked = Topup::query()->lockForUpdate()->findOrFail($topup->id);
            if ($locked->status !== 'pending') {
                throw new \DomainException("Bu to'lov allaqachon ko'rib chiqilgan");
            }

            $tenant = Tenant::findOrFail($locked->tenant_id);
            $this->wallet->credit($tenant, $locked->amount, 'topup', "Balans to'ldirildi (chek #{$locked->id})", $locked, $admin->id);

            $locked->update(['status' => 'approved', 'reviewed_by' => $admin->id, 'reviewed_at' => now()]);
            AuditLog::record('topup.approved', $locked, ['amount' => $locked->amount], $tenant->id);

            return $locked;
        });

        $this->afterCredit($done, __("Balansingiz :sum so'mga to'ldirildi", ['sum' => money($done->amount)]));

        return $done;
    }

    public function reject(Topup $topup, User $admin, string $reason): Topup
    {
        return DB::transaction(function () use ($topup, $admin, $reason) {
            $locked = Topup::query()->lockForUpdate()->findOrFail($topup->id);
            if ($locked->status !== 'pending') {
                throw new \DomainException("Bu to'lov allaqachon ko'rib chiqilgan");
            }
            $locked->update([
                'status' => 'rejected', 'reviewed_by' => $admin->id, 'reviewed_at' => now(),
                'reject_reason' => $reason,
            ]);
            AuditLog::record('topup.rejected', $locked, ['reason' => $reason], $locked->tenant_id);

            $tenant = Tenant::findOrFail($locked->tenant_id);
            $this->notify->toOwner($tenant, "To'lov cheki rad etildi", $reason, route('app.subscription.index'), 'danger', 'x', 'subscription.manage');
            Realtime::tenant($tenant->id, 'topup', ['status' => 'rejected']);

            return $locked;
        });
    }

    /** Payme orqali to'lov uchun buyurtma yaratadi va to'lov havolasini qaytaradi. */
    public function createPayme(Tenant $tenant, User $user, int $amount): array
    {
        if (! $this->paymeEnabled()) {
            throw new \DomainException("Payme hozircha ulanmagan. Karta raqami orqali to'lang");
        }
        if ($amount < $this->minAmount()) {
            throw new \DomainException(__("Eng kam to'lov summasi: :sum so'm", ['sum' => money($this->minAmount())]));
        }

        $topup = Topup::create([
            'tenant_id' => $tenant->id, 'method' => 'payme', 'amount' => $amount,
            'status' => 'pending', 'created_by' => $user->id,
        ]);

        return [$topup, $this->checkoutUrl($topup)];
    }

    public function checkoutUrl(Topup $topup): string
    {
        $cfg = config('services.payme');
        $params = implode(';', [
            'm='.$cfg['merchant_id'],
            'ac.topup_id='.$topup->id,
            'a='.($topup->amount * 100),
            'l='.app()->getLocale(),
            'c='.route('app.subscription.index'),
            'cr=UZS',
        ]);
        $base = $cfg['test_mode'] ? $cfg['test_checkout_url'] : $cfg['checkout_url'];

        return rtrim($base, '/').'/'.base64_encode($params);
    }

    /** Payme to'lovi bajarilganda chaqiriladi (PerformTransaction). */
    public function completePayme(Topup $topup): void
    {
        $done = DB::transaction(function () use ($topup) {
            $locked = Topup::query()->lockForUpdate()->findOrFail($topup->id);
            if ($locked->status === 'approved') {
                return null;
            }
            $tenant = Tenant::findOrFail($locked->tenant_id);
            $this->wallet->credit($tenant, $locked->amount, 'topup', "Payme orqali to'ldirildi (#{$locked->id})", $locked, null);
            $locked->update(['status' => 'approved', 'reviewed_at' => now()]);
            AuditLog::record('topup.payme_completed', $locked, ['amount' => $locked->amount], $tenant->id);

            return $locked;
        });

        if ($done) {
            $this->afterCredit($done, __("Payme orqali :sum so'm balansga tushdi", ['sum' => money($done->amount)]));
        }
    }

    /** Payme to'lovi bekor qilinganda (CancelTransaction, state=2 dan). Balans yetarli bo'lmasa istisno. */
    public function reversePayme(Topup $topup): void
    {
        DB::transaction(function () use ($topup) {
            $locked = Topup::query()->lockForUpdate()->findOrFail($topup->id);
            $tenant = Tenant::findOrFail($locked->tenant_id);
            $this->wallet->debit($tenant, $locked->amount, 'refund', "Payme to'lovi qaytarildi (#{$locked->id})", $locked, null);
            $locked->update(['status' => 'canceled']);
            AuditLog::record('topup.payme_reversed', $locked, ['amount' => $locked->amount], $tenant->id);
        });
    }

    private function afterCredit(Topup $topup, string $message): void
    {
        $tenant = Tenant::findOrFail($topup->tenant_id);
        $this->notify->toOwner($tenant, $message, "Hozirgi balans: ".money($tenant->balance)." so'm", route('app.subscription.index'), 'success', 'wallet', 'subscription.manage');
        Realtime::tenant($tenant->id, 'topup', ['status' => 'approved', 'balance' => $tenant->balance]);
        Realtime::platform('topup.done', ['tenant' => $tenant->name]);
    }

    public function receiptUrl(Topup $topup): ?string
    {
        return $topup->receipt_path && Storage::disk('local')->exists($topup->receipt_path) ? $topup->receipt_path : null;
    }
}
