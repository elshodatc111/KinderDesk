<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Substitution extends Model
{
    use BelongsToTenant;

    protected $table = 'substitutions';

    protected $fillable = ['tenant_id','date','group_id','absent_employee_id','substitute_employee_id','created_by'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d'];
    }
}
