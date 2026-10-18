<?php

namespace App\Models;

use App\Support\Settings\SettingsRegistry;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Tenant extends Model
{
    protected $fillable = [
        'public_key', 'name', 'edition_id', 'owner_user_id', 'phone', 'email', 'tax_id', 'region', 'city',
        'address', 'logo_path', 'status', 'trial_started_at', 'trial_ends_at', 'access_expires_at', 'balance',
        'auto_renew', 'reminded_for', 'expired_notified_for', 'deletion_notice_sent_at', 'last_active_at',
        'ai_disabled', 'ai_token_limit', 'ai_warned_month', 'lifetime', 'manual_readonly_reason', 'manual_readonly_until', 'manual_readonly_at', 'manual_readonly_by', 'created_by_admin',
    ];

    protected function casts(): array
    {
        return [
            'trial_started_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'access_expires_at' => 'datetime',
            'deletion_notice_sent_at' => 'datetime',
            'lifetime' => 'boolean',
            'ai_disabled' => 'boolean',
            'created_by_admin' => 'boolean',
            'manual_readonly_until' => 'datetime',
            'manual_readonly_at' => 'datetime',
            'last_active_at' => 'datetime',
            'balance' => 'integer',
            'auto_renew' => 'boolean',
            'reminded_for' => 'datetime',
            'expired_notified_for' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Tenant $t) {
            $t->public_key ??= (string) Str::uuid();
        });
    }

    /** @var array<string, mixed>|null */
    private ?array $settingsCache = null;

    /** @var array<string, bool>|null */
    private ?array $featureCache = null;

    /* ---------------- Aloqalar ---------------- */

    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class)->orderByDesc('ends_on');
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class)->orderByDesc('id');
    }

    public function topups(): HasMany
    {
        return $this->hasMany(Topup::class)->orderByDesc('id');
    }

    public function featureOverrides(): HasMany
    {
        return $this->hasMany(TenantFeatureOverride::class);
    }

    public function smsAccount(): HasOne
    {
        return $this->hasOne(TenantSmsAccount::class);
    }

    /* ---------------- Sozlamalar ---------------- */

    private function loadSettings(): array
    {
        if ($this->settingsCache === null) {
            $this->settingsCache = DB::table('tenant_settings')
                ->where('tenant_id', $this->id)
                ->pluck('value', 'key')
                ->map(fn ($v) => json_decode($v, true))
                ->all();
        }

        return $this->settingsCache;
    }

    /** Qiymat tartibi: bog'cha sozlamasi -> nashr boshlang'ich qiymati -> reyestr default. */
    public function setting(string $key, mixed $default = null): mixed
    {
        $stored = $this->loadSettings();
        if (array_key_exists($key, $stored)) {
            return $stored[$key];
        }

        $edition = $this->edition?->defaults ?? [];
        if (array_key_exists($key, $edition)) {
            return $edition[$key];
        }

        $def = SettingsRegistry::definition($key);

        return $def['default'] ?? $default;
    }

    public function setSetting(string $key, mixed $value): void
    {
        $value = SettingsRegistry::cast($key, $value);
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->id, 'key' => $key],
            ['value' => json_encode($value, JSON_UNESCAPED_UNICODE), 'updated_at' => now(), 'created_at' => now()]
        );
        $this->settingsCache = null;
    }

    /* ---------------- Xususiyatlar (feature flags) ---------------- */

    /** @return array<string, bool> */
    public function featureMap(): array
    {
        if ($this->featureCache !== null) {
            return $this->featureCache;
        }

        $all = config('kinderdesk.features');
        $edition = $this->edition?->features ?? [];
        $overrides = $this->featureOverrides()->pluck('enabled', 'feature_key')->all();

        // Bog'cha o'zi o'chirib qo'ygan (yashirgan) bo'limlar
        $hidden = (array) $this->setting('modules.disabled', []);

        $map = [];
        foreach ($all as $key => $def) {
            if (! empty($def['core'])) {
                $map[$key] = true;

                continue;
            }
            $allowed = $this->lifetime || (array_key_exists($key, $overrides) ? (bool) $overrides[$key] : in_array($key, $edition, true));
            $map[$key] = $allowed && ! in_array($key, $hidden, true);
        }

        return $this->featureCache = $map;
    }

    /** Nashr/admin ruxsat bergan imkoniyatlar (bog'cha o'zi yashirganlarini hisobga olmasdan). */
    public function allowedFeatures(): array
    {
        $edition = $this->edition?->features ?? [];
        $overrides = $this->featureOverrides()->pluck('enabled', 'feature_key')->all();
        $out = [];
        foreach (config('kinderdesk.features') as $key => $def) {
            $out[$key] = ! empty($def['core']) || $this->lifetime || (array_key_exists($key, $overrides) ? (bool) $overrides[$key] : in_array($key, $edition, true));
        }

        return $out;
    }

    public function hasFeature(string $key): bool
    {
        return $this->featureMap()[$key] ?? false;
    }

    public function forgetCaches(): void
    {
        $this->settingsCache = null;
        $this->featureCache = null;
    }

    /* ---------------- Obuna holati ---------------- */

    public function currentSubscription(): ?Subscription
    {
        $today = now()->toDateString();

        return $this->subscriptions()
            ->where('status', 'active')
            ->whereDate('starts_on', '<=', $today)
            ->whereDate('ends_on', '>=', $today)
            ->first();
    }

    /** Super admin qo'lda «faqat ko'rish» qo'ygan (sabab majburiy, muddat ixtiyoriy). Avtomatik holatdan ustun turadi. */
    public function isManualReadOnly(): bool
    {
        return filled($this->manual_readonly_reason)
            && (! $this->manual_readonly_until || now()->lessThanOrEqualTo($this->manual_readonly_until));
    }

    /** trial | active | lifetime | read_only | suspended */
    public function accessState(): string
    {
        if ($this->status === 'suspended') {
            return 'suspended';
        }
        if ($this->isManualReadOnly()) {
            return 'read_only';
        }
        if ($this->lifetime) {
            return 'lifetime';
        }
        if (! $this->access_expires_at || now()->greaterThan($this->access_expires_at)) {
            return 'read_only';
        }

        return $this->currentSubscription() ? 'active' : 'trial';
    }

    public function isReadOnly(): bool
    {
        return in_array($this->accessState(), ['read_only', 'suspended'], true);
    }

    /** Tugash lahzasini qayta hisoblaydi: max(sinov oxiri, aktiv obuna oxiri). */
    public function refreshAccess(): void
    {
        $candidates = [];
        if ($this->trial_ends_at) {
            $candidates[] = $this->trial_ends_at->copy();
        }
        $lastEnd = $this->subscriptions()->where('status', 'active')->max('ends_on');
        if ($lastEnd) {
            $candidates[] = Carbon::parse($lastEnd)->endOfDay();
        }
        $this->access_expires_at = $candidates ? collect($candidates)->max() : null;
        $this->save();
    }

    /** Qolgan to'liq kunlar (manfiy bo'lsa — tugagan). */
    public function daysLeft(): int
    {
        if (! $this->access_expires_at) {
            return -1;
        }

        return (int) now()->startOfDay()->diffInDays($this->access_expires_at->copy()->startOfDay(), false);
    }

    /** Foydalanish tugagan sanadan beri necha kun o'tdi. */
    public function daysSinceExpiry(): int
    {
        if (! $this->access_expires_at || now()->lessThanOrEqualTo($this->access_expires_at)) {
            return 0;
        }

        return (int) $this->access_expires_at->copy()->startOfDay()->diffInDays(now()->startOfDay());
    }

    public function deletionDueOn(): ?Carbon
    {
        return $this->access_expires_at?->copy()->addDays(config('kinderdesk.delete_after_days'));
    }

    public function initials(): string
    {
        return mb_strtoupper(mb_substr(trim($this->name), 0, 2));
    }
}
