<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Sinov (demo) muddatidan foydalangan telefon/email/STIR xeshlari. Bog'cha o'chirilsa ham saqlanadi. */
class TrialRegistry extends Model
{
    protected $table = 'trial_registry';

    public $timestamps = false;

    protected $fillable = ['kind', 'hash', 'created_at'];

    public static function hashFor(string $kind, string $value): string
    {
        return hash_hmac('sha256', $kind.':'.$value, config('app.key'));
    }

    public static function exists_(string $kind, ?string $normalized): bool
    {
        if (! $normalized) {
            return false;
        }

        return static::query()->where('kind', $kind)->where('hash', static::hashFor($kind, $normalized))->exists();
    }

    public static function remember(string $kind, ?string $normalized): void
    {
        if (! $normalized) {
            return;
        }
        static::query()->firstOrCreate(
            ['kind' => $kind, 'hash' => static::hashFor($kind, $normalized)],
            ['created_at' => now()]
        );
    }
}
