<?php

namespace App\Models;

use App\Services\Telegram\TelegramApi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** Bot: har bir bog'chaning o'z boti + bitta platforma boti (2 bosqichli tasdiq uchun). Token shifrlangan. */
class TelegramBot extends Model
{
    protected $fillable = [
        'tenant_id', 'is_platform', 'token', 'bot_id', 'username', 'name', 'description', 'short_description', 'protect_content',
        'events', 'templates', 'secret', 'last_update_id', 'status', 'last_error', 'last_ok_at',
    ];

    protected $hidden = ['token', 'secret'];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted', 'is_platform' => 'boolean', 'protect_content' => 'boolean',
            'events' => 'array', 'templates' => 'array', 'last_ok_at' => 'datetime', 'last_update_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $b) {
            $b->secret = $b->secret ?: Str::random(40);
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(TgContact::class, 'bot_id')->withoutGlobalScopes();
    }

    public static function platform(): ?self
    {
        return static::where('is_platform', true)->first();
    }

    public static function forTenant(int $tenantId): ?self
    {
        return static::where('tenant_id', $tenantId)->where('is_platform', false)->first();
    }

    public function api(): TelegramApi
    {
        return new TelegramApi((string) $this->token);
    }

    public function usable(): bool
    {
        return filled($this->token) && $this->status !== 'error';
    }

    public function eventEnabled(string $key): bool
    {
        return (bool) (($this->events ?? [])[$key] ?? true);
    }

    public function template(string $key): string
    {
        $t = trim((string) (($this->templates ?? [])[$key] ?? ''));

        return $t !== '' ? $t : (string) config("kinderdesk.telegram.templates.$key");
    }

    public function link(string $payload): string
    {
        return 'https://t.me/'.$this->username.'?start='.$payload;
    }
}
