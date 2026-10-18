<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PayslipItem extends Model
{
    use BelongsToTenant;

    protected $table = 'payslip_items';

    protected $fillable = ['tenant_id','employee_id','period','type','amount','reason','source','finance_transaction_id','created_by'];

    protected function casts(): array
    {
        return ['period' => 'date:Y-m-d', 'amount' => 'integer'];
    }
}
