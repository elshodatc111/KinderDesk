<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** To'garak: alohida a'zolik, davomad va to'lov. */
class Club extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'teacher_id', 'fee', 'billing', 'schedule', 'description', 'status'];

    protected function casts(): array
    {
        return ['fee' => 'integer'];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'teacher_id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(ClubKid::class);
    }

    public function activeLinks(): HasMany
    {
        return $this->hasMany(ClubKid::class)->whereNull('ended_on');
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    public function isPerSession(): bool
    {
        return $this->billing === 'per_session';
    }

    public function feeLabel(): string
    {
        return money($this->fee).($this->isPerSession() ? " so'm / mashg'ulot" : " so'm / oy");
    }
}
