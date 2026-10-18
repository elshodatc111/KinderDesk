<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceTransaction extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'wallet', 'account', 'type', 'amount', 'status', 'category', 'description', 'kid_id', 'employee_id',
        'payslip_id', 'transfer_group', 'occurred_on', 'created_by', 'confirmed_by', 'confirmed_at',
        'canceled_by', 'canceled_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_on' => 'date:Y-m-d',
            'confirmed_at' => 'datetime',
            'canceled_at' => 'datetime',
            'amount' => 'integer',
        ];
    }

    public function kid(): BelongsTo
    {
        return $this->belongsTo(Kid::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withoutGlobalScopes();
    }

    public static function typeLabels(): array
    {
        return [
            'kid_payment' => "Bola to'lovi",
            'kid_refund' => "Qaytarilgan to'lov",
            'income' => 'Daromad',
            'expense' => 'Xarajat',
            'salary' => 'Ish haqi',
            'transfer' => "Hisoblar o'rtasida o'tkazma",
            'handover' => "Kassadan topshirilgan pul",
            'deposit' => "Qo'shimcha mablag'",
            'desk_fund' => "Kassaga berilgan naqd pul",
            'subsidy' => 'Subsidiya',
        ];
    }

    public static function accountLabels(): array
    {
        return ['cash' => 'Kassa (naqd)', 'card' => 'Karta', 'bank' => 'Bank hisobi'];
    }

    public function typeLabel(): string
    {
        return self::typeLabels()[$this->type] ?? $this->type;
    }
}
