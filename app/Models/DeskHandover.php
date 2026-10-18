<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kassadan direktor moliyasiga pul topshirish (direktor tasdiqlaydi). */
class DeskHandover extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'account', 'amount', 'note', 'status', 'reject_reason', 'created_by', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'reviewed_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withoutGlobalScopes();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withoutGlobalScopes();
    }
}
