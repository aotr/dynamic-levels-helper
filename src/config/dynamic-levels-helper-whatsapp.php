<?php

return [
    'default_provider' => env('WHATSAPP_PROVIDER', 'goinfinito'),

    "goinfinito"=>[
        'api_url' => env('GOINFINITO_API_URL', 'https://api.goinfinito.com/unified/v2/send'),
        'api_token' => env('GOINFINITO_API_TOKEN'),
        'from_number' => env('GOINFINITO_FROM_NUMBER'),
    ]

];
