<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Support\Navigation;
use Illuminate\Support\Facades\Cache;

/** Chat bilim manbai: yo'riqnoma matni, bog'chaning yoqilgan bo'limlari va foydalanuvchi ochishi mumkin bo'lgan sahifalar. Bog'cha ma'lumoti YO'Q. */
class Knowledge
{
    public function guide(string $lang): string
    {
        return Cache::remember("ai:guide:$lang", 3600, function () use ($lang) {
            $file = resource_path("views/app/guide/$lang.blade.php");
            if (! is_file($file)) {
                return '';
            }
            $raw = file_get_contents($file);
            preg_match_all('/<section[^>]*>(.*?)<\/section>/s', $raw, $m);
            $text = implode("\n", $m[1] ?? []);
            $text = preg_replace('/@\w+(\([^)]*\))?/', ' ', $text);
            $text = preg_replace('/\{\{.*?\}\}|\{!!.*?!!\}/s', ' ', $text);
            $text = html_entity_decode(strip_tags(preg_replace('/<\/(p|li|h\d|div|tr)>/', "\n", $text)));

            return mb_substr(trim(preg_replace("/[ \t]+/", ' ', preg_replace("/\n{2,}/", "\n", $text))), 0, 14000);
        });
    }

    /** Foydalanuvchiga ochiq sahifalar ro'yxati (nomlari). @return array<int,string> */
    public function sections(User $user): array
    {
        $out = [];
        foreach (Navigation::tenant($user) as $group) {
            foreach ($group['items'] as $item) {
                $out[] = __($item['label']);
            }
        }

        return $out;
    }

    /** Bog'chada yoqilgan imkoniyatlar tavsifi. @return array<int,string> */
    public function features(): array
    {
        $out = [];
        foreach (config('kinderdesk.features') as $key => $def) {
            if (tenant()->hasFeature($key)) {
                $out[] = __($def['label']).' — '.__($def['desc']);
            }
        }

        return $out;
    }
}
