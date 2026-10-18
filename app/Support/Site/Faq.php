<?php

namespace App\Support\Site;

use App\Models\PlatformSetting;

/** Sotuv sahifasidagi «Ko'p beriladigan savollar». Super admin sozlamalaridan tahrirlanadi (JSON, ikki tilda). */
class Faq
{
    private const DEFAULTS = [
        ["Sinov tugagach nima bo'ladi?", "Tizim faqat ko'rish rejimiga o'tadi: ma'lumotlaringiz joyida, lekin yangi yozuv kirita olmaysiz. Obunani faollashtirsangiz, ish davom etadi."],
        ["Ma'lumotlarim boshqa bog'chalarga ko'rinadimi?", "Yo'q. Har bir bog'chaning ma'lumoti butunlay alohida, faqat o'z xodimlaringiz ko'ra oladi."],
        ["Obuna tugashiga oldin ogohlantirasizmi?", "Ha. Tugashiga 3 kun qolganda direktorga ilova ichida va SMS orqali xabar boradi. Obuna o'zi yangilanmaydi: direktor balansni to'ldirib, tarifni o'zi tanlab sotib oladi."],
        ["Bepul sinovni qayta olsam bo'ladimi?", "Yo'q. Bir telefon raqam, email yoki STIR uchun sinov faqat bir marta beriladi."],
        ["SMS qanday yuboriladi?", "Bog'changiz o'z Eskiz hisobini ulaydi. Qaysi holatda SMS ketishini sozlamalardan belgilaysiz; matnlar Eskiz tasdig'idan o'tgach ishlaydi."],
    ];

    /** @return array<int, array{q: array{uz:string, ru:string}, a: array{uz:string, ru:string}}> */
    public static function defaults(): array
    {
        $tr = fn (string $s) => app('translator')->get($s, [], 'ru');

        return array_map(fn ($r) => ['q' => ['uz' => $r[0], 'ru' => $tr($r[0])], 'a' => ['uz' => $r[1], 'ru' => $tr($r[1])]], self::DEFAULTS);
    }

    public static function all(): array
    {
        $raw = PlatformSetting::get('site.faq');
        if ($raw !== null && $raw !== '') {
            $list = json_decode($raw, true);
            if (is_array($list)) {
                return $list;
            }
        }

        return self::defaults();
    }

    public static function save(array $rows): void
    {
        $clean = [];
        foreach ($rows as $r) {
            $q = ['uz' => trim((string) ($r['q_uz'] ?? '')), 'ru' => trim((string) ($r['q_ru'] ?? ''))];
            $a = ['uz' => trim((string) ($r['a_uz'] ?? '')), 'ru' => trim((string) ($r['a_ru'] ?? ''))];
            if (($q['uz'] === '' && $q['ru'] === '') || ($a['uz'] === '' && $a['ru'] === '')) {
                continue;
            }
            $clean[] = ['q' => $q, 'a' => $a];
        }
        PlatformSetting::put('site.faq', json_encode($clean, JSON_UNESCAPED_UNICODE));
    }

    public static function pick(array $pair, ?string $locale = null): string
    {
        $l = $locale ?: app()->getLocale();

        return $pair[$l] ?? '' ?: ($pair['uz'] ?? ($pair['ru'] ?? ''));
    }
}
