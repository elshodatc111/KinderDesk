<?php

namespace App\Support\Site;

use App\Models\PlatformSetting;

/** Sayt pastki qismidagi ijtimoiy tarmoq havolalari: super admin «Sozlamalar»da kiritadi. */
class Social
{
    public const NETWORKS = [
        'telegram' => ['label' => 'Telegram kanal', 'hosts' => ['t.me', 'telegram.me', 'telegram.dog'], 'base' => 'https://t.me/', 'placeholder' => '@kinderdesk yoki https://t.me/kinderdesk'],
        'instagram' => ['label' => 'Instagram sahifa', 'hosts' => ['instagram.com'], 'base' => 'https://instagram.com/', 'placeholder' => '@kinderdesk yoki https://instagram.com/kinderdesk'],
        'youtube' => ['label' => 'YouTube kanal', 'hosts' => ['youtube.com', 'youtu.be'], 'base' => 'https://youtube.com/', 'placeholder' => '@kinderdesk yoki https://youtube.com/@kinderdesk'],
    ];

    /**
     * Kiritilgan matnni xavfsiz https havolaga aylantiradi.
     * Bo'sh -> '' (havola o'chiriladi); noto'g'ri -> null.
     */
    public static function normalize(string $network, ?string $value): ?string
    {
        $def = self::NETWORKS[$network] ?? null;
        $v = trim((string) $value);
        if (! $def) {
            return null;
        }
        if ($v === '') {
            return '';
        }
        // @nom yoki oddiy nom
        $isHandle = preg_match('/^@[A-Za-z0-9_.\-]{3,64}$/', $v) || (preg_match('/^[A-Za-z0-9_\-]{3,64}$/', $v));
        if ($isHandle) {
            $handle = ltrim($v, '@');

            return $def['base'].($network === 'youtube' ? '@' : '').$handle;
        }
        if (! preg_match('~^https?://~i', $v)) {
            $v = 'https://'.$v;
        }
        $parts = parse_url($v);
        $host = strtolower(preg_replace('/^(www|m)\./', '', (string) ($parts['host'] ?? '')));
        if (! $host || ! in_array($host, $def['hosts'], true)) {
            return null;
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        $path = ($parts['path'] ?? '/');
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return 'https://'.$host.$path.$query;
    }

    /** @return array<string, array{label: string, url: string}> faqat to'ldirilganlar */
    public static function links(): array
    {
        $out = [];
        foreach (self::NETWORKS as $key => $def) {
            $url = trim((string) PlatformSetting::get('social.'.$key));
            if ($url !== '' && str_starts_with($url, 'https://')) {
                $out[$key] = ['label' => $def['label'], 'url' => $url];
            }
        }

        return $out;
    }
}
