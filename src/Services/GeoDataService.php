<?php

declare(strict_types=1);

namespace Aotr\DynamicLevelHelper\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GeoDataService
{
    /**
     * Base URL for remote files.
     *
     * @var string
     */
    protected string $baseUrl = 'https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/refs/heads/master/json/';

    /**
     * Remote file names mapped to keys (URL-encoded where needed).
     *
     * @var array<string,string>
     */
    protected array $remoteFiles = [
        'countries+states' => 'countries%2Bstates.json',
        'countries' => 'countries.json',
        'cities' => 'cities.json',
        'countries+cities' => 'countries%2Bcities.json',
        'countries+states+cities' => 'countries%2Bstates%2Bcities.json',
        'regions' => 'regions.json',
        'states+cities' => 'states%2Bcities.json',
        'states' => 'states.json',
        'subregions' => 'subregions.json',
    ];

    /**
     * Local storage paths mapped to keys.
     *
     * @var array<string,string>
     */
    protected array $files = [
        'countries+states' => 'remote/countries+states.json',
        'countries' => 'remote/countries.json',
        'cities' => 'remote/cities.json',
        'countries+cities' => 'remote/countries+cities.json',
        'countries+states+cities' => 'remote/countries+states+cities.json',
        'regions' => 'remote/regions.json',
        'states+cities' => 'remote/states+cities.json',
        'states' => 'remote/states.json',
        'subregions' => 'remote/subregions.json',
    ];

    /**
     * Sync the JSON files from remote.
     */
    public function sync(): void
    {
        foreach ($this->remoteFiles as $key => $remoteFile) {
            $path = $this->files[$key];
            $url = $this->baseUrl . $remoteFile;
            $gzUrl = $url . '.gz';

            try {
                // Try regular JSON file first
                $result = $this->tryDownloadFile($url, $path, false);

                // If regular JSON returns 404, try .gz version
                if ($result === 404) {
                    Log::info("sync:countries-states-json – JSON not found, trying gzip version for {$key}");
                    $this->tryDownloadFile($gzUrl, $path, true);
                }
            } catch (Throwable $e) {
                Log::error("sync:countries-states-json – error syncing {$key}: {$e->getMessage()}", [
                    'exception' => $e,
                ]);
            }
        }
    }

    /**
     * Try to download a file from URL.
     *
     * @param string $url
     * @param string $path
     * @param bool $isGzipped
     * @return int|bool Returns 404 if not found, true on success, false on other failure
     */
    protected function tryDownloadFile(string $url, string $path, bool $isGzipped): int|bool
    {
        // 1) HEAD request to check availability and get Content-Length
        $head = Http::timeout(10)->head($url);

        if ($head->status() === 404) {
            return 404;
        }

        if (!$head->successful()) {
            Log::warning("sync:countries-states-json – HEAD {$url} returned {$head->status()}");
            return false;
        }

        $remoteSize = (int) $head->header('Content-Length', 0);

        // For gzipped files, we can't compare sizes directly since decompressed size differs
        if (!$isGzipped) {
            if ($remoteSize <= 0) {
                Log::warning("sync:countries-states-json – remote size zero for {$url}");
                return false;
            }

            // 2) Check local file size
            $localSize = Storage::disk('local')->exists($path)
                ? Storage::disk('local')->size($path)
                : 0;

            // 3) If sizes match, nothing to do
            if ($localSize === $remoteSize) {
                return true;
            }
        }

        // 4) Download the file using streaming to avoid memory issues
        if ($isGzipped) {
            return $this->streamDownloadGzip($url, $path);
        }

        return $this->streamDownload($url, $path);
    }

    /**
     * Stream download a regular file directly to storage.
     *
     * @param string $url
     * @param string $path
     * @return bool
     */
    protected function streamDownload(string $url, string $path): bool
    {
        $storagePath = Storage::disk('local')->path($path);
        $this->ensureDirectoryExists(dirname($storagePath));

        $fp = fopen($storagePath, 'w');
        if (!$fp) {
            Log::error("sync:countries-states-json – failed to open file for writing: {$storagePath}");
            return false;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_FAILONERROR => true,
        ]);

        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);
        fclose($fp);

        if (!$success || $httpCode >= 400) {
            @unlink($storagePath);
            Log::warning("sync:countries-states-json – stream download failed for {$url}: {$error}");
            return false;
        }

        return true;
    }

    /**
     * Stream download and decompress a gzip file to storage.
     * Uses file-based decompression to avoid memory issues with large files.
     *
     * @param string $url
     * @param string $path
     * @return bool
     */
    protected function streamDownloadGzip(string $url, string $path): bool
    {
        $storagePath = Storage::disk('local')->path($path);
        $tempGzPath = $storagePath . '.gz.tmp';
        $this->ensureDirectoryExists(dirname($storagePath));

        // Step 1: Download gzip file to temp location
        $fp = fopen($tempGzPath, 'w');
        if (!$fp) {
            Log::error("sync:countries-states-json – failed to open temp file for writing: {$tempGzPath}");
            return false;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 600, // 10 minutes for large files
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_FAILONERROR => true,
        ]);

        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);
        fclose($fp);

        if (!$success || $httpCode >= 400) {
            @unlink($tempGzPath);
            Log::warning("sync:countries-states-json – gzip download failed for {$url}: {$error}");
            return false;
        }

        // Step 2: Decompress using streaming (file-based, not memory-based)
        $result = $this->decompressGzipFile($tempGzPath, $storagePath);

        // Cleanup temp file
        @unlink($tempGzPath);

        return $result;
    }

    /**
     * Decompress a gzip file to destination using streaming.
     *
     * @param string $gzPath
     * @param string $destPath
     * @return bool
     */
    protected function decompressGzipFile(string $gzPath, string $destPath): bool
    {
        $gzHandle = gzopen($gzPath, 'rb');
        if (!$gzHandle) {
            Log::error("sync:countries-states-json – failed to open gzip file: {$gzPath}");
            return false;
        }

        $destHandle = fopen($destPath, 'w');
        if (!$destHandle) {
            gzclose($gzHandle);
            Log::error("sync:countries-states-json – failed to open destination file: {$destPath}");
            return false;
        }

        // Read and write in chunks to keep memory usage low
        $chunkSize = 1024 * 1024; // 1MB chunks
        while (!gzeof($gzHandle)) {
            $chunk = gzread($gzHandle, $chunkSize);
            if ($chunk === false) {
                break;
            }
            fwrite($destHandle, $chunk);
        }

        gzclose($gzHandle);
        fclose($destHandle);

        return true;
    }

    /**
     * Ensure a directory exists.
     *
     * @param string $dir
     * @return void
     */
    protected function ensureDirectoryExists(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * Get countries data.
     *
     * @return array|null
     */
    public function getCountries(): ?array
    {
        return $this->loadJson('countries');
    }

    /**
     * Get countries with states data.
     *
     * @return array|null
     */
    public function getCountriesWithStates(): ?array
    {
        return $this->loadJson('countries+states');
    }

    /**
     * Get cities data.
     *
     * @return array|null
     */
    public function getCities(): ?array
    {
        return $this->loadJson('cities');
    }

    /**
     * Get countries with cities data.
     *
     * @return array|null
     */
    public function getCountriesWithCities(): ?array
    {
        return $this->loadJson('countries+cities');
    }

    /**
     * Get countries with states and cities data.
     *
     * @return array|null
     */
    public function getCountriesStatesCities(): ?array
    {
        return $this->loadJson('countries+states+cities');
    }

    /**
     * Get regions data.
     *
     * @return array|null
     */
    public function getRegions(): ?array
    {
        return $this->loadJson('regions');
    }

    /**
     * Get states with cities data.
     *
     * @return array|null
     */
    public function getStatesWithCities(): ?array
    {
        return $this->loadJson('states+cities');
    }

    /**
     * Get states data.
     *
     * @return array|null
     */
    public function getStates(): ?array
    {
        return $this->loadJson('states');
    }

    /**
     * Get subregions data.
     *
     * @return array|null
     */
    public function getSubregions(): ?array
    {
        return $this->loadJson('subregions');
    }

    /**
     * Load JSON data from storage with caching.
     *
     * @param string $key
     * @return array|null
     */
    protected function loadJson(string $key): ?array
    {
        $path = $this->files[$key] ?? null;
        if (!$path) {
            return null;
        }

        return Cache::remember("geo_data_{$key}", 3600, function () use ($path) {
            if (Storage::disk('local')->exists($path)) {
                $content = Storage::disk('local')->get($path);
                return json_decode($content, true);
            }
            return null;
        });
    }
}
