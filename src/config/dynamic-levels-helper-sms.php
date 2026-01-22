<?php

return [
    'default_provider' => env('SMS_PROVIDER', 'myvaluefirst'),

    /*
    |--------------------------------------------------------------------------
    | Country to Provider Mappings
    |--------------------------------------------------------------------------
    |
    | Define which SMS provider should be used for specific country codes.
    | If a country code is not mapped here, the default_provider will be used.
    |
    | Example:
    |   91 => 'myvaluefirst',  // India uses MyValueFirst
    |   1 => 'onex',           // USA/Canada uses Onex
    |   44 => 'infobip',       // UK uses Infobip
    |
    */
    'country_mappings' => [
        91 => env('SMS_PROVIDER_91', 'internal'),       // India -> internal by default
        1 => env('SMS_PROVIDER_1', 'onex'),             // USA -> onex
        44 => env('SMS_PROVIDER_44', 'infobip'),        // UK -> infobip
        // Add more mappings as needed
    ],

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
            // If empty array [], this provider can send to any country
            // If specified [91, 1], only these country codes are allowed
            'expected_countries' => [],
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
            'expected_countries' => [],  // e.g., [1] to restrict to USA only
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
            'expected_countries' => [],  // e.g., [91] to restrict to India only
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
            'expected_countries' => [],
        ],

        'internal' => [
            'url' => env('INTERNAL_SMS_URL'),
            'format' => [
                'method' => 'sms',
                'api_key' => env('INTERNAL_SMS_API_KEY'),
                'to' => '',
                'sender' => env('INTERNAL_SMS_SENDER'),
                'message' => '',
                'custom' => '',
                'flash' => env('INTERNAL_SMS_FLASH', '0'),
            ],
            'expected_countries' => [91],  // Internal messaging only for India
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Whitelist Country Codes
    |--------------------------------------------------------------------------
    |
    | List of country codes that are allowed for SMS sending.
    | If validate_country_codes is enabled, only these countries can receive SMS.
    |
    */
    'whitelist_country_codes' => [
        91,   // India
        1,    // USA/Canada
        44,   // UK
        65,   // Singapore
        60,   // Malaysia
        971,  // UAE
        966,  // Saudi Arabia
    ],

    /*
    |--------------------------------------------------------------------------
    | Country Code Validation
    |--------------------------------------------------------------------------
    |
    | Enable or disable country code validation against whitelist.
    | When enabled, only whitelisted country codes can send SMS.
    |
    */
    'validate_country_codes' => env('SMS_VALIDATE_COUNTRY_CODES', false),
];
