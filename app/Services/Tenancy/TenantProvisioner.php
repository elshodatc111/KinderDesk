<?php

namespace App\Services\Tenancy;

use App\Models\AuditLog;
use App\Models\Edition;
use App\Models\Role;
use App\Models\SmsEventRule;
use App\Models\SmsTemplate;
use App\Models\Tenant;
use App\Models\TrialRegistry;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\Realtime;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

class TenantProvisioner
{
    public function __construct(private NotificationService $notify, private TenantContext $context)
    {
    }

    /** Telefon/email/STIR bilan sinov muddatidan avval foydalanilganmi? */
    public function trialUsedBefore(string $phone, ?string $email, ?string $taxId): ?string
    {
        if (TrialRegistry::exists_('phone', normalize_phone($phone))) {
            return 'telefon raqam';
        }
        if (TrialRegistry::exists_('email', normalize_email($email))) {
            return 'email manzil';
        }
        if ($taxId && TrialRegistry::exists_('tax_id', preg_replace('/\D+/', '', $taxId))) {
            return 'STIR (soliq raqami)';
        }

        return null;
    }

    /**
     * Yangi bog'cha: 14 kunlik sinov bilan.
     *
     * @param  array{name:string, owner_name:string, phone:string, password:string, email?:?string, edition:string, tax_id?:?string, region?:?string, city?:?string}  $data
     *
     * @throws TrialAlreadyUsed
     */
    public function register(array $data): Tenant
    {
        $phone = normalize_phone($data['phone']);
        $email = $data['email'] ?? null;
        $taxId = isset($data['tax_id']) ? preg_replace('/\D+/', '', (string) $data['tax_id']) : null;

        if ($used = $this->trialUsedBefore($phone, $email, $taxId)) {
            throw new TrialAlreadyUsed(__('Bu :used bilan sinov muddatidan avval foydalanilgan. Davom etish uchun obuna xarid qiling yoki mavjud hisobingizga kiring.', ['used' => __($used)]));
        }

        $edition = Edition::where('key', $data['edition'])->where('is_active', true)->firstOrFail();

        $tenant = DB::transaction(function () use ($data, $phone, $email, $taxId, $edition) {
            $trialDays = config('kinderdesk.trial_days');
            $trialEnd = now()->addDays($trialDays)->endOfDay();

            $tenant = Tenant::create([
                'name' => $data['name'],
                'edition_id' => $edition->id,
                'phone' => $phone,
                'email' => $email,
                'tax_id' => $taxId ?: null,
                'region' => $data['region'] ?? null,
                'city' => $data['city'] ?? null,
                'status' => 'active',
                'trial_started_at' => now(),
                'trial_ends_at' => $trialEnd,
                'access_expires_at' => $trialEnd,
                'balance' => 0,
            ]);

            $this->seedRoles($tenant);

            $owner = User::create([
                'tenant_id' => $tenant->id,
                'role_id' => Role::where('tenant_id', $tenant->id)->where('key', 'director')->value('id'),
                'name' => $data['owner_name'],
                'phone' => $phone,
                'email' => $email,
                'password' => $data['password'],
                'is_owner' => true,
                'status' => 'active',
                'phone_verified_at' => now(),
                'locale' => app()->getLocale(),
            ]);
            $tenant->update(['owner_user_id' => $owner->id]);

            $this->seedSms($tenant);

            TrialRegistry::remember('phone', $phone);
            TrialRegistry::remember('email', normalize_email($email));
            TrialRegistry::remember('tax_id', $taxId);

            AuditLog::record('tenant.registered', $tenant, ['edition' => $edition->key], $tenant->id);

            return $tenant;
        });

        $owner = $tenant->owner;
        $this->notify->toUser(
            $owner,
            "KinderDesk'ga xush kelibsiz!",
            __(":n kunlik bepul sinov boshlandi. Sozlamalardan bog'changizga moslang.", ['n' => config('kinderdesk.trial_days')]),
            route('app.settings.index'),
            'success',
            'sparkles'
        );
        $this->notify->toSuperAdmins("Yangi bog'cha: {$tenant->name}", $tenant->edition->name.' nashri, '.format_phone($tenant->phone), route('admin.tenants.show', $tenant), 'info', 'building');
        Realtime::platform('tenant.registered', ['tenant' => $tenant->name]);

        return $tenant;
    }

    /**
     * Super admin yaratadigan bog'cha: demo ma'lumotsiz, «sinov reestri»ga yozilmaydi (telefon, email, STIR).
     *
     * @param  array{name:string, owner_name:string, phone:string, password:string, email?:?string, edition_id:int, lifetime?:bool, months?:int, tax_id?:?string}  $data
     */
    public function createByAdmin(array $data, User $admin): Tenant
    {
        $phone = normalize_phone($data['phone']);
        $edition = Edition::findOrFail($data['edition_id']);
        $lifetime = (bool) ($data['lifetime'] ?? false);
        $months = (int) ($data['months'] ?? 0);

        $tenant = DB::transaction(function () use ($data, $phone, $edition, $lifetime, $admin) {
            $tenant = Tenant::create([
                'name' => $data['name'], 'edition_id' => $edition->id, 'phone' => $phone, 'email' => $data['email'] ?? null,
                'tax_id' => ! empty($data['tax_id']) ? preg_replace('/\D+/', '', (string) $data['tax_id']) : null,
                'status' => 'active', 'lifetime' => $lifetime, 'created_by_admin' => true, 'balance' => 0,
            ]);
            $this->seedRoles($tenant);
            $owner = User::create([
                'tenant_id' => $tenant->id,
                'role_id' => Role::where('tenant_id', $tenant->id)->where('key', 'director')->value('id'),
                'name' => $data['owner_name'], 'phone' => $phone, 'email' => $data['email'] ?? null, 'password' => $data['password'],
                'is_owner' => true, 'status' => 'active', 'phone_verified_at' => now(), 'locale' => app()->getLocale(),
            ]);
            $tenant->update(['owner_user_id' => $owner->id]);
            $this->seedSms($tenant);
            AuditLog::record('tenant.created_by_admin', $tenant, ['edition' => $edition->key, 'lifetime' => $lifetime, 'by' => $admin->id], $tenant->id);

            return $tenant;
        });

        if (! $lifetime && $months > 0) {
            app(\App\Services\Billing\SubscriptionService::class)->grant($tenant, $edition, $months, $admin->id);
        }
        $tenant->refresh();
        $this->notify->toUser($tenant->owner, "KinderDesk'ga xush kelibsiz!", __("Bog'changiz tayyor. Sozlamalardan moslang."), route('app.settings.index'), 'success', 'sparkles');

        return $tenant;
    }

    /** Tizim rollarini yaratadi. */
    public function seedRoles(Tenant $tenant): void
    {
        foreach (config('kinderdesk.system_roles') as $key => $def) {
            Role::create([
                'tenant_id' => $tenant->id,
                'key' => $key,
                'name' => $def['name'],
                'permissions' => $def['permissions'],
                'is_system' => true,
            ]);
        }
    }

    /** Standart SMS shablonlari (qoralama) va o'chirilgan hodisa qoidalari. */
    public function seedSms(Tenant $tenant): void
    {
        $this->context->run($tenant, function () use ($tenant) {
            foreach (config('kinderdesk.sms_events') as $key => $def) {
                if ($def['scope'] !== 'tenant') {
                    continue;
                }
                $tpl = SmsTemplate::create([
                    'tenant_id' => $tenant->id,
                    'event_key' => $key,
                    'name' => $def['label'],
                    'body' => $def['body'],
                    'status' => 'draft',
                ]);
                SmsEventRule::create([
                    'tenant_id' => $tenant->id,
                    'event_key' => $key,
                    'enabled' => false,
                    'template_id' => $tpl->id,
                ]);
            }
        });
    }
}
