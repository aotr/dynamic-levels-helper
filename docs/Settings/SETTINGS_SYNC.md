# Settings System — Database & File Synchronization

**Purpose**: Sync settings between database and settings file (`config/dynamic-settings.json`), with database taking precedence when both exist.

---

## Sync Service

**File**: `app/Services/SettingsSyncService.php`

```php
<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class SettingsSyncService
{
    /**
     * Sync direction: 'db-to-file', 'file-to-db', 'merge'
     */
    protected string $direction = 'merge';

    /**
     * Track what was synced
     */
    protected array $syncReport = [
        'direction' => null,
        'synced' => [],
        'skipped' => [],
        'created' => [],
        'updated' => [],
        'total_db' => 0,
        'total_file' => 0,
        'conflicts' => [],
        'errors' => [],
    ];

    /**
     * Sync from database to settings file
     */
    public function syncDbToFile(): array
    {
        $this->direction = 'db-to-file';
        $this->syncReport['direction'] = 'Database → Settings File';

        try {
            // Load all settings from database
            $settings = Setting::all();
            $this->syncReport['total_db'] = $settings->count();

            // Build hierarchical structure
            $fileData = [];
            foreach ($settings as $setting) {
                $this->setNestedValue($fileData, $setting->key, $setting->value);
            }

            // Write to settings file
            $filePath = config_path('dynamic-settings.json');
            file_put_contents($filePath, json_encode($fileData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $this->syncReport['synced'] = $fileData;
            Log::info('Settings synced from database to file', [
                'count' => $settings->count(),
                'file' => $filePath,
            ]);

            return $this->syncReport;
        } catch (Exception $e) {
            $this->syncReport['errors'][] = $e->getMessage();
            Log::error('Failed to sync database to file', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Sync from settings file to database
     */
    public function syncFileToDb(): array
    {
        $this->direction = 'file-to-db';
        $this->syncReport['direction'] = 'Settings File → Database';

        try {
            // Load settings file
            $filePath = config_path('dynamic-settings.json');

            if (!file_exists($filePath)) {
                $this->syncReport['errors'][] = "Settings file not found: {$filePath}";
                Log::warning('Settings file not found for sync', ['file' => $filePath]);
                return $this->syncReport;
            }

            $fileData = json_decode(file_get_contents($filePath), true);
            if (!is_array($fileData)) {
                throw new Exception('Settings file is not valid JSON');
            }

            $this->syncReport['total_file'] = $this->countKeys($fileData);

            // Flatten file data and sync to database
            $flatData = $this->flattenArray($fileData);

            foreach ($flatData as $key => $value) {
                $setting = Setting::where('key', $key)
                    ->where('scope_type', null)
                    ->where('scope_id', null)
                    ->first();

                if ($setting) {
                    $setting->update(['value' => $value]);
                    $this->syncReport['updated'][$key] = $value;
                } else {
                    Setting::create([
                        'key' => $key,
                        'group' => 'general',
                        'display_name' => ucfirst(str_replace('.', ' ', $key)),
                        'value' => $value,
                        'type' => $this->detectType($value),
                    ]);
                    $this->syncReport['created'][$key] = $value;
                }
            }

            Log::info('Settings synced from file to database', [
                'created' => count($this->syncReport['created']),
                'updated' => count($this->syncReport['updated']),
            ]);

            return $this->syncReport;
        } catch (Exception $e) {
            $this->syncReport['errors'][] = $e->getMessage();
            Log::error('Failed to sync file to database', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Merge: Database takes precedence, fill gaps from file
     */
    public function syncMerge(): array
    {
        $this->direction = 'merge';
        $this->syncReport['direction'] = 'Merge (DB precedence)';

        try {
            // Load from both sources
            $dbSettings = Setting::all();
            $this->syncReport['total_db'] = $dbSettings->count();

            $filePath = config_path('dynamic-settings.json');
            $fileData = [];
            if (file_exists($filePath)) {
                $fileData = json_decode(file_get_contents($filePath), true) ?? [];
                $this->syncReport['total_file'] = $this->countKeys($fileData);
            }

            $flatFileData = $this->flattenArray($fileData);
            $dbKeys = $dbSettings->pluck('value', 'key')->toArray();

            // Step 1: Database settings take precedence
            // Update file with all DB settings (overwrite from file if conflict)
            $mergedData = $fileData;

            foreach ($dbSettings as $setting) {
                $this->setNestedValue($mergedData, $setting->key, $setting->value);

                if (isset($flatFileData[$setting->key]) && 
                    $flatFileData[$setting->key] !== $setting->value) {
                    $this->syncReport['conflicts'][$setting->key] = [
                        'file_value' => $flatFileData[$setting->key],
                        'db_value' => $setting->value,
                        'selected' => 'db (database takes precedence)',
                    ];
                }

                $this->syncReport['synced'][$setting->key] = $setting->value;
            }

            // Step 2: Add file-only keys that don't exist in DB
            foreach ($flatFileData as $key => $value) {
                if (!isset($dbKeys[$key])) {
                    Setting::create([
                        'key' => $key,
                        'group' => 'general',
                        'display_name' => ucfirst(str_replace('.', ' ', $key)),
                        'value' => $value,
                        'type' => $this->detectType($value),
                    ]);

                    $this->syncReport['created'][$key] = $value;
                    Log::info('Created setting from file', ['key' => $key]);
                }
            }

            // Write merged data back to file
            file_put_contents($filePath, json_encode($mergedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            Log::info('Settings merged (database precedence)', [
                'synced' => count($this->syncReport['synced']),
                'conflicts' => count($this->syncReport['conflicts']),
                'created' => count($this->syncReport['created']),
            ]);

            return $this->syncReport;
        } catch (Exception $e) {
            $this->syncReport['errors'][] = $e->getMessage();
            Log::error('Failed to merge settings', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Flatten nested array to dot notation
     */
    protected function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix ? "{$prefix}.{$key}" : $key;

            if (is_array($value)) {
                $result = array_merge($result, $this->flattenArray($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }

    /**
     * Set nested array value using dot notation
     */
    protected function setNestedValue(array &$array, string $key, $value): void
    {
        $keys = explode('.', $key);
        $current = &$array;

        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $current[$k] = $value;
            } else {
                if (!isset($current[$k]) || !is_array($current[$k])) {
                    $current[$k] = [];
                }
                $current = &$current[$k];
            }
        }
    }

    /**
     * Detect value type for settings table
     */
    protected function detectType($value): string
    {
        if (is_bool($value)) {
            return 'boolean';
        }
        if (is_int($value)) {
            return 'integer';
        }
        if (is_float($value)) {
            return 'decimal';
        }
        if (is_array($value)) {
            return 'json';
        }
        return 'text';
    }

    /**
     * Count keys in nested array
     */
    protected function countKeys(array $array): int
    {
        $count = 0;
        foreach ($array as $value) {
            if (is_array($value)) {
                $count += $this->countKeys($value);
            } else {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Get sync report
     */
    public function getReport(): array
    {
        return $this->syncReport;
    }

    /**
     * Pretty print report
     */
    public function printReport(): string
    {
        $report = $this->syncReport;
        $output = "\n";
        $output .= "Sync Direction: {$report['direction']}\n";
        $output .= "=====================================\n";
        $output .= "Database Settings: {$report['total_db']}\n";
        $output .= "File Settings: {$report['total_file']}\n";
        $output .= "Synced: " . count($report['synced']) . "\n";
        $output .= "Created: " . count($report['created']) . "\n";
        $output .= "Updated: " . count($report['updated']) . "\n";
        $output .= "Conflicts (DB wins): " . count($report['conflicts']) . "\n";

        if (!empty($report['conflicts'])) {
            $output .= "\nConflicts:\n";
            foreach ($report['conflicts'] as $key => $conflict) {
                $output .= "  • {$key}\n";
                $output .= "    File: " . json_encode($conflict['file_value']) . "\n";
                $output .= "    DB:   " . json_encode($conflict['db_value']) . " (SELECTED)\n";
            }
        }

        if (!empty($report['errors'])) {
            $output .= "\nErrors:\n";
            foreach ($report['errors'] as $error) {
                $output .= "  ❌ {$error}\n";
            }
        }

        return $output;
    }
}
```

---

## Artisan Command

**File**: `app/Console/Commands/SettingsSyncCommand.php`

```php
<?php

namespace App\Console\Commands;

use App\Services\SettingsSyncService;
use Illuminate\Console\Command;

class SettingsSyncCommand extends Command
{
    protected $signature = 'settings:sync {direction=merge : db-to-file, file-to-db, or merge}';

    protected $description = 'Sync settings between database and settings file';

    public function handle()
    {
        $direction = $this->argument('direction');

        if (!in_array($direction, ['db-to-file', 'file-to-db', 'merge'])) {
            $this->error("Invalid direction: {$direction}");
            $this->info('Valid options: db-to-file, file-to-db, merge');
            return 1;
        }

        $this->info("🔄 Starting settings sync: {$direction}");

        try {
            $service = new SettingsSyncService();

            match ($direction) {
                'db-to-file' => $service->syncDbToFile(),
                'file-to-db' => $service->syncFileToDb(),
                'merge' => $service->syncMerge(),
            };

            // Print report
            $this->line($service->printReport());
            $this->info('✅ Settings sync completed successfully');

            return 0;
        } catch (Exception $e) {
            $this->error("❌ Sync failed: {$e->getMessage()}");
            return 1;
        }
    }
}
```

---

## Usage Examples

### Sync from Database to File
Push all DB settings to settings file (overwrite):
```bash
php artisan settings:sync db-to-file
```

**Use case**: After making changes in admin UI, export to file for version control.

### Sync from File to Database
Pull all file settings to database (create missing):
```bash
php artisan settings:sync file-to-db
```

**Use case**: Deploy new settings file → sync to database.

### Merge (Default, DB Takes Precedence)
Merge both sources, database settings win on conflicts:
```bash
php artisan settings:sync merge
# or just
php artisan settings:sync
```

**Use case**: 
- Combine environment-specific settings
- Database admin changes override file defaults
- Keep both in sync with smart conflict resolution

---

## Sync Behavior Matrix

| Scenario | Database | File | Merge Result |
|----------|----------|------|--------------|
| **Key in DB only** | ✅ value1 | ❌ missing | DB: value1 |
| **Key in File only** | ❌ missing | ✅ value2 | DB: value2 (created) |
| **Key in both, same value** | ✅ value1 | ✅ value1 | ✅ value1 (no change) |
| **Key in both, different** | ✅ value1 | ✅ value2 | **DB: value1** ⚠️ (conflict) |

---

## Real-World Example

### Initial State

**Database**:
```
email.driver = "mailgun"
email.from = "noreply@app.com"
upload.max_size = 10485760
```

**File** (`config/dynamic-settings.json`):
```json
{
  "email": {
    "driver": "sendgrid",
    "from": "support@example.com"
  },
  "features": {
    "chat": true
  }
}
```

### Run Merge
```bash
php artisan settings:sync merge
```

### Result

**Conflicts** (DB wins):
```
email.driver: File='sendgrid' → DB='mailgun' (SELECTED)
email.from: File='support@example.com' → DB='noreply@app.com' (SELECTED)
```

**Created** (from File):
```
features.chat = true
```

**Final File** (after sync):
```json
{
  "email": {
    "driver": "mailgun",
    "from": "noreply@app.com"
  },
  "features": {
    "chat": true
  },
  "upload": {
    "max_size": 10485760
  }
}
```

---

## Sync Report Example

```
Sync Direction: Merge (DB precedence)
=====================================
Database Settings: 15
File Settings: 8
Synced: 12
Created: 3
Updated: 0
Conflicts (DB wins): 2

Conflicts:
  • email.driver
    File: "sendgrid"
    DB:   "mailgun" (SELECTED)
  • api.stripe.key
    File: "sk_test_old"
    DB:   "sk_test_new" (SELECTED)

✅ Settings sync completed successfully
```

---

## Scheduled Sync (Optional)

**File**: `app/Console/Kernel.php`

Auto-sync database to file daily (backup):

```php
protected function schedule(Schedule $schedule)
{
    // Backup: Sync database to file daily at 2 AM
    $schedule->command('settings:sync db-to-file')
        ->dailyAt('02:00')
        ->onSuccess(function () {
            Log::info('Daily settings backup to file completed');
        })
        ->onFailure(function () {
            Log::error('Daily settings backup failed');
        });

    // Optional: Merge changes weekly
    $schedule->command('settings:sync merge')
        ->weekly()
        ->mondays()
        ->at('03:00');
}
```

---

## Integration with CI/CD

**Example**: Deploy new settings file → sync to production database

```yaml
# .github/workflows/deploy.yml

- name: Sync settings to database
  run: |
    php artisan settings:sync file-to-db
    
- name: Verify settings
  run: |
    php artisan settings:database-setup
```

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| **File not found** | Create `config/dynamic-settings.json` with `{}` |
| **JSON parse error** | Validate JSON syntax: `php -r 'json_decode(file_get_contents("config/dynamic-settings.json"));'` |
| **Database connection failed** | Run `php artisan settings:database-setup` first |
| **Conflicts show wrong precedence** | Check your sync direction, merge always picks DB |
| **Synced but not visible** | Clear cache: `php artisan cache:clear` |

---

## Best Practices

1. **Before deploying**, run merge to sync all sources
2. **Make admin changes in DB** via UI, sync to file for version control
3. **Keep file as defaults**, database as overrides
4. **Monitor conflicts**, understand why values differ
5. **Version control the file**, not the database
6. **Test sync in staging** before production

