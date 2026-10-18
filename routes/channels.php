<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('tenant.{id}', fn ($user, $id) => (int) $user->tenant_id === (int) $id && $user->isActive());
Broadcast::channel('platform', fn ($user) => (bool) $user->is_super_admin && $user->isActive());
Broadcast::channel('user.{id}', fn ($user, $id) => (int) $user->id === (int) $id);
