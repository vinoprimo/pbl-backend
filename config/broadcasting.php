<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    */
    'default' => env('BROADCAST_DRIVER', 'pusher'),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    */
    'connections' => [
        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'useTLS' => true,
                // For local development, you can use these options:
                // 'host' => env('PUSHER_HOST', '127.0.0.1'),
                // 'port' => env('PUSHER_PORT', 6001),
                // 'scheme' => env('PUSHER_SCHEME', 'https'),
                'encrypted' => true,
                'curl_options' => [
                    CURLOPT_SSL_VERIFYHOST => env('APP_ENV') === 'local' ? 0 : 2,
                    CURLOPT_SSL_VERIFYPEER => env('APP_ENV') === 'local' ? 0 : 1,
                ],
            ],
            'client_options' => [
                'timeout' => 30,
            ],
        ],

        'ably' => [
            'driver' => 'ably',
            'key' => env('ABLY_KEY'),
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],
    ],
];
