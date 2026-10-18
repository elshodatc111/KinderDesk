<?php

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;

if (! function_exists('tenant')) {
    /** Joriy so'rovdagi bog'cha (tenant) yoki null. */
    function tenant(): ?Tenant
    {
        return app(TenantContext::class)->tenant();
    }
}

if (! function_exists('money')) {
    /** 1234567 -> "1 234 567" */
    function money(int|float|string|null $amount, bool $withCurrency = false): string
    {
        $text = number_format((float) ($amount ?? 0), 0, '.', ' ');
        if ($withCurrency) {
            $text .= ' '.(tenant()?->setting('general.currency', "so'm") ?? "so'm");
        }

        return $text;
    }
}

if (! function_exists('normalize_phone')) {
    /** Telefonni 998XXXXXXXXX ko'rinishiga keltiradi. Noto'g'ri bo'lsa null. */
    function normalize_phone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '') {
            return null;
        }
        if (strlen($digits) === 9) {
            $digits = '998'.$digits;
        }
        if (strlen($digits) === 12 && str_starts_with($digits, '998')) {
            return $digits;
        }

        return null;
    }
}

if (! function_exists('format_phone')) {
    /** 998901234567 -> "+998 90 123 45 67" */
    function format_phone(?string $phone): string
    {
        $p = preg_replace('/\D+/', '', (string) $phone);
        if (strlen($p) === 12) {
            return sprintf('+%s %s %s %s %s', substr($p, 0, 3), substr($p, 3, 2), substr($p, 5, 3), substr($p, 8, 2), substr($p, 10, 2));
        }

        return (string) $phone;
    }
}

if (! function_exists('normalize_email')) {
    /** Takroriy demo'ni aniqlash uchun email'ni bir xil ko'rinishga keltiradi. */
    function normalize_email(?string $email): ?string
    {
        $email = strtolower(trim((string) $email));
        if ($email === '' || ! str_contains($email, '@')) {
            return null;
        }
        [$local, $domain] = explode('@', $email, 2);
        $local = explode('+', $local, 2)[0];
        if (in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
            $local = str_replace('.', '', $local);
            $domain = 'gmail.com';
        }

        return $local.'@'.$domain;
    }
}

if (! function_exists('kd_setting')) {
    /** Joriy bog'cha sozlamasi. */
    function kd_setting(string $key, mixed $default = null): mixed
    {
        return tenant()?->setting($key, $default) ?? $default;
    }
}

if (! function_exists('feature')) {
    /** Joriy bog'chada xususiyat yoqiqmi? */
    function feature(string $key): bool
    {
        return tenant()?->hasFeature($key) ?? false;
    }
}

if (! function_exists('kd_title')) {
    /** Sahifa sarlavhasi (@section('title')) ni joriy tilga tarjima qiladi. */
    function kd_title(string $default = ''): string
    {
        $t = trim(html_entity_decode(\Illuminate\Support\Facades\View::yieldContent('title'), ENT_QUOTES));

        return __($t !== "" ? $t : $default);
    }
}

if (! function_exists('kd_desc')) {
    /** Bazada saqlangan izoh ("Oylik to'lov", "Proporsional hisob: 10.2026") ni joriy tilga o'giradi. */
    function kd_desc(?string $s): ?string
    {
        if ($s === null || $s === '') {
            return $s;
        }
        $t = __($s);
        if ($t !== $s || ! str_contains($s, ': ')) {
            return $t;
        }
        [$head, $tail] = explode(': ', $s, 2);

        return __($head).': '.__($tail);
    }
}
