<?php

declare(strict_types=1);

namespace Aotr\DynamicLevelHelper\Console\Commands;

use Aotr\DynamicLevelHelper\Services\LucideIconService;
use Illuminate\Console\Command;

/**
 * Artisan command to cache Lucide icons.
 *
 * Usage:
 *   php artisan lucide:cache check              # Cache single icon
 *   php artisan lucide:cache --list=check,download  # Cache multiple icons
 *   php artisan lucide:cache --force            # Force re-download cached icons
 *   php artisan lucide:cache --clear            # Clear all cached icons
 *   php artisan lucide:cache --status           # Show cache status
 */
class LucideCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lucide:cache
                            {icon? : A single icon name to cache}
                            {--list= : Comma-separated list of icons to cache}
                            {--force : Force re-download even if icon is cached}
                            {--clear : Clear all cached icons}
                            {--status : Show cache status}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cache Lucide icons for improved performance';

    /**
     * The Lucide icon service.
     */
    protected LucideIconService $service;

    /**
     * Create a new command instance.
     */
    public function __construct(LucideIconService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Handle --status option
        if ($this->option('status')) {
            return $this->showStatus();
        }

        // Handle --clear option
        if ($this->option('clear')) {
            return $this->clearCache();
        }

        // Collect icons to cache
        $icons = $this->collectIcons();

        if (empty($icons)) {
            $this->info('No icons specified. Usage examples:');
            $this->line('  php artisan lucide:cache check');
            $this->line('  php artisan lucide:cache --list=check,download,alert-circle');
            $this->line('  php artisan lucide:cache --status');
            $this->line('  php artisan lucide:cache --clear');
            return 0;
        }

        return $this->cacheIcons($icons);
    }

    /**
     * Collect icons from arguments and options.
     */
    protected function collectIcons(): array
    {
        $icons = [];

        // Single icon from argument
        if ($icon = $this->argument('icon')) {
            $icons[] = $icon;
        }

        // Icons from --list option
        if ($list = $this->option('list')) {
            $listIcons = array_map('trim', explode(',', $list));
            $icons = array_merge($icons, $listIcons);
        }

        return array_unique(array_filter($icons));
    }

    /**
     * Cache the specified icons.
     */
    protected function cacheIcons(array $icons): int
    {
        $force = $this->option('force');
        $this->info('Caching ' . count($icons) . ' icon(s)...');
        $this->newLine();

        $successCount = 0;
        $failCount = 0;

        foreach ($icons as $icon) {
            try {
                $exists = $this->service->exists($icon);

                if ($exists && !$force) {
                    $this->line("  <comment>⊘</comment> {$icon} (already cached, use --force to re-download)");
                    $successCount++;
                    continue;
                }

                $this->service->cache($icon, $force);
                $this->line("  <info>✓</info> {$icon}");
                $successCount++;
            } catch (\Throwable $e) {
                $this->line("  <error>✖</error> {$icon}: {$e->getMessage()}");
                $failCount++;
            }
        }

        $this->newLine();
        $this->info("Cached: {$successCount}, Failed: {$failCount}");

        return $failCount > 0 ? 1 : 0;
    }

    /**
     * Show cache status.
     */
    protected function showStatus(): int
    {
        $this->info('Lucide Icon Cache Status');
        $this->newLine();

        $cachedIcons = $this->service->getCachedIcons();
        $count = count($cachedIcons);

        $this->line("  Storage disk: <comment>" . config('lucide.icon_storage_disk', 'local') . "</comment>");
        $this->line("  Storage path: <comment>" . config('lucide.icon_storage_path', 'lucide/icons') . "</comment>");
        $this->line("  Remote source: <comment>" . config('lucide.remote_source') . "</comment>");
        $this->line("  Cached icons: <comment>{$count}</comment>");

        if ($count > 0 && $this->getOutput()->isVerbose()) {
            $this->newLine();
            $this->line('  Cached icons:');
            foreach ($cachedIcons as $icon) {
                $this->line("    - {$icon}");
            }
        }

        return 0;
    }

    /**
     * Clear all cached icons.
     */
    protected function clearCache(): int
    {
        $cachedIcons = $this->service->getCachedIcons();
        $count = count($cachedIcons);

        if ($count === 0) {
            $this->info('No cached icons to clear.');
            return 0;
        }

        if (!$this->confirm("Are you sure you want to clear {$count} cached icon(s)?")) {
            $this->info('Operation cancelled.');
            return 0;
        }

        $this->service->clearCache();
        $this->info("Cleared {$count} cached icon(s).");

        return 0;
    }
}
