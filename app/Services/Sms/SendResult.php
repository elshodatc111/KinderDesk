<?php

namespace App\Services\Sms;

class SendResult
{
    public function __construct(
        public bool $ok,
        public ?string $providerId = null,
        public ?string $error = null,
    ) {
    }

    public static function ok(?string $providerId = null): self
    {
        return new self(true, $providerId);
    }

    public static function fail(string $error): self
    {
        return new self(false, null, $error);
    }
}
