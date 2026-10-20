<?php

namespace App\Support\Site;

use App\Models\PlatformSetting;

/**
 * Qidiruv tizimlari uchun yordamchi: indekslanadigan sahifalar, canonical/hreflang manzillari,
 * JSON-LD (tuzilgan ma'lumot) va ijtimoiy tarmoq rasmlari.
 */
class Seo
{
    /** Faqat shu sahifalar indekslanadi; kabinet, admin va kirish sahifalari noindex. */
    public const INDEXABLE = ['home', 'legal', 'news.index', 'news.show'];

    public const LOCALES = ['uz' => 'uz_UZ', 'ru' => 'ru_RU'];

    public static function indexable(): bool
    {
        return request()->routeIs(...self::INDEXABLE);
    }

    /** Berilgan til uchun joriy sahifaning toza manzili (?lang=xx; sahifalash bo'lsa ?page). */
    public static function urlFor(string $locale): string
    {
        $query = ['lang' => $locale];
        $page = (int) request()->query('page', 1);
        if ($page > 1 && request()->routeIs('news.index')) {
            $query['page'] = $page;
        }

        return url()->current().'?'.http_build_query($query);
    }

    /** @return array<string, string> hreflang => manzil (x-default: o'zbekcha). */
    public static function alternates(): array
    {
        $alt = [];
        foreach (array_keys(self::LOCALES) as $l) {
            $alt[$l] = self::urlFor($l);
        }
        $alt['x-default'] = $alt['uz'];

        return $alt;
    }

    public static function ogImage(): string
    {
        $file = app()->getLocale() === 'ru' ? 'og-ru.png' : 'og-uz.png';

        return asset($file);
    }

    /** Ijtimoiy tarmoq rasmi o'lchami (og-uz.png / og-ru.png: 1200x630). */
    public static function ogImageIsDefault(string $image): bool
    {
        return in_array($image, [asset('og-uz.png'), asset('og-ru.png')], true);
    }

    /** Sayt bo'ylab Organization + WebSite (JSON-LD graph). */
    public static function siteGraph(): array
    {
        $home = route('home');
        $same = array_values(array_map(fn ($s) => $s['url'], Social::links()));
        $org = [
            '@type' => 'Organization',
            '@id' => $home.'#organization',
            'name' => 'KinderDesk',
            'url' => $home,
            'logo' => asset('logo.png'),
            'areaServed' => 'UZ',
        ];
        if ($same) {
            $org['sameAs'] = $same;
        }
        $phone = trim((string) PlatformSetting::get('support.phone'));
        if ($phone !== '') {
            $org['contactPoint'] = [
                '@type' => 'ContactPoint',
                'contactType' => 'customer support',
                'telephone' => $phone,
                'availableLanguage' => ['uz', 'ru'],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                $org,
                ['@type' => 'WebSite', '@id' => $home.'#website', 'name' => 'KinderDesk', 'url' => $home, 'inLanguage' => ['uz', 'ru'], 'publisher' => ['@id' => $home.'#organization']],
            ],
        ];
    }

    /** <script> ichiga xavfsiz JSON (</script> va & belgilari kodlanadi). */
    public static function json(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '{}';
    }

    /** Sahifa uchun BreadcrumbList. @param array<int, array{0:string,1:string}> $items [nom, manzil] */
    public static function breadcrumbs(array $items): array
    {
        $list = [];
        foreach (array_values($items) as $i => [$name, $url]) {
            $list[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => $url];
        }

        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list];
    }
}
