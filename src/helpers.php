<?php

if (!function_exists('getClientIP')) {
    function getClientIP()
    {
        $headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (isset($_SERVER[$header])) {
                return $_SERVER[$header];
            }
        }

        return 'UNKNOWN';
    }
}
