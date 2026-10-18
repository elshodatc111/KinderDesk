<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AdminBonusEvent extends Model
{
    use BelongsToTenant;

    protected $table = 'admin_bonus_events';

    protected $fillable = ['tenant_id','kid_id','period','amount'];

    protected function casts(): array
    {
        return ['period' => 'date:Y-m-d'];
    }
}
