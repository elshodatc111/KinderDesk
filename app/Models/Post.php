<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** Sotuv sahifasidagi yangilik (post): matn, rasm + matn yoki YouTube video + matn. UZ va RU. */
class Post extends Model
{
    public const TYPES = ['text' => 'Matn', 'image' => 'Rasm + matn', 'video' => 'Video + matn'];

    protected $fillable = ['type', 'slug', 'title_uz', 'title_ru', 'body_uz', 'body_ru', 'cover_path', 'youtube_id', 'status', 'published_at', 'edited_at', 'is_pinned', 'comments_enabled', 'views'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'edited_at' => 'datetime', 'is_pinned' => 'boolean', 'comments_enabled' => 'boolean', 'views' => 'integer'];
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PostComment::class);
    }

    /** Saytda ko'rinadigan: nashr qilingan va vaqti kelgan postlar. */
    public function scopeVisible(Builder $q): Builder
    {
        return $q->where('status', 'published')->where('published_at', '<=', now());
    }

    public function isVisible(): bool
    {
        return $this->status === 'published' && $this->published_at && $this->published_at->lte(now());
    }

    public function isScheduled(): bool
    {
        return $this->status === 'published' && $this->published_at && $this->published_at->gt(now());
    }

    private function ru(): bool
    {
        return app()->getLocale() === 'ru';
    }

    public function title(): string
    {
        return $this->ru() && filled($this->title_ru) ? $this->title_ru : $this->title_uz;
    }

    /** Joriy til bo'yicha matn; rus matni bo'sh bo'lsa, o'zbekcha ko'rsatiladi. */
    public function body(): string
    {
        $ru = $this->ru() && filled(trim(strip_tags((string) $this->body_ru)));

        return (string) ($ru ? $this->body_ru : $this->body_uz);
    }

    public function excerpt(int $limit = 150): string
    {
        $text = html_entity_decode(strip_tags(str_replace(['</p>', '</li>', '<br>', '</h2>', '</h3>'], ' ', $this->body())), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return Str::limit(trim(preg_replace('/\s+/u', ' ', $text)), $limit);
    }

    public function readMinutes(): int
    {
        $words = str_word_count(strip_tags($this->body()), 0, 'АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯабвгдеёжзийклмнопрстуфхцчшщъыьэюяЎўҚқҒғҲҳ');

        return max(1, (int) ceil($words / 180));
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? route('news.cover', [$this->slug, 'v' => substr(md5($this->cover_path), 0, 8)]) : null;
    }

    public function videoThumb(): ?string
    {
        return $this->youtube_id ? 'https://i.ytimg.com/vi/'.$this->youtube_id.'/hqdefault.jpg' : null;
    }

    public function embedUrl(): ?string
    {
        return $this->youtube_id ? 'https://www.youtube-nocookie.com/embed/'.$this->youtube_id.'?rel=0&modestbranding=1&autoplay=1' : null;
    }

    /** Kartada ko'rsatiladigan rasm: muqova yoki video eskizi. */
    public function image(): ?string
    {
        return $this->type === 'video' ? $this->videoThumb() : $this->coverUrl();
    }

    public function url(): string
    {
        return route('news.show', $this->slug);
    }

    public static function makeSlug(string $title, ?int $ignoreId = null): string
    {
        $clean = str_replace(["'", 'ʻ', 'ʼ', '’', '`', '‘'], '', $title);
        $base = Str::limit(Str::slug($clean), 80, '') ?: 'yangilik';
        $slug = $base;
        $i = 2;
        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
