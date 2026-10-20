<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Support\Site\Legal;
use Illuminate\Support\Facades\Cache;

/** robots.txt va sitemap.xml: qidiruv tizimlari uchun. */
class SeoController extends Controller
{
    /** Yopiq bo'limlar (kabinet, admin, kirish, API va h.k.) indekslanmaydi. */
    private const PRIVATE_PATHS = ['app', 'admin', 'kirish', 'royxatdan-otish', 'parolni-tiklash', 'xavfsizlik', 'rozilik', 'til', 'k', 'api', 'docs', 'broadcasting', 'webhooks'];

    public function robots()
    {
        $base = request()->getBasePath();
        $lines = ['User-agent: *', 'Allow: /'];
        foreach (self::PRIVATE_PATHS as $p) {
            $lines[] = 'Disallow: '.$base.'/'.$p;
        }
        $lines[] = '';
        $lines[] = 'Sitemap: '.url('sitemap.xml');

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    public function sitemap()
    {
        $xml = Cache::remember('seo.sitemap.'.md5(url('/')), 3600, fn () => $this->build());

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    private function build(): string
    {
        // Statik sahifalar uchun lastmod: tegishli shablon o'zgargan vaqt
        $mtime = fn (string $view) => is_file($f = resource_path('views/'.$view)) ? \Illuminate\Support\Carbon::createFromTimestamp(filemtime($f)) : null;
        $pages = [[route('home'), $mtime('public/home.blade.php')], [route('news.index'), null]];
        foreach (array_keys(Legal::PAGES) as $page) {
            $pages[] = [route('legal', $page), $mtime('public/legal.blade.php')];
        }
        try {
            foreach (Post::visible()->orderByDesc('published_at')->get(['slug', 'published_at', 'edited_at']) as $p) {
                $pages[] = [route('news.show', $p->slug), ($p->edited_at ?? $p->published_at)];
            }
            $latest = Post::visible()->max('published_at');
            if ($latest) {
                $pages[1][1] = \Illuminate\Support\Carbon::parse($latest);
            }
        } catch (\Throwable) {
            // migratsiya hali ishlamagan bo'lsa ham sitemap chiqadi
        }

        $langs = ['uz', 'ru'];
        $out = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'];
        foreach ($pages as [$loc, $mod]) {
            foreach ($langs as $l) {
                $out[] = '<url><loc>'.e($loc.'?lang='.$l).'</loc>'.($mod ? '<lastmod>'.$mod->toAtomString().'</lastmod>' : '');
                foreach ($langs as $a) {
                    $out[] = '<xhtml:link rel="alternate" hreflang="'.$a.'" href="'.e($loc.'?lang='.$a).'"/>';
                }
                $out[] = '<xhtml:link rel="alternate" hreflang="x-default" href="'.e($loc.'?lang=uz').'"/></url>';
            }
        }
        $out[] = '</urlset>';

        return implode("\n", $out)."\n";
    }
}
