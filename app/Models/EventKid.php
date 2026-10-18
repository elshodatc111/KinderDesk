<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class EventKid extends Model
{
    use BelongsToTenant;

    protected $table = 'event_kids';

    protected $fillable = ['tenant_id','event_id','kid_id','attended','charged'];

    protected function casts(): array
    {
        return ['charged' => 'boolean'];
    }

    public function kid(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Kid::class);
    }
}
