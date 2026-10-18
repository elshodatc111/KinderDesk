<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Lead extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'type', 'status', 'name', 'phone', 'phone2', 'source', 'data', 'assigned_to', 'note',
        'result', 'lost_reason', 'converted_id', 'next_call_on', 'stage_changed_at',
    ];

    protected function casts(): array
    {
        return ['data' => 'array', 'next_call_on' => 'date', 'stage_changed_at' => 'datetime'];
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'noteable')->orderByDesc('id');
    }

    public function scopeOfType($q, string $type)
    {
        return $q->where('type', $type);
    }
}
