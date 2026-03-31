# Settings System — Database Selection & Auto-Initialization

**Purpose**: Configure which database to use (MySQL, SQLite, PostgreSQL, etc.) with automatic SQLite creation if file doesn't exist.

---

## Configuration File

**File**: `config/settings.php`

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Settings Database
    |--------------------------------------------------------------------------
    |
    | Which database connection to use for storing settings.
    | Options: 'mysql', 'sqlite', 'pgsql', 'sqlsrv'
    |
    */
    'database' => env('SETTINGS_DATABASE', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | SQLite Auto-Creation
    |--------------------------------------------------------------------------
    |
    | If using SQLite, automatically create the database file if it doesn't exist.
    | Set to false to disable auto-creation (you'll create it manually).
    |
    */
    'sqlite_auto_create' => env('SETTINGS_SQLITE_AUTO_CREATE', true),

    /*
    |--------------------------------------------------------------------------
    | SQLite Database Path
    |--------------------------------------------------------------------------
    |
    | Path to SQLite database file (relative to storage_path)
    | Default: storage/app/settings.sqlite
    |
    */
    'sqlite_path' => env('SETTINGS_SQLITE_PATH', 'app/settings.sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    */
    'cache' => [
        'enabled' => env('SETTINGS_CACHE_ENABLED', true),
        'ttl' => env('SETTINGS_CACHE_TTL', 604800), // 7 days
    ],

    /*
    |--------------------------------------------------------------------------
    | Enable Activity Logging
    |--------------------------------------------------------------------------
    |
    | Track who changed what and when. Requires settings_audits table.
    |
    */
    'audit_log' => env('SETTINGS_AUDIT_LOG', false),

    /*
    |--------------------------------------------------------------------------
    | Encrypted Fields
    |--------------------------------------------------------------------------
    |
    | Settings keys that should be encrypted at rest in the database.
    | Example: 'api.stripe.secret_key', 'email.password'
    |
    */
    'encrypted_keys' => [
        // 'api.stripe.secret_key',
        // 'email.password',
    ],
];
```

---

## Environment Variables

**File**: `.env`

```bash
# Database selection (mysql, sqlite, pgsql, sqlsrv)
SETTINGS_DATABASE=sqlite

# SQLite options
SETTINGS_SQLITE_AUTO_CREATE=true
SETTINGS_SQLITE_PATH=app/settings.sqlite

# Caching
SETTINGS_CACHE_ENABLED=true
SETTINGS_CACHE_TTL=604800

# Auditing
SETTINGS_AUDIT_LOG=false
```

---

## Enhanced SettingsService with Database Selection

**File**: `app/Services/SettingsService.php`

```php
<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class SettingsService
{
    protected const CACHE_PREFIX = 'settings';

    protected ?string $selectedDatabase = null;
    protected bool $dbAvailable = false;

    /**
     * Initialize service and detect selected database
     */
    public function __construct()
    {
        $this->initializeDatabase();
    }

    /**
     * Auto-detect and initialize database
     */
    protected function initializeDatabase(): void
    {
        try {
            $selectedDb = config('settings.database', 'mysql');

            // If SQLite selected, check/create database
            if ($selectedDb === 'sqlite') {
                $this->initializeSQLiteIfNeeded();
            }

            // Test connection
            DB::connection($selectedDb)->getPdo();
            $this->selectedDatabase = $selectedDb;
            $this->dbAvailable = true;

            Log::info("Settings using database: {$selectedDb}");
        } catch (Exception $e) {
            Log::warning('Failed to initialize settings database', [
                'error' => $e->getMessage(),
                'selected_db' => $selectedDb ?? 'unknown',
            ]);
            $this->dbAvailable = false;
        }
    }

    /**
     * Create SQLite database file if it doesn't exist
     */
    protected function initializeSQLiteIfNeeded(): void
    {
        $dbDir = storage_path(dirname(config('settings.sqlite_path', 'app/settings.sqlite')));
        $dbFile = storage_path(config('settings.sqlite_path', 'app/settings.sqlite'));

        try {
            // Create directory if it doesn't exist
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0755, true);
                Log::info("Created settings database directory: {$dbDir}");
            }

            // Create SQLite file if it doesn't exist
            if (!file_exists($dbFile)) {
                // Touch the file to create it
                touch($dbFile);
                Log::info("Created SQLite settings database: {$dbFile}");

                // Run migrations on new database
                $this->runMigrationsOnSQLite($dbFile);
            }
        } catch (Exception $e) {
            Log::error('Failed to initialize SQLite database', [
                'path' => $dbFile,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Run settings migrations on SQLite database
     */
    protected function runMigrationsOnSQLite(string $dbFile): void
    {
        try {
            // Create settings table if it doesn't exist
            $pdo = new \PDO("sqlite:{$dbFile}");
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            $pdo->exec('
                CREATE TABLE IF NOT EXISTS settings (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    "key" TEXT NOT NULL,
                    "group" TEXT DEFAULT "general",
                    display_name TEXT,
                    value JSON,
                    meta JSON,
                    type TEXT,
                    "order" INTEGER DEFAULT 1,
                    scope_type VARCHAR(255),
                    scope_id BIGINT UNSIGNED,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    deleted_at TIMESTAMP
                )
            ');

            // Create unique index
            $pdo->exec('
                CREATE UNIQUE INDEX IF NOT EXISTS settings_key_scope_unique
                ON settings ("key", scope_type, scope_id)
            ');

            // Create other indexes
            $pdo->exec('CREATE INDEX IF NOT EXISTS settings_group ON settings ("group")');
            $pdo->exec('CREATE INDEX IF NOT EXISTS settings_scope ON settings (scope_type, scope_id)');

            Log::info('Settings table migrated on SQLite database');
        } catch (Exception $e) {
            Log::error('Failed to migrate SQLite settings table', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get setting with database fallback
     */
    public function get($key, $default = null, $scope = null)
    {
        if (!$this->dbAvailable) {
            Log::warning('Settings database not available, returning default', [
                'key' => $key,
                'scope' => $scope,
            ]);
            return $default;
        }

        // Check cache first
        $cacheKey = $this->cacheKey($key, $scope);
        if ($this->isCacheEnabled()) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        // Query database
        try {
            $settings = $this->loadMerged($scope);
            $value = data_get($settings, $key, $default);

            // Cache the value
            if ($this->isCacheEnabled() && $value !== null) {
                Cache::put($cacheKey, $value, config('settings.cache.ttl', 604800));
            }

            return $value;
        } catch (Exception $e) {
            Log::error('Error reading settings', [
                'key' => $key,
                'database' => $this->selectedDatabase,
                'error' => $e->getMessage(),
            ]);
            return $default;
        }
    }

    /**
     * Set setting value
     */
    public function set($key, $value, $scope = null)
    {
        if (!$this->dbAvailable) {
            throw new Exception('Settings database is not available');
        }

        try {
            $oldValue = $this->get($key, null, $scope);

            $setting = Setting::updateOrCreate(
                [
                    'key' => $key,
                    'scope_type' => $this->resolveScopeType($scope),
                    'scope_id' => $this->resolveScopeId($scope),
                ],
                ['value' => $value]
            );

            // Log change if auditing enabled
            if (config('settings.audit_log', false)) {
                $this->logAuditChange($setting, $oldValue, $value);
            }

            // Invalidate cache
            $this->invalidateCache($key, $scope);

            Log::info('Setting updated', [
                'key' => $key,
                'database' => $this->selectedDatabase,
            ]);
        } catch (Exception $e) {
            Log::error('Error writing settings', [
                'key' => $key,
                'database' => $this->selectedDatabase,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Check if caching is enabled
     */
    protected function isCacheEnabled(): bool
    {
        return config('settings.cache.enabled', true);
    }

    /**
     * Generate cache key
     */
    protected function cacheKey($key, $scope = null): string
    {
        if (is_null($scope)) {
            return self::CACHE_PREFIX . ':' . $key;
        }
        return self::CACHE_PREFIX . ':' . $key . ':' . ($scope ? spl_object_hash($scope) : 'null');
    }

    /**
     * Invalidate cache
     */
    protected function invalidateCache($key, $scope = null): void
    {
        if ($this->isCacheEnabled()) {
            Cache::forget($this->cacheKey($key, $scope));
        }
    }

    /**
     * Load merged settings (global + scoped)
     */
    protected function loadMerged($scope = null)
    {
        $global = $this->loadFromDatabase(null);

        if ($scope) {
            $scoped = $this->loadFromDatabase($scope);
            return array_replace_recursive($global, $scoped);
        }

        return $global;
    }

    /**
     * Load settings from database
     */
    protected function loadFromDatabase($scope = null): array
    {
        $query = Setting::query();

        if (is_null($scope)) {
            $query->whereNull('scope_type');
        } else {
            $type = is_string($scope) 
                ? explode(':', $scope)[0] ?? null 
                : class_basename($scope);
            $id = is_string($scope) 
                ? explode(':', $scope)[1] ?? null 
                : $scope->id ?? null;

            $query->where('scope_type', $type)->where('scope_id', $id);
        }

        return $query->get()
            ->pluck('value', 'key')
            ->toArray();
    }

    /**
     * Resolve scope type
     */
    protected function resolveScopeType($scope): ?string
    {
        if (is_null($scope)) {
            return null;
        }
        return is_string($scope) ? explode(':', $scope)[0] : class_basename($scope);
    }

    /**
     * Resolve scope id
     */
    protected function resolveScopeId($scope): ?int
    {
        if (is_null($scope)) {
            return null;
        }
        if (is_string($scope)) {
            $parts = explode(':', $scope);
            return isset($parts[1]) ? (int) $parts[1] : null;
        }
        return $scope->id ?? null;
    }

    /**
     * Log audit change (if enabled)
     */
    protected function logAuditChange(Setting $setting, $oldValue, $newValue): void
    {
        // Requires setting_audits table
        // TODO: Implement audit logging
    }

    /**
     * Get database connection info
     */
    public function getDatabaseInfo(): array
    {
        return [
            'database' => $this->selectedDatabase,
            'available' => $this->dbAvailable,
            'cache_enabled' => $this->isCacheEnabled(),
        ];
    }
}
```

---

## Service Provider Registration

**File**: `app/Providers/SettingsServiceProvider.php`

```php
<?php

namespace App\Providers;

use App\Services\SettingsService;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/settings.php', 'settings'
        );

        $this->app->singleton(SettingsService::class, function ($app) {
            return new SettingsService();
        });
    }

    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../../config/settings.php' => config_path('settings.php'),
        ], 'config');
    }
}
```

---

## Artisan Command for Database Setup

**File**: `app/Console/Commands/SettingsDatabaseSetup.php`

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SettingsDatabaseSetup extends Command
{
    protected $signature = 'settings:database-setup';
    protected $description = 'Verify and initialize settings database';

    public function handle()
    {
        $this->info('🔧 Settings Database Setup');
        $this->line('');

        $database = config('settings.database', 'mysql');
        $this->info("Selected Database: {$database}");

        // Try to connect
        try {
            DB::connection($database)->getPdo();
            $this->info('✅ Database connection successful');
        } catch (\Exception $e) {
            $this->error('❌ Database connection failed: ' . $e->getMessage());
            return 1;
        }

        // If SQLite, show file info
        if ($database === 'sqlite') {
            $dbPath = storage_path(config('settings.sqlite_path', 'app/settings.sqlite'));
            $this->line('');
            $this->info("SQLite Database File:");
            $this->line("  Path: {$dbPath}");
            $this->line("  Exists: " . (file_exists($dbPath) ? '✅ Yes' : '❌ No'));

            if (file_exists($dbPath)) {
                $size = filesize($dbPath);
                $this->line("  Size: " . ($size > 0 ? "{$size} bytes" : 'empty (new)'));
            }
        }

        // Run migrations
        $this->line('');
        if ($this->confirm('Run migrations for settings table?')) {
            $this->call('migrate', [
                '--path' => 'database/migrations/settings',
            ]);
            $this->info('✅ Migrations complete');
        }

        $this->line('');
        $this->info('✅ Settings database setup complete!');
        return 0;
    }
}
```

---

## Setup Instructions by Database Type

### Option 1: MySQL (Default)

```bash
# .env
SETTINGS_DATABASE=mysql

# Then run migrations
php artisan migrate

# Verify
php artisan settings:database-setup
```

### Option 2: SQLite (Auto-Create)

```bash
# .env
SETTINGS_DATABASE=sqlite
SETTINGS_SQLITE_AUTO_CREATE=true
SETTINGS_SQLITE_PATH=app/settings.sqlite

# Run setup command (auto-creates database + tables)
php artisan settings:database-setup

# Or manually run migrations on SQLite
php artisan migrate --database=sqlite
```

### Option 3: PostgreSQL

```bash
# .env
SETTINGS_DATABASE=pgsql

# Then run migrations
php artisan migrate

# Verify
php artisan settings:database-setup
```

---

## Quick Start Checklist

### For MySQL:
- [ ] `config/settings.php`: Set `'database' => 'mysql'`
- [ ] `.env`: `SETTINGS_DATABASE=mysql`
- [ ] Run: `php artisan migrate`
- [ ] Verify: `php artisan settings:database-setup`

### For SQLite (Recommended for Dev):
- [ ] `config/settings.php`: Set `'database' => 'sqlite'`
- [ ] `.env`: `SETTINGS_DATABASE=sqlite`
- [ ] Run: `php artisan settings:database-setup` (auto-creates)
- [ ] Verify: Check `storage/app/settings.sqlite` exists

### For Production:
- [ ] Choose database (MySQL/PostgreSQL recommended)
- [ ] Create database credentials
- [ ] Update `.env` with connection details
- [ ] Run: `php artisan settings:database-setup`
- [ ] Enable audit logging: `SETTINGS_AUDIT_LOG=true`
- [ ] Setup encryption for sensitive keys
- [ ] Configure Redis cache for performance

---

## Testing Database Initialization

```bash
# Test MySQL
SETTINGS_DATABASE=mysql php artisan settings:database-setup

# Test SQLite (auto-creates if missing)
SETTINGS_DATABASE=sqlite php artisan settings:database-setup

# Test that service can read/write
php artisan tinker
>>> app(SettingsService::class)->set('test.key', 'value');
>>> app(SettingsService::class)->get('test.key');
// Should output: 'value'
```

---

## Migration Files Location

All migration files should be in:
```
database/migrations/settings/
├── 2026_03_25_000000_create_settings_table.php
├── 2026_03_25_000001_create_setting_audits_table.php (optional)
└── 2026_03_25_000002_add_indexes.php (optional)
```

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| **SQLite file not created** | Check storage/ permissions, run with `php artisan` (uses correct permissions) |
| **Database connection failed** | Verify database credentials in `.env` and config |
| **Settings table doesn't exist** | Run `php artisan migrate` or `php artisan settings:database-setup` |
| **Cached stale values** | Run `php artisan cache:clear` or disable cache with `SETTINGS_CACHE_ENABLED=false` |
| **Permission denied (SQLite)** | Check that `storage/app/` is writable by web server |

