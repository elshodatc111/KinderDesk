<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\GeneralNotification;
use App\Support\Realtime;
use Illuminate\Support\Collection;

class NotificationService
{
    public function toUser(User $user, string $title, string $body = '', ?string $url = null, string $level = 'info', string $icon = 'bell', ?string $permission = null): void
    {
        $user->notify(new GeneralNotification($title, $body, $url, $level, $icon, $permission));
        Realtime::user($user->id, [
            'title' => $title, 'body' => $body, 'url' => $url, 'level' => $level, 'icon' => $icon,
            'unread' => $user->unreadVisibleNotificationCount(),
        ]);
    }

    /** Bog'cha egasiga (direktorga). $permission berilsa — shu ruxsati borlarga (direktor doim kiradi). */
    public function toOwner(Tenant $tenant, string $title, string $body = '', ?string $url = null, string $level = 'info', string $icon = 'bell', ?string $permission = null): void
    {
        if ($permission) {
            $this->toTenantUsers($tenant, $permission, $title, $body, $url, $level, $icon);

            return;
        }
        $owner = $tenant->owner ?? $tenant->users()->where('is_owner', true)->first();
        if ($owner) {
            $this->toUser($owner, $title, $body, $url, $level, $icon);
        }
    }

    /** Bog'chadagi ruxsati bor barcha foydalanuvchilarga. */
    public function toTenantUsers(Tenant $tenant, string $permission, string $title, string $body = '', ?string $url = null, string $level = 'info', string $icon = 'bell'): void
    {
        $this->usersWithPermission($tenant, $permission)->each(
            fn (User $u) => $this->toUser($u, $title, $body, $url, $level, $icon, $permission)
        );
    }

    public function toSuperAdmins(string $title, string $body = '', ?string $url = null, string $level = 'info', string $icon = 'bell'): void
    {
        User::where('is_super_admin', true)->where('status', 'active')->get()->each(
            fn (User $u) => $this->toUser($u, $title, $body, $url, $level, $icon)
        );
    }

    /** @return Collection<int, User> */
    public function usersWithPermission(Tenant $tenant, string $permission): Collection
    {
        return $tenant->users()->where('status', 'active')->with('role')->get()
            ->filter(fn (User $u) => $u->hasPermission($permission));
    }
}
