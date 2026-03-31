# Settings System — History & Audit Trail

**Purpose**: Track all setting changes with who changed it, when, old/new values, and reason for audit compliance.

---

## Database Schema

**File**: `database/migrations/2026_03_31_create_settings_audit_log_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings_audit_log', function (Blueprint $table) {
            $table->id();
            
            // What changed
            $table->string('setting_key')->index();
            $table->string('group')->index();
            $table->enum('action', ['created', 'updated', 'deleted'])->index();
            
            // Old vs New
            $table->longText('old_value')->nullable();
            $table->longText('new_value')->nullable();
            $table->string('type')->default('text'); // text, json, boolean, integer, etc.
            
            // Who & Why
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name')->nullable(); // Fallback if user deleted
            $table->string('user_email')->nullable();
            $table->text('reason')->nullable(); // Why the change was made
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            
            // Scope (if multi-tenant)
            $table->string('scope_type')->nullable()->index();
            $table->unsignedBigInteger('scope_id')->nullable()->index();
            
            // Timestamps
            $table->timestamp('changed_at')->useCurrent()->index();
            $table->timestamps();
            
            // Composite indices for common queries
            $table->index(['setting_key', 'changed_at']);
            $table->index(['user_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings_audit_log');
    }
};
```

---

## Model: SettingAuditLog

**File**: `app/Models/SettingAuditLog.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettingAuditLog extends Model
{
    protected $table = 'settings_audit_log';

    protected $fillable = [
        'setting_key',
        'group',
        'action',
        'old_value',
        'new_value',
        'type',
        'user_id',
        'user_name',
        'user_email',
        'reason',
        'ip_address',
        'user_agent',
        'scope_type',
        'scope_id',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    /**
     * Get the user who made the change
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Scope: Get history for a specific setting
     */
    public function scopeForKey($query, string $key)
    {
        return $query->where('setting_key', $key)
            ->orderBy('changed_at', 'desc');
    }

    /**
     * Scope: Get history for a setting group
     */
    public function scopeForGroup($query, string $group)
    {
        return $query->where('group', $group)
            ->orderBy('changed_at', 'desc');
    }

    /**
     * Scope: Get history by user
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId)
            ->orderBy('changed_at', 'desc');
    }

    /**
     * Scope: Get history within date range
     */
    public function scopeFromDate($query, $startDate, $endDate = null)
    {
        $query->whereDate('changed_at', '>=', $startDate);

        if ($endDate) {
            $query->whereDate('changed_at', '<=', $endDate);
        }

        return $query;
    }

    /**
     * Scope: Get only updates (exclude creates/deletes)
     */
    public function scopeUpdatesOnly($query)
    {
        return $query->where('action', 'updated');
    }

    /**
     * Format old → new for display
     */
    public function formatChange(): string
    {
        return "'{$this->old_value}' → '{$this->new_value}'";
    }

    /**
     * Get human-readable action
     */
    public function getActionLabel(): string
    {
        return match ($this->action) {
            'created' => '✨ Created',
            'updated' => '📝 Updated',
            'deleted' => '🗑️ Deleted',
            default => $this->action,
        };
    }

    /**
     * Check if values actually changed (filters 'no-op' updates)
     */
    public function valueChanged(): bool
    {
        return $this->old_value !== $this->new_value;
    }

    /**
     * Get before/after diff
     */
    public function getDiff(): array
    {
        return [
            'before' => $this->old_value,
            'after' => $this->new_value,
            'changed' => $this->valueChanged(),
            'action' => $this->action,
            'reason' => $this->reason,
            'timestamp' => $this->changed_at->format('Y-m-d H:i:s'),
            'user' => $this->user_name ?? 'System',
        ];
    }
}
```

---

## Enhanced SettingsService with Audit Logging

Update `app/Services/SettingsService.php`:

```php
<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\SettingAuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class SettingsService
{
    // ... existing code ...

    /**
     * Set a setting and log the change
     */
    public function set(
        string $key,
        $value,
        ?string $scope = null,
        ?string $reason = null
    ): void {
        // Get existing setting
        $setting = $this->resolveScopedSetting($key, $scope);
        $oldValue = $setting?->value;

        // Determine action
        $action = $setting ? 'updated' : 'created';

        // Create or update
        if ($setting) {
            $setting->update(['value' => $value]);
        } else {
            $setting = Setting::create([
                'key' => $key,
                'group' => $this->extractGroup($key),
                'display_name' => $this->humanize($key),
                'value' => $value,
                'type' => $this->detectType($value),
                'scope_type' => $this->resolveScopeType($scope),
                'scope_id' => $this->resolveScopeId($scope),
            ]);
        }

        // Log the change
        $this->logAuditChange(
            key: $key,
            action: $action,
            oldValue: $oldValue,
            newValue: $value,
            type: $setting->type,
            reason: $reason,
            scope: $scope
        );

        // Invalidate cache
        $this->invalidate($scope);
    }

    /**
     * Log a setting change to audit trail
     */
    protected function logAuditChange(
        string $key,
        string $action,
        $oldValue,
        $newValue,
        string $type,
        ?string $reason = null,
        ?string $scope = null
    ): void {
        try {
            $user = Auth::user();
            
            SettingAuditLog::create([
                'setting_key' => $key,
                'group' => $this->extractGroup($key),
                'action' => $action,
                'old_value' => is_array($oldValue) ? json_encode($oldValue) : $oldValue,
                'new_value' => is_array($newValue) ? json_encode($newValue) : $newValue,
                'type' => $type,
                'user_id' => $user?->id,
                'user_name' => $user?->name ?? 'System',
                'user_email' => $user?->email,
                'reason' => $reason,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'scope_type' => $this->resolveScopeType($scope),
                'scope_id' => $this->resolveScopeId($scope),
                'changed_at' => now(),
            ]);

            Log::info("Setting changed: {$key}", [
                'action' => $action,
                'user' => $user?->id,
                'reason' => $reason,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to log setting audit", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get audit history for a setting
     */
    public function getHistory(string $key, ?string $scope = null, int $limit = 50): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = SettingAuditLog::forKey($key);

        if ($scope) {
            $query->where('scope_type', $this->resolveScopeType($scope))
                ->where('scope_id', $this->resolveScopeId($scope));
        }

        return $query->orderBy('changed_at', 'desc')
            ->paginate($limit);
    }

    /**
     * Get full audit report for date range
     */
    public function getAuditReport($startDate, $endDate = null, ?int $userId = null)
    {
        $query = SettingAuditLog::fromDate($startDate, $endDate);

        if ($userId) {
            $query->byUser($userId);
        }

        return $query->orderBy('changed_at', 'desc')
            ->get()
            ->groupBy('setting_key');
    }

    /**
     * Export audit trail (for compliance)
     */
    public function exportAudit($startDate, $endDate = null): array
    {
        $logs = SettingAuditLog::fromDate($startDate, $endDate)
            ->orderBy('changed_at', 'desc')
            ->get();

        return $logs->map(function (SettingAuditLog $log) {
            return [
                'timestamp' => $log->changed_at->toIso8601String(),
                'setting_key' => $log->setting_key,
                'group' => $log->group,
                'action' => $log->action,
                'before' => $log->old_value,
                'after' => $log->new_value,
                'changed_by' => $log->user_name,
                'email' => $log->user_email,
                'reason' => $log->reason,
                'ip_address' => $log->ip_address,
            ];
        })->toArray();
    }

    // ... rest of existing code ...
}
```

---

## Observer: Auto-Log Changes

**File**: `app/Observers/SettingHistoryObserver.php`

```php
<?php

namespace App\Observers;

use App\Models\Setting;
use App\Models\SettingAuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class SettingHistoryObserver
{
    /**
     * Auto-log when a setting is created
     */
    public function created(Setting $setting)
    {
        $this->logChange($setting, 'created', null, $setting->value);
    }

    /**
     * Auto-log when a setting is updated
     */
    public function updated(Setting $setting)
    {
        $originalValue = $setting->getOriginal('value');

        // Only log if value actually changed
        if ($originalValue !== $setting->value) {
            $this->logChange($setting, 'updated', $originalValue, $setting->value);
        }
    }

    /**
     * Auto-log when a setting is deleted
     */
    public function deleted(Setting $setting)
    {
        $this->logChange($setting, 'deleted', $setting->value, null);
    }

    /**
     * Log the change to audit trail
     */
    protected function logChange(Setting $setting, string $action, $oldValue, $newValue)
    {
        try {
            $user = Auth::user();

            SettingAuditLog::create([
                'setting_key' => $setting->key,
                'group' => $setting->group,
                'action' => $action,
                'old_value' => is_array($oldValue) ? json_encode($oldValue) : $oldValue,
                'new_value' => is_array($newValue) ? json_encode($newValue) : $newValue,
                'type' => $setting->type,
                'user_id' => $user?->id,
                'user_name' => $user?->name ?? 'System',
                'user_email' => $user?->email,
                'reason' => null, // Can be filled manually later if needed
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'scope_type' => $setting->scope_type,
                'scope_id' => $setting->scope_id,
                'changed_at' => now(),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error(
                'Failed to log setting audit',
                ['error' => $e->getMessage()]
            );
        }
    }
}
```

**Register in `AppServiceProvider::boot()`**:

```php
public function boot()
{
    \App\Models\Setting::observe(\App\Observers\SettingHistoryObserver::class);
}
```

---

## Artisan Command: View History

**File**: `app/Console/Commands/SettingsHistoryCommand.php`

```php
<?php

namespace App\Console\Commands;

use App\Models\SettingAuditLog;
use Illuminate\Console\Command;

class SettingsHistoryCommand extends Command
{
    protected $signature = 'settings:history {--key= : Show history for specific setting} {--group= : Show history for group} {--user= : Show changes by user ID} {--limit=20 : Number of records}';

    protected $description = 'View settings change history and audit trail';

    public function handle()
    {
        $query = SettingAuditLog::query();

        if ($key = $this->option('key')) {
            $query->where('setting_key', $key);
            $this->info("📝 History for setting: {$key}");
        } elseif ($group = $this->option('group')) {
            $query->where('group', $group);
            $this->info("📁 History for group: {$group}");
        } elseif ($userId = $this->option('user')) {
            $query->where('user_id', $userId);
            $this->info("👤 Changes by user: {$userId}");
        } else {
            $this->info("📊 Recent settings changes:");
        }

        $logs = $query->orderBy('changed_at', 'desc')
            ->limit($this->option('limit'))
            ->get();

        if ($logs->isEmpty()) {
            $this->info('No changes found.');
            return 0;
        }

        // Build table
        $headers = [
            'Setting',
            'Action',
            'Old Value',
            'New Value',
            'Changed By',
            'When',
            'Reason',
        ];

        $rows = $logs->map(function (SettingAuditLog $log) {
            return [
                $log->setting_key,
                $log->getActionLabel(),
                substr($log->old_value ?? '—', 0, 15),
                substr($log->new_value ?? '—', 0, 15),
                $log->user_name ?? 'System',
                $log->changed_at->diffForHumans(),
                $log->reason ? substr($log->reason, 0, 20) . '...' : '—',
            ];
        })->toArray();

        $this->table($headers, $rows);

        return 0;
    }
}
```

---

## Usage Examples

### Via Artisan Command

```bash
# Show latest 20 changes (default)
php artisan settings:history

# Show changes for specific setting
php artisan settings:history --key=email.driver

# Show changes for entire group
php artisan settings:history --group=email

# Show changes by user
php artisan settings:history --user=1

# Show last 50 changes
php artisan settings:history --limit=50
```

**Output**:
```
📝 History for setting: email.driver
+----------------+----------+--------+----------+------------+-----------+--------+
| Setting        | Action   | Old    | New      | Changed By | When      | Reason |
+----------------+----------+--------+----------+------------+-----------+--------+
| email.driver   | 📝 Updated | smtp | mailgun  | John Doe   | 2 hours ago | Performance |
| email.driver   | ✨ Created | —    | smtp     | System     | 1 day ago  | —      |
+----------------+----------+--------+----------+------------+-----------+--------+
```

### Via Code (Controller/Livewire)

```php
// Get history for a setting
use App\Models\SettingAuditLog;

$history = SettingAuditLog::forKey('email.driver')
    ->orderBy('changed_at', 'desc')
    ->paginate(10);

foreach ($history as $log) {
    echo $log->setting_key . " changed from " . $log->old_value . " to " . $log->new_value;
    echo " by " . $log->user_name . " on " . $log->changed_at->format('Y-m-d H:i:s');
    if ($log->reason) {
        echo " (" . $log->reason . ")";
    }
}
```

### Via SettingsService

```php
use App\Services\SettingsService;

$service = app(SettingsService::class);

// Set with reason (logged automatically)
$service->set(
    key: 'email.driver',
    value: 'mailgun',
    reason: 'Performance improvement - mailgun is faster'
);

// Get history
$history = $service->getHistory('email.driver');

// Get audit report for date range
$report = $service->getAuditReport(
    startDate: now()->subDays(30),
    endDate: now()
);

// Export for compliance
$csv = $service->exportAudit(
    startDate: now()->subMonths(3),
    endDate: now()
);
```

---

## Livewire Component: View History in UI

**File**: `app/Http/Livewire/SettingHistory.php`

```php
<?php

namespace App\Http\Livewire;

use App\Models\SettingAuditLog;
use Livewire\Component;
use Livewire\WithPagination;

class SettingHistory extends Component
{
    use WithPagination;

    public ?string $settingKey = null;
    public ?string $group = null;
    public int $limit = 20;

    public function mount(?string $settingKey = null, ?string $group = null)
    {
        $this->settingKey = $settingKey;
        $this->group = $group;
    }

    public function render()
    {
        $query = SettingAuditLog::query();

        if ($this->settingKey) {
            $query->where('setting_key', $this->settingKey);
        }

        if ($this->group) {
            $query->where('group', $this->group);
        }

        $logs = $query->orderBy('changed_at', 'desc')
            ->paginate($this->limit);

        return view('livewire.setting-history', [
            'logs' => $logs,
        ]);
    }
}
```

**File**: `resources/views/livewire/setting-history.blade.php`

```blade
<div class="space-y-4">
    <h3 class="text-lg font-semibold">Settings History</h3>

    @if ($logs->count())
        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse border border-gray-300">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="border px-4 py-2 text-left">Setting</th>
                        <th class="border px-4 py-2 text-left">Action</th>
                        <th class="border px-4 py-2 text-left">Old Value</th>
                        <th class="border px-4 py-2 text-left">New Value</th>
                        <th class="border px-4 py-2 text-left">Changed By</th>
                        <th class="border px-4 py-2 text-left">When</th>
                        <th class="border px-4 py-2 text-left">Reason</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($logs as $log)
                        <tr class="hover:bg-gray-50">
                            <td class="border px-4 py-2">{{ $log->setting_key }}</td>
                            <td class="border px-4 py-2">
                                <span class="px-2 py-1 rounded text-sm {{ $log->action === 'updated' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' }}">
                                    {{ $log->getActionLabel() }}
                                </span>
                            </td>
                            <td class="border px-4 py-2 font-mono text-sm">{{ Str::limit($log->old_value, 20) }}</td>
                            <td class="border px-4 py-2 font-mono text-sm">{{ Str::limit($log->new_value, 20) }}</td>
                            <td class="border px-4 py-2">{{ $log->user_name ?? 'System' }}</td>
                            <td class="border px-4 py-2 text-sm text-gray-600">{{ $log->changed_at->diffForHumans() }}</td>
                            <td class="border px-4 py-2 text-sm">{{ $log->reason ? Str::limit($log->reason, 30) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $logs->links() }}
    @else
        <p class="text-gray-500">No history found.</p>
    @endif
</div>
```

---

## Compliance & Export

### Export to CSV (for audits)

```php
// In a controller or command
use App\Models\SettingAuditLog;

$logs = SettingAuditLog::fromDate(now()->subMonths(1))->get();

$csv = "Timestamp,Setting,Group,Action,Before,After,User,Email,Reason,IP\n";

foreach ($logs as $log) {
    $csv .= implode(',', [
        $log->changed_at->toIso8601String(),
        $log->setting_key,
        $log->group,
        $log->action,
        '"' . str_replace('"', '""', $log->old_value) . '"',
        '"' . str_replace('"', '""', $log->new_value) . '"',
        $log->user_name,
        $log->user_email,
        '"' . str_replace('"', '""', $log->reason) . '"',
        $log->ip_address,
    ]) . "\n";
}

return response($csv, 200, [
    'Content-Type' => 'text/csv',
    'Content-Disposition' => 'attachment; filename="settings-audit-' . now()->format('Y-m-d') . '.csv"',
]);
```

---

## Best Practices

✅ **Always log with reason** — Help future auditors understand why changes were made  
✅ **Archive old logs** — Move to data warehouse after 1–2 years for compliance  
✅ **Monitor sensitive keys** — Set up alerts for api.*, secret.* changes  
✅ **Review monthly** — Check audit log for unauthorized changes  
✅ **Export quarterly** — Archive CSV for compliance/legal holds  

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| **Very large audit log** | Create archival table, move records >1 year old |
| **Performance slow on query** | Ensure indices created, use `changed_at` date range in queries |
| **User info missing** | User deleted but email stored in `user_email` column |
| **JSON values not readable** | Add `-> > 'key'` to JSON decode in serialization |

