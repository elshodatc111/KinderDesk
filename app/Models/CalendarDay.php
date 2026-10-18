<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CalendarDay extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'date', 'kind', 'title'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d'];
    }
}
