<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'tenant_id', 'role_id', 'name', 'phone', 'email', 'password', 'is_super_admin', 'is_owner',
        'status', 'phone_verified_at', 'locale', 'last_login_at',
        'tg_chat_id', 'tg_username', 'tg_linked_at', 'tg_link_hash', 'tg_link_expires', 'tg_recovery',
    ];

    protected $hidden = ['password', 'remember_token', 'tg_recovery', 'tg_link_hash'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'is_owner' => 'boolean',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'tg_linked_at' => 'datetime',
            'tg_link_expires' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class)->withoutGlobalScopes();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** Ruxsatni tekshiradi. Bog'cha egasi (direktor) hamma narsaga ega. */
    public function hasPermission(string $permission): bool
    {
        if ($this->is_super_admin || ! $this->tenant_id) {
            return false;
        }
        if ($this->is_owner) {
            return true;
        }

        return (bool) $this->role?->allows($permission);
    }

    /**
     * Bildirishnomalar: faqat o'z ruxsatiga mos keladiganlar (perm bo'sh yoki ruxsati bor).
     */
    public function visibleNotifications()
    {
        $q = $this->notifications();
        if ($this->is_super_admin || $this->is_owner) {
            return $q;
        }
        $allowed = array_values(array_filter(array_keys(config('kinderdesk.permissions')), fn ($p) => $this->hasPermission($p)));

        return $q->where(fn ($w) => $w->whereNull('data->perm')->orWhereIn('data->perm', $allowed));
    }

    public function unreadVisibleNotificationCount(): int
    {
        return $this->visibleNotifications()->whereNull('read_at')->count();
    }

    /** Foydalanuvchining barcha ruxsatlari (ko'rsatish uchun). */
    public function permissionList(): array
    {
        if ($this->is_owner) {
            return array_keys(config('kinderdesk.permissions'));
        }
        $perms = $this->role?->permissions ?? [];

        return in_array('*', $perms, true) ? array_keys(config('kinderdesk.permissions')) : $perms;
    }

    public function roleName(): string
    {
        if ($this->is_super_admin) {
            return 'Super admin';
        }
        if ($this->is_owner) {
            return 'Direktor';
        }

        return $this->role?->name ?? 'Xodim';
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name));
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $second = mb_substr($parts[1] ?? '', 0, 1);

        return mb_strtoupper($first.$second);
    }

    /** Bildirishnomalar telefon raqami orqali SMS'ga yo'naltiriladi. */
    public function routeNotificationForSms(): string
    {
        return $this->phone;
    }
}
