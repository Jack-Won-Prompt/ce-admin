<?php

return [
    'default' => env('BROADCAST_CONNECTION', 'pusher'),

    'connections' => [
        'pusher' => [
            'driver'  => 'pusher',
            'key'     => env('PUSHER_APP_KEY'),
            'secret'  => env('PUSHER_APP_SECRET'),
            'app_id'  => env('PUSHER_APP_ID'),
            'options' => array_filter([
                'cluster' => env('PUSHER_APP_CLUSTER', 'ap3'),
                'useTLS'  => true,
                // PUSHER_HOST가 설정된 경우에만 host/port/scheme 적용 (Soketi 등 자체 호스팅용)
                'host'    => env('PUSHER_HOST') ?: null,
                'port'    => env('PUSHER_HOST') ? env('PUSHER_PORT', 443) : null,
                'scheme'  => env('PUSHER_HOST') ? env('PUSHER_SCHEME', 'https') : null,
            ], fn($v) => $v !== null),
        ],

        'log'  => ['driver' => 'log'],
        'null' => ['driver' => 'null'],
    ],
];
