<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    use BelongsToTenant;

    protected $table = 'subjects';

    protected $fillable = ['tenant_id','name','kind','club_id','employee_id','pay_mode','lesson_rate','fixed_salary','status'];

    protected function casts(): array
    {
        return ['lesson_rate' => 'integer', 'fixed_salary' => 'integer'];
    }

    public function club(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
