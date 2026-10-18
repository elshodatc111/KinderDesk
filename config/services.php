<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Platforma SMS (Eskiz) — OTP va direktorlarga ogohlantirishlar
    |--------------------------------------------------------------------------
    | driver: "log" (mahalliy sinov, SMS yuborilmaydi, storage/logs ga yoziladi)
    |         "eskiz" (haqiqiy yuborish)
    */
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
    ],

    'eskiz' => [
        'base_url' => env('ESKIZ_BASE_URL', 'https://notify.eskiz.uz/api'),
        'email' => env('ESKIZ_EMAIL'),
        'password' => env('ESKIZ_PASSWORD'),
        'from' => env('ESKIZ_FROM', '4546'),
        // Endpointlar Eskiz hujjatiga ko'ra o'zgarsa, shu yerdan to'g'rilanadi
        'endpoints' => [
            'login' => '/auth/login',
            'refresh' => '/auth/refresh',
            'me' => '/auth/user',
            'limit' => '/user/get-limit',
            'send' => '/message/sms/send',
            'status' => '/message/sms/status_by_id',
            'template_create' => '/user/template',
            'template_list' => '/user/templates',
        ],
        'callback_url' => env('ESKIZ_CALLBACK_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payme (Merchant API)
    |--------------------------------------------------------------------------
    */
    'payme' => [
        'merchant_id' => env('PAYME_MERCHANT_ID'),
        'key' => env('PAYME_KEY'),
        'test_key' => env('PAYME_TEST_KEY'),
        'test_mode' => (bool) env('PAYME_TEST_MODE', true),
        'checkout_url' => env('PAYME_CHECKOUT_URL', 'https://checkout.paycom.uz'),
        'test_checkout_url' => env('PAYME_TEST_CHECKOUT_URL', 'https://checkout.test.paycom.uz'),
        // Payme so'rovlari faqat shu IP oralig'idan keladi (bo'sh qoldirsangiz tekshirilmaydi)
        'allowed_ips' => array_filter(explode(',', (string) env('PAYME_ALLOWED_IPS', ''))),
        'timeout_ms' => 43_200_000, // 12 soat
    ],

    'postmark' => ['key' => env('POSTMARK_API_KEY')],
    'resend' => ['key' => env('RESEND_API_KEY')],
    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
