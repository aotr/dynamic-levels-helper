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
            $remoteSizeFormatted = $this->formatBytes($remoteSize);
            $this->info("  ↓ Downloading gzipped version ({$remoteSizeFormatted})...");
        }

        // 4) Download the file using streaming to avoid memory issues
        if ($isGzipped) {
            $result = $this->streamDownloadGzip($url, $path);
        } else {
            $result = $this->streamDownload($url, $path);
        }

        if ($result) {
            $finalSize = Storage::disk('local')->exists($path)
                ? $this->formatBytes(Storage::disk('local')->size($path))
                : 'unknown';
            $this->info("  ✔ Saved to storage/app/{$path} ({$finalSize})");
        }

        return $result;
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
            $this->error("  ✖ Failed to open file for writing");
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
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => function ($resource, $downloadSize, $downloaded) {
                if ($downloadSize > 0) {
                    $percent = round(($downloaded / $downloadSize) * 100, 1);
                    $downloadedFormatted = $this->formatBytes($downloaded);
                    $totalFormatted = $this->formatBytes($downloadSize);
                    $this->output->write("\r  ↓ Downloading... {$percent}% ({$downloadedFormatted}/{$totalFormatted})    ");
                }
                return 0;
            },
        ]);

        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);
        fclose($fp);

        $this->output->write("\r" . str_repeat(' ', 80) . "\r"); // Clear progress line

        if (!$success || $httpCode >= 400) {
            @unlink($storagePath);
            $this->error("  ✖ Download failed: {$error}");
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
            $this->error("  ✖ Failed to open temp file for writing");
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
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => function ($resource, $downloadSize, $downloaded) {
                if ($downloadSize > 0) {
                    $percent = round(($downloaded / $downloadSize) * 100, 1);
                    $downloadedFormatted = $this->formatBytes($downloaded);
                    $totalFormatted = $this->formatBytes($downloadSize);
                    $this->output->write("\r  ↓ Downloading... {$percent}% ({$downloadedFormatted}/{$totalFormatted})    ");
                }
                return 0;
            },
        ]);

        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);
        fclose($fp);

        $this->output->write("\r" . str_repeat(' ', 80) . "\r"); // Clear progress line

        if (!$success || $httpCode >= 400) {
            @unlink($tempGzPath);
            $this->error("  ✖ Download failed: {$error}");
            Log::warning("sync:countries-states-json – gzip download failed for {$url}: {$error}");
            return false;
        }

        // Step 2: Decompress using streaming (file-based, not memory-based)
        $this->line("  ⚙ Decompressing...");
        $result = $this->decompressGzipFile($tempGzPath, $storagePath);

        // Cleanup temp file
        @unlink($tempGzPath);

        if (!$result) {
            $this->error("  ✖ Decompression failed");
        }

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
        $bytesWritten = 0;
        while (!gzeof($gzHandle)) {
            $chunk = gzread($gzHandle, $chunkSize);
            if ($chunk === false) {
                break;
            }
            fwrite($destHandle, $chunk);
            $bytesWritten += strlen($chunk);

            // Show progress for large files
            if ($bytesWritten % (10 * 1024 * 1024) === 0) { // Every 10MB
                $this->output->write("\r  ⚙ Decompressing... " . $this->formatBytes($bytesWritten) . "    ");
            }
        }

        gzclose($gzHandle);
        fclose($destHandle);

        $this->output->write("\r" . str_repeat(' ', 80) . "\r"); // Clear progress line

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
     * Format bytes to human readable format.
     *
     * @param int $bytes
     * @return string
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
