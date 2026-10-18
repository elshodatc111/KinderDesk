<?php

namespace App\Support\Site;

/**
 * Post matnini xavfsiz HTML'ga aylantiradi (ruxsat etilgan teglar ro'yxati).
 * Natija to'liq shu klass tomonidan yig'iladi: kiruvchi teg yoki atribut to'g'ridan-to'g'ri o'tmaydi,
 * shuning uchun script, onerror, style, iframe va h.k. hech qachon chiqmaydi.
 */
class HtmlSanitizer
{
    /** kiruvchi teg => chiqariladigan teg */
    private const MAP = [
        'p' => 'p', 'div' => 'p', 'br' => 'br', 'hr' => 'hr',
        'strong' => 'strong', 'b' => 'strong', 'em' => 'em', 'i' => 'em', 'u' => 'u', 's' => 's', 'strike' => 's', 'del' => 's',
        'h1' => 'h2', 'h2' => 'h2', 'h3' => 'h3', 'h4' => 'h3', 'h5' => 'h3', 'h6' => 'h3',
        'ul' => 'ul', 'ol' => 'ol', 'li' => 'li', 'blockquote' => 'blockquote', 'a' => 'a',
    ];

    /** Ichidagi matni ham olib tashlanadigan teglar */
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'template', 'noscript', 'svg', 'math', 'form', 'input', 'button', 'textarea', 'select', 'link', 'meta', 'head', 'title', 'base', 'frame', 'frameset', 'applet', 'audio', 'video', 'canvas'];

    private const VOID = ['br', 'hr'];

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }
        $prev = libxml_use_internal_errors(true);
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->loadHTML('<?xml encoding="UTF-8"><body><div id="kd-root">'.$html.'</div></body>', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $root = $doc->getElementById('kd-root');
        if (! $root) {
            return '';
        }
        $out = self::children($root);
        // bo'sh paragraflar va ortiqcha qator uzilishlarini tozalash
        $out = preg_replace('~<p>(\s|&nbsp;|<br>)*</p>~u', '', $out);
        $out = preg_replace('~(?:\s*<br>\s*){3,}~', '<br><br>', $out);

        return trim($out);
    }

    private static function children(\DOMNode $node): string
    {
        $s = '';
        foreach ($node->childNodes as $child) {
            $s .= self::node($child);
        }

        return $s;
    }

    private static function node(\DOMNode $n): string
    {
        if ($n instanceof \DOMText) {
            return htmlspecialchars($n->nodeValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        if (! $n instanceof \DOMElement) {
            return '';   // izoh, CDATA, processing instruction
        }
        $tag = strtolower($n->tagName);
        if (in_array($tag, self::DROP, true)) {
            return '';
        }
        if (! isset(self::MAP[$tag])) {
            return self::children($n);   // noma'lum teg: qobig'i olib tashlanadi, matni qoladi
        }
        $out = self::MAP[$tag];
        if (in_array($out, self::VOID, true)) {
            return '<'.$out.'>';
        }
        $inner = self::children($n);
        if ($out === 'a') {
            $href = self::safeHref($n->getAttribute('href'));
            if ($href === null) {
                return $inner;
            }

            return '<a href="'.htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" target="_blank" rel="noopener noreferrer nofollow">'.$inner.'</a>';
        }

        return '<'.$out.'>'.$inner.'</'.$out.'>';
    }

    /** Faqat http(s), mailto, tel va sayt ichidagi (/...) havolalar. */
    public static function safeHref(?string $href): ?string
    {
        $href = trim((string) $href);
        if ($href === '' || strlen($href) > 500) {
            return null;
        }
        // boshqaruv belgilari va entity orqali yashirilgan «java&#10;script:» hiylasiga qarshi
        $probe = strtolower(preg_replace('/[\x00-\x20\x7f]+/', '', html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if (preg_match('~^(https?://|mailto:|tel:)~', $probe)) {
            return $href;
        }
        if (str_starts_with($probe, '/') && ! str_starts_with($probe, '//')) {
            return $href;
        }

        return null;
    }
}
