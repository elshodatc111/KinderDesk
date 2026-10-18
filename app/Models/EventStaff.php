<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class EventStaff extends Model
{
    use BelongsToTenant;

    protected $table = 'event_staff';

    protected $fillable = ['tenant_id','event_id','employee_id','pay'];

    protected function casts(): array
    {
        return ['pay' => 'integer'];
    }

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
