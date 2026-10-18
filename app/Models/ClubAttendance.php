<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ClubAttendance extends Model
{
    use BelongsToTenant;

    protected $table = 'club_attendance';

    protected $fillable = ['tenant_id', 'club_id', 'kid_id', 'date', 'status', 'marked_by'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d'];
    }
}
