<?php

namespace Aotr\DynamicLevelHelper\Console\Commands;

use Aotr\DynamicLevelHelper\Services\EnhancedDBService;
use Illuminate\Console\Command;

/**
 * Enhanced Database Service Management Command
 *
 * Provides monitoring and management capabilities for the EnhancedDBService
 */
class EnhancedDBServiceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'enhanced-db:manage
                            {action : The action to perform (stats|performance|reset|config)}
                            {--clear-metrics : Clear performance metrics}
                            {--format=table : Output format (table|json)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage and monitor the Enhanced Database Service';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');
        $format = $this->option('format');

        try {
            switch ($action) {
                case 'stats':
                    return $this->showConnectionStats($format);

                case 'performance':
                    return $this->showPerformanceMetrics($format);

                case 'reset':
                    return $this->resetService();

                case 'config':
                    return $this->showConfiguration($format);

                default:
                    $this->error("Unknown action: {$action}");
                    $this->line('Available actions: stats, performance, reset, config');
                    return 1;
            }
        } catch (\Exception $e) {
            $this->error("Error executing action '{$action}': " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Show connection pool statistics
     */
    private function showConnectionStats(string $format): int
    {
        $dbService = EnhancedDBService::getInstance();
        $stats = $dbService->getConnectionPoolStats();

        $this->info('Enhanced Database Service - Connection Pool Statistics');
        $this->line('');

        if ($format === 'json') {
            $this->line(json_encode($stats, JSON_PRETTY_PRINT));
            return 0;
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Current Pool Size', $stats['current_pool_size']],
                ['Max Connections', $stats['max_connections']],
                ['Active Connections', $stats['active_connections']],
                ['Pool Utilization', $stats['pool_utilization'] . '%'],
            ]
        );

        if (!empty($stats['connection_usage'])) {
            $this->line('');
            $this->info('Active Connection Usage:');

            $connectionData = [];
            foreach ($stats['connection_usage'] as $connection => $lastUsed) {
                $connectionData[] = [
                    'Connection',
                    $connection,
                    date('Y-m-d H:i:s', $lastUsed),
                    round((time() - $lastUsed) / 60, 2) . ' min ago'
                ];
            }

            if (!empty($connectionData)) {
                $this->table(['Type', 'Connection', 'Last Used', 'Idle Time'], $connectionData);
            }
        }

        return 0;
    }

    /**
     * Show performance metrics
     */
    private function showPerformanceMetrics(string $format): int
    {
        $dbService = EnhancedDBService::getInstance();
        $metrics = $dbService->getPerformanceMetrics();

        $this->info('Enhanced Database Service - Performance Metrics');
        $this->line('');

        if (empty($metrics)) {
            $this->warn('No performance metrics available. Enable query profiling in configuration.');
            return 0;
        }

        if ($format === 'json') {
            $this->line(json_encode($metrics, JSON_PRETTY_PRINT));
            return 0;
        }

        $tableData = [];
        foreach ($metrics as $procedure => $data) {
            $tableData[] = [
                $procedure,
                $data['total_calls'],
                round($data['avg_time'], 4) . 's',
                round($data['min_time'], 4) . 's',
                round($data['max_time'], 4) . 's',
                round($data['total_time'], 4) . 's',
                $data['total_result_sets'],
            ];
        }

        $this->table(
            ['Stored Procedure', 'Total Calls', 'Avg Time', 'Min Time', 'Max Time', 'Total Time', 'Result Sets'],
            $tableData
        );

        if ($this->option('clear-metrics')) {
            $dbService->clearPerformanceMetrics();
            $this->info('Performance metrics cleared.');
        }

        return 0;
    }

    /**
     * Reset the database service
     */
    private function resetService(): int
    {
        if ($this->confirm('Are you sure you want to reset the Enhanced Database Service? This will close all connections.')) {
            EnhancedDBService::resetInstance();
            $this->info('Enhanced Database Service has been reset successfully.');
            return 0;
        }

        $this->info('Reset cancelled.');
        return 0;
    }

    /**
     * Show current configuration
     */
    private function showConfiguration(string $format): int
    {
        $config = config('dynamic-levels-helper.enhanced_db_service', []);

        $this->info('Enhanced Database Service Configuration');
        $this->line('');

        if ($format === 'json') {
            $this->line(json_encode($config, JSON_PRETTY_PRINT));
            return 0;
        }

        // Flatten config for table display
        $flatConfig = $this->flattenArray($config);
        $tableData = [];

        foreach ($flatConfig as $key => $value) {
            $tableData[] = [$key, is_bool($value) ? ($value ? 'true' : 'false') : $value];
        }

        $this->table(['Configuration Key', 'Value'], $tableData);

        return 0;
    }

    /**
     * Flatten a multi-dimensional array for display
     */
    private function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix . $key;

            if (is_array($value)) {
                $result = array_merge($result, $this->flattenArray($value, $newKey . '.'));
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }
}
