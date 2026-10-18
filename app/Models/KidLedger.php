<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KidLedger extends Model
{
    use BelongsToTenant;

    protected $table = 'kid_ledger';

    protected $fillable = [
        'tenant_id', 'kid_id', 'type', 'amount', 'period', 'charge_key', 'group_id', 'club_id', 'finance_transaction_id',
        'status', 'description', 'created_by',
    ];

    protected function casts(): array
    {
        return ['period' => 'date:Y-m-d', 'amount' => 'integer'];
    }

    public function kid(): BelongsTo
    {
        return $this->belongsTo(Kid::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public static function typeLabels(): array
    {
        return [
            'charge' => "Oylik hisob (yechim)",
            'payment' => "To'lov",
            'discount' => 'Chegirma',
            'refund' => 'Qaytarish',
            'adjustment' => 'Tuzatish',
        ];
    }

    public function typeLabel(): string
    {
        return self::typeLabels()[$this->type] ?? $this->type;
    }
}
