<?php

return [
    'default_provider' => env('SMS_PROVIDER', 'myvaluefirst'),

    'providers' => [
        'sinfini' => [
            'url' => 'http://global.sinfini.com/api/v3/index.php?',
            'format' => [
                'method' => 'sms',
                'api_key' => 'A3c6d4c073dbf5a451b3714ff3f9e2f3b',
                'to' => '',
                'sender' => 'DYNAEQ',
                'message' => '',
                'custom' => '',
                'flash' => '0',
            ],
        ],

        'onex' => [
            'url' => 'http://203.212.70.200/smpp/sendsms?',
            'format' => [
                'username' => 'dynaeqapi',
                'password' => 'dynaeqapi123',
                'to' => '',
                'from' => 'VALSTK',
                'text' => '',
            ],
        ],

        'myvaluefirst' => [
            'url' => 'https://www.myvaluefirst.com/smpp/sendsms?dlr-url&',
            'format' => [
                'username' => 'DynamicEhtptran',
                'password' => 'Dyna@123',
                'to' => '',
                'from' => 'VALSTK',
                'text' => '',
                'dlr-mask' => '19',
            ],
        ],

        'infobip' => [
            'url' => 'https://lzq5ww.api.infobip.com/sms/1/text/query?',
            'api_url' => 'https://lzq5ww.api.infobip.com/sms/2/text/advanced',
            'format' => [
                'username' => 'ValueStock',
                'password' => 'Dynamic@1234',
                'indiaDltContentTemplateId' => '1707161475145434548',
                'indiaDltPrincipalEntityId' => '1701157866538664127',
                'to' => '',
                'from' => 'VALSTK',
                'text' => '',
            ],
        ],
    ],
];
