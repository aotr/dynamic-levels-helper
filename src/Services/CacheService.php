<?php

namespace Aotr\DynamicLevelHelper\Services;

use Illuminate\Support\Facades\Cache;

class CacheService
{
    /**
     * Generate a unique cache key based on the validated request data.
     *
     * @param  array  $validatedData
     * @param  string  $type
     * @return string
     */
    public function generateCacheKey(array $validatedData, string $type = 'response'): string
    {
        // Serialize the key parts to ensure uniqueness
        $keyParts = array_merge(
            $validatedData,
            ['type' => $type]
        );

        return 'api_cache:' . md5(serialize($keyParts));
    }

    /**
     * Retrieve data from the cache.
     *
     * @param  string  $key
     * @return mixed|null
     */
    public function getCache(string $key)
    {
        return Cache::get($key);
    }

    /**
     * Store data in the cache.
     *
     * @param  string  $key
     * @param  mixed  $data
     * @param  \DateTime|int  $ttl
     * @return void
     */
    public function setCache(string $key, $data, $ttl): void
    {
        Cache::put($key, $data, $ttl);
    }

    /**
     * Invalidate one or more cache keys.
     *
     * @param  array  $keys
     * @return void
     */
    public function invalidateCache(array $keys): void
    {
        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Refresh cache by regenerating the data.
     *
     * @param  string  $key
     * @param  callable  $dataGenerator
     * @param  \DateTime|int  $ttl
     * @return mixed
     */
    public function refreshCache(string $key, callable $dataGenerator, $ttl)
    {
        $data = $dataGenerator();
        $this->setCache($key, $data, $ttl);

        return $data;
    }

    /**
     * Cache data only if it doesn't already exist.
     *
     * @param  string  $key
     * @param  callable  $dataGenerator
     * @param  \DateTime|int  $ttl
     * @return mixed
     */
    public function cacheIfNotExists(string $key, callable $dataGenerator, $ttl)
    {
        return Cache::remember($key, $ttl, $dataGenerator);
    }
}
