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

        // 4) Download the file
        $response = Http::timeout(60)->get($url);

        if (!$response->successful()) {
            Log::warning("sync:countries-states-json – GET {$url} returned {$response->status()}");
            return false;
        }

        $content = $response->body();

        // 5) Decompress if gzipped
        if ($isGzipped) {
            $decompressed = @gzdecode($content);
            if ($decompressed === false) {
                Log::error("sync:countries-states-json – failed to decompress gzip file {$url}");
                return false;
            }
            $content = $decompressed;
        }

        Storage::disk('local')->put($path, $content);
        return true;
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
