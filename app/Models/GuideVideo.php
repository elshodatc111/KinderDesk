<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GuideVideo extends Model
{
    protected $fillable = ['title_uz', 'title_ru', 'youtube_id', 'sort', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort' => 'integer'];
    }

    public function title(): string
    {
        return app()->getLocale() === 'ru' && $this->title_ru ? $this->title_ru : $this->title_uz;
    }

    /** YouTube havolasidan (watch, youtu.be, embed, shorts) yoki to'g'ridan-to'g'ri 11 belgili ID'dan ID ajratadi. */
    public static function extractId(string $input): ?string
    {
        $input = trim($input);
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $input)) {
            return $input;
        }
        if (preg_match('~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/|v/))([A-Za-z0-9_-]{11})~', $input, $m)) {
            return $m[1];
        }

        return null;
    }
}
