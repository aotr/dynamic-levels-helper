<?php

declare(strict_types=1);

namespace Aotr\DynamicLevelHelper\Console\Commands;

use Illuminate\Console\Command;

/**
 * Artisan command to manage the geo data sync shell script.
 *
 * This command provides functionality to publish, update, discover,
 * and run the sync-geo-data.sh shell script that serves as an
 * alternative to the PHP-based sync command for users experiencing
 * memory issues with large file downloads.
 */
class GeoDataScriptCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'geo:script
                            {action=status : Action to perform: publish, update, status, run}
                            {--dir= : Target directory for the script (default: project root)}
                            {--force : Force overwrite existing script}
                            {--run-dir= : Storage directory for downloaded files when running}
                            {--only= : Comma-separated list of files to download when running}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage the geo data sync shell script for users with memory constraints';

    /**
     * Path to the source script in the package.
     */
    protected string $sourceScript;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
        $this->sourceScript = dirname(__DIR__, 3) . '/scripts/sync-geo-data.sh';
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'publish' => $this->publishScript(),
            'update' => $this->updateScript(),
            'status' => $this->showStatus(),
            'run' => $this->runScript(),
            default => $this->showHelp(),
        };
    }

    /**
     * Publish the script to the project root.
     */
    protected function publishScript(): int
    {
        $targetDir = $this->option('dir') ?: base_path();
        $targetPath = rtrim($targetDir, '/') . '/sync-geo-data.sh';

        if (file_exists($targetPath) && !$this->option('force')) {
            $this->warn("Script already exists at: {$targetPath}");
            $this->line('Use --force to overwrite.');
            return 1;
        }

        if (!file_exists($this->sourceScript)) {
            $this->error('Source script not found in package.');
            return 1;
        }

        // Ensure target directory exists
        $dir = dirname($targetPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Copy the script
        if (copy($this->sourceScript, $targetPath)) {
            // Make it executable
            chmod($targetPath, 0755);

            $this->info('✓ Script published successfully!');
            $this->newLine();
            $this->line("Location: <comment>{$targetPath}</comment>");
            $this->newLine();
            $this->line('Usage:');
            $this->line("  <comment>./sync-geo-data.sh</comment>                    # Download all files");
            $this->line("  <comment>./sync-geo-data.sh --force</comment>            # Force re-download");
            $this->line("  <comment>./sync-geo-data.sh --only=countries,states</comment>");
            $this->line("  <comment>./sync-geo-data.sh --help</comment>             # Show all options");

            return 0;
        }

        $this->error('Failed to publish script.');
        return 1;
    }

    /**
     * Update an existing script with the latest version from the package.
     */
    protected function updateScript(): int
    {
        $targetDir = $this->option('dir') ?: base_path();
        $targetPath = rtrim($targetDir, '/') . '/sync-geo-data.sh';

        if (!file_exists($targetPath)) {
            $this->warn('Script not found. Publishing instead...');
            return $this->publishScript();
        }

        if (!file_exists($this->sourceScript)) {
            $this->error('Source script not found in package.');
            return 1;
        }

        // Check if update is needed
        $sourceHash = md5_file($this->sourceScript);
        $targetHash = md5_file($targetPath);

        if ($sourceHash === $targetHash && !$this->option('force')) {
            $this->info('Script is already up to date.');
            return 0;
        }

        // Backup existing script
        $backupPath = $targetPath . '.backup';
        copy($targetPath, $backupPath);

        // Update the script
        if (copy($this->sourceScript, $targetPath)) {
            chmod($targetPath, 0755);
            $this->info('✓ Script updated successfully!');
            $this->line("Backup saved at: <comment>{$backupPath}</comment>");
            return 0;
        }

        $this->error('Failed to update script.');
        return 1;
    }

    /**
     * Show the current status of the script.
     */
    protected function showStatus(): int
    {
        $this->info('Geo Data Sync Script Status');
        $this->newLine();

        // Check package script
        $this->line('Package Script:');
        if (file_exists($this->sourceScript)) {
            $this->line("  Location: <comment>{$this->sourceScript}</comment>");
            $this->line("  Size: <comment>" . $this->formatBytes(filesize($this->sourceScript)) . "</comment>");
            $this->line("  Modified: <comment>" . date('Y-m-d H:i:s', filemtime($this->sourceScript)) . "</comment>");
        } else {
            $this->error('  Package script not found!');
        }

        $this->newLine();

        // Check published script
        $publishedPath = base_path('sync-geo-data.sh');
        $this->line('Published Script:');
        if (file_exists($publishedPath)) {
            $this->line("  Location: <comment>{$publishedPath}</comment>");
            $this->line("  Size: <comment>" . $this->formatBytes(filesize($publishedPath)) . "</comment>");
            $this->line("  Modified: <comment>" . date('Y-m-d H:i:s', filemtime($publishedPath)) . "</comment>");
            $this->line("  Executable: <comment>" . (is_executable($publishedPath) ? 'Yes' : 'No') . "</comment>");

            // Check if update available
            if (file_exists($this->sourceScript)) {
                $sourceHash = md5_file($this->sourceScript);
                $targetHash = md5_file($publishedPath);
                if ($sourceHash !== $targetHash) {
                    $this->newLine();
                    $this->warn('⚠ Update available! Run: php artisan geo:script update');
                }
            }
        } else {
            $this->line("  <comment>Not published</comment>");
            $this->line("  Run: <comment>php artisan geo:script publish</comment>");
        }

        $this->newLine();

        // Check storage directory
        $storagePath = storage_path('app/geo-data');
        $this->line('Storage Directory:');
        $this->line("  Location: <comment>{$storagePath}</comment>");
        if (is_dir($storagePath)) {
            $files = glob($storagePath . '/*.json');
            $this->line("  Files: <comment>" . count($files) . " JSON files</comment>");

            if (count($files) > 0) {
                $totalSize = array_sum(array_map('filesize', $files));
                $this->line("  Total Size: <comment>" . $this->formatBytes($totalSize) . "</comment>");
            }
        } else {
            $this->line("  <comment>Directory not found</comment>");
        }

        return 0;
    }

    /**
     * Run the script directly.
     */
    protected function runScript(): int
    {
        // Check if script is published
        $scriptPath = base_path('sync-geo-data.sh');

        if (!file_exists($scriptPath)) {
            $this->warn('Script not published. Publishing first...');
            $this->publishScript();
        }

        if (!is_executable($scriptPath)) {
            chmod($scriptPath, 0755);
        }

        // Build command arguments
        $args = [];

        if ($dir = $this->option('run-dir')) {
            $args[] = "--dir={$dir}";
        }

        if ($only = $this->option('only')) {
            $args[] = "--only={$only}";
        }

        if ($this->option('force')) {
            $args[] = '--force';
        }

        $command = $scriptPath . ' ' . implode(' ', $args);

        $this->info("Running: {$command}");
        $this->newLine();

        // Execute the script
        passthru($command, $exitCode);

        return $exitCode;
    }

    /**
     * Show help information.
     */
    protected function showHelp(): int
    {
        $this->error("Unknown action. Available actions: publish, update, status, run");
        $this->newLine();
        $this->line('Examples:');
        $this->line('  <comment>php artisan geo:script status</comment>   # Show script status');
        $this->line('  <comment>php artisan geo:script publish</comment>  # Publish script to project');
        $this->line('  <comment>php artisan geo:script update</comment>   # Update to latest version');
        $this->line('  <comment>php artisan geo:script run</comment>      # Run the script');

        return 1;
    }

    /**
     * Format bytes to human readable string.
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
