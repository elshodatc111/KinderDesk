<?php

namespace App\Services\Billing;

use App\Models\AuditLog;
use App\Models\Edition;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\NotificationService;
use App\Support\Realtime;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    public function __construct(private WalletService $wallet, private NotificationService $notify)
    {
    }

    /**
     * Balansdan to'lab obuna sotib olish.
     *
     * @throws InsufficientBalance
     * @throws \DomainException
     */
    public function purchase(Tenant $tenant, Plan $plan, string $source = 'balance', ?int $by = null): Subscription
    {
        if (! $plan->is_active || ! $plan->edition->is_active) {
            throw new \DomainException('Bu tarif hozir sotuvda emas');
        }

        return DB::transaction(function () use ($tenant, $plan, $source, $by) {
            $locked = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);

            [$start, $stacked] = $this->nextStart($locked);

            if ($stacked && $locked->edition_id !== $plan->edition_id) {
                throw new \DomainException("Joriy obuna tugamaguncha boshqa nashr (".$plan->edition->name.") tarifini sotib olib bo'lmaydi");
            }

            $this->wallet->debit(
                $locked,
                $plan->price,
                'subscription',
                "Obuna: {$plan->edition->name} — {$plan->label()}",
                null,
                $by
            );

            $sub = Subscription::create([
                'tenant_id' => $locked->id,
                'plan_id' => $plan->id,
                'edition_id' => $plan->edition_id,
                'months' => $plan->months,
                'price' => $plan->price,
                'starts_on' => $start,
                'ends_on' => $start->copy()->addMonthsNoOverflow($plan->months)->subDay(),
                'status' => 'active',
                'source' => $source,
                'created_by' => $by,
            ]);

            if (! $stacked && $locked->edition_id !== $plan->edition_id) {
                $locked->edition_id = $plan->edition_id;
                $locked->save();
            }
            $locked->refresh()->refreshAccess();
            $locked->forceFill(['reminded_for' => null, 'expired_notified_for' => null, 'deletion_notice_sent_at' => null])->save();
            $locked->forgetCaches();

            $tenant->setRawAttributes($locked->getAttributes(), true);
            $tenant->forgetCaches();
            $tenant->unsetRelation('edition');

            AuditLog::record('subscription.purchased', $sub, ['plan' => $plan->id, 'source' => $source], $locked->id);

            DB::afterCommit(function () use ($locked, $sub) {
                Realtime::tenant($locked->id, 'subscription', ['state' => 'active', 'ends_on' => $sub->ends_on->toDateString()]);
                Realtime::platform('tenant.subscribed', ['tenant' => $locked->name]);
            });

            return $sub;
        });
    }

    /** Super admin tomonidan bepul muddat berish. */
    public function grant(Tenant $tenant, Edition $edition, int $months, ?int $by = null): Subscription
    {
        return DB::transaction(function () use ($tenant, $edition, $months, $by) {
            $locked = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);
            [$start] = $this->nextStart($locked);

            $sub = Subscription::create([
                'tenant_id' => $locked->id,
                'plan_id' => null,
                'edition_id' => $edition->id,
                'months' => $months,
                'price' => 0,
                'starts_on' => $start,
                'ends_on' => $start->copy()->addMonthsNoOverflow($months)->subDay(),
                'status' => 'active',
                'source' => 'admin_grant',
                'created_by' => $by,
            ]);
            if ($locked->edition_id !== $edition->id) {
                $locked->edition_id = $edition->id;
                $locked->save();
            }
            $locked->refresh()->refreshAccess();
            $locked->forceFill(['reminded_for' => null, 'expired_notified_for' => null, 'deletion_notice_sent_at' => null])->save();
            $tenant->setRawAttributes($locked->getAttributes(), true);
            $tenant->forgetCaches();
            $tenant->unsetRelation('edition');

            AuditLog::record('subscription.granted', $sub, ['months' => $months], $locked->id);
            DB::afterCommit(fn () => Realtime::tenant($locked->id, 'subscription', ['state' => 'active']));

            return $sub;
        });
    }

    /** Yangi obuna qaysi sanadan boshlanishi: faol obuna bo'lsa — uning oxiridan keyingi kun. */
    public function nextStart(Tenant $tenant): array
    {
        $today = Carbon::today();
        $lastEnd = $tenant->subscriptions()->where('status', 'active')->max('ends_on');

        if ($lastEnd && Carbon::parse($lastEnd)->greaterThanOrEqualTo($today)) {
            return [Carbon::parse($lastEnd)->addDay()->startOfDay(), true];
        }

        return [$today, false];
    }

    /** Oxirgi obuna bilan bir xil nashr va muddatdagi joriy narxli tarif. */
    public function renewalPlanFor(Tenant $tenant): ?Plan
    {
        $last = $tenant->subscriptions()->where('source', '!=', 'admin_grant')->orderByDesc('ends_on')->first()
            ?? $tenant->subscriptions()->orderByDesc('ends_on')->first();
        if (! $last) {
            return null;
        }

        return Plan::query()
            ->where('edition_id', $last->edition_id)
            ->where('months', $last->months)
            ->where('is_active', true)
            ->first();
    }
}
