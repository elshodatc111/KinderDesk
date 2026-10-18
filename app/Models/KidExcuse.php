<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KidExcuse extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'kid_id', 'period', 'days', 'reason', 'status', 'amount', 'entered_by', 'decided_by', 'decided_at'];

    protected function casts(): array
    {
        return ['period' => 'date:Y-m-d', 'decided_at' => 'datetime', 'days' => 'integer', 'amount' => 'integer'];
    }

    public function kid(): BelongsTo
    {
        return $this->belongsTo(Kid::class);
    }

    public static function statusLabels(): array
    {
        return ['pending' => 'Kutilmoqda', 'approved' => 'Tasdiqlandi', 'rejected' => 'Rad etildi'];
    }
}
