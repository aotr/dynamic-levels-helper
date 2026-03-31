# Settings System - Troubleshooting Guide

Common issues and solutions.

---

## Cache Issues

### Problem: Settings not updating (changed value still shows old value)

**Cause:** Cache not being invalidated

**Solution 1: Manual invalidation**
```php
settings()->invalidate();  // Clear cache
$value = settings('app.name');  // Re-fetch from DB
```

**Solution 2: Verify cache is enabled**
```php
// Check config/settings.php
'cache' => [
    'enabled' => true,  // Make sure this is true
],

// Or temporarily disable
config(['settings.cache.enabled' => false]);
$value = settings('app.name');  // Bypasses cache
config(['settings.cache.enabled' => true]);
```

**Solution 3: Check cache driver**
```php
// Ensure cache driver is configured in .env
CACHE_DRIVER=redis   // or file, database, memcached

// Test cache directly
Cache::put('test', 'value', 60);
echo Cache::get('test');  // Should print 'value'
```

**Solution 4: Clear all cache**
```bash
php artisan cache:clear
php artisan settings:warm  # Re-populate
```

---

### Problem: Too many database queries

**Cause:** Cache is disabled or not warmed

**Solution 1: Enable cache**
```php
// In config/settings.php
'cache' => [
    'enabled' => true,
    'ttl' => null,  // Forever until invalidated
],
```

**Solution 2: Warm cache on deploy**
```bash
# In deployment script
php artisan migrate
php artisan settings:warm
php artisan cache:clear
```

**Solution 3: Pre-load frequently used settings**
```php
// In AppServiceProvider boot()
public function boot() {
    // Pre-load these at startup
    $important = settings()->group('app');
    $important = settings()->group('mail');
}
```

---

## Settings Not Persisting

### Problem: Settings are created but disappear

**Cause:** Soft deletes or transaction rollback

**Solution 1: Check if setting exists**
```php
// Verify before and after
echo settings('key1') . "\n";  // Check before
settings()->set('key1', 'value', 'group', 'string');
echo settings('key1') . "\n";  // Check after

// Or check database directly
SELECT * FROM settings WHERE key = 'key1';
```

**Solution 2: Transaction issues**
```php
// If wrapping in transaction, ensure it's committed
DB::transaction(function () {
    settings()->set('key1', 'value', 'group', 'string');
});  // Auto-commits

// Don't use deprecated DB::beginTransaction() without commit
```

**Solution 3: Check for hard deletes**
```php
// Settings are soft-deleted by default
// If you deleted with forceDelete():
$setting->forceDelete();  // Data is gone

// Restore soft-deleted
$setting->restore();

// Or from scope filter:
Setting::withTrashed()->where('key', 'app.name')->first();
```

---

## Database Issues

### Problem: Table not found when accessing settings

**Cause:** Migration not run or table dropped

**Solution 1: Run migrations**
```bash
php artisan migrate

# Or specific migration
php artisan migrate --path=/database/migrations/YOUR_MIGRATION.php
```

**Solution 2: Check migration status**
```bash
php artisan migrate:status
# Shows which migrations are pending
```

**Solution 3: Recreate tables**
```bash
# Danger: This drops existing data
php artisan migrate:refresh --path=/database/migrations/

# Better: Create schema manually
php artisan make:migration create_settings_table_again
```

**Solution 4: Verify tables exist**
```php
// In tinker
>>> DB::table('settings')->count();
=> 0

>>> Schema::hasTable('settings');
=> true
```

---

### Problem: SettingAuditLog table missing

**Cause:** Audit migration not published

**Solution:**
```bash
php artisan vendor:publish --tag=settings-migrations
php artisan migrate
```

---

## Scope Issues

### Problem: User-scoped settings showing global values

**Cause:** Incorrect scope parameter

**Solution 1: Verify scope is passed**
```php
// Wrong - no scope
$value = settings('app.name');  // Gets global

// Right - pass user as scope
$value = settings('app.name', null, Auth::user());  // Gets user's value
```

**Solution 2: Check database scope**
```php
// Query to see what's in DB
SELECT * FROM settings 
WHERE key = 'app.name' 
AND (model_type IS NULL OR model_type = 'App\Models\User');

// Should show entries with model_id = user's ID for scoped
```

**Solution 3: Use string syntax if model unavailable**
```php
// If you have ID but not model instance
$userId = 1;
$value = settings('app.name', null, 'App\Models\User:' . $userId);
```

**Solution 4: Merge logic issue**
```php
// If loadMerged() not working, check:
// 1. Global setting exists
// 2. Scoped setting exists
// 3. Scoped one should override

settings()->set('app.name', 'Global', 'app', 'string');  // Global
settings()->set('app.name', 'User', 'app', 'string', $user);  // Scoped

$value = settings('app.name', null, $user);  // Should be 'User'
```

---

## Type Casting Issues

### Problem: Boolean always returns true/false regardless of stored value

**Cause:** Casting working correctly (is a feature, not a bug)

**Reason:** Boolean type always casts 1/0 to true/false:
```php
settings()->set('feature.enabled', '1', 'features', 'boolean');
$value = settings('feature.enabled');  // true (not '1')

settings()->set('feature.enabled', '0', 'features', 'boolean');
$value = settings('feature.enabled');  // false (not '0')
```

**To store as string instead:**
```php
settings()->set('feature.enabled', 'true', 'features', 'string');
$value = settings('feature.enabled');  // 'true' (string)

// Or handle yourself:
$enabled = strtolower(settings('feature.enabled', 'false')) === 'true';
```

---

### Problem: JSON type not returning array

**Cause:** Value stored as JSON string, not encoded

**Solution 1: Encode when setting**
```php
// Wrong
$data = ['key' => 'value'];
settings()->set('app.config', $data, 'app', 'json');  // Might not serialize

// Right
$data = ['key' => 'value'];
settings()->set('app.config', json_encode($data), 'app', 'json');

// Verify in DB
SELECT value FROM settings WHERE key = 'app.config';
// Should show: {"key":"value"}
```

**Solution 2: Decode when needed**
```php
$value = settings('app.config');
if (is_string($value)) {
    $value = json_decode($value, true);  // Convert to array
}
```

**Solution 3: Use Model casting**
```php
// If you access Setting model directly:
$setting = Setting::whereKey('app.config')->first();
echo $setting->value;  // JSON string
echo $setting->value_decoded;  // Would need custom accessor
```

---

### Problem: Integer type returning string

**Cause:** Database column is VARCHAR/TEXT

**Solution 1: Change column type**
```php
// Create migration
Schema::table('settings', function (Blueprint $table) {
    $table->text('value')->change();  // Or use mediumText
});

php artisan migrate
```

**Solution 2: Cast in retrieval**
```php
$ttl = (int) settings('cache.ttl', 3600);  // Force cast
```

**Solution 3: Verify type is set correctly**
```php
$setting = Setting::whereKey('cache.ttl')->first();
echo $setting->type;  // Should be 'integer'

// If wrong, update:
$setting->update(['type' => 'integer']);
```

---

## Performance Issues

### Problem: Livewire form is slow to load

**Cause 1:** Too many settings in database

**Solution:**
```php
// Limit initial load
public function mount() {
    $this->loadSettings();  // Only loads what's displayed
}

// Or paginate in template
{{ $settings->paginate(20) }}
```

**Cause 2:** Query not indexed

**Solution:**
```bash
# Create migration to add indexes
php artisan make:migration add_settings_indexes
```

```php
// In migration
Schema::table('settings', function (Blueprint $table) {
    $table->index('key');
    $table->index('group');
    $table->index(['model_type', 'model_id']);
    $table->index('created_at');
});

php artisan migrate
```

**Cause 3:** Audit log too large

**Solution:**
```bash
# Prune old entries
php artisan settings:history --prune=90

# Or manually
settings()->pruneAuditLogs(90);
```

---

### Problem: Memory usage growing over time

**Cause:** Cache not clearing old entries

**Solution 1: Implement Redis memory limits**
```php
// In config/database.php
'redis' => [
    'client' => 'phpredis',
    'maxmemory' => '256mb',
    'maxmemory-policy' => 'allkeys-lru',  // Auto-evict old keys
],
```

**Solution 2: Prune cache regularly**
```php
// In console/Kernel.php
protected function schedule(Schedule $schedule) {
    $schedule->command('cache:prune-stale-tags')
        ->hourly();
}
```

**Solution 3: Check what's in cache**
```php
// In tinker or command
$cache = Cache::get('settings');
echo count($cache);  // How many settings cached
echo json_encode($cache, JSON_PRETTY_PRINT);  // See contents
```

---

## API/Request Issues

### Problem: /admin/settings returns 403 Forbidden

**Cause:** Missing authentication or authorization

**Solution 1: Add middleware**
```php
// In routes that use settings form:
Route::get('/admin/settings', SettingsPage::class)
    ->middleware(['auth', 'admin']);  // Add auth!
```

**Solution 2: Create admin middleware**
```php
// app/Http/Middleware/IsAdmin.php
class IsAdmin {
    public function handle($request, $next) {
        if (!Auth::check() || !Auth::user()->is_admin) {
            abort(403);
        }
        return $next($request);
    }
}

// Register in Kernel.php
protected $routeMiddleware = [
    'admin' => \App\Http\Middleware\IsAdmin::class,
];
```

**Solution 3: Check authentication in Livewire**
```php
// In SettingsForm component
public function mount() {
    if (!Auth::check()) {
        abort(401);
    }
    if (!Auth::user()->can('manage-settings')) {
        abort(403);
    }
}
```

---

### Problem: Settings not updating via Livewire form

**Cause 1:** Form validation failing silently

**Solution:**
```php
// In SettingsForm, check for errors:
if ($this->getErrorBag()->isNotEmpty()) {
    $this->dispatch('alert', 'Validation failed: ' . implode(', ', $this->errors->all()));
    return;
}
```

**Cause 2:** Livewire not connected**

**Solution:**
```blade
<!-- Ensure Livewire scripts are loaded -->
@livewire('crud')
@livewireScripts  <!-- Required in layout! -->
```

**Cause 3:** Model not found**

**Solution:**
```php
// In saveSetting()
$setting = Setting::whereKey($key)->first();
if (!$setting) {
    $this->dispatch('alert', 'Setting "' . $key . '" not found');
    return;
}
```

---

## Authentication Issues

### Problem: User methods not found in audit logs

**Cause:** Causer is soft-deleted or not eager-loaded

**Solution:**
```php
// In view, eager load causer:
$logs = settings()->getAuditLog()
    ->with('causer')  // Eager load relationship
    ->get();

// In blade:
{{ $log->causer?->name ?? 'Deleted User' }}
```

---

## Migration Issues

### Problem: Migration already exists error

**Cause:** File timestamp conflicts

**Solution:**
```bash
# Delete old migration file and create new one
rm database/migrations/2026_03_31_000000_create_settings_table.php

# Create new with fresh timestamp
php artisan make:migration create_settings_table
```

---

### Problem: Foreign key constraint fails on migrate

**Cause:** Setting references non-existent model

**Solution 1: Disable constraints**
```php
// In migration
Schema::disableForeignKeyConstraints();
// ... your operations
Schema::enableForeignKeyConstraints();
```

**Solution 2: Check model exists before referencing**
```php
// Don't add foreign key if not needed
// OR ensure model migration runs first
```

---

## Livewire Component Issues

### Problem: Livewire component not rendering

**Cause 1:** Component not registered

**Solution:**
```bash
# Verify in service provider
php artisan make:livewire settings-form
# Or check DynamicLevelHelperServiceProvider has registration
```

**Cause 2:** Missing assets**

**Solution:**
```bash
php artisan vendor:publish --tag=settings-views
php artisan vendor:publish --tag=livewire-assets
```

**Cause 3:** JavaScript error in console**

**Solution:**
```js
// Check browser console (F12)
// Look for:
// - Livewire not initialized
// - Component not found
// - JavaScript syntax errors

// Clear browser cache:
// Chrome: Ctrl+Shift+Delete
// Or Livewire cache: php artisan livewire:discover
```

---

## Export/Import Issues

### Problem: CSV export is blank or corrupted

**Cause:** Headers sent before CSV

**Solution:**
```php
// Ensure no output before download_response

// Wrong:
echo "Starting export";  // This breaks CSV
return response()->streamDownload(...);

// Right:
return response()->streamDownload(...);  // Goes directly to download
```

---

## Error Debugging

### Enable detailed error logging

```php
// config/logging.php
'channels' => [
    'settings' => [
        'driver' => 'single',
        'path' => storage_path('logs/settings.log'),
        'level' => 'debug',
    ],
],

// In code:
Log::channel('settings')->debug('Setting value: ' . $value);
```

### Check database errors

```bash
# Enable query logging
DB::enableQueryLog();
$value = settings('app.name');
dd(DB::getQueryLog());

# Or use DebugBar
composer require barryvdh/laravel-debugbar --dev
```

### Test in Tinker

```bash
php artisan tinker

>>> settings()->set('test.key', 'test value', 'test', 'string')
>>> settings('test.key')
>>> settings()->all()
>>> settings()->getHistory('test.key')
>>> exit
```

---

## Getting Help

If issue persists:

1. **Check logs**
   ```bash
   tail -f storage/logs/laravel.log
   ```

2. **Review tests**
   ```bash
   php artisan test tests/Feature/SettingsIntegrationTest.php --verbose
   ```

3. **Check GitHub issues**
   - aotr/dynamic-levels-helper/issues
   - Search similar problems

4. **Ask questions**
   - Provide error message and stacktrace
   - Show your code (sanitized)
   - Include Laravel/PHP version
   - Include settings config

---

**Related:** [Quick Start](SETTINGS_QUICK_START.md) | [API Reference](SETTINGS_API_REFERENCE.md) | [Examples](SETTINGS_EXAMPLES.md)
