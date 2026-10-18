<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Topup extends Model
{
    protected $fillable = [
        'tenant_id', 'method', 'amount', 'status', 'receipt_path', 'receipt_hash', 'paid_on', 'sender_card',
        'note', 'reviewed_by', 'reviewed_at', 'reject_reason', 'created_by',
    ];

    protected function casts(): array
    {
        return ['paid_on' => 'date:Y-m-d', 'reviewed_at' => 'datetime', 'amount' => 'integer'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function paymeTransactions(): HasMany
    {
        return $this->hasMany(PaymeTransaction::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'Kutilmoqda',
            'approved' => 'Tasdiqlandi',
            'rejected' => 'Rad etildi',
            'canceled' => 'Bekor qilindi',
            default => $this->status,
        };
    }
}
