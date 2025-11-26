<?php

declare(strict_types=1);

namespace Aotr\DynamicLevelHelper\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SyncCountriesAndStatesJsonFilesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:countries-states-json';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch (or re-fetch) countries.json and countries+states.json if missing or changed';

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
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->info('Syncing country and state JSON files...');

        foreach ($this->remoteFiles as $key => $remoteFile) {
            $path = $this->files[$key];
            $url = $this->baseUrl . $remoteFile;
            $gzUrl = $url . '.gz';

            try {
                $this->info("Checking {$key}…");

                // Try regular JSON file first
                $result = $this->tryDownloadFile($url, $path, $key, false);

                // If regular JSON returns 404, try .gz version
                if ($result === 404) {
                    $this->line("  ↳ JSON not found, trying gzip version...");
                    Log::info("sync:countries-states-json – JSON not found, trying gzip version for {$key}");
                    $this->tryDownloadFile($gzUrl, $path, $key, true);
                }
            } catch (Throwable $e) {
                $this->error("  ✖ Exception: " . $e->getMessage());
                Log::error("sync:countries-states-json – error syncing {$key}: {$e->getMessage()}", [
                    'exception' => $e,
                ]);
            }
        }

        $this->info('Done.');
        return 0;
    }

    /**
     * Try to download a file from URL.
     *
     * @param string $url
     * @param string $path
     * @param string $key
     * @param bool $isGzipped
     * @return int|bool Returns 404 if not found, true on success, false on other failure
     */
    protected function tryDownloadFile(string $url, string $path, string $key, bool $isGzipped): int|bool
    {
        // 1) HEAD request to check availability and get Content-Length
        $head = Http::timeout(10)->head($url);

        if ($head->status() === 404) {
            return 404;
        }

        if (!$head->successful()) {
            $this->error("  ✖ HEAD failed with status {$head->status()}");
            Log::warning("sync:countries-states-json – HEAD {$url} returned {$head->status()}");
            return false;
        }

        $remoteSize = (int) $head->header('Content-Length', 0);

        // For gzipped files, we can't compare sizes directly since decompressed size differs
        if (!$isGzipped) {
            if ($remoteSize <= 0) {
                $this->error("  ✖ Remote file size is zero or missing, skipping");
                Log::warning("sync:countries-states-json – remote size zero for {$url}");
                return false;
            }

            // 2) Check local file size
            $localSize = Storage::disk('local')->exists($path)
                ? Storage::disk('local')->size($path)
                : 0;

            // 3) If sizes match, nothing to do
            if ($localSize === $remoteSize) {
                $this->info("  ✓ Up-to-date (size {$remoteSize} bytes)");
                return true;
            }

            $this->info("  ↓ Downloading (local: {$localSize} → remote: {$remoteSize})");
        } else {
            $this->info("  ↓ Downloading gzipped version...");
        }

        // 4) Download the file
        $response = Http::timeout(60)->get($url);

        if (!$response->successful()) {
            $this->error("  ✖ GET failed with status {$response->status()}");
            Log::warning("sync:countries-states-json – GET {$url} returned {$response->status()}");
            return false;
        }

        $content = $response->body();

        // 5) Decompress if gzipped
        if ($isGzipped) {
            $decompressed = @gzdecode($content);
            if ($decompressed === false) {
                $this->error("  ✖ Failed to decompress gzip file");
                Log::error("sync:countries-states-json – failed to decompress gzip file {$url}");
                return false;
            }
            $content = $decompressed;
        }

        Storage::disk('local')->put($path, $content);
        $this->info("  ✔ Saved to storage/app/{$path}");
        return true;
    }
}
