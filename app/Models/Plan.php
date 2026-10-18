<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Plan extends Model
{
    protected $fillable = ['edition_id', 'months', 'name', 'price', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'price' => 'integer', 'months' => 'integer'];
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    public function monthlyEquivalent(): int
    {
        return (int) round($this->price / max(1, $this->months));
    }

    public function label(): string
    {
        return match ($this->months) {
            1 => '1 oy',
            3 => '3 oy',
            12 => '1 yil',
            default => $this->months.' oy',
        };
    }
}
