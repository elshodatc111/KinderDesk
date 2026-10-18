<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class EventCost extends Model
{
    use BelongsToTenant;

    protected $table = 'event_costs';

    protected $fillable = ['tenant_id','event_id','kind','title','planned','actual'];

    protected function casts(): array
    {
        return ['planned' => 'integer', 'actual' => 'integer'];
    }
}
