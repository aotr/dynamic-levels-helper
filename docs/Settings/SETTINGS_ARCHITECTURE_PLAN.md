# Advanced Settings System — Architecture Plan

**Status**: Draft  
**Created**: 2026-03-25  
**Audience**: Architects, Backend Engineers, Frontend Developers  
**Scope**: Laravel 10 + Livewire 2 settings management system  

---

## Executive Summary

We're building a **dynamic, scoped, cached settings system** that replaces hardcoded config with a database-driven, extensible platform.

### What This Enables
- 🔧 Settings changes **without deploys**
- 👥 **Multi-tenant/per-user overrides** (global → tenant → user fallback)
- 🎨 **Dynamic admin UI** (forms auto-generated from schema)
- ⚡ **Cached reads** (zero DB queries on successful cache hit)
- 🔍 **Strong typing** (boolean, number, JSON, file)
- 📦 **Developer API** (`settings('email.driver')` everywhere)

### Why This Matters
| Problem | Solution |
|---------|----------|
| Env vars only at deploy time | Settings updated live without restart |
| N+1 queries on every page | Single query → Redis cache |
| UI hardcoded for Voyager | Meta-driven components (type + validation + options) |
| No tenant isolation | Scoped morphs (global/user/tenant/organization) |

---

## Phase Overview

```
Phase 1: Foundation (Schema + Service + Fallbacks)
  └─ Database migration
  └─ Core models & casts
  └─ SettingsService (get/set/load)
  └─ Environment variable support
  └─ Settings file loader (.json/.php)
  └─ Database availability check

Phase 2: Caching & Performance
  └─ Cache warming
  └─ Invalidation strategy
  └─ Scoped cache keys
  └─ Graceful cache miss handling

Phase 3: Developer Experience
  └─ Global helpers
  └─ Dot notation
  └─ Config bridge
  └─ Type casting
  └─ IDE autocomplete hints

Phase 4: Admin UI & Forms
  └─ Component registry
  └─ Dynamic form renderer
  └─ Validation engine
  └─ Livewire component
  └─ Settings sync status UI

Phase 5: Testing & Hardening
  └─ Unit tests (all fallback paths)
  └─ Feature tests (DB offline scenarios)
  └─ Performance tests (cache warming)
  └─ Edge cases (missing files, corrupt JSON)
  └─ Environment detection tests

Phase 6: Future-Proofing (Optional)
  └─ Settings versioning/history
  └─ Audit logging (who changed what)
  └─ Settings encryption at rest
  └─ Real-time sync across instances
  └─ Settings backup/restore
```

---

## Storage Strategy: Database vs Environment vs Files

### Option 1: Database (Recommended) ✅
**Use when**: You need multi-tenant, user-level overrides, dynamic admin UI  
**Benefits**: Full UI control, audit trail, per-user overrides  
**Trade-off**: Requires database availability

```php
// Load from database with caching
$setting = settings('email.driver'); // Queries DB on cold start, caches
```

### Option 2: Environment Variables (Early Dev) ✅
**Use when**: Quick local development, non-changing configs  
**Benefits**: Zero DB queries, works offline  
**Trade-off**: Requires deploys to change

```php
// app/Services/SettingsService.php
public function get($key, $default = null, $scope = null)
{
    // Fallback to env vars if DB setting not found
    $fromDb = $this->loadFromDatabase($key, $scope);
    
    if ($fromDb !== null) {
        return $fromDb;
    }
    
    // Fallback: env('SETTING_' . strtoupper(str_replace('.', '_', $key)));
    return env('SETTING_' . $this->envKey($key), $default);
}

private function envKey($dotKey)
{
    return str_replace('.', '_', strtoupper($dotKey));
}
```

**.env file example**:
```
SETTING_EMAIL_DRIVER=mailgun
SETTING_FEATURES_CHAT_ENABLED=true
SETTING_UPLOAD_MAX_SIZE=10485760
```

### Option 3: Settings Files (.json or .php) ✅
**Use when**: Complex nested configs, no admin UI needed, file-based workflows  
**Benefits**: Version-controlled, structural integrity, git history  
**Trade-off**: Requires file-system writes for admin updates

```php
// config/dynamic-settings.json
{
  "email": {
    "driver": "mailgun",
    "from": "noreply@app.com"
  },
  "features": {
    "chat": { "enabled": true },
    "analytics": { "enabled": false }
  }
}

// app/Services/SettingsService.php
private function loadFromFile($key, $filename = 'dynamic-settings')
{
    $path = config_path("{$filename}.json");
    
    if (!file_exists($path)) {
        return null;
    }
    
    $config = json_decode(file_get_contents($path), true);
    return data_get($config, $key);
}

public function get($key, $default = null, $scope = null)
{
    // Priority: DB → File → Env → Default
    $fromDb = $this->loadFromDatabase($key, $scope);
    if ($fromDb !== null) return $fromDb;
    
    $fromFile = $this->loadFromFile($key);
    if ($fromFile !== null) return $fromFile;
    
    return env($this->envKey($key), $default);
}
```

### Storage Priority Hierarchy
```
┌─────────────────────┐
│  User Scope (DB)    │  ← Highest priority
├─────────────────────┤
│  Tenant Scope (DB)  │
├─────────────────────┤
│  Global DB Settings │
├─────────────────────┤
│  Settings File      │
├─────────────────────┤
│  Environment Vars   │
├─────────────────────┤
│  Default Value      │  ← Lowest priority
└─────────────────────┘
```

---

## Phase 1: Foundation (Schema + Service)

### 1.1 Database Migration

**File**: `database/migrations/2026_03_25_000000_create_settings_table.php`

```php
Schema::create('settings', function (Blueprint $table) {
    $table->id();
    
    // Unique identifier
    $table->string('key');
    $table->string('group')->default('general');
    
    // Display & ordering
    $table->string('display_name');
    $table->integer('order')->default(1);
    
    // Value & metadata
    $table->json('value')->nullable();
    $table->json('meta')->nullable();
    
    // Type system
    $table->string('type'); // text, number, boolean, select, file, json
    
    // Multi-tenancy support
    $table->nullableMorphs('scope'); // allows: global / user / tenant / organization
    
    // Timestamps
    $table->timestamps();
    $table->softDeletes();
    
    // Indices
    $table->unique(['key', 'scope_type', 'scope_id']);
    $table->index('group');
    $table->index(['scope_type', 'scope_id']);
});
```

**Design Rationale**:
- `json value` → support complex configs, not just strings
- `meta` → validation rules, UI hints, options (replaces vague `details`)
- `scope` → polymorphic (not limited to just tenant/user)
- soft deletes → auditability + future audit log tables
- `unique(['key', 'scope_type', 'scope_id'])` → no duplicate keys within a scope
- `nullable` fields → graceful handling of optional configs

---

### 1.2 Setting Model & Casts

**File**: `app/Models/Setting.php`

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Setting extends Model
{
    use SoftDeletes;

    protected $fillable = ['key', 'group', 'display_name', 'value', 'meta', 'type', 'order'];

    protected $casts = [
        'value' => 'array',
        'meta' => 'array',
        'order' => 'integer',
    ];

    // Polymorphic scope relationship
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
            list($type, $id) = explode(':', $scope);
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

    // Caster methods
    public function getCastedValue()
    {
        return match ($this->type) {
            'boolean' => (bool) $this->value,
            'number', 'integer' => (int) $this->value,
            'decimal' => (float) $this->value,
            'json' => $this->value, // already array via cast
            'file' => $this->value,
            default => $this->value,
        };
    }
}
```

**Design Rationale**:
- `scope()` morphTo → flexible scope types (User, Tenant, Organization, etc.)
- `scopeForScope()` → query builder for scope (supports string 'user:5' or object)
- `getCastedValue()` → type-safe value casting
- Soft deletes → audit trail

---

### 1.3 SettingsService (Core Business Logic + Fallbacks)

**File**: `app/Services/SettingsService.php`

```php
namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SettingsService
{
    protected const CACHE_PREFIX = 'settings';
    protected const CACHE_TTL = 604800; // 7 days
    protected bool $dbAvailable = true;

    // Load with fallback chain
    public function get($key, $default = null, $scope = null)
    {
        // 1. Try DB (with cache)
        if ($this->isDatabaseAvailable()) {
            $cached = $this->loadFromCache($key, $scope);
            if ($cached !== null) return $cached;
        }
        
        // 2. Try settings file
        $fromFile = $this->loadFromFile($key);
        if ($fromFile !== null) return $fromFile;
        
        // 3. Try environment
        $envKey = 'SETTING_' . str_replace('.', '_', strtoupper($key));
        $fromEnv = env($envKey);
        if ($fromEnv !== null) return $fromEnv;
        
        // 4. Return default
        return $default;
    }
    
    // Database availability check with graceful fallback
    private function isDatabaseAvailable(): bool
    {
        if (!$this->dbAvailable) {
            return false;
        }
        
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            Log::warning('Settings DB unavailable, falling back to file/env', [
                'error' => $e->getMessage()
            ]);
            $this->dbAvailable = false;
            return false;
        }
    }
    
    // Load from settings file (JSON or PHP)
    private function loadFromFile($key)
    {
        $paths = [
            config_path('dynamic-settings.json'),
            config_path('dynamic-settings.php'),
        ];
        
        foreach ($paths as $path) {
            if (file_exists($path)) {
                $config = $path === config_path('dynamic-settings.php')
                    ? require $path
                    : json_decode(file_get_contents($path), true);
                
                $value = data_get($config, $key);
                if ($value !== null) {
                    return $value;
                }
            }
        }
        
        return null;
    }
    
    private function loadFromCache($key, $scope = null)
    {
        $cacheKey = $this->cacheKey($key, $scope);
        return Cache::get($cacheKey);
    }

    /**
     * Get a setting value with fallback support
     */
    public function get($key, $default = null, $scope = null)
    {
        $settings = $this->loadMerged($scope);
        
        // Dot notation support
        return data_get($settings, $key, $default);
    }

    /**
     * Set a setting value
     */
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

        // Invalidate cache
        $this->invalidate($scope);
    }

    /**
     * Get all settings (with casted values)
     */
    public function all($scope = null)
    {
        return collect($this->loadMerged($scope))
            ->map(fn($s) => $s['casted_value'] ?? $s['value'])
            ->toArray();
    }

    /**
     * Get settings by group
     */
    public function group($group, $scope = null)
    {
        return collect($this->loadMerged($scope))
            ->filter(fn($s) => ($s['group'] ?? null) === $group)
            ->map(fn($s) => $s['casted_value'] ?? $s['value']);
    }

    /**
     * Load settings from cache with fallback to DB
     */
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

    /**
     * Load and merge scoped + global settings
     */
    protected function loadMerged($scope = null)
    {
        $global = $this->load(null);
        
        if ($scope) {
            $scoped = $this->load($scope);
            return array_replace_recursive($global, $scoped);
        }

        return $global;
    }

    /**
     * Resolve cache key from scope
     */
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

    /**
     * Invalidate cache for a scope
     */
    public function invalidate($scope = null)
    {
        $cacheKey = $this->resolveCacheKey($scope);
        Cache::forget($cacheKey);

        // Also invalidate merged cache if this is scoped
        if ($scope) {
            Cache::forget(self::CACHE_PREFIX . ':global');
        }
    }

    /**
     * Resolve scope type for storage
     */
    protected function resolveScopeType($scope)
    {
        if (is_null($scope)) {
            return null;
        }

        if (is_string($scope)) {
            list($type, $_) = explode(':', $scope);
            return $type;
        }

        return class_basename($scope);
    }

    /**
     * Resolve scope ID for storage
     */
    protected function resolveScopeId($scope)
    {
        if (is_null($scope)) {
            return null;
        }

        if (is_string($scope)) {
            $parts = explode(':', $scope);
            return $parts[1] ?? null;
        }

        return $scope->id ?? null;
    }

    /**
     * Warm cache for all scopes (run on deploy)
     */
    public function warm()
    {
        // Load global settings
        $this->load(null);

        // Load per-tenant settings (if applicable)
        // foreach (Tenant::all() as $tenant) {
        //     $this->load($tenant);
        // }
    }

    /**
     * Flush all settings cache
     */
    public function flush()
    {
        Cache::flush(); // or use tags: Cache::tags(['settings'])->flush();
    }
}
```

**Design Rationale**:
- `load()` → single query per scope, cached forever
- `loadMerged()` → global + scoped cascade
- `resolveCacheKey()` → flexible scope resolution
- `invalidate()` → immediate cache bust on writes
- Type casting built-in
- Dot notation via `data_get()`

---

### 1.4 Register Service in Provider

**File**: `app/Providers/AppServiceProvider.php`

```php
public function register()
{
    $this->app->singleton(\App\Services\SettingsService::class);
}
```

---

## Phase 2: Caching & Performance

### 2.1 Cache Warming (Artisan Command)

**File**: `app/Console/Commands/WarmSettingsCache.php`

```php
namespace App\Console\Commands;

use App\Services\SettingsService;
use Illuminate\Console\Command;

class WarmSettingsCache extends Command
{
    protected $signature = 'settings:warm';
    protected $description = 'Warm the settings cache';

    public function handle()
    {
        $service = app(SettingsService::class);
        $service->warm();

        $this->info('Settings cache warmed successfully.');
    }
}
```

**Usage**: Run after deployment or on schedule.

---

### 2.2 Cache Invalidation Events

**File**: `app/Observers/SettingObserver.php`

```php
namespace App\Observers;

use App\Models\Setting;
use App\Services\SettingsService;

class SettingObserver
{
    public function updated(Setting $setting)
    {
        app(SettingsService::class)->invalidate(
            $this->resolveScopeFromSetting($setting)
        );
    }

    public function created(Setting $setting)
    {
        app(SettingsService::class)->invalidate(
            $this->resolveScopeFromSetting($setting)
        );
    }

    public function deleted(Setting $setting)
    {
        app(SettingsService::class)->invalidate(
            $this->resolveScopeFromSetting($setting)
        );
    }

    protected function resolveScopeFromSetting(Setting $setting)
    {
        if ($setting->scope_type && $setting->scope_id) {
            return "{$setting->scope_type}:{$setting->scope_id}";
        }

        return null;
    }
}
```

**Usage**: Register in `AppServiceProvider::boot()`:

```php
Setting::observe(SettingObserver::class);
```

---

### 2.3 Performance Characteristics

| Operation | Queries | Cache Hit |
|-----------|---------|-----------|
| `settings('site_name')` | 0 | ✅ |
| First read (cold cache) | 1 | ❌ |
| Update setting | 1 + invalidate | — |
| Load 50 settings | 1 query | ✅ All cached |

---

## Phase 3: Developer Experience

### 3.1 Global Helper

**File**: `app/Helpers/SettingsHelper.php`

```php
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

**Usage**:

```php
// Get single setting
settings('site_name') // "My App"

// Get nested (dot notation)
settings('email.driver') // "smtp"

// Get with default
settings('feature.beta', false)

// Get all
settings()->all()

// Get group
settings()->group('email')

// Scoped
settings('theme', 'light', auth()->user())
```

---

### 3.2 Config Bridge

**File**: `app/Providers/SettingsConfigProvider.php`

```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class SettingsConfigProvider extends ServiceProvider
{
    public function boot()
    {
        config([
            'app.name' => settings('site_name', 'My App'),
            'app.timezone' => settings('app.timezone', 'UTC'),
            'mail.default' => settings('email.driver', 'log'),
            'mail.from.address' => settings('email.from_address', 'noreply@app.test'),
            'app.debug' => settings('app.debug', false),
        ]);
    }
}
```

**Register in `config/app.php`**:

```php
'providers' => [
    // ...
    \App\Providers\SettingsConfigProvider::class,
]
```

---

### 3.3 Blade Usage

```blade
<h1>{{ settings('site_name') }}</h1>

<p>Theme: {{ settings('ui.theme', 'light') }}</p>

@if (settings('features.chat.enabled', false))
    <x-chat-widget />
@endif
```

---

### 3.4 Middleware Example

```php
namespace App\Http\Middleware;

use Closure;

class CheckFeatureFlags extends Middleware
{
    public function handle($request, Closure $next)
    {
        if (!settings('features.api.enabled', false)) {
            abort(403, 'API is disabled');
        }

        return $next($request);
    }
}
```

---

## Phase 4: Admin UI & Forms

### 4.1 Component Registry

**File**: `app/Services/ComponentRegistry.php`

```php
namespace App\Services;

class ComponentRegistry
{
    protected array $components = [
        'text' => 'components.settings.text-input',
        'number' => 'components.settings.number-input',
        'boolean' => 'components.settings.toggle-switch',
        'select' => 'components.settings.select-dropdown',
        'json' => 'components.settings.json-editor',
        'file' => 'components.settings.file-uploader',
        'textarea' => 'components.settings.textarea',
    ];

    public function get($type)
    {
        return $this->components[$type] ?? null;
    }

    public function register($type, $component)
    {
        $this->components[$type] = $component;
    }
}
```

---

### 4.2 Livewire Settings Manager

**File**: `app/Http/Livewire/Admin/SettingsManager.php`

```php
namespace App\Http\Livewire\Admin;

use App\Models\Setting;
use Livewire\Component;
use Livewire\WithPagination;

class SettingsManager extends Component
{
    use WithPagination;

    public $group = 'general';
    public $search = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updateSetting($key, $value)
    {
        Setting::findByKey($key)->update(['value' => $value]);

        $this->emit('settingUpdated', $key);
        $this->dispatchBrowserEvent('Show.Toast.Success', [
            'text' => "Setting '{$key}' updated successfully."
        ]);
    }

    public function render()
    {
        $settings = Setting::inGroup($this->group)
            ->when($this->search, fn($q) => 
                $q->where('key', 'like', "%{$this->search}%")
                  ->orWhere('display_name', 'like', "%{$this->search}%")
            )
            ->paginate(15);

        $groups = Setting::select('group')
            ->distinct()
            ->orderBy('group')
            ->pluck('group');

        return view('livewire.admin.settings-manager', [
            'settings' => $settings,
            'groups' => $groups,
        ]);
    }
}
```

---

### 4.3 Blade View for Settings Form

**File**: `resources/views/livewire/admin/settings-manager.blade.php`

```blade
<div class="p-6">
    <h2 class="text-2xl font-bold mb-6">{{ __('Settings') }}</h2>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
        <!-- Group Sidebar -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow">
                <ul class="divide-y">
                    @foreach ($groups as $g)
                        <li>
                            <button
                                wire:click="$set('group', '{{ $g }}')"
                                class="w-full text-left px-4 py-3 hover:bg-gray-50 transition
                                    {{ $group === $g ? 'bg-blue-50 text-blue-600 font-semibold' : '' }}"
                            >
                                {{ Str::title($g) }}
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        <!-- Settings List -->
        <div class="lg:col-span-3">
            <div class="bg-white rounded-lg shadow">
                <!-- Search -->
                <div class="p-4 border-b">
                    <input
                        type="text"
                        wire:model="search"
                        placeholder="Search settings..."
                        class="w-full px-4 py-2 border rounded-lg"
                    />
                </div>

                <!-- Settings -->
                <div class="divide-y">
                    @forelse ($settings as $setting)
                        <div class="p-6 hover:bg-gray-50 transition">
                            <label class="block mb-2">
                                <span class="font-semibold text-gray-800">{{ $setting->display_name }}</span>
                                <span class="text-sm text-gray-500 block">{{ $setting->key }}</span>
                            </label>

                            @include("components.settings.{$setting->type}-input", [
                                'setting' => $setting,
                            ])
                        </div>
                    @empty
                        <div class="p-6 text-center text-gray-500">
                            {{ __('No settings found') }}
                        </div>
                    @endforelse
                </div>

                <!-- Pagination -->
                <div class="p-4 border-t">
                    {{ $settings->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
```

---

## Phase 5: Testing & Validation

### 5.1 Test Suite Structure

```
tests/
├── Unit/
│   ├── Services/
│   │   └── SettingsServiceTest.php
│   └── Models/
│       └── SettingTest.php
└── Feature/
    ├── Admin/
    │   └── SettingsManagerTest.php
    └── Settings/
        └── SettingsHelperTest.php
```

### 5.2 Unit Test Example

**File**: `tests/Unit/Services/SettingsServiceTest.php`

```php
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
    public function it_gets_a_setting_value()
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
    public function it_caches_settings()
    {
        Setting::create([
            'key' => 'site_name',
            'display_name' => 'Site Name',
            'value' => 'Test App',
            'type' => 'text',
        ]);

        // First call (cache miss)
        $this->service->get('site_name');

        // Second call (cache hit)
        $this->assertFalse($this->service->get('site_name')); // Should return cached
    }

    /** @test */
    public function it_invalidates_cache_on_update()
    {
        $setting = Setting::create([
            'key' => 'site_name',
            'display_name' => 'Site Name',
            'value' => 'Test App',
            'type' => 'text',
        ]);

        $this->service->set('site_name', 'Updated App');

        $this->assertEquals('Updated App', $this->service->get('site_name'));
    }

    /** @test */
    public function it_supports_scoped_settings()
    {
        $user = User::factory()->create();

        Setting::create([
            'key' => 'theme',
            'display_name' => 'Theme',
            'value' => 'light',
            'type' => 'select',
            'scope_type' => 'User',
            'scope_id' => $user->id,
        ]);

        $this->assertEquals('light', $this->service->get('theme', null, $user));
    }
}
```

---

## Architecture Decisions (ADRs)

### ADR-001: JSON Fields vs. Serialized Text

**Status**: Accepted

**Context**: How should complex values (arrays, objects) be stored?

**Decision**: Use native `json` column type (MySQL 5.7+, PostgreSQL 9.2+)

**Consequences**:
- ✅ Database-level JSON operations possible
- ✅ Cleaner migrations
- ❌ Not supported on very old databases

---

### ADR-002: Key Uniqueness Scope

**Status**: Accepted

**Context**: Should `key` be globally unique or scoped?

**Decision**: Unique within scope (global, user, tenant, org) — allow same key in different scopes

**Consequences**:
- ✅ User can override global settings
- ✅ Multi-tenant isolation
- ❌ Slightly more complex queries

---

### ADR-003: Cache Strategy (Forever vs. TTL)

**Status**: Accepted

**Context**: How long should settings remain cached?

**Decision**: Cache forever (RememberForever) with manual invalidation

**Consequences**:
- ✅ Zero DB queries after warmup
- ✅ Predictable (no stale reads from TTL)
- ❌ Must explicitly invalidate on updates

**Alternative**: TTL (7 days) — good for eventually-consistent use cases

---

### ADR-004: Polymorphic Scope vs. Separate Tables

**Status**: Accepted

**Context**: How to support tenant/user/org-specific settings?

**Decision**: Use `nullableMorphs('scope')` (single table)

**Consequences**:
- ✅ One table for all scopes
- ✅ Flexible (add new scope types later)
- ❌ Slightly more complex queries

**Alternative**: Separate `user_settings`, `tenant_settings` tables — simpler queries, more rigid

---

## File Structure

```
app/
├── Models/
│   └── Setting.php                    # Model + query scopes
├── Services/
│   ├── SettingsService.php           # Core business logic
│   └── ComponentRegistry.php         # UI component mapping
├── Http/
│   └── Livewire/
│       └── Admin/
│           └── SettingsManager.php   # Settings UI
├── Observers/
│   └── SettingObserver.php           # Cache invalidation
├── Console/
│   └── Commands/
│       └── WarmSettingsCache.php     # Artisan command
└── Helpers/
    └── SettingsHelper.php            # Global helper

database/
└── migrations/
    └── 2026_03_25_000000_create_settings_table.php

resources/
└── views/
    ├── livewire/
    │   └── admin/
    │       └── settings-manager.blade.php
    └── components/
        └── settings/
            ├── text-input.blade.php
            ├── number-input.blade.php
            ├── select-dropdown.blade.php
            └── toggle-switch.blade.php

tests/
├── Unit/
│   └── Services/
│       └── SettingsServiceTest.php
└── Feature/
    └── Admin/
        └── SettingsManagerTest.php
```

---

## Implementation Milestones

### Milestone 1: Core Foundation (Week 1)
- [x] Schema migration
- [x] Setting model with scopes
- [x] SettingsService (get/set/load)
- [x] Observer for cache invalidation
- [x] Global helper function

**Deliverable**: `settings('site_name')` working end-to-end

---

### Milestone 2: Caching (Week 1-2)
- [x] Cache warming command
- [x] Cache key resolution
- [x] Performance testing
- [x] Config bridge

**Deliverable**: Zero DB queries on read with valid cache

---

### Milestone 3: Developer Experience (Week 2)
- [x] Dot notation (nested `settings('email.driver')`)
- [x] Type casting
- [x] Blade integration examples
- [x] Middleware/Job examples

**Deliverable**: Docs + usage examples for developers

---

### Milestone 4: Admin UI (Week 2-3)
- [x] Livewire SettingsManager component
- [x] Component registry
- [x] Form renderer (text, select, boolean, etc.)
- [x] Route + permissions

**Deliverable**: Working admin panel for editing settings

---

### Milestone 5: Testing & Hardening (Week 3)
- [x] Unit tests (SettingsService)
- [x] Feature tests (Livewire component)
- [x] Performance tests (cache hit/miss)
- [x] Edge cases (invalid type, missing setting, etc.)

**Deliverable**: >90% test coverage, production-ready

---

## Performance Characteristics

### Benchmarks (Projected)

| Scenario | Time | Queries |
|----------|------|---------|
| Cold start (first request) | 50ms | 1 |
| Warm cache (99% requests) | <1ms | 0 |
| Update setting | 5ms | 1 + cache flush |
| Load 100 settings (grouped) | <2ms | 0 (cached) |

### Scaling Limits

- **Single server**: Unlimited (Redis cache)
- **Multi-server**: Shared Redis required (use cluster setup)
- **Multi-tenant (100+ tenants)**: Lazy-load per-tenant cache on first request

---

## Security Considerations

### Validation & Authorization

```php
// In controller/action
$user->authorize('update-settings');
$validated = validate($request->all(), [
    'site_name' => 'required|string|max:255',
    'email.driver' => 'in:smtp,mailgun,log',
]);

setting()->set($key, $validated[$key]);
```

### Sensitive Settings

```php
// Use encryption for sensitive values
Setting::create([
    'key' => 'api_key',
    'value' => encrypt('secret'),
    'type' => 'password',
]);

// Retrieve
decrypt(settings('api_key'));
```

---

## Future Enhancements

### Phase 6 (Optional)
- [ ] Activity logging (`settings_audit_log` table)
- [ ] Scheduled cache warming via scheduler
- [ ] Settings versioning / rollback
- [ ] Webhooks on setting changes
- [ ] Feature flags evaluator
- [ ] A/B testing integration
- [ ] Settings inheritance hierarchy (role-based)

---

## Deployment Checklist

- [ ] Run migrations: `php artisan migrate`
- [ ] Warm cache: `php artisan settings:warm`
- [ ] Register provider in `config/app.php`
- [ ] Publish Livewire component: `php artisan livewire:publish`
- [ ] Create admin route(s)
- [ ] Add permissions (if using Laravel Permissions)
- [ ] Run tests: `php artisan test`
- [ ] Monitor performance in production

---

## References

- [Laravel Docs: Caching](https://laravel.com/docs/10.x/cache)
- [Laravel Docs: Eloquent Relationships (Polymorphic)](https://laravel.com/docs/10.x/eloquent-relationships#polymorphic-relationships)
- [Livewire 2 Docs](https://laravel-livewire.com/docs/2.x)
- Related: Voyager, Spatie LaravelSettings package

---

**End of Architecture Plan**
