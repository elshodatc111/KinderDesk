<?php

namespace App\Support\I18n;

/**
 * Blade shablonlaridagi oddiy matnlarni avtomatik {!! __('...') !!} ga o'raydi.
 * Kalit — o'zbekcha asl matn; rus tilidagi tarjima lang/ru.json faylida.
 */
class BladeTranslator
{
    private const ATTRS = 'placeholder|title|aria-label|alt|label';

    public static function transform(string $tpl, ?array &$keys = null): string
    {
        $paren = '(?&p)';
        $mustache = '\{\{--.*?--\}\}|\{\{.*?\}\}|\{!!.*?!!\}';
        $directive = '(?<![\w@])@\w+(?:[ \t]*'.$paren.')?';
        $qd = '"(?:[^"@{]|'.$mustache.'|@\w+[ \t]*(?&p)|@|\{)*"';
        $qs = "'(?:[^'@{]|".$mustache."|@\\w+[ \\t]*(?&p)|@|\\{)*'";
        $tagInner = '(?:'.$qd.'|'.$qs.'|'.$mustache.'|'.$directive.'|[^>"\'])*';

        $regex = '~(?(DEFINE)(?<p>\((?:[^()]++|(?&p))*\)))'
            .'(?<keep>'
            .'\{\{--.*?--\}\}'                                   // blade izoh
            .'|@verbatim.*?@endverbatim'
            .'|@php\b(?![ \t]*\().*?@endphp'
            .'|<\?php.*?\?>'
            .'|<(?:script|style|textarea)\b'.$tagInner.'>.*?</(?:script|style|textarea)>'
            .'|\{\{.*?\}\}|\{!!.*?!!\}'
            .'|'.$directive
            .')'
            .'|(?<tag><\/?[a-zA-Z][\w:.\-]*'.$tagInner.'\/?>|<!--.*?-->|<!DOCTYPE[^>]*>)'
            .'|(?<text>[^<@{]+|.)'
            .'~su';

        return preg_replace_callback($regex, function ($m) use (&$keys) {
            if (isset($m['tag']) && $m['tag'] !== '') {
                return self::attrs($m['tag'], $keys);
            }
            if (isset($m['text']) && $m['text'] !== '') {
                return self::text($m['text'], $keys);
            }
            if (isset($m['keep']) && str_starts_with($m['keep'], '{{') && ! str_starts_with($m['keep'], '{{--')) {
                return self::echo($m['keep']);
            }

            return $m[0];
        }, $tpl) ?? $tpl;
    }

    /** Yorliq (label) ko'rinishidagi ifodalarni tarjimaga o'raydi: {{ $def['label'] }} → {{ __($def['label']) }} */
    private static function echo(string $chunk): string
    {
        $expr = trim(substr($chunk, 2, -2));
        if (str_contains($expr, '__(') || str_contains($expr, ' ') || str_contains($expr, '?')) {
            return $chunk;
        }
        $patterns = [
            '/^\$\w+(?:->\w+|\[[^\]]+\])*(?:->|\[\')(?:label|desc|help|title|tagline|name|text|description)(?:\'\])?$/',
            '/^\$(?:l|lbl|label|text|opt|tab)$/',
            '/^\$\w+(?:->\w+)*->(?:roleName|positionLabel|typeLabel|statusLabel|label)\(\)$/',
            '/^[\\\w]+::\w*[lL]abels\(\)\[[^\]]+\]$/',
            '/^\$\w*[lL]abels?\[[^\]]+\]$/',
        ];
        if (preg_match('/^\$\w+(?:->\w+)*->description$/', $expr)) {
            return '{{ kd_desc('.$expr.') }}';
        }
        foreach ($patterns as $re) {
            if (preg_match($re, $expr)) {
                return '{{ __('.$expr.') }}';
            }
        }

        return $chunk;
    }

    private static function text(string $t, ?array &$keys): string
    {
        if (! preg_match('/\p{L}/u', preg_replace('/&[#\w]+;/', '', $t))) {
            return $t;
        }
        if (! preg_match('/^(\s*)(.*?)(\s*)$/su', $t, $m) || $m[2] === '') {
            return $t;
        }
        $key = preg_replace('/\s+/u', ' ', $m[2]);
        if (is_array($keys)) {
            $keys[$key] = true;
        }

        return $m[1].'{!! __('.self::quote($key).') !!}'.$m[3];
    }

    private static function attrs(string $tag, ?array &$keys): string
    {
        if (! preg_match('/(?:'.self::ATTRS.')=/', $tag)) {
            return $tag;
        }

        return preg_replace_callback('/(?<![:\w@\-.])('.self::ATTRS.')="([^"{}@]*\p{L}[^"{}@]*)"/u', function ($m) use (&$keys) {
            if (str_contains($m[2], '$')) {
                return $m[0];
            }
            $key = html_entity_decode(trim($m[2]), ENT_QUOTES);
            if (is_array($keys)) {
                $keys[$key] = true;
            }

            return $m[1].'="{{ __('.self::quote($key).') }}"';
        }, $tag);
    }

    private static function quote(string $s): string
    {
        return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $s)."'";
    }
}
