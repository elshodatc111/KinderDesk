<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    use BelongsToTenant;

    protected $table = 'complaints';

    protected $fillable = ['tenant_id','date','employee_id','group_id','note','created_by'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d'];
    }
}
