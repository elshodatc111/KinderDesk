<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClubKid extends Model
{
    use BelongsToTenant;

    protected $table = 'club_kid';

    protected $fillable = ['tenant_id', 'club_id', 'kid_id', 'started_on', 'ended_on'];

    protected function casts(): array
    {
        return ['started_on' => 'date:Y-m-d', 'ended_on' => 'date:Y-m-d'];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function kid(): BelongsTo
    {
        return $this->belongsTo(Kid::class);
    }
}
