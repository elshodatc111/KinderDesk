<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AiReport extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'date', 'period', 'status', 'model', 'facts', 'result', 'attempts', 'error'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'facts' => 'array', 'result' => 'array'];
    }
}
