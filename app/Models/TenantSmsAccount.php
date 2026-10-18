<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSmsAccount extends Model
{
    protected $fillable = [
        'tenant_id', 'email', 'password', 'sender', 'token', 'token_expires_at', 'balance', 'last_checked_at',
        'last_error', 'is_active',
    ];

    protected $hidden = ['password', 'token'];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
