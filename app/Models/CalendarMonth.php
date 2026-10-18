<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CalendarMonth extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'month', 'confirmed_by', 'confirmed_at'];

    protected function casts(): array
    {
        return ['month' => 'date:Y-m-d', 'confirmed_at' => 'datetime'];
    }
}
