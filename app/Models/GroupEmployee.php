<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupEmployee extends Model
{
    use BelongsToTenant;

    protected $table = 'group_employee';

    protected $fillable = ['tenant_id', 'group_id', 'employee_id', 'role_in_group', 'started_on', 'ended_on', 'started_by', 'ended_by'];

    protected function casts(): array
    {
        return ['started_on' => 'date:Y-m-d', 'ended_on' => 'date:Y-m-d'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
