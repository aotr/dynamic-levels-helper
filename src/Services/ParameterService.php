<?php
declare(strict_types=1);

namespace Aotr\DynamicLevelHelper\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use InvalidArgumentException;

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
}
