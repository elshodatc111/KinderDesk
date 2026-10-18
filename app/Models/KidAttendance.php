<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KidAttendance extends Model
{
    use BelongsToTenant;

    protected $table = 'kid_attendance';

    protected $fillable = ['tenant_id', 'group_id', 'kid_id', 'date', 'status', 'marked_by'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d'];
    }

    public function kid(): BelongsTo
    {
        return $this->belongsTo(Kid::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
