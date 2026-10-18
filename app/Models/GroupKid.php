<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupKid extends Model
{
    use BelongsToTenant;

    protected $table = 'group_kid';

    protected $fillable = ['tenant_id', 'group_id', 'kid_id', 'started_on', 'ended_on', 'note', 'started_by', 'ended_by'];

    protected function casts(): array
    {
        return ['started_on' => 'date:Y-m-d', 'ended_on' => 'date:Y-m-d'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function kid(): BelongsTo
    {
        return $this->belongsTo(Kid::class);
    }
}
