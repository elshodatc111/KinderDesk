<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ImportRun extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'user_id', 'source', 'filename', 'status', 'payload', 'summary'];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return ['summary' => 'array'];
    }
}
