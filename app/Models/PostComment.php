<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PostComment extends Model
{
    public const STATUSES = ['pending' => 'Kutilmoqda', 'approved' => 'Tasdiqlangan', 'hidden' => 'Yashirilgan'];

    protected $fillable = ['post_id', 'parent_id', 'user_id', 'author_name', 'body', 'status', 'is_staff', 'ip_hash'];

    protected function casts(): array
    {
        return ['is_staff' => 'boolean'];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Super admin javoblari (bir daraja). */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('id');
    }

    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('status', 'approved');
    }

    public static function hashIp(?string $ip): string
    {
        return hash_hmac('sha256', (string) $ip, (string) config('app.key'));
    }
}
