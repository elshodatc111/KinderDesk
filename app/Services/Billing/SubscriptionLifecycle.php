<?php

namespace App\Services\Billing;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\NotificationService;
use App\Services\Sms\SmsService;
use App\Support\Realtime;
use Carbon\Carbon;

/**
 * Kunlik avtomatik jarayon (php artisan subscriptions:process):
 *  1) muddati tugagan obunalarni yopadi (avtomatik yechish YO'Q: direktor tarifni o'zi tanlab, balansdan sotib oladi)
 *  2) tugashiga 3 kun qolganda direktorga SMS + ilova ichida xabar
 *  3) tugagan bo'lsa "faqat ko'rish" haqida xabar
 *  4) tugaganiga 90 kun bo'lganda super adminga o'chirish haqida xabar (o'chirish faqat qo'lda)
 */
class SubscriptionLifecycle
{
    public function __construct(
        private SubscriptionService $subscriptions,
        private SmsService $sms,
        private NotificationService $notify,
    ) {
    }

    /** @return array<string,int> */
    public function run(): array
    {
        $stats = ['renewed' => 0, 'expired' => 0, 'reminded' => 0, 'expired_notified' => 0, 'deletion_flagged' => 0];

        // 1) Muddati o'tgan obunalar
        Subscription::query()->where('status', 'active')->whereDate('ends_on', '<', Carbon::today())->get()
            ->each(function (Subscription $sub) use (&$stats) {
                $sub->update(['status' => 'expired']);
                $stats['expired']++;
            });

        Tenant::query()->with('edition')->where('status', 'active')->where('lifetime', false)->chunkById(100, function ($tenants) use (&$stats) {
            foreach ($tenants as $tenant) {
                $this->processTenant($tenant, $stats);
            }
        });

        return $stats;
    }

    private function processTenant(Tenant $tenant, array &$stats): void
    {
        $tenant->refreshAccess();
        $expiresAt = $tenant->access_expires_at;
        if (! $expiresAt) {
            return;
        }

        $expired = now()->greaterThan($expiresAt);

        // 2) Tugashiga N kun qolganda eslatma
        if (! $expired) {
            $days = $tenant->daysLeft();
            $alreadyReminded = $tenant->reminded_for && $tenant->reminded_for->equalTo($expiresAt);
            if ($days <= config('kinderdesk.reminder_days') && ! $alreadyReminded) {
                $this->sms->sendPlatform($tenant->phone, 'subscription_expiring', [
                    'bogcha' => $tenant->name, 'sana' => $expiresAt->format('d.m.Y'),
                ]);
                $this->notify->toOwner(
                    $tenant,
                    $days <= 0 ? __('Obuna bugun tugaydi') : __('Obuna tugashiga :n kun qoldi', ['n' => $days]),
                    $tenant->currentSubscription()
                        ? __('Obuna :date kuni tugaydi.', ['date' => $expiresAt->format('d.m.Y')])
                        : __('Sinov muddati :date kuni tugaydi.', ['date' => $expiresAt->format('d.m.Y')]),
                    route('app.subscription.index'),
                    'warning',
                    'clock',
                    'subscription.manage'
                );
                $tenant->forceFill(['reminded_for' => $expiresAt])->save();
                $stats['reminded']++;
            }

            return;
        }

        // 3) Tugagan: bir marta xabar
        $notified = $tenant->expired_notified_for && $tenant->expired_notified_for->equalTo($expiresAt);
        if (! $notified) {
            $this->sms->sendPlatform($tenant->phone, 'subscription_expired', ['bogcha' => $tenant->name]);
            $this->notify->toOwner(
                $tenant,
                "Obuna muddati tugadi",
                "Tizim faqat ko'rish rejimida. Davom etish uchun balansni to'ldirib, obunani faollashtiring.",
                route('app.subscription.index'),
                'danger',
                'lock',
                'subscription.manage'
            );
            Realtime::tenant($tenant->id, 'subscription', ['state' => 'read_only']);
            $tenant->forceFill(['expired_notified_for' => $expiresAt])->save();
            $stats['expired_notified']++;
        }

        // 5) 90 kundan keyin super adminga xabar
        if (! $tenant->deletion_notice_sent_at
            && $tenant->daysSinceExpiry() >= config('kinderdesk.delete_after_days')) {
            $this->notify->toSuperAdmins(
                "O'chirishga tayyor: {$tenant->name}",
                __("Obuna tugaganiga :n kundan oshdi. Ma'lumotlarni qaytarib bo'lmas qilib o'chirishingiz mumkin.", ['n' => config('kinderdesk.delete_after_days')]),
                route('admin.tenants.show', $tenant),
                'danger',
                'trash'
            );
            Realtime::platform('tenant.deletion_due', ['tenant' => $tenant->name, 'id' => $tenant->id]);
            $tenant->forceFill(['deletion_notice_sent_at' => now()])->save();
            $stats['deletion_flagged']++;
        }
    }
}
