# Settings System Quick Start

Database-driven, cached, multi-scoped settings for the dynamic-levels-helper package.

## Installation

### 1. Publish Config & Migration

```bash
# Publish settings configuration
php artisan vendor:publish --tag=settings-config

# Publish database migration  
php artisan vendor:publish --tag=settings-migrations

# Run migration
php artisan migrate
```

### 2. Environment Setup (Optional)

```bash
# .env
SETTINGS_DATABASE=mysql              # or sqlite, pgsql, sqlsrv
SETTINGS_CACHE_ENABLED=true          # Enable caching
SETTINGS_AUDIT_LOG=false             # Track all changes
```

## Usage

### Get Settings

```php
// Get a single value
settings('mail.driver')           // Returns: 'mailgun'
settings('mail.driver', 'smtp')   // With default fallback

// Get all settings
settings()                        // Returns: array of all settings

// Get settings for a scope
settings('email.driver', null, 'User:5')  // User-specific override
```

### Set Settings

```php
// Set a value (creates or updates)
SettingsHelper::set('mail.driver', 'postmark')

// Set with scope
SettingsHelper::set('theme', 'dark', 'User:5')  // User-specific theme
```

### Using the Facade

```php
use Aotr\DynamicLevelHelper\Facades\SettingsHelper;

SettingsHelper::get('api.key')
SettingsHelper::set('api.key', 'xyz123')
SettingsHelper::all()
SettingsHelper::group('email')      // Get all in 'email' group
SettingsHelper::warm()              // Warm cache
```

### Warming Cache

```bash
# Warm settings cache for optimal performance (zero DB queries)
php artisan settings:warm
```

## Database Schema

```
settings
├── id (int, PK)
├── key (string) - e.g., 'mail.driver', 'api.stripe.key'
├── group (string) - e.g., 'mail', 'api', 'features'
├── display_name (string) - e.g., 'Mail Driver', 'Stripe API Key'
├── value (json) - The setting value
├── meta (json) - Additional metadata (options for select, range for slider, etc.)
├── type (string) - e.g., 'string', 'boolean', 'select', 'integer'
├── order (int) - Display order
├── scope_type (string) - Polymorphic: 'User', 'Team', null for global
├── scope_id (int) - Polymorphic: user ID, team ID
├── created_at
├── updated_at
├── deleted_at (soft delete)
└── Indexes: unique(key, scope_type, scope_id), group, scope
```

## Caching Behavior

- **First Read**: Query DB → Cache forever
- **Subsequent Reads**: O(1) cache hit (<1ms)
- **On Create/Update/Delete**: Observer auto-invalidates cache immediately
- **Cold Start**: `php artisan settings:warm` hydrates all caches

## Multi-Scope Example

```php
// Global setting (applies to all users)
SettingsHelper::set('app.maintenance', true)

// User-1 override
SettingsHelper::set('theme', 'dark', 'User:1')

// Merged result for User:1
settings('app.maintenance', scope: 'User:1')  // true (from global)
settings('theme', scope: 'User:1')             // 'dark' (from scope)
```

## Configuration

See `config/settings.php`:

- `database` - Which DB to use for settings
- `cache.enabled` - Turn caching on/off  
- `cache.ttl` - Cache TTL in seconds
- `audit_log` - Enable change history tracking
- `encrypted_keys` - Keys to encrypt at rest

## Type System

Automatically casts values based on `type`:

```
'boolean' → (bool) cast
'integer' → (int) cast
'decimal' → (float) cast
'string' → as-is (default)
'select' → one of predefined options
'multi_select' → array of options
```

## Next: Admin UI

Phase 3/4 will add a Livewire form builder to manage settings visually. Coming soon!

---

For full architecture details, see [SETTINGS_ARCHITECTURE_PLAN.md](https://github.com/aotr/dynamic-levels-helper/blob/main/docs/Settings/SETTINGS_ARCHITECTURE_PLAN.md)
