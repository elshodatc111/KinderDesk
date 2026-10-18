<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Edition extends Model
{
    protected $fillable = ['key', 'name', 'tagline', 'description', 'features', 'defaults', 'sort', 'is_active'];

    protected function casts(): array
    {
        return ['features' => 'array', 'defaults' => 'array', 'is_active' => 'boolean'];
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class)->orderBy('months');
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function color(): string
    {
        return config("kinderdesk.editions.{$this->key}.color", 'teal');
    }
}
