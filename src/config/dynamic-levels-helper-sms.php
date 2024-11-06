<?php

return [
    'default_provider' => env('SMS_PROVIDER', 'myvaluefirst'),

    'providers' => [
        'sinfini' => [
            'url' => env('SINFINI_URL'),
            'format' => [
                'method' => 'sms',
                'api_key' => env('SINFINI_API_KEY'),
                'to' => '',
                'sender' => env('SINFINI_SENDER'),
                'message' => '',
                'custom' => '',
                'flash' => env('SINFINI_FLASH', '0'),
            ],
        ],

        'onex' => [
            'url' => env('ONEX_URL'),
            'format' => [
                'username' => env('ONEX_USERNAME'),
                'password' => env('ONEX_PASSWORD'),
                'to' => '',
                'from' => env('ONEX_FROM'),
                'text' => '',
            ],
        ],

        'myvaluefirst' => [
            'url' => env('MYVALUEFIRST_URL'),
            'format' => [
                'username' => env('MYVALUEFIRST_USERNAME'),
                'password' => env('MYVALUEFIRST_PASSWORD'),
                'to' => '',
                'from' => env('MYVALUEFIRST_FROM'),
                'text' => '',
                'dlr-mask' => env('MYVALUEFIRST_DLR_MASK', '19'),
            ],
        ],

        'infobip' => [
            'url' => env('INFOBIP_URL'),
            'api_url' => env('INFOBIP_API_URL'),
            'format' => [
                'username' => env('INFOBIP_USERNAME'),
                'password' => env('INFOBIP_PASSWORD'),
                'indiaDltContentTemplateId' => env('INFOBIP_DLT_TEMPLATE_ID'),
                'indiaDltPrincipalEntityId' => env('INFOBIP_DLT_ENTITY_ID'),
                'to' => '',
                'from' => env('INFOBIP_FROM'),
                'text' => '',
            ],
        ],
    ],
];
