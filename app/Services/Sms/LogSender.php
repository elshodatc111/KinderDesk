<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/** Mahalliy sinov uchun: SMS yuborilmaydi, matn log'ga yoziladi. */
class LogSender implements SmsSender
{
    public function send(string $phone, string $message): SendResult
    {
        Log::info("[SMS:log] {$phone}: {$message}");

        return SendResult::ok('log-'.uniqid());
    }
}
