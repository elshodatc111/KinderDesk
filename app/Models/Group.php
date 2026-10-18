<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'type', 'size', 'monthly_fee', 'daily_fee', 'start_time', 'end_time', 'capacity', 'age_range', 'room', 'description', 'status'];

    public const TYPES = ['main', 'saturday', 'evening'];

    protected function casts(): array
    {
        return ['monthly_fee' => 'integer', 'daily_fee' => 'integer'];
    }

    public function kidLinks(): HasMany
    {
        return $this->hasMany(GroupKid::class);
    }

    public function activeKidLinks(): HasMany
    {
        return $this->hasMany(GroupKid::class)->whereNull('ended_on');
    }

    public function employeeLinks(): HasMany
    {
        return $this->hasMany(GroupEmployee::class);
    }

    public function activeEmployeeLinks(): HasMany
    {
        return $this->hasMany(GroupEmployee::class)->whereNull('ended_on');
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    public function scopeMain($q)
    {
        return $q->where('type', 'main');
    }

    /** Kunlik hisoblanadigan guruh (shanba / kechki). */
    public function isDaily(): bool
    {
        return $this->type !== 'main';
    }

    public function sizeLabel(): string
    {
        return $this->size === 'small' ? 'Kichik guruh' : 'Katta guruh';
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'saturday' => 'Shanba guruhi',
            'evening' => 'Kechki guruh',
            default => 'Asosiy guruh',
        };
    }

    /** Guruh to'lov summasi (asosiy — oylik, qolganlari — kunlik). */
    public function feeLabel(): string
    {
        return $this->isDaily() ? __(":sum so'm / kun", ['sum' => money($this->daily_fee)]) : __(":sum so'm / oy", ['sum' => money($this->monthly_fee)]);
    }

    public function timeLabel(): ?string
    {
        return $this->start_time && $this->end_time ? substr($this->start_time, 0, 5).'–'.substr($this->end_time, 0, 5) : null;
    }

    public function isFull(): bool
    {
        return $this->capacity && $this->activeKidLinks()->count() >= $this->capacity;
    }
}
