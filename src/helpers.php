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
