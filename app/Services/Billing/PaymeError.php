<?php

namespace App\Services\Billing;

class PaymeError extends \Exception
{
    public function __construct(int $code, string $message, public ?string $data = null)
    {
        parent::__construct($message, $code);
    }
}
