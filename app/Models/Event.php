<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use BelongsToTenant;

    protected $table = 'events';

    protected $fillable = ['tenant_id','title','date','deadline','status','note','approved_total','price','reapproval','approved_by','approved_at','locked_at','closed_at','created_by'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'deadline' => 'date:Y-m-d', 'reapproval' => 'boolean', 'approved_total' => 'integer', 'price' => 'integer', 'approved_at' => 'datetime', 'locked_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function costs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EventCost::class);
    }

    public function kids(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EventKid::class);
    }

    public function staff(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EventStaff::class);
    }

    public function plannedTotal(): int
    {
        return (int) $this->costs->sum('planned') + (int) $this->staff->sum('pay');
    }
}
