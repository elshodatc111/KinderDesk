<?php

namespace App\Services\Guardians;

class PhoneHash
{
    public static function make(?string $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        return $digits === '' ? null : hash_hmac('sha256', $digits, (string) config('app.key'));
    }
}
