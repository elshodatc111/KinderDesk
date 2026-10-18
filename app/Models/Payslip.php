<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'employee_id', 'period', 'strategy', 'base_amount', 'worked_days', 'working_days',
        'late_count', 'kids_count', 'kpi_amount', 'bonus', 'penalty', 'advance', 'detail', 'approved_at', 'approved_by', 'total', 'paid', 'status', 'note', 'created_by',
    ];

    protected function casts(): array
    {
        return ['period' => 'date:Y-m-d', 'detail' => 'array', 'approved_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function remaining(): int
    {
        return max(0, $this->total - $this->paid);
    }
}
