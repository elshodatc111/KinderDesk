<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupJob extends Model
{
    protected $fillable = ['uid', 'status', 'for_date', 'path', 'size', 'sha256', 'error', 'requested_ip', 'downloads', 'started_at', 'ready_at', 'expires_at'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ready_at' => 'datetime', 'expires_at' => 'datetime', 'for_date' => 'date:Y-m-d'];
    }
}
