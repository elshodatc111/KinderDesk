<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PayScheme extends Model
{
    use BelongsToTenant;

    protected $table = 'pay_schemes';

    protected $fillable = ['tenant_id','key','params'];

    protected function casts(): array
    {
        return ['params' => 'array'];
    }
}
