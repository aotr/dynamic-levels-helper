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
     * Format amounts with Indian currency conventions and extensive customization options.
     *
     * @param mixed $amount The amount to format (int, float, string, null)
     * @param array $options Configuration options
     * @return string Formatted amount string
     */
    function amount_format($amount, array $options = []): string
    {
        // Safe input handling - convert to float or default to 0
        $numericAmount = 0.0;
        try {
            if (is_numeric($amount)) {
                $numericAmount = (float) $amount;
            } elseif (is_string($amount) && !empty($amount)) {
                // Remove currency symbols and commas for parsing
                $cleanAmount = preg_replace('/[^\d.-]/', '', $amount);
                if (is_numeric($cleanAmount)) {
                    $numericAmount = (float) $cleanAmount;
                }
            }
        } catch (\Throwable $e) {
            // Fallback to 0 on any error
            $numericAmount = 0.0;
        }

        // Default options
        $defaults = [
            'symbol' => '₹',
            'decimals' => 2,
            'hide_zero_decimals' => true,
            'decimal_separator' => '.',
            'thousands_separator' => ',',
            'symbol_position' => 'before',
            'symbol_space' => false,
            'negative_format' => 'minus', // 'minus' or 'brackets'
        ];

        $config = array_merge($defaults, $options);

        // Handle negative formatting
        $isNegative = $numericAmount < 0;
        $absAmount = abs($numericAmount);

        // Format the number with Indian numbering
        $formattedNumber = formatIndianCurrencyCustom($absAmount, $config);

        // Apply currency symbol
        $symbol = $config['symbol'];
        $space = $config['symbol_space'] ? ' ' : '';

        if ($config['symbol_position'] === 'before') {
            $result = $symbol . $space . $formattedNumber;
        } else {
            $result = $formattedNumber . $space . $symbol;
        }

        // Apply negative formatting
        if ($isNegative) {
            if ($config['negative_format'] === 'brackets') {
                $result = '(' . $result . ')';
            } else {
                $result = '-' . $result;
            }
        }

        return $result;
    }
}

if (!function_exists('formatIndianCurrencyCustom')) {
    /**
     * Format numbers using Indian numbering system with custom configuration.
     *
     * @param float $amount The amount to format
     * @param array $config Configuration options
     * @return string Formatted number string
     */
    function formatIndianCurrencyCustom(float $amount, array $config = []): string
    {
        $defaults = [
            'decimals' => 2,
            'hide_zero_decimals' => true,
            'decimal_separator' => '.',
            'thousands_separator' => ',',
        ];

        $config = array_merge($defaults, $config);

        // Round to specified decimals
        $roundedAmount = round($amount, $config['decimals']);

        // Split into integer and decimal parts
        $parts = explode('.', (string) $roundedAmount);
        $integerPart = $parts[0];
        $decimalPart = isset($parts[1]) ? $parts[1] : '';

        // Apply Indian numbering to integer part
        $formattedInteger = applyIndianNumbering($integerPart, $config['thousands_separator']);

        // Handle decimal part
        $result = $formattedInteger;

        if (!empty($decimalPart) && $decimalPart !== '0') {
            // Pad decimal part if necessary
            $decimalPart = str_pad($decimalPart, $config['decimals'], '0', STR_PAD_RIGHT);
            $result .= $config['decimal_separator'] . $decimalPart;
        } elseif (!$config['hide_zero_decimals'] && $config['decimals'] > 0) {
            $result .= $config['decimal_separator'] . str_repeat('0', $config['decimals']);
        }

        return $result;
    }
}

if (!function_exists('applyIndianNumbering')) {
    /**
     * Apply Indian numbering system comma placement.
     *
     * @param string $number The number as string
     * @param string $separator The thousands separator
     * @return string Number with Indian comma placement
     */
    function applyIndianNumbering(string $number, string $separator = ','): string
    {
        // Remove any existing separators
        $number = preg_replace('/[^\d]/', '', $number);

        if (empty($number)) {
            return '0';
        }

        $length = strlen($number);

        // Handle numbers less than 1000
        if ($length <= 3) {
            return $number;
        }

        $result = '';

        // Process from right to left
        for ($i = $length - 1, $count = 0; $i >= 0; $i--, $count++) {
            $result = $number[$i] . $result;

            // Add separator after every 2 digits for the first group, then every 2 digits
            if ($count === 2 && $i > 0) {
                $result = $separator . $result;
                $count = 0;
            }
        }

        return $result;
    }
}
