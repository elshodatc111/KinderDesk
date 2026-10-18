<?php

return [

    /*
    | Real-time uchun Laravel Reverb (WebSocket) ishlatiladi.
    | Reverb ishlamasa ham tizim ishlaydi: brauzer avtomatik "polling" rejimiga o'tadi.
    */
    'default' => env('BROADCAST_CONNECTION', 'null'),

    // Brauzer Reverb'ga ulanadigan manzil (bo'sh bo'lsa — joriy sayt manzili)
    'public_host' => env('REVERB_PUBLIC_HOST'),

    'connections' => [

        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                'host' => env('REVERB_HOST', '127.0.0.1'),
                'port' => env('REVERB_PORT', 8080),
                'scheme' => env('REVERB_SCHEME', 'http'),
                'useTLS' => env('REVERB_SCHEME', 'http') === 'https',
            ],
            'client_options' => [
                'timeout' => 2,
                'connect_timeout' => 1,
            ],
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
