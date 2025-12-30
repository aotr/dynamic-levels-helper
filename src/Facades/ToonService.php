<?php

declare(strict_types=1);

namespace Aotr\DynamicLevelHelper\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * ToonService Facade
 * 
 * @method static string encode(mixed $data, array $options = [])
 * @method static mixed decode(string $toonString, array $options = [])
 * @method static string encodeArray(array $data, array $options = [])
 * @method static string encodeCollection(\Illuminate\Support\Collection $collection, array $options = [])
 * @method static \Illuminate\Support\Collection decodeToCollection(string $toonString, array $options = [])
 * @method static array getCompressionStats(mixed $data)
 * @method static array batchEncode(array $items, array $options = [])
 * @method static array batchDecode(array $toonStrings, array $options = [])
 * @method static string cacheEncode(string $key, mixed $data, int $ttl = 3600, array $options = [])
 * @method static mixed getCached(string $key, array $options = [])
 * @method static bool isValidToon(string $toonString)
 * @method static bool isAvailable()
 * @method static array getServiceInfo()
 * @method static bool clearCache()
 * 
 * @see \Aotr\DynamicLevelHelper\Services\ToonService
 */
class ToonService extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'toon-service';
    }
}