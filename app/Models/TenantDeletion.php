<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantDeletion extends Model
{
    public $timestamps = false;

    protected $fillable = ['old_tenant_id', 'name', 'reason', 'deleted_by', 'stats', 'deleted_at'];

    protected function casts(): array
    {
        return ['stats' => 'array', 'deleted_at' => 'datetime'];
    }
}
