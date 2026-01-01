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

if (!function_exists('addOrUpdateQueryParam')) {
/**
 * Intelligently adds or updates a query parameter in a given URL.
 *
 * This function correctly handles URLs with or without existing query strings,
 * overwrites parameters if they already exist, and preserves URL fragments (#).
 *
 * @param string $url The original URL to modify.
 * @param string $key The query parameter key to add or update.
 * @param string $value The value to set for the query parameter.
 * @return string The newly constructed URL.
 */
function addOrUpdateQueryParam(string $url, string $key, string $value): string
{
    // 1. Parse the URL into its components (scheme, host, path, query, fragment).
    // This is a much safer way to deconstruct a URL than using string functions.
    $parsedUrl = parse_url($url);

    // If parsing fails for any reason, return the original URL to avoid errors.
    if ($parsedUrl === false) {
        return $url;
    }

    // 2. Parse the existing query string into an associative array.
    // For example, 'foo=bar&baz=qux' becomes ['foo' => 'bar', 'baz' => 'qux'].
    $queryParams = [];
    if (isset($parsedUrl['query'])) {
        parse_str($parsedUrl['query'], $queryParams);
    }

    // 3. Add or update the parameter.
    // This is the core logic. If the key exists, its value is updated.
    // If it doesn't exist, it's added to the array.
    $queryParams[$key] = $value;

    // 4. Rebuild the query string from the (potentially modified) array.
    // http_build_query() automatically handles URL encoding.
    $newQueryString = http_build_query($queryParams);

    // 5. Reassemble the final URL from its components.
    $newUrl = '';
    // Start with scheme and host (e.g., "https://example.com")
    if (isset($parsedUrl['scheme'])) {
        $newUrl .= $parsedUrl['scheme'] . '://';
    }
    if (isset($parsedUrl['host'])) {
        $newUrl .= $parsedUrl['host'];
    }
    if (isset($parsedUrl['port'])) {
        $newUrl .= ':' . $parsedUrl['port'];
    }
    // Add the path (e.g., "/some/page")
    if (isset($parsedUrl['path'])) {
        $newUrl .= $parsedUrl['path'];
    }
    // Add the newly built query string
    if ($newQueryString) {
        $newUrl .= '?' . $newQueryString;
    }
    // Finally, re-attach the fragment if it existed (e.g., "#section1")
    if (isset($parsedUrl['fragment'])) {
        $newUrl .= '#' . $parsedUrl['fragment'];
    }

    return $newUrl;
}
}

if (!function_exists('amount_format')) {
    /**
     * Format amount in Indian currency format with customizable options.
     *
     * @param float|int|string|null $amount The amount to format
     * @param array $options Configuration options:
     *   - 'symbol' (string): Currency symbol, default '₹' (use '' for no symbol)
     *   - 'decimals' (int): Number of decimal places, default 2
     *   - 'hide_zero_decimals' (bool): Hide .00 decimals, default true
     *   - 'decimal_separator' (string): Decimal separator, default '.'
     *   - 'thousands_separator' (string): Thousands separator, default ','
     *   - 'symbol_position' (string): 'before' or 'after', default 'before'
     *   - 'symbol_space' (bool): Add space between symbol and amount, default false
     *   - 'negative_format' (string): 'minus' or 'brackets', default 'minus'
     * @return string Formatted amount string
     */
    function amount_format($amount, array $options = []): string
    {
        // Default configuration
        $defaults = [
            'symbol' => '₹',
            'decimals' => 2,
            'hide_zero_decimals' => true,
            'decimal_separator' => '.',
            'thousands_separator' => ',',
            'symbol_position' => 'before', // 'before' or 'after'
            'symbol_space' => false,
            'negative_format' => 'minus', // 'minus' or 'brackets'
        ];

        try {
            // Merge with defaults - use array_merge for safety
            $config = is_array($options) ? array_merge($defaults, $options) : $defaults;

            // Convert to float and handle null/empty values safely
            if (is_null($amount) || $amount === '') {
                $numericAmount = 0.0;
            } elseif (is_string($amount)) {
                // Remove any existing currency symbols and spaces for parsing
                $cleanAmount = preg_replace('/[^\d.-]/', '', $amount);
                $numericAmount = is_numeric($cleanAmount) ? (float) $cleanAmount : 0.0;
            } elseif (is_numeric($amount)) {
                $numericAmount = (float) $amount;
            } else {
                $numericAmount = 0.0;
            }

            // Handle negative amounts
            $isNegative = $numericAmount < 0;
            $absoluteAmount = abs($numericAmount);

            // Format using custom Indian currency formatter
            $formattedAmount = formatIndianCurrencyCustom($absoluteAmount, $config);

            // Add currency symbol if specified
            if (!empty($config['symbol'])) {
                $space = !empty($config['symbol_space']) ? ' ' : '';

                if (!empty($config['symbol_position']) && $config['symbol_position'] === 'after') {
                    $formattedAmount = $formattedAmount . $space . $config['symbol'];
                } else {
                    $formattedAmount = $config['symbol'] . $space . $formattedAmount;
                }
            }

            // Handle negative formatting
            if ($isNegative) {
                if (!empty($config['negative_format']) && $config['negative_format'] === 'brackets') {
                    $formattedAmount = '(' . $formattedAmount . ')';
                } else {
                    $formattedAmount = '-' . $formattedAmount;
                }
            }

            return $formattedAmount;

        } catch (\Throwable $e) {
            // Comprehensive error handling - return safe fallback
            try {
                return is_null($amount) ? '₹0' : (string) $amount;
            } catch (\Throwable $fallbackE) {
                return '₹0';
            }
        }
    }
}

if (!function_exists('formatIndianCurrencyCustom')) {
    /**
     * Custom Indian currency formatter with proper Indian numbering system.
     * Formats numbers with commas in Indian style: 1,00,000 (1 lakh), 1,00,00,000 (1 crore)
     *
     * @param float $number The number to format
     * @param array $config Configuration array with formatting options
     * @return string Formatted number string
     */
    function formatIndianCurrencyCustom($number, array $config = []): string
    {
        try {
            // Ensure safe defaults
            $decimals = isset($config['decimals']) && is_numeric($config['decimals']) ? (int) $config['decimals'] : 2;
            $decimalSeparator = isset($config['decimal_separator']) && is_string($config['decimal_separator']) ? $config['decimal_separator'] : '.';
            $thousandsSeparator = isset($config['thousands_separator']) && is_string($config['thousands_separator']) ? $config['thousands_separator'] : ',';
            $hideZeroDecimals = isset($config['hide_zero_decimals']) ? (bool) $config['hide_zero_decimals'] : true;

            // Ensure number is valid float
            $number = is_numeric($number) ? abs((float) $number) : 0.0;

            // Split into integer and decimal parts using PHP's number_format for safety
            $formattedNumber = number_format($number, $decimals, '.', '');
            $parts = explode('.', $formattedNumber);
            $integerPart = $parts[0] ?? '0';
            $decimalPart = isset($parts[1]) ? $parts[1] : str_repeat('0', $decimals);

            // Apply Indian numbering system to integer part
            $formattedInteger = applyIndianNumbering($integerPart, $thousandsSeparator);

            // Handle decimal part based on configuration
            if ($decimals > 0 && !empty($decimalPart)) {
                // Check if we should hide zero decimals
                if ($hideZeroDecimals && (int) $decimalPart === 0) {
                    return $formattedInteger;
                } else {
                    return $formattedInteger . $decimalSeparator . $decimalPart;
                }
            }

            return $formattedInteger;

        } catch (\Throwable $e) {
            // Safe fallback formatting
            try {
                return is_numeric($number) ? number_format((float) $number, 2) : '0';
            } catch (\Throwable $fallbackE) {
                return '0';
            }
        }
    }
}

if (!function_exists('applyIndianNumbering')) {
    /**
     * Apply Indian numbering system (lakhs, crores) to an integer string.
     * 
     * @param string $integerPart The integer part as string
     * @param string $separator The thousands separator to use
     * @return string Formatted integer with Indian numbering
     */
    function applyIndianNumbering(string $integerPart, string $separator = ','): string
    {
        try {
            // Handle empty or invalid input
            if (empty($integerPart) || !ctype_digit($integerPart)) {
                return '0';
            }

            // For numbers less than 1000, no formatting needed
            if (strlen($integerPart) <= 3) {
                return $integerPart;
            }

            // Reverse the string for easier processing
            $reversed = strrev($integerPart);
            $length = strlen($reversed);
            $formatted = '';

            // Apply Indian numbering: first 3 digits, then groups of 2
            for ($i = 0; $i < $length; $i++) {
                // Add separator after first 3 digits, then every 2 digits
                if ($i === 3 || ($i > 3 && ($i - 3) % 2 === 0)) {
                    $formatted .= $separator;
                }
                $formatted .= $reversed[$i];
            }

            // Reverse back to get the final result
            return strrev($formatted);

        } catch (\Throwable $e) {
            // Safe fallback
            return is_string($integerPart) && ctype_digit($integerPart) ? $integerPart : '0';
        }
    }
}
