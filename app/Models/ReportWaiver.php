<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ReportWaiver extends Model
{
    use BelongsToTenant;

    protected $table = 'report_waivers';

    protected $fillable = ['tenant_id','employee_id','period','date','reason','created_by'];

    protected function casts(): array
    {
        return ['period' => 'date:Y-m-d', 'date' => 'date:Y-m-d'];
    }
}
