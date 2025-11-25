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
     * Remote URLs mapped to keys.
     *
     * @var array<string,string>
     */
    protected array $urls = [
        'countries+states' => 'https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/refs/heads/master/json/countries%2Bstates.json',
        'countries' => 'https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/refs/heads/master/json/countries.json',
        'cities' => 'https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/refs/heads/master/json/cities.json',
        'countries+cities' => 'https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/refs/heads/master/json/countries%2Bcities.json',
        'countries+states+cities' => 'https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/refs/heads/master/json/countries%2Bstates%2Bcities.json',
        'regions' => 'https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/refs/heads/master/json/regions.json',
        'states+cities' => 'https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/refs/heads/master/json/states%2Bcities.json',
        'states' => 'https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/refs/heads/master/json/states.json',
        'subregions' => 'https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/refs/heads/master/json/subregions.json',
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
        foreach ($this->urls as $key => $url) {
            $path = $this->files[$key];
            try {
                // 1) HEAD request to get Content-Length
                $head = Http::timeout(10)->head($url);

                if (! $head->successful()) {
                    Log::warning("sync:countries-states-json – HEAD {$url} returned {$head->status()}");
                    continue;
                }

                $remoteSize = (int) $head->header('Content-Length', 0);
                if ($remoteSize <= 0) {
                    Log::warning("sync:countries-states-json – remote size zero for {$url}");
                    continue;
                }

                // 2) Check local file size
                $localSize = Storage::disk('local')->exists($path)
                    ? Storage::disk('local')->size($path)
                    : 0;

                // 3) If sizes match, nothing to do
                if ($localSize === $remoteSize) {
                    continue;
                }

                // 4) Otherwise, download the new file
                $response = Http::timeout(30)->get($url);

                if (! $response->successful()) {
                    Log::warning("sync:countries-states-json – GET {$url} returned {$response->status()}");
                    continue;
                }

                Storage::disk('local')->put($path, $response->body());
            }
            catch (Throwable $e) {
                Log::error("sync:countries-states-json – error syncing {$url}: {$e->getMessage()}", [
                    'exception' => $e,
                ]);
            }
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
