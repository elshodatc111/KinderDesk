<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class GroupEvent extends Model
{
    use BelongsToTenant;

    protected $table = 'group_events';

    protected $fillable = ['tenant_id','group_id','date','kind','title','status','created_by','approved_by','approved_at'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'approved_at' => 'datetime'];
    }

    public function group(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function staff(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'group_event_staff');
    }
}
