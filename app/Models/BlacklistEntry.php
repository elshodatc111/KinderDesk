<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class BlacklistEntry extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'type', 'name', 'phones', 'phone_hashes', 'cert_hash', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['name' => 'encrypted', 'phones' => 'encrypted:array', 'phone_hashes' => 'array'];
    }

    public function author(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
