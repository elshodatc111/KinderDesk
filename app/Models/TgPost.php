<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TgPost extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'kind', 'state', 'group_id', 'employee_id', 'user_id', 'text', 'source_chat_id', 'source_message_ids',
        'media_group_id', 'recipients', 'sent_at', 'deleted_at',
    ];

    protected function casts(): array
    {
        return ['source_message_ids' => 'array', 'sent_at' => 'datetime', 'deleted_at' => 'datetime', 'recipients' => 'integer'];
    }

    public const KINDS = ['report' => 'Kunlik hisobot', 'meal' => 'Ovqat', 'announce' => "E'lon"];

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function outbox(): HasMany
    {
        return $this->hasMany(TgOutbox::class, 'post_id');
    }

    /** O'chirish faqat yuborilgandan keyin 30 daqiqa ichida. */
    public function canDelete(): bool
    {
        return $this->state === 'sent' && $this->sent_at && $this->sent_at->gt(now()->subMinutes((int) config('kinderdesk.telegram.delete_minutes')));
    }
}
