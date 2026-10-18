<?php

namespace App\Console\Commands;

use App\Support\I18n\BladeTranslator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class I18nExtract extends Command
{
    protected $signature = 'kd:i18n {--missing : Faqat tarjimasi yo\'q kalitlarni chiqarish} {--out= : JSON faylga yozish}';

    protected $description = "Blade shablonlaridagi tarjima kalitlarini yig'adi va lang/ru.json bilan solishtiradi";

    public function handle(): int
    {
        $keys = [];
        foreach (File::allFiles(resource_path('views')) as $f) {
            if (str_ends_with($f->getFilename(), '.blade.php') && ! str_contains($f->getPath(), '/guide')) {
                BladeTranslator::transform(File::get($f->getPathname()), $keys);
            }
        }
        // Aniq __('...') chaqiruvlari (shablonlar, kontrollerlar, servislar)
        foreach ([resource_path('views'), app_path()] as $dir) {
            foreach (File::allFiles($dir) as $f) {
                if (! str_ends_with($f->getFilename(), '.php')) {
                    continue;
                }
                $src = File::get($f->getPathname());
                if (preg_match_all('/__\(\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)")/s', $src, $mm, PREG_SET_ORDER)) {
                    foreach ($mm as $m) {
                        $k = $m[1] !== '' ? str_replace("\\'", "'", $m[1]) : stripcslashes($m[2] ?? '');
                        if ($k !== '' && preg_match('/\p{L}/u', $k) && ! preg_match('/^[a-z_]+\.[a-z_.]+$/', $k)) {
                            $keys[$k] = true;
                        }
                    }
                }
            }
        }
        $ru = json_decode(File::get(lang_path('ru.json')), true) ?: [];
        $all = array_keys($keys);
        $missing = array_values(array_filter($all, fn ($k) => ! isset($ru[$k])));
        $this->info(count($all).' kalit, tarjimasi yo\'q: '.count($missing));
        if ($out = $this->option('out')) {
            File::put($out, json_encode($this->option('missing') ? $missing : $all, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }

        return self::SUCCESS;
    }
}
