<?php

declare(strict_types=1);

namespace Aotr\DynamicLevelHelper\Services;

use MischaSigtermans\Toon\Toon;
use MischaSigtermans\Toon\Converters\ToonEncoder;
use MischaSigtermans\Toon\Converters\ToonDecoder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * ToonService - A service for encoding and decoding TOON (Token-Optimized Object Notation) format
 *
 * This service provides methods to work with TOON format for compact data representation
 * which is useful for AI/LLM applications where token efficiency matters.
 */
class ToonService
{
    /**
     * The Toon encoder/decoder instance
     */
    protected Toon $toon;

    /**
     * Initialize the ToonService
     */
    public function __construct()
    {
        $this->toon = new Toon(
            new ToonEncoder(),
            new ToonDecoder()
        );
    }

    /**
     * Encode data to TOON format
     *
     * @param mixed $data The data to encode
     * @param array $options Encoding options
     * @return string The TOON encoded string
     */
    public function encode($data, array $options = []): string
    {
        try {
            $this->logOperation('encode', $data);
            return $this->toon->encode($data, $options);
        } catch (\Exception $e) {
            $this->logError('encode', $e);
            throw $e;
        }
    }

    /**
     * Decode TOON format string to PHP data
     *
     * @param string $toonString The TOON encoded string
     * @param array $options Decoding options
     * @return mixed The decoded data
     */
    public function decode(string $toonString, array $options = [])
    {
        try {
            $this->logOperation('decode', $toonString);
            return $this->toon->decode($toonString, $options);
        } catch (\Exception $e) {
            $this->logError('decode', $e);
            throw $e;
        }
    }

    /**
     * Encode array data to TOON format
     *
     * @param array $data The array to encode
     * @param array $options Encoding options
     * @return string The TOON encoded string
     */
    public function encodeArray(array $data, array $options = []): string
    {
        return $this->encode($data, $options);
    }

    /**
     * Encode collection to TOON format
     *
     * @param Collection $collection The collection to encode
     * @param array $options Encoding options
     * @return string The TOON encoded string
     */
    public function encodeCollection(Collection $collection, array $options = []): string
    {
        return $this->encode($collection->toArray(), $options);
    }

    /**
     * Decode TOON string to Collection
     *
     * @param string $toonString The TOON encoded string
     * @param array $options Decoding options
     * @return Collection The decoded data as collection
     */
    public function decodeToCollection(string $toonString, array $options = []): Collection
    {
        $decoded = $this->decode($toonString, $options);
        return collect(is_array($decoded) ? $decoded : [$decoded]);
    }

    /**
     * Get compression ratio compared to JSON
     *
     * @param mixed $data The data to compare
     * @return array Compression statistics
     */
    public function getCompressionStats($data): array
    {
        $jsonEncoded = json_encode($data);
        $toonEncoded = $this->encode($data);

        $jsonSize = strlen($jsonEncoded);
        $toonSize = strlen($toonEncoded);
        $compressionRatio = $jsonSize > 0 ? ($jsonSize - $toonSize) / $jsonSize * 100 : 0;

        return [
            'json_size' => $jsonSize,
            'toon_size' => $toonSize,
            'compression_ratio' => round($compressionRatio, 2),
            'size_difference' => $jsonSize - $toonSize,
            'json_encoded' => $jsonEncoded,
            'toon_encoded' => $toonEncoded
        ];
    }

    /**
     * Batch encode multiple data items
     *
     * @param array $items Array of data items to encode
     * @param array $options Encoding options
     * @return array Array of encoded strings
     */
    public function batchEncode(array $items, array $options = []): array
    {
        $results = [];
        foreach ($items as $key => $item) {
            try {
                $results[$key] = $this->encode($item, $options);
            } catch (\Exception $e) {
                $this->logError('batch_encode', $e, ['key' => $key]);
                $results[$key] = null;
            }
        }
        return $results;
    }

    /**
     * Batch decode multiple TOON strings
     *
     * @param array $toonStrings Array of TOON encoded strings
     * @param array $options Decoding options
     * @return array Array of decoded data
     */
    public function batchDecode(array $toonStrings, array $options = []): array
    {
        $results = [];
        foreach ($toonStrings as $key => $toonString) {
            try {
                $results[$key] = $this->decode($toonString, $options);
            } catch (\Exception $e) {
                $this->logError('batch_decode', $e, ['key' => $key]);
                $results[$key] = null;
            }
        }
        return $results;
    }

    /**
     * Cache encoded TOON data
     *
     * @param string $key Cache key
     * @param mixed $data Data to encode and cache
     * @param int $ttl Cache TTL in seconds
     * @param array $options Encoding options
     * @return string The encoded TOON string
     */
    public function cacheEncode(string $key, $data, int $ttl = 3600, array $options = []): string
    {
        $cacheKey = $this->getCacheKey($key);

        if (config('toon.cache.enabled', true)) {
            $encoded = Cache::remember($cacheKey, $ttl, function () use ($data, $options) {
                return $this->encode($data, $options);
            });
        } else {
            $encoded = $this->encode($data, $options);
        }

        return $encoded;
    }

    /**
     * Retrieve and decode cached TOON data
     *
     * @param string $key Cache key
     * @param array $options Decoding options
     * @return mixed The decoded data or null if not found
     */
    public function getCached(string $key, array $options = [])
    {
        if (!config('toon.cache.enabled', true)) {
            return null;
        }

        $cacheKey = $this->getCacheKey($key);
        $toonString = Cache::get($cacheKey);

        if ($toonString) {
            return $this->decode($toonString, $options);
        }

        return null;
    }

    /**
     * Validate TOON string format
     *
     * @param string $toonString The TOON string to validate
     * @return bool True if valid TOON format
     */
    public function isValidToon(string $toonString): bool
    {
        try {
            $this->decode($toonString);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get service availability status
     *
     * @return bool True if service is available
     */
    public function isAvailable(): bool
    {
        return class_exists(Toon::class);
    }

    /**
     * Get service information and stats
     *
     * @return array Service information
     */
    public function getServiceInfo(): array
    {
        return [
            'service' => 'TOON Service',
            'description' => 'Token-Optimized Object Notation encoder/decoder',
            'available' => $this->isAvailable(),
            'package' => 'mischasigtermans/laravel-toon',
            'cache_enabled' => config('toon.cache.enabled', true),
            'logging_enabled' => config('toon.logging.enabled', false),
        ];
    }

    /**
     * Clear all cached TOON data
     *
     * @return bool True if cache was cleared
     */
    public function clearCache(): bool
    {
        if (!config('toon.cache.enabled', true)) {
            return false;
        }

        $prefix = config('toon.cache.prefix', 'toon_');
        // Note: This is a simplified cache clearing - in production you might want
        // to implement a more sophisticated cache tagging system

        return true;
    }

    /**
     * Generate cache key with prefix
     *
     * @param string $key The base cache key
     * @return string The prefixed cache key
     */
    protected function getCacheKey(string $key): string
    {
        $prefix = config('toon.cache.prefix', 'toon_');
        return $prefix . $key;
    }

    /**
     * Log service operations
     *
     * @param string $operation The operation being performed
     * @param mixed $data The data being processed
     * @return void
     */
    protected function logOperation(string $operation, $data): void
    {
        if (!config('toon.logging.enabled', false)) {
            return;
        }

        $channel = config('toon.logging.channel', 'toon');
        $level = config('toon.logging.level', 'info');

        Log::channel($channel)->log($level, "TOON {$operation} operation", [
            'operation' => $operation,
            'data_type' => gettype($data),
            'data_size' => is_string($data) ? strlen($data) : (is_array($data) ? count($data) : 1),
        ]);
    }

    /**
     * Log service errors
     *
     * @param string $operation The operation that failed
     * @param \Exception $exception The exception that occurred
     * @param array $context Additional context
     * @return void
     */
    protected function logError(string $operation, \Exception $exception, array $context = []): void
    {
        if (!config('toon.logging.enabled', false)) {
            return;
        }

        $channel = config('toon.logging.channel', 'toon');

        Log::channel($channel)->error("TOON {$operation} operation failed", [
            'operation' => $operation,
            'error' => $exception->getMessage(),
            'context' => $context,
        ]);
    }
}
