<?php

namespace App\Services\Ai;

/** AI ishlatib bo'lmaydi: sabab foydalanuvchiga ko'rsatiladigan matn. */
class AiException extends \RuntimeException
{
    public function __construct(string $message, public readonly string $reason = 'unavailable')
    {
        parent::__construct($message);
    }
}
