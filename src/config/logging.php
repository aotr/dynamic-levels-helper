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
    ],
];
