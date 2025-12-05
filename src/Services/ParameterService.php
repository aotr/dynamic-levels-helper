<?php
declare(strict_types=1);

namespace Aotr\DynamicLevelHelper\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use InvalidArgumentException;

/**
 * Parameter Service for processing and formatting request data into delimited strings
 *
 * This service provides multiple ways to convert array data or Laravel Request objects
 * into formatted parameter strings, commonly used for stored procedure parameters.
 */
class ParameterService
{
    /**
     * Join a list of parameters into a single string.
     *
     * @param  array|Request|string    $data       An array of data, a Laravel Request, or a pre-built string.
     * @param  array<string|int>|null  $sequence   If given, only these keys (in order); if null, all keys.
     * @param  string                  $delimiter  What to join values with (default: '^^').
     * @return string                              The concatenated result, or '' if there’s nothing.
     *
     * @throws InvalidArgumentException            If you pass something other than array|Request|string,
     *                                             or if your sequence contains non–string/int keys.
     */
    public static function process(
        array|Request|string $data,
        array|null           $sequence  = null,
        string               $delimiter = '^^'
    ): string {
        // 1) If it's already a string, assume it's your final "param_list"
        if (is_string($data)) {
            return $data;
        }

        // 2) If it’s a Request, grab all its input
        if ($data instanceof Request) {
            $data = $data->all();
        }

        // 3) Now it MUST be an array
        if (! is_array($data)) {
            throw new InvalidArgumentException(sprintf(
                'ParameterService::process expects array|Request|string; %s given.',
                get_debug_type($data)
            ));
        }

        // 4) If empty, bail early
        if (empty($data)) {
            return '';
        }

        // 5) Determine which keys to pull
        $keys = $sequence ?? array_keys($data);
        if (! is_array($keys)) {
            throw new InvalidArgumentException(sprintf(
                'ParameterService::process sequence must be an array; %s given.',
                get_debug_type($keys)
            ));
        }

        // 6) Extract & cast each value
        $values = [];
        foreach ($keys as $key) {
            if (! is_string($key) && ! is_int($key)) {
                throw new InvalidArgumentException(sprintf(
                    'Sequence keys must be string|int; %s given.',
                    get_debug_type($key)
                ));
            }
            // Arr::get handles nested keys (e.g. 'user.id')
            $raw = Arr::get($data, $key, '');
            $values[] = is_scalar($raw) ? (string) $raw : '';
        }

        // 7) If *all* values are empty, return '' instead of '^^^'
        if (count(array_filter($values, fn($v) => $v !== '')) === 0) {
            return '';
        }

        // 8) Otherwise glue them up
        return implode($delimiter, $values);
    }

    /**
     * Simple parameter processing with '^^' delimiter (legacy approach)
     *
     * Converts request or array data into a string joined by '^^'.
     * This method provides a simpler API compared to the main process() method.
     *
     * @param array|Request $request The input data (array or Laravel Request)
     * @param array $sequence Optional list of keys to extract in order
     * @return string Concatenated string of parameter values
     * @throws InvalidArgumentException If invalid input type is provided
     */
    public static function processSimple(array|Request $request, array $sequence = []): string
    {
        // Validate input type
        if (!is_array($request) && !($request instanceof Request)) {
            throw new InvalidArgumentException(sprintf(
                'ParameterService::processSimple expects array|Request; %s given.',
                get_debug_type($request)
            ));
        }

        $getValue = function ($key) use ($request) {
            // Get the value from array or Request
            $value = is_array($request)
                ? ($request[$key] ?? '')
                : $request->input($key, '');

            // Force string conversion for scalar types, leave non-scalar as empty string
            return is_scalar($value) ? (string) $value : '';
        };

        // If sequence of keys is provided
        if (!empty($sequence)) {
            return implode('^^', array_map($getValue, $sequence));
        }

        // If no sequence, get all values from the array or Request
        $values = is_array($request)
            ? array_values($request)
            : array_values($request->all());

        // Ensure all values are strings, ignore arrays/objects
        $cleanedValues = array_map(function ($value) {
            return is_scalar($value) ? (string) $value : '';
        }, $values);

        return implode('^^', $cleanedValues);
    }

    /**
     * Process parameters using the simple approach (alias for processSimple)
     *
     * @param array|Request $request The input data
     * @param array $sequence Optional list of keys to extract in order
     * @return string Concatenated string of parameter values
     */
    public static function quick(array|Request $request, array $sequence = []): string
    {
        return self::processSimple($request, $sequence);
    }

    /**
     * Create parameter string from specific values
     *
     * @param mixed ...$values Variable number of values to concatenate
     * @return string Concatenated string with '^^' delimiter
     */
    public static function fromValues(...$values): string
    {
        $cleanedValues = array_map(function ($value) {
            return is_scalar($value) ? (string) $value : '';
        }, $values);

        return implode('^^', $cleanedValues);
    }

    /**
     * Split a parameter string back into individual values
     *
     * @param string $parameterString The delimited parameter string
     * @param string $delimiter The delimiter used (default: '^^')
     * @return array Array of individual parameter values
     */
    public static function split(string $parameterString, string $delimiter = '^^'): array
    {
        if (empty($parameterString)) {
            return [];
        }

        return explode($delimiter, $parameterString);
    }

    /**
     * Validate that all required parameters are present
     *
     * @param array|Request $request The input data
     * @param array $requiredKeys List of required parameter keys
     * @return bool True if all required parameters are present and non-empty
     */
    public static function validateRequired(array|Request $request, array $requiredKeys): bool
    {
        $data = $request instanceof Request ? $request->all() : $request;

        foreach ($requiredKeys as $key) {
            $value = Arr::get($data, $key);
            if (empty($value) && $value !== '0' && $value !== 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get missing required parameters
     *
     * @param array|Request $request The input data
     * @param array $requiredKeys List of required parameter keys
     * @return array List of missing parameter keys
     */
    public static function getMissingRequired(array|Request $request, array $requiredKeys): array
    {
        $data = $request instanceof Request ? $request->all() : $request;
        $missing = [];

        foreach ($requiredKeys as $key) {
            $value = Arr::get($data, $key);
            if (empty($value) && $value !== '0' && $value !== 0) {
                $missing[] = $key;
            }
        }

        return $missing;
    }
}
