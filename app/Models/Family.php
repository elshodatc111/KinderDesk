<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Family extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'effective_from', 'confirmed_by', 'confirmed_at'];

    protected function casts(): array
    {
        return ['effective_from' => 'date:Y-m-d', 'confirmed_at' => 'datetime'];
    }

    public function kids(): HasMany
    {
        return $this->hasMany(Kid::class);
    }
}
