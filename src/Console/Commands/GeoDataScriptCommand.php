<?php

declare(strict_types=1);

namespace Aotr\DynamicLevelHelper\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GeoDataScriptCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'geo:script
                            {action=status : Action to perform: publish, update, status, run}
                            {--force : Force overwrite existing script}
                            {--dir= : Custom storage directory for run action}
                            {--only= : Only download specific files (comma-separated) for run action}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage the geo data sync shell script';

    /**
     * Script source path.
     *
     * @var string
     */
    protected string $sourcePath;

    /**
     * Script destination path.
     *
     * @var string
     */
    protected string $destPath;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
        $this->sourcePath = dirname(__DIR__, 3) . '/scripts/sync-geo-data.sh';
        $this->destPath = base_path('scripts/sync-geo-data.sh');
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
     * Publish the script to the project.
     */
    protected function publishScript(): int
    {
        if (File::exists($this->destPath) && !$this->option('force')) {
            $this->warn('Script already exists at: ' . $this->destPath);
            $this->line('Use --force to overwrite or "update" action to update.');
            return 1;
        }

        return $this->copyScript('Published');
    }

    /**
     * Update the script if a newer version is available.
     */
    protected function updateScript(): int
    {
        if (!File::exists($this->destPath)) {
            $this->warn('Script not published yet. Publishing now...');
            return $this->copyScript('Published');
        }

        // Compare file contents
        $sourceContent = File::get($this->sourcePath);
        $destContent = File::get($this->destPath);

        if ($sourceContent === $destContent) {
            $this->info('Script is already up-to-date.');
            return 0;
        }

        // Check if user has modified the script
        if (!$this->option('force')) {
            $this->warn('Script has differences from the package version.');
            if (!$this->confirm('Do you want to overwrite your local changes?')) {
                $this->line('Update cancelled.');
                return 1;
            }
        }

        return $this->copyScript('Updated');
    }

    /**
     * Show script status.
     */
    protected function showStatus(): int
    {
        $this->info('Geo Data Sync Script Status');
        $this->line('');

        // Package script info
        $this->line('Package script:');
        if (File::exists($this->sourcePath)) {
            $sourceSize = File::size($this->sourcePath);
            $sourceModified = date('Y-m-d H:i:s', File::lastModified($this->sourcePath));
            $this->line("  Path: {$this->sourcePath}");
            $this->line("  Size: {$sourceSize} bytes");
            $this->line("  Modified: {$sourceModified}");
        } else {
            $this->error('  Package script not found!');
        }

        $this->line('');

        // Published script info
        $this->line('Published script:');
        if (File::exists($this->destPath)) {
            $destSize = File::size($this->destPath);
            $destModified = date('Y-m-d H:i:s', File::lastModified($this->destPath));
            $this->line("  Path: {$this->destPath}");
            $this->line("  Size: {$destSize} bytes");
            $this->line("  Modified: {$destModified}");

            // Check if files are identical
            if (File::exists($this->sourcePath)) {
                $identical = File::get($this->sourcePath) === File::get($this->destPath);
                $status = $identical ? '<fg=green>Up-to-date</>' : '<fg=yellow>Modified/Outdated</>';
                $this->line("  Status: {$status}");
            }
        } else {
            $this->warn('  Not published yet.');
            $this->line('  Run: php artisan geo:script publish');
        }

        $this->line('');
        $this->line('Available actions:');
        $this->line('  php artisan geo:script publish  - Publish the script');
        $this->line('  php artisan geo:script update   - Update to latest version');
        $this->line('  php artisan geo:script run      - Run the script');
        $this->line('  php artisan geo:script status   - Show this status');

        return 0;
    }

    /**
     * Run the script.
     */
    protected function runScript(): int
    {
        // Determine which script to run
        $scriptPath = File::exists($this->destPath) ? $this->destPath : $this->sourcePath;

        if (!File::exists($scriptPath)) {
            $this->error('Script not found!');
            return 1;
        }

        // Build command arguments
        $args = [];

        if ($dir = $this->option('dir')) {
            $args[] = "--dir {$dir}";
        }

        if ($only = $this->option('only')) {
            $args[] = "--only {$only}";
        }

        $command = "bash {$scriptPath} " . implode(' ', $args);

        $this->info('Running geo data sync script...');
        $this->line("Command: {$command}");
        $this->line('');

        // Execute the script
        passthru($command, $exitCode);

        return $exitCode;
    }

    /**
     * Copy the script to destination.
     */
    protected function copyScript(string $action): int
    {
        // Ensure directory exists
        $destDir = dirname($this->destPath);
        if (!File::isDirectory($destDir)) {
            File::makeDirectory($destDir, 0755, true);
        }

        // Copy the script
        if (!File::copy($this->sourcePath, $this->destPath)) {
            $this->error('Failed to copy script!');
            return 1;
        }

        // Make it executable
        chmod($this->destPath, 0755);

        $this->info("{$action} script to: {$this->destPath}");
        $this->line('');
        $this->line('Usage:');
        $this->line('  ./scripts/sync-geo-data.sh                    # Run with defaults');
        $this->line('  ./scripts/sync-geo-data.sh --force            # Force re-download');
        $this->line('  ./scripts/sync-geo-data.sh --only countries   # Download specific files');
        $this->line('  ./scripts/sync-geo-data.sh --help             # Show help');
        $this->line('');
        $this->line('Or use the artisan command:');
        $this->line('  php artisan geo:script run');

        return 0;
    }

    /**
     * Show help for unknown action.
     */
    protected function showHelp(): int
    {
        $this->error('Unknown action: ' . $this->argument('action'));
        $this->line('');
        $this->line('Available actions:');
        $this->line('  publish - Publish the script to your project');
        $this->line('  update  - Update the script to the latest version');
        $this->line('  status  - Show script status and version info');
        $this->line('  run     - Run the script directly');

        return 1;
    }
}
