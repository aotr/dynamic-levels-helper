# Settings System — Implementation Starter Template

**Use this to copy-paste and get started immediately. Fill in `[TODO]` sections.**

---

## Phase 0: Configuration (Database Selection)

**File**: `config/settings.php`

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Settings Database
    |--------------------------------------------------------------------------
    |
    | Database to use for settings storage.
    | Options: 'mysql' (default), 'sqlite', 'pgsql', 'sqlsrv'
    |
    | SQLite is auto-created in storage/app/settings.sqlite if:
    | - SETTINGS_DATABASE=sqlite
    | - SETTINGS_SQLITE_AUTO_CREATE=true
    |
    */
    'database' => env('SETTINGS_DATABASE', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | SQLite Auto-Creation
    |--------------------------------------------------------------------------
    |
    | If using SQLite, automatically create DB file + tables if missing.
    |
    */
    'sqlite_auto_create' => env('SETTINGS_SQLITE_AUTO_CREATE', true),
    'sqlite_path' => env('SETTINGS_SQLITE_PATH', 'app/settings.sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => env('SETTINGS_CACHE_ENABLED', true),
        'ttl' => env('SETTINGS_CACHE_TTL', 604800), // 7 days
    ],

    /*
    |--------------------------------------------------------------------------
    | Activity Logging (Optional)
    |--------------------------------------------------------------------------
    */
    'audit_log' => env('SETTINGS_AUDIT_LOG', false),

    /*
    |--------------------------------------------------------------------------
    | Encrypted Fields (Optional)
    |--------------------------------------------------------------------------
    |
    | Settings keys to encrypt at rest (e.g., API keys, passwords)
    |
    */
    'encrypted_keys' => [
        // 'api.stripe.secret_key',
        // 'email.password',
    ],
];
```

**.env file** (choose your database):

**For MySQL**:
```bash
SETTINGS_DATABASE=mysql
```

**For SQLite** (auto-creates if missing):
```bash
SETTINGS_DATABASE=sqlite
SETTINGS_SQLITE_AUTO_CREATE=true
SETTINGS_SQLITE_PATH=app/settings.sqlite
```

**For PostgreSQL**:
```bash
SETTINGS_DATABASE=pgsql
```

---

## Phase 1: Migration

**File**: `database/migrations/2026_03_25_000000_create_settings_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            
            $table->string('key');
            $table->string('group')->default('general');
            $table->string('display_name');
            
            $table->json('value')->nullable();
            $table->json('meta')->nullable();
            
            $table->string('type');
            $table->integer('order')->default(1);
            
            $table->nullableMorphs('scope');
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['key', 'scope_type', 'scope_id']);
            $table->index('group');
            $table->index(['scope_type', 'scope_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('settings');
    }
};
```

---

## Phase 1: Model

**File**: `app/Models/Setting.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Setting extends Model
{
    use SoftDeletes;

    protected $fillable = ['key', 'group', 'display_name', 'value', 'meta', 'type', 'order'];
    protected $casts = ['value' => 'array', 'meta' => 'array', 'order' => 'integer'];

    // Polymorphic relationship
    public function scope()
    {
        return $this->morphTo();
    }

    // Query scopes
    public function scopeForScope($query, $scope = null)
    {
        if (is_null($scope)) {
            return $query->whereNull('scope_type');
        }

        if (is_string($scope)) {
            [$type, $id] = explode(':', $scope);
            return $query->where('scope_type', $type)->where('scope_id', $id);
        }

        return $query
            ->where('scope_type', class_basename($scope))
            ->where('scope_id', $scope->id);
    }

    public function scopeInGroup($query, $group)
    {
        return $query->where('group', $group);
    }

    // Type casting
    public function getCastedValue()
    {
        return match ($this->type) {
            'boolean' => (bool) $this->value,
            'number', 'integer' => (int) $this->value,
            'decimal' => (float) $this->value,
            default => $this->value,
        };
    }
}
```

---

## Phase 1: Service

**File**: `app/Services/SettingsService.php`

```php
<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    protected const CACHE_PREFIX = 'settings';

    public function get($key, $default = null, $scope = null)
    {
        $settings = $this->loadMerged($scope);
        return data_get($settings, $key, $default);
    }

    public function set($key, $value, $scope = null)
    {
        Setting::updateOrCreate(
            [
                'key' => $key,
                'scope_type' => $this->resolveScopeType($scope),
                'scope_id' => $this->resolveScopeId($scope),
            ],
            ['value' => $value]
        );

        $this->invalidate($scope);
    }

    public function all($scope = null)
    {
        return collect($this->loadMerged($scope))
            ->map(fn($s) => $s['casted_value'] ?? $s['value'])
            ->toArray();
    }

    public function group($group, $scope = null)
    {
        return collect($this->loadMerged($scope))
            ->filter(fn($s) => ($s['group'] ?? null) === $group)
            ->map(fn($s) => $s['casted_value'] ?? $s['value']);
    }

    protected function load($scope = null)
    {
        $cacheKey = $this->resolveCacheKey($scope);

        return Cache::rememberForever($cacheKey, function () use ($scope) {
            return Setting::query()
                ->forScope($scope)
                ->get()
                ->map(fn($s) => [
                    'key' => $s->key,
                    'group' => $s->group,
                    'value' => $s->value,
                    'casted_value' => $s->getCastedValue(),
                    'type' => $s->type,
                    'meta' => $s->meta,
                ])
                ->keyBy('key')
                ->toArray();
        });
    }

    protected function loadMerged($scope = null)
    {
        $global = $this->load(null);
        
        if ($scope) {
            $scoped = $this->load($scope);
            return array_replace_recursive($global, $scoped);
        }

        return $global;
    }

    protected function resolveCacheKey($scope = null)
    {
        if (is_null($scope)) {
            return self::CACHE_PREFIX . ':global';
        }

        if (is_string($scope)) {
            return self::CACHE_PREFIX . ':' . str_replace(':', ':', $scope);
        }

        $type = class_basename($scope);
        $id = $scope->id;
        return self::CACHE_PREFIX . ":{$type}:{$id}";
    }

    public function invalidate($scope = null)
    {
        $cacheKey = $this->resolveCacheKey($scope);
        Cache::forget($cacheKey);
    }

    protected function resolveScopeType($scope)
    {
        if (is_null($scope)) return null;
        if (is_string($scope)) {
            [$type] = explode(':', $scope);
            return $type;
        }
        return class_basename($scope);
    }

    protected function resolveScopeId($scope)
    {
        if (is_null($scope)) return null;
        if (is_string($scope)) {
            $parts = explode(':', $scope);
            return $parts[1] ?? null;
        }
        return $scope->id ?? null;
    }

    public function warm()
    {
        $this->load(null);
        // [TODO]: Warm per-tenant if applicable
    }

    public function flush()
    {
        Cache::flush();
    }
}
```

---

## Phase 1: Register Service

**File**: `app/Providers/AppServiceProvider.php` (add to `register()`)

```php
public function register()
{
    $this->app->singleton(\App\Services\SettingsService::class);
}
```

---

## Phase 2: Observer

**File**: `app/Observers/SettingObserver.php`

```php
<?php

namespace App\Observers;

use App\Models\Setting;
use App\Services\SettingsService;

class SettingObserver
{
    public function updated(Setting $setting)
    {
        app(SettingsService::class)->invalidate($this->resolveScope($setting));
    }

    public function created(Setting $setting)
    {
        app(SettingsService::class)->invalidate($this->resolveScope($setting));
    }

    public function deleted(Setting $setting)
    {
        app(SettingsService::class)->invalidate($this->resolveScope($setting));
    }

    protected function resolveScope(Setting $setting)
    {
        if ($setting->scope_type && $setting->scope_id) {
            return "{$setting->scope_type}:{$setting->scope_id}";
        }
        return null;
    }
}
```

**Register in `AppServiceProvider::boot()`**:

```php
public function boot()
{
    \App\Models\Setting::observe(\App\Observers\SettingObserver::class);
}
```

---

## Phase 2: Warm Cache Command

**File**: `app/Console/Commands/WarmSettingsCache.php`

```php
<?php

namespace App\Console\Commands;

use App\Services\SettingsService;
use Illuminate\Console\Command;

class WarmSettingsCache extends Command
{
    protected $signature = 'settings:warm';
    protected $description = 'Warm the settings cache';

    public function handle()
    {
        app(SettingsService::class)->warm();
        $this->info('Settings cache warmed successfully.');
    }
}
```

---

## Phase 2.5 (Optional): Settings History & Audit Trail

For compliance and debugging, track all setting changes with who, when, and why.

**Files to add**:
1. `database/migrations/2026_03_31_create_settings_audit_log_table.php` — Audit log table
2. `app/Models/SettingAuditLog.php` — Model with query scopes (forKey, byUser, fromDate)
3. `app/Observers/SettingHistoryObserver.php` — Auto-logs all changes
4. `app/Console/Commands/SettingsHistoryCommand.php` — View history via CLI

See full implementation: [SETTINGS_HISTORY.md](./SETTINGS_HISTORY.md)

**Quick Setup**:

```bash
# 1. Copy files from SETTINGS_HISTORY.md

# 2. Register observer in AppServiceProvider::boot()
Setting::observe(\App\Observers\SettingHistoryObserver::class);

# 3. Run migration
php artisan migrate

# 4. View history
php artisan settings:history --key=email.driver
```

**Usage**:

```php
// Automatically logged on any set()
$service->set(
    key: 'api.key',
    value: 'new_key_123',
    reason: 'Quarterly rotation'
);

// View history
$history = SettingAuditLog::forKey('api.key')
    ->orderBy('changed_at', 'desc')
    ->get();

// Export for audit
$logs = SettingAuditLog::fromDate(now()->subMonths(3))->get();
```

**Features**:
- ✅ Tracks who, when, before/after values
- ✅ Stores reason for each change
- ✅ Captures IP address & user agent
- ✅ Query by setting key, user, or date range
- ✅ Export to CSV for compliance

---

## Phase 3: Global Helper

**File**: `app/Helpers/SettingsHelper.php`

```php
<?php

if (!function_exists('settings')) {
    function settings($key = null, $default = null, $scope = null)
    {
        $service = app(\App\Services\SettingsService::class);

        if (is_null($key)) {
            return $service;
        }

        return $service->get($key, $default, $scope);
    }
}
```

**Register in `composer.json`**:

```json
"autoload": {
    "files": [
        "app/Helpers/SettingsHelper.php"
    ]
}
```

Then `composer dump-autoload`.

---

## Phase 3: Config Bridge

**File**: `app/Providers/SettingsConfigProvider.php`

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class SettingsConfigProvider extends ServiceProvider
{
    public function boot()
    {
        config([
            'app.name' => settings('site_name', 'My App'),
            'mail.default' => settings('email.driver', 'log'),
            'mail.from.address' => settings('email.from_address', 'noreply@app.test'),
        ]);
    }
}
```

**Register in `config/app.php` providers**:

```php
\App\Providers\SettingsConfigProvider::class,
```

---

## Phase 4: Livewire Component

**File**: `app/Http/Livewire/Admin/SettingsManager.php`

```php
<?php

namespace App\Http\Livewire\Admin;

use App\Models\Setting;
use Livewire\Component;

class SettingsManager extends Component
{
    public $group = 'general';
    public $search = '';

    public function updateSetting($key, $value)
    {
        Setting::where('key', $key)->first()->update(['value' => $value]);
        
        // [TODO]: Emit event for toast
        // $this->dispatchBrowserEvent('Show.Toast.Success', ['text' => 'Setting updated!']);
    }

    public function render()
    {
        $settings = Setting::inGroup($this->group)
            ->when($this->search, fn($q) => 
                $q->where('key', 'like', "%{$this->search}%")
                  ->orWhere('display_name', 'like', "%{$this->search}%")
            )
            ->paginate(15);

        $groups = Setting::select('group')->distinct()->orderBy('group')->pluck('group');

        return view('livewire.admin.settings-manager', [
            'settings' => $settings,
            'groups' => $groups,
        ]);
    }
}
```

---

## Phase 4: Blade Template

**File**: `resources/views/livewire/admin/settings-manager.blade.php`

```blade
<div class="p-6">
    <h2 class="text-2xl font-bold mb-6">Settings</h2>

    <div class="grid grid-cols-4 gap-6">
        <!-- Sidebar -->
        <div class="col-span-1">
            <div class="bg-white rounded shadow">
                <ul class="divide-y">
                    @foreach ($groups as $g)
                        <li>
                            <button
                                wire:click="$set('group', '{{ $g }}')"
                                class="w-full text-left px-4 py-3 hover:bg-gray-50
                                    {{ $group === $g ? 'bg-blue-50 text-blue-600 font-bold' : '' }}"
                            >
                                {{ Str::title($g) }}
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        <!-- Main -->
        <div class="col-span-3">
            <div class="bg-white rounded shadow">
                <!-- Search -->
                <div class="p-4 border-b">
                    <input
                        type="text"
                        wire:model="search"
                        placeholder="Search..."
                        class="w-full px-4 py-2 border rounded"
                    />
                </div>

                <!-- Settings List -->
                <div class="divide-y">
                    @forelse ($settings as $setting)
                        <div class="p-6">
                            <label class="block mb-2">
                                <strong>{{ $setting->display_name }}</strong>
                                <small class="text-gray-500">{{ $setting->key }}</small>
                            </label>
                            
                            {{-- [TODO]: Component switch based on $setting->type --}}
                            @if ($setting->type === 'text')
                                <input
                                    type="text"
                                    value="{{ $setting->value }}"
                                    wire:change="updateSetting('{{ $setting->key }}', $event.target.value)"
                                    class="w-full px-4 py-2 border rounded"
                                />
                            @elseif ($setting->type === 'boolean')
                                <input
                                    type="checkbox"
                                    @checked($setting->value)
                                    wire:change="updateSetting('{{ $setting->key }}', $event.target.checked)"
                                />
                            @endif
                        </div>
                    @empty
                        <div class="p-6 text-center text-gray-500">
                            No settings found
                        </div>
                    @endforelse
                </div>

                {{ $settings->links() }}
            </div>
        </div>
    </div>
</div>
```

---

## Phase 5: Unit Test

**File**: `tests/Unit/Services/SettingsServiceTest.php`

```php
<?php

namespace Tests\Unit\Services;

use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SettingsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SettingsService::class);
    }

    /** @test */
    public function it_gets_a_setting()
    {
        Setting::create([
            'key' => 'site_name',
            'display_name' => 'Site Name',
            'value' => 'Test App',
            'type' => 'text',
        ]);

        $this->assertEquals('Test App', $this->service->get('site_name'));
    }

    /** @test */
    public function it_sets_a_setting()
    {
        $this->service->set('site_name', 'Updated');
        
        $this->assertEquals('Updated', $this->service->get('site_name'));
    }

    /** @test */
    public function it_returns_default_if_not_found()
    {
        $this->assertEquals('default', $this->service->get('nonexistent', 'default'));
    }

    // [TODO]: Add more tests (cache hit, scope, validation, etc.)
}
```

---

## Seeder (Populate Initial Settings)

**File**: `database/seeders/SettingsSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run()
    {
        $settings = [
            [
                'key' => 'site_name',
                'group' => 'general',
                'display_name' => 'Site Name',
                'value' => 'My App',
                'type' => 'text',
            ],
            [
                'key' => 'email_driver',
                'group' => 'email',
                'display_name' => 'Email Driver',
                'value' => 'log',
                'type' => 'select',
                'meta' => ['options' => ['smtp', 'mailgun', 'log']],
            ],
            // [TODO]: Add more settings
        ];

        foreach ($settings as $setting) {
            Setting::create($setting);
        }
    }
}
```

**Usage**: `php artisan db:seed SettingsSeeder`

---

## Quick Start Checklist

```
□ Step 1: Choose Database
  □ Copy config/settings.php to config/
  □ Update .env: SETTINGS_DATABASE = mysql|sqlite|pgsql
  □ Run: php artisan settings:database-setup (auto-creates SQLite if needed)

□ Step 2: Core Implementation
  □ Copy Migration → run php artisan migrate
  □ Copy Model → add to app/Models
  □ Copy Service → add to app/Services
  □ Register Service in AppServiceProvider

□ Step 3: Caching & History (Optional)
  □ Caching:
    □ Copy Observer → register in AppServiceProvider::boot()
    □ Copy Command → add to app/Console/Commands
    □ Warm cache: php artisan settings:warm
  □ History (recommended for compliance):
    □ Copy migration → run php artisan migrate
    □ Copy SettingAuditLog model → app/Models
    □ Copy SettingHistoryObserver → app/Observers
    □ Copy SettingsHistoryCommand → app/Console/Commands
    □ Register observer in AppServiceProvider::boot()
    □ View history: php artisan settings:history

□ Step 4: Developer API
  □ Copy Helper → add to app/Helpers + composer.json autoload
  □ Test: php artisan tinker
    >>> app(SettingsService::class)->set('test', 'value')
    >>> app(SettingsService::class)->get('test')

□ Step 5: Admin UI
  □ Copy Livewire component → app/Http/Livewire
  □ Copy Blade template → resources/views/livewire
  □ Add route to admin panel

□ Step 6: Testing
  □ Add tests to tests/Unit/Services
  □ Run: php artisan test

□ Step 7: Production Ready
  □ Enable caching: SETTINGS_CACHE_ENABLED=true
  □ Setup audit logging: SETTINGS_AUDIT_LOG=true
  □ Configure encrypted keys (if sensitive data)
```

---

## Optional: Environment Variables Fallback

**File**: `.env`

For development or when database isn't required:

```bash
# Settings fallback (loaded if DB setting not found)
SETTING_EMAIL_DRIVER=mailgun
SETTING_EMAIL_FROM=noreply@app.com
SETTING_FEATURES_CHAT_ENABLED=true
SETTING_UPLOAD_MAX_SIZE=10485760
```

**SettingsService will check in order**:
1. Database (if available)
2. Settings file 
3. Environment variables
4. Default

---

## Optional: Settings File (Version-Controlled)

**File**: `config/dynamic-settings.json`

For early development or configs that don't need admin UI:

```json
{
  "email": {
    "driver": "mailgun",
    "from": "noreply@app.com"
  },
  "features": {
    "chat": {
      "enabled": true
    },
    "analytics": {
      "enabled": false
    }
  },
  "upload": {
    "max_size": 10485760,
    "allowed_types": ["jpg", "png", "pdf"]
  }
}
```

---

## Optional: SQLite Fallback (Production DB Downtime)

**File**: `config/database.php`

For automatic fallback if primary database is unavailable:

```php
'connections' => [
    // ... existing connections
    
    'settings_fallback' => [
        'driver' => 'sqlite',
        'database' => storage_path('app/settings.sqlite'),
        'prefix' => '',
    ],
],
```

**Scheduled sync** (`app/Console/Kernel.php`):

```php
protected function schedule(Schedule $schedule)
{
    // Sync primary DB → SQLite every 5 minutes
    $schedule->call(function () {
        try {
            $settings = DB::connection('mysql')
                ->table('settings')
                ->get();
            
            DB::connection('settings_fallback')
                ->table('settings')
                ->truncate();
            
            DB::connection('settings_fallback')
                ->table('settings')
                ->insert($settings->toArray());
            
            Log::info('Settings synced to SQLite fallback');
        } catch (Exception $e) {
            Log::warning('Failed to sync settings to fallback');
        }
    })->everyFiveMinutes();
}
```

---

## Optional: Settings Sync (Bidirectional Database ↔ File Sync)

For teams that manage settings in both database (admin UI) and version-controlled files (deployment).

**File**: `app/Services/SettingsSyncService.php`

See [SETTINGS_SYNC.md](./SETTINGS_SYNC.md) for complete `SettingsSyncService` and `SettingsSyncCommand` implementation.

**Quick Start**:

```php
// app/Providers/AppServiceProvider.php - Boot method
use App\Services\SettingsSyncService;

public function boot()
{
    // Register sync service
    $this->app->singleton(SettingsSyncService::class);
}
```

**Register the Artisan command**:

Copy `SettingsSyncCommand.php` to `app/Console/Commands/`

**Usage Examples**:

```bash
# Sync database → file (export admin changes)
php artisan settings:sync db-to-file

# Sync file → database (deploy new settings)
php artisan settings:sync file-to-db

# Merge both (database takes precedence on conflicts)
php artisan settings:sync merge
```

**Database Precedence**:

When both database and file have the same key with different values:
- ✅ Database value wins
- 📝 Conflict is logged to sync report
- 📄 File is updated with database value

**Sync Report Example**:

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
```

**Scheduled Backup** (optional):

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Daily backup: DB → File at 2 AM
    $schedule->command('settings:sync db-to-file')
        ->dailyAt('02:00')
        ->onSuccess(function () {
            Log::info('Daily settings backup to file completed');
        });

    // Weekly merge on Mondays
    $schedule->command('settings:sync merge')
        ->weekly()
        ->mondays()
        ->at('03:00');
}
```

**CI/CD Integration**:

```yaml
# .github/workflows/deploy.yml
- name: Sync settings to production database
  run: php artisan settings:sync file-to-db
```

For full implementation details, see [SETTINGS_SYNC.md](./SETTINGS_SYNC.md).

---

## What Comes After?

- [ ] Add more input types (number, json, file, etc.)
- [ ] Activity logging (`settings_audit_log` table)
- [ ] Feature flags evaluator
- [ ] A/B testing integration
- [ ] Settings rollback/versioning

---

**Created**: 2026-03-25  
**Use this as a starting point, then customize for your app.**
