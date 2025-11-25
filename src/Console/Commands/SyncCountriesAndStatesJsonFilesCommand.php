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
     * Remote URLs mapped to local storage paths.
     *
     * @var array<string,string>
     */
    protected array $files = [
        'https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/refs/heads/master/json/countries%2Bstates.json'
            => 'remote/countries+states.json',
        'https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/refs/heads/master/json/countries.json'
            => 'remote/countries.json',
        'https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/refs/heads/master/json/cities.json'
            => 'remote/cities.json',
        'https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/refs/heads/master/json/countries%2Bcities.json'
            => 'remote/countries+cities.json',
        'https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/refs/heads/master/json/countries%2Bstates%2Bcities.json'
            => 'remote/countries+states+cities.json',
        'https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/refs/heads/master/json/regions.json'
            => 'remote/regions.json',
        'https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/refs/heads/master/json/states%2Bcities.json'
            => 'remote/states+cities.json',
        'https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/refs/heads/master/json/states.json'
            => 'remote/states.json',
        'https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/refs/heads/master/json/subregions.json'
            => 'remote/subregions.json',
    ];

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        foreach ($this->files as $url => $path) {
            try {
                $this->info("Checking {$url}…");

                // 1) HEAD request to get Content-Length
                $head = Http::timeout(10)->head($url);

                if (! $head->successful()) {
                    $this->error("  ✖ HEAD failed with status {$head->status()}");
                    Log::warning("sync:countries-states-json – HEAD {$url} returned {$head->status()}");
                    continue;
                }

                $remoteSize = (int) $head->header('Content-Length', 0);
                if ($remoteSize <= 0) {
                    $this->error("  ✖ Remote file size is zero or missing, skipping");
                    Log::warning("sync:countries-states-json – remote size zero for {$url}");
                    continue;
                }

                // 2) Check local file size
                $localSize = Storage::disk('local')->exists($path)
                    ? Storage::disk('local')->size($path)
                    : 0;

                // 3) If sizes match, nothing to do
                if ($localSize === $remoteSize) {
                    $this->info("  ✓ Up-to-date (size {$remoteSize} bytes)");
                    continue;
                }

                // 4) Otherwise, download the new file
                $this->info("  ↓ Downloading (local: {$localSize} → remote: {$remoteSize})");
                $response = Http::timeout(30)->get($url);

                if (! $response->successful()) {
                    $this->error("  ✖ GET failed with status {$response->status()}");
                    Log::warning("sync:countries-states-json – GET {$url} returned {$response->status()}");
                    continue;
                }

                Storage::disk('local')->put($path, $response->body());
                $this->info("  ✔ Saved to storage/app/{$path}");
            }
            catch (Throwable $e) {
                $this->error("  ✖ Exception: " . $e->getMessage());
                Log::error("sync:countries-states-json – error syncing {$url}: {$e->getMessage()}", [
                    'exception' => $e,
                ]);
            }
        }

        return 0;
    }
}
