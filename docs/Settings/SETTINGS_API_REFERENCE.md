# Settings System - API Reference

## SettingsService Class

Core service for managing settings. Access via `settings()` helper or `SettingsHelper` facade.

### Methods

#### `get($key, $default = null, $scope = null): mixed`

Retrieve a setting value with automatic type casting.

**Parameters:**
- `$key` (string): Setting key (e.g., 'app.name')
- `$default` (mixed): Default value if not found
- `$scope` (Model|string|null): Scope (user model or 'App\Models\User:1')

**Returns:** Mixed (casted to setting type)

**Examples:**
```php
settings()->get('app.name');
settings()->get('feature.enabled', false);  // Default false
settings()->get('app.name', null, Auth::user());  // Scoped
```

---

#### `set($key, $value, $group = 'app', $type = 'string', $scope = null): Setting`

Create or update a setting.

**Parameters:**
- `$key` (string): Setting key
- `$value` (mixed): Value to store
- `$group` (string): Group for organization
- `$type` (string): Type for casting ('string', 'boolean', 'integer', 'float', 'json', 'text', 'select')
- `$scope` (Model|null): Optional scope

**Returns:** Setting model instance

**Examples:**
```php
settings()->set('app.name', 'My App', 'app', 'string');
settings()->set('feature.enabled', true, 'features', 'boolean');
settings()->set('cache.ttl', 3600, 'cache', 'integer');
settings()->set('app.data', json_encode($data), 'app', 'json', Auth::user());
```

---

#### `delete($key, $scope = null): bool`

Soft-delete a setting (preserves audit history).

**Parameters:**
- `$key` (string): Setting key
- `$scope` (Model|null): Optional scope

**Returns:** bool

**Examples:**
```php
settings()->delete('app.old_key');
settings()->delete('user.theme', Auth::user());
```

---

#### `all($scope = null): array`

Retrieve all settings (or all for a scope).

**Parameters:**
- `$scope` (Model|null): Optional scope filter

**Returns:** Associative array of [key => value]

**Examples:**
```php
$all = settings()->all();
$userSettings = settings()->all(Auth::user());

// Returns:
// ['app.name' => 'My App', 'app.url' => '...', ...]
```

---

#### `group($groupName, $scope = null): array`

Retrieve all settings in a group.

**Parameters:**
- `$groupName` (string): Group name
- `$scope` (Model|null): Optional scope

**Returns:** Associative array of [key => value]

**Examples:**
```php
$appSettings = settings()->group('app');
$mailSettings = settings()->group('mail');

// Returns settings where group='app'
// ['app.name' => '...', 'app.url' => '...']
```

---

#### `exists($key, $scope = null): bool`

Check if a setting exists.

**Parameters:**
- `$key` (string): Setting key
- `$scope` (Model|null): Optional scope

**Returns:** bool

**Examples:**
```php
if (settings()->exists('feature.newUI')) {
    // Feature is available
}
```

---

#### `invalidate(): void`

Clear the settings cache. Auto-called on any change.

**Examples:**
```php
settings()->invalidate();
$value = settings('app.name');  // Reloads from DB
```

---

#### `warm(): void`

Pre-load all settings into cache.

**Examples:**
```php
settings()->warm();  // Cache all settings
```

---

#### `getHistory($key, int $limit = 50): Collection`

Get change history for a specific setting.

**Parameters:**
- `$key` (string): Setting key
- `$limit` (int): Maximum entries to return

**Returns:** Collection of SettingAuditLog models

**Examples:**
```php
$history = settings()->getHistory('app.name');

foreach ($history as $entry) {
    $entry->action;        // 'created', 'updated', 'deleted'
    $entry->old_value;     // Previous value
    $entry->new_value;     // New value
    $entry->causer_id;     // User ID who changed it
    $entry->causer_type;   // 'App\Models\User'
    $entry->created_at;    // When changed
}
```

---

#### `getAuditLog(array $filters = []): Collection`

Get paginated audit log with optional filters.

**Parameters:**
- `$filters` (array): Optional filters
  - `key`: Setting key
  - `group`: Setting group
  - `action`: 'created', 'updated', 'deleted'
  - `user_id`: User who made change
  - `date_from`: Starting date
  - `date_to`: Ending date

**Returns:** Collection of SettingAuditLog models

**Examples:**
```php
// All audit entries
$logs = settings()->getAuditLog();

// Filtered
$logs = settings()->getAuditLog([
    'key' => 'app.name',
    'action' => 'updated',
]);

// By date range
$logs = settings()->getAuditLog([
    'date_from' => now()->subDays(7),
    'date_to' => now(),
]);

// Loop results
foreach ($logs as $log) {
    echo $log->key;
    echo $log->action;
    echo $log->old_value;
    echo $log->new_value;
    echo $log->causer->name;
}
```

---

#### `pruneAuditLogs(int $daysOld): int`

Remove audit logs older than specified days.

**Parameters:**
- `daysOld` (int): Remove entries older than N days

**Returns:** Number of deleted entries

**Examples:**
```php
$deleted = settings()->pruneAuditLogs(90);  // Remove 90+ day old logs
echo "Deleted $deleted audit entries";
```

---

#### `loadMerged($scope = null): array`

Internal method. Loads and merges global + scoped settings.

**Parameters:**
- `$scope` (Model|null): Scope to merge

**Returns:** Merged array

---

## SettingAuditLog Model

Represents a single change to a setting.

### Properties

- `id`: Primary key
- `key`: Setting key (e.g., 'app.name')
- `group`: Setting group (e.g., 'app')
- `action`: 'created', 'updated', or 'deleted'
- `old_value`: Previous value (null for creates)
- `new_value`: New value (null for deletes)
- `causer_type`: Model type (e.g., 'App\Models\User')
- `causer_id`: Model ID who made change
- `request_ip`: IP address of request
- `metadata`: JSON metadata
- `created_at`: When change occurred
- `deleted_at`: Soft delete timestamp

### Query Scopes

```php
// Filter by key
SettingAuditLog::forKey('app.name')->get();

// Filter by group
SettingAuditLog::forGroup('app')->get();

// Filter by action
SettingAuditLog::byAction('updated')->get();

// Filter by user
SettingAuditLog::byUser(Auth::user())->get();

// Filter by date range
SettingAuditLog::dateRange(now()->subDays(7), now())->get();

// Get latest entries
SettingAuditLog::latest()->take(10)->get();
```

---

## Setting Model

Represents a single setting.

### Properties

- `id`: Primary key
- `key`: Unique key (e.g., 'app.name')
- `value`: String value
- `group`: Group classification
- `type`: Type for casting
- `description`: Optional description
- `model_type`: Scope model type (nullable)
- `model_id`: Scope model ID (nullable)
- `created_at`: Creation timestamp
- `updated_at`: Last update timestamp
- `deleted_at`: Soft delete timestamp

### Query Scopes

```php
// Filter by scope
$global = Setting::forScope(null)->get();           // Global only
$user = Setting::forScope($user)->get();            // User-scoped
$string = Setting::forScope('App\Models\User:1')->get();

// Filter by group
Setting::inGroup('app')->get();

// Get specific key
Setting::whereKey('app.name')->first();
```

---

## Console Commands

### `php artisan settings:warm`

Pre-load all settings into cache.

**Output:**
```
Settings cache has been warmed.
```

---

### `php artisan settings:history`

View audit log with optional filtering.

**Options:**
```
--key=NAME       Filter by setting key
--group=NAME     Filter by group
--action=ACTION  Filter by action (created/updated/deleted)
--user=ID        Filter by user ID
--limit=N        Number of entries (default: 50)
--export=FORMAT  Export as csv or json
--prune=DAYS     Delete entries older than N days
```

**Examples:**
```bash
# View all history
php artisan settings:history

# View specific setting
php artisan settings:history --key=app.name

# View by group
php artisan settings:history --group=app

# View recent changes
php artisan settings:history --limit=10

# Export to CSV
php artisan settings:history --export=csv

# Delete old entries
php artisan settings:history --prune=90

# Combine filters
php artisan settings:history --group=app --action=updated --limit=20
```

---

## Livewire Component

### `<livewire:settings-form />`

Interactive admin form for managing settings.

**Features:**
- Search by key or value
- Filter by group
- Inline editing
- Create new settings
- Delete settings
- Type-aware form rendering

**Blade Usage:**
```blade
<livewire:settings-form />

<!-- With key prefix (optional) -->
<livewire:settings-form :key="'admin-settings'" />
```

**Public Properties:**
- `$settings`: Array of settings
- `$groups`: Available groups
- `$search`: Search query
- `$selectedGroup`: Filtered group
- `$editingKey`: Currently editing key
- `$editingValue`: Current edit value
- `$editingType`: Current edit type

**Public Methods:**
- `loadSettings()`: Reload from DB
- `filterByGroup($group)`: Filter by group
- `filterBySearch()`: Filter by search query
- `editSetting($key)`: Load for editing
- `saveSetting($key)`: Persist changes
- `deleteSetting($key)`: Soft delete
- `createSetting()`: Create new
- `resetSetting($key)`: Restore default
- `clearFilters()`: Reset filters

---

## Helper Functions

### `settings($key = null, $default = null, $scope = null)`

Convenience helper for accessing SettingsService.

**Usage:**
```php
// Get service
$service = settings();

// Get value
$value = settings('app.name');

// Get with default
$value = settings('app.name', 'Default');

// Get with scope
$value = settings('app.name', null, Auth::user());
```

---

## Facade

### `SettingsHelper` Facade

Type-hinted facade for IDE autocomplete.

**Usage:**
```php
use Aotr\DynamicLevelHelper\Facades\SettingsHelper;

SettingsHelper::get('app.name');
SettingsHelper::set('app.name', 'New Name', 'app', 'string');
SettingsHelper::all();
SettingsHelper::group('app');
SettingsHelper::getHistory('app.name');
SettingsHelper::getAuditLog(['key' => 'app.name']);
SettingsHelper::pruneAuditLogs(90);
SettingsHelper::invalidate();
SettingsHelper::warm();
```

---

## Configuration

`config/settings.php`:

```php
return [
    // Database connection to use
    'database' => 'mysql',

    // Cache settings
    'cache' => [
        'enabled' => true,     // Enable caching
        'ttl' => null,         // null = forever
    ],

    // Audit logging
    'audit' => [
        'enabled' => true,     // Track changes
        'log_ip' => true,      // Log request IP
    ],

    // Use encryption for sensitive settings
    'encrypted' => [
        'api.key',
        'mail.password',
        'secrets.*',
    ],
];
```

---

## Type Casting Support

| Type | Stored As | Retrieved As | Example |
|------|-----------|-------------|---------|
| string | string | string | "hello" |
| boolean | 1 or 0 | true/false | true |
| integer | integer | int | 42 |
| float | float | float | 3.14 |
| json | JSON string | array | ['key' => 'value'] |
| text | text | string | "Long text..." |
| select | string | string | "option" |
| date | string | Carbon | Carbon object |

---

## Error Handling

```php
try {
    settings()->set('app.name', 'Value', 'app', 'string');
} catch (\Illuminate\Database\QueryException $e) {
    // Database error
    Log::error('Settings DB error: ' . $e->getMessage());
} catch (\Exception $e) {
    // Unexpected error
    Log::error('Settings error: ' . $e->getMessage());
}
```

---

## Events & Hooks

Settings system fires Laravel model events:

```php
// When created
Setting::created(function ($setting) {
    Log::info('Setting created: ' . $setting->key);
});

// When updated
Setting::updated(function ($setting) {
    Log::info('Setting updated: ' . $setting->key);
});

// When deleted
Setting::deleted(function ($setting) {
    Log::info('Setting deleted: ' . $setting->key);
});
```

---

## Performance Tips

1. **Use cache** - Enabled by default, dramatically faster
2. **Batch operations** - Set multiple at once rather than one-by-one
3. **Prune old logs** - Keep audit table manageable
4. **Use scopes** - Avoid loading unneeded settings
5. **Warm cache on deploy** - Pre-populate in production

```php
// Batch set
$settings = ['app.name' => 'App', 'app.url' => 'https://...'];
foreach ($settings as $key => $value) {
    settings()->set($key, $value, 'app', 'string');
}

// Rather than retrieving repeatedly
foreach ($keys as $key) {
    settings($key);  // Re-queries DB each time
}

// Do this instead
$all = settings()->all();
foreach ($keys as $key) {
    $value = $all[$key];  // In-memory lookup
}
```

---

## Debugging

```php
// Dump all settings
dd(settings()->all());

// Dump specific group
dd(settings()->group('app'));

// Check cache status
dd(Cache::get('settings'));

// View history for key
dd(settings()->getHistory('app.name'));

// View audit log
dd(settings()->getAuditLog(['key' => 'app.name']));
```

---

**See Also:** [Quick Start Guide](SETTINGS_QUICK_START.md) | [Examples](SETTINGS_EXAMPLES.md) | [Troubleshooting](SETTINGS_TROUBLESHOOTING.md)
