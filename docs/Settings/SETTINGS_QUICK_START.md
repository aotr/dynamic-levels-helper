# Settings System - Quick Start Guide

## Installation & Setup

The settings system is built into the dynamic-levels-helper package. No additional installation needed!

### Publish Assets

```bash
# Publish configuration
php artisan vendor:publish --tag=settings-config

# Publish migrations
php artisan vendor:publish --tag=settings-migrations

# Publish Livewire admin UI views
php artisan vendor:publish --tag=settings-views

# Run migrations
php artisan migrate
```

### Warm Cache (Optional)

Pre-load all settings into cache for better performance:

```bash
php artisan settings:warm
```

## Basic Usage

### Get a Setting

```php
// With helper
$appName = settings('app.name');

// With default value
$appName = settings('app.name', 'Default App');

// With facade
$appName = SettingsHelper::get('app.name');
```

### Set a Setting

```php
// Create or update
settings()->set('app.name', 'My App', 'app', 'string');

// Shorter version (updates existing, uses defaults for new)
settings()->set('app.url', 'https://example.com');
```

### Delete a Setting

```php
settings()->delete('app.name');
```

### Get All Settings

```php
// All settings
$all = settings()->all();

// By group
$appSettings = settings()->group('app');
```

## Admin Dashboard

### Access the Livewire Component

Add to your Blade template:

```blade
<livewire:settings-form />
```

Or in a full page:

```blade
<x-layouts.app>
    <livewire:settings-form />
</x-layouts.app>
```

### Features

- **Search**: Find settings by key or value
- **Filter**: Filter settings by group
- **Edit**: Inline editing with type-aware inputs
- **Create**: Add new settings easily
- **Delete**: Remove settings (soft-deletes)
- **Type Awareness**: Displays correct input type based on setting type

## Common Types

```php
// String (default)
settings()->set('app.name', 'My App', 'app', 'string');

// Boolean (stored as 1/0, returns true/false)
settings()->set('feature.enabled', true, 'features', 'boolean');

// Integer (stored as integer)
settings()->set('cache.ttl', 3600, 'cache', 'integer');

// Float (stored as float)
settings()->set('tax.rate', 0.18, 'app', 'float');

// JSON (for complex data)
$config = ['db' => 'mysql', 'cache' => 'redis'];
settings()->set('app.config', json_encode($config), 'app', 'json');

// Select (enum-like)
settings()->set('locale', 'en', 'app', 'select');

// Text (large text)
settings()->set('app.description', 'Long description...', 'app', 'text');
```

## Scoped Settings (Multi-Tenancy)

Settings can be scoped to users, teams, or any Eloquent model:

```php
// Define scope as model instance
$user = Auth::user();
settings()->set('theme.color', 'dark', 'user', 'string', $user);

// Retrieve with scope
$theme = settings('theme.color', null, $user);

// Or use string syntax
$theme = settings('theme.color', null, 'App\Models\User:' . $user->id);
```

## Audit & History

### View Change History

```php
// Get all changes to a setting
$history = settings()->getHistory('app.name');

// Returns collection of SettingAuditLog models
foreach ($history as $entry) {
    echo $entry->action;      // 'created', 'updated', 'deleted'
    echo $entry->old_value;   // Previous value
    echo $entry->new_value;   // New value
    echo $entry->causer->name; // Who made change
    echo $entry->created_at;  // When
}
```

### View Audit Log

```php
// All audit entries
$logs = settings()->getAuditLog();

// Filter options
$logs = settings()->getAuditLog([
    'key' => 'app.name',      // Filter by key
    'group' => 'app',         // Filter by group
    'action' => 'updated',    // Filter by action
    'user_id' => 1,           // Filter by user
]);

// Using CLI
php artisan settings:history                    # All
php artisan settings:history --key=app.name    # By key
php artisan settings:history --group=app       # By group
php artisan settings:history --action=updated  # By action
```

### Prune Old Logs

Keep audit logs manageable by removing old entries:

```php
// Remove logs older than 90 days
settings()->pruneAuditLogs(90);

// Via CLI
php artisan settings:history --prune=90
```

## Caching

The settings system uses Redis/in-memory cache for speed. Cache is automatically managed:

- **Automatically invalidated** when settings change (create/update/delete)
- **Automatically warmed** on first access
- **Can be manually warmed** with `php artisan settings:warm`
- **Can be manually invalidated** with `settings()->invalidate()`

### Configuration

Enable/disable caching in `config/settings.php`:

```php
'cache' => [
    'enabled' => true,      // Enable caching
    'ttl' => null,          // null = forever (cache only invalidated on change)
],
```

## Best Practices

1. **Use groups** to organize settings logically
   ```php
   settings()->set('app.name', 'App', 'app', 'string');
   settings()->set('app.url', 'https://example.com', 'app', 'string');
   settings()->set('mail.host', 'smtp.com', 'mail', 'string');
   ```

2. **Use dot notation** for hierarchical keys
   ```php
   'app.database.host' // Good
   'app-database-host' // Bad
   ```

3. **Set type correctly** for proper casting
   ```php
   // Stored as '1', retrieved as true
   settings()->set('feature.enabled', true, 'features', 'boolean');
   
   // Stored as '1', retrieved as string '1'
   settings()->set('feature.enabled', '1', 'features', 'string');
   ```

4. **Use scopes for multi-tenancy**
   ```php
   // Global setting (all users see same value)
   settings()->set('app.maxUploadSize', 10, 'app', 'integer');
   
   // Per-user setting (each user has own value)
   settings()->set('ui.theme', 'dark', 'ui', 'string', Auth::user());
   ```

5. **Backup before bulk changes**
   ```bash
   php artisan settings:history --export=csv > settings_backup_$(date +%s).csv
   ```

## Troubleshooting

### Settings Not Updating

1. **Check cache is cleared**
   ```php
   settings()->invalidate();  // Clear cache
   $value = settings('key');   // Re-fetch
   ```

2. **Verify setting exists**
   ```php
   dd(settings()->all());  // See all settings
   ```

3. **Check scope**
   ```php
   // Verify you're using correct scope
   $value = settings('key', null, Auth::user()); // With scope
   $value = settings('key'); // Global
   ```

### Performance Issues

1. **Warm cache**
   ```bash
   php artisan settings:warm
   ```

2. **Check audit log size**
   ```bash
   php artisan settings:history --prune=90
   ```

3. **Monitor cache hit rate**
   ```php
   Cache::get('settings'); // Check if populated
   ```

### Permission Issues

Ensure user has permission to access the Livewire component. Add middleware as needed:

```php
// In your route
Route::get('/settings', SettingsPage::class)
    ->middleware(['auth', 'admin']);
```

## Next Steps

- [API Reference](SETTINGS_API_REFERENCE.md) - Complete API documentation
- [Examples](SETTINGS_EXAMPLES.md) - Real-world usage patterns
- [Architecture](SETTINGS_ARCHITECTURE_PLAN.md) - System design details
