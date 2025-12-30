<?php

return [
    'channels' => [
        'stp' => [
            'driver' => 'daily',
            'path' => storage_path('logs/data-service/stp.log'),
            'level' => 'debug',
            'days' => 7, // logs will be retained for 7 days
        ],
        'api' => [
            'driver' => 'daily',
            'path' => storage_path('logs/data-service/api.log'),
            'level' => 'debug',
            'days' => 7, // logs will be retained for 7 days
        ],
        'goinfinito' => [
            'driver' => 'daily',
            'path' => storage_path('logs/third_party/goinfinito/log.log'),
            'level' => 'debug',
            'days' => 7,
        ],
        'curl' => [
            'driver' => 'daily',
            'path' => storage_path('logs/external_curl/log.log'),
            'level' => 'debug',
            'days' => 7,
        ],
        'toon' => [
            'driver' => 'daily',
            'path' => storage_path('logs/third_party/toon/log.log'),
            'level' => 'debug',
            'days' => 7,
        ],
    ],
];
