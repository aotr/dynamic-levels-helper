<?php

namespace Aotr\DynamicLevelHelper\Console\Commands;

use Aotr\DynamicLevelHelper\Models\SettingAuditLog;
use Aotr\DynamicLevelHelper\Services\SettingsService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\Table;

class SettingsHistoryCommand extends Command
{
    protected $signature = 'settings:history
                            {--key= : Filter by setting key}
                            {--group= : Filter by setting group}
                            {--action= : Filter by action (created, updated, deleted)}
                            {--limit=50 : Number of records to display}
                            {--export= : Export to CSV file}
                            {--prune= : Delete logs older than N days}';

    protected $description = 'View and manage settings audit log history';

    public function handle()
    {
        // Handle prune operation
        if ($this->option('prune')) {
            return $this->handlePrune();
        }

        // Build query with filters
        $query = SettingAuditLog::query();

        if ($this->option('key')) {
            $query->where('key', $this->option('key'));
        }

        if ($this->option('group')) {
            $query->where('group', $this->option('group'));
        }

        if ($this->option('action')) {
            $query->where('action', $this->option('action'));
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->limit($this->option('limit'))
            ->get();

        if ($logs->isEmpty()) {
            $this->info('📭 No audit logs found with the given filters.');
            return 0;
        }

        // Handle export
        if ($this->option('export')) {
            return $this->handleExport($logs);
        }

        // Display in table
        $this->displayTable($logs);
        return 0;
    }

    protected function displayTable($logs)
    {
        $headers = ['Key', 'Action', 'Group', 'Changed By', 'Changed At', 'Changes'];
        $rows = [];

        foreach ($logs as $log) {
            $changedBy = $log->auditable_type
                ? "{$log->auditable_type}:{$log->auditable_id}"
                : 'System';

            $changes = match ($log->action) {
                'deleted' => '❌ Deleted',
                'created' => '✅ Created',
                default => $this->formatChanges($log->old_value, $log->new_value),
            };

            $rows[] = [
                $log->key,
                ucfirst($log->action),
                $log->group,
                $changedBy,
                $log->created_at->format('Y-m-d H:i:s'),
                $changes,
            ];
        }

        $table = new Table($this->output);
        $table->setHeaders($headers)->setRows($rows);
        $table->render();

        $this->newLine();
        $this->info("✓ Showing {$logs->count()} of {$logs->count()} records");
    }

    protected function formatChanges($old, $new)
    {
        if (json_encode($old) === json_encode($new)) {
            return '(No change)';
        }

        $oldStr = is_array($old) ? json_encode($old) : (string) $old;
        $newStr = is_array($new) ? json_encode($new) : (string) $new;

        // Truncate for display
        if (strlen($oldStr) > 20) {
            $oldStr = substr($oldStr, 0, 17) . '...';
        }
        if (strlen($newStr) > 20) {
            $newStr = substr($newStr, 0, 17) . '...';
        }

        return "$oldStr → $newStr";
    }

    protected function handleExport($logs)
    {
        $path = $this->option('export');

        $fp = fopen($path, 'w');
        if (!$fp) {
            $this->error("❌ Cannot write to $path");
            return 1;
        }

        // CSV headers
        fputcsv($fp, ['Key', 'Group', 'Action', 'Old Value', 'New Value', 'Changed By', 'Reason', 'IP Address', 'Created At']);

        // CSV rows
        foreach ($logs as $log) {
            fputcsv($fp, [
                $log->key,
                $log->group,
                $log->action,
                json_encode($log->old_value),
                json_encode($log->new_value),
                $log->auditable_type ? "{$log->auditable_type}:{$log->auditable_id}" : 'System',
                $log->reason ?? '',
                $log->ip_address ?? '',
                $log->created_at->toDateTimeString(),
            ]);
        }

        fclose($fp);

        $this->info("✓ Exported {$logs->count()} records to $path");
        return 0;
    }

    protected function handlePrune()
    {
        $days = (int) $this->option('prune');

        if ($days < 1) {
            $this->error('❌ Prune days must be >= 1');
            return 1;
        }

        if (!$this->confirm("⚠️  Delete audit logs older than $days days? This cannot be undone.")) {
            $this->info('Cancelled.');
            return 0;
        }

        $deleted = app(SettingsService::class)->pruneAuditLogs($days);

        $this->info("✓ Deleted $deleted audit log records older than $days days");
        return 0;
    }
}
