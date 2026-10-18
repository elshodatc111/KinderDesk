<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Subsidiya: kutilayotgan (hisoblangan) va olingan summa alohida yuritiladi. */
class Subsidy extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'title', 'source', 'period', 'kids_count', 'rate', 'expected', 'received', 'account', 'received_on', 'status', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['period' => 'date:Y-m-d', 'received_on' => 'date:Y-m-d', 'expected' => 'integer', 'received' => 'integer', 'rate' => 'integer'];
    }
}
