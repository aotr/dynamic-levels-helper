# Phase 2: Audit Logging & History

Compliance-grade tracking of all settings changes with user attribution, IP logging, and export capabilities.

## Components

### 1. Migration: `2026_03_31_000001_create_setting_audit_logs_table.php`

Stores all setting changes with:
- `key`, `group` - which setting changed
- `old_value`, `new_value` - before/after values
- `auditable_type`, `auditable_id` - polymorphic: who made the change
- `action` - 'created', 'updated', 'deleted'
- `reason` - optional reason for change
- `ip_address`, `user_agent` - requester context

### 2. Model: `SettingAuditLog`

```php
use Aotr\DynamicLevelHelper\Models\SettingAuditLog;

// Query scopes
SettingAuditLog::forKey('email.driver')->get();        // All changes to a key
SettingAuditLog::inGroup('email')->get();              // All changes in a group
SettingAuditLog::byUser(1)->get();                     // Changes by User:1
SettingAuditLog::byAction('updated')->get();           // Only updates
SettingAuditLog::dateRange('2026-01-01', '2026-03-31')->get();
```

### 3. Observer: `SettingHistoryObserver`

Auto-logs every change (create/update/delete) with:
- Current user (via `Auth::user()`)
- IP address & user agent
- Timestamp
- Old vs. new values

Logs silently to prevent audit logging from breaking the app.

### 4. Service Methods

```php
use Aotr\DynamicLevelHelper\Services\SettingsService;

$service = app(SettingsService::class);

// Get history for a single key
$logs = $service->getHistory('email.driver', limit: 25);

// Query with filters
$logs = $service->getAuditLog([
    'group' => 'email',
    'action' => 'updated',
    'from_date' => '2026-01-01',
], limit: 100);

// Clean old logs (retention policy)
$deleted = $service->pruneAuditLogs(days: 90);  // Delete 90+ day old logs
```

### 5. Console Command: `php artisan settings:history`

View, export, and manage audit logs from CLI.

## Usage

### View Recent Changes

```bash
# Show last 50 changes
php artisan settings:history

# Filter by setting key
php artisan settings:history --key=email.driver

# Filter by group
php artisan settings:history --group=email

# Filter by action
php artisan settings:history --action=updated

# Customize limit
php artisan settings:history --limit=100
```

### Export to CSV

```bash
# Export filtered logs to CSV
php artisan settings:history --key=api.* --export=audit_$(date +%Y%m%d).csv

# Full audit trail
php artisan settings:history --export=audit.csv
```

### Data Retention

```bash
# Delete logs older than 90 days
php artisan settings:history --prune=90

# Delete logs older than 1 year
php artisan settings:history --prune=365
```

## Example Output

```
┌─────────────────────┬─────────┬────────┬────────────────┬─────────────────────────┬──────────────────────┐
│ Key                 │ Action  │ Group  │ Changed By     │ Changed At              │ Changes              │
├─────────────────────┼─────────┼────────┼────────────────┼─────────────────────────┼──────────────────────┤
│ email.driver        │ updated │ email  │ User:5         │ 2026-03-31 14:22:15     │ mailgun → postmark   │
│ email.driver        │ created │ email  │ System         │ 2026-03-25 09:00:00     │ ✅ Created           │
│ api.key             │ updated │ api    │ User:1         │ 2026-03-30 16:45:30     │ abc123... → xyz789.. │
└─────────────────────┴─────────┴────────┴────────────────┴─────────────────────────┴──────────────────────┘
✓ Showing 3 records
```

## Security & Compliance

✅ **Audit Trail** — Full history of who changed what and when
✅ **User Attribution** — Tracks which user/system made the change
✅ **IP Logging** — Captures requester IP address for security
✅ **Immutable** — Logs created with SoftDelete (can be restored if needed)
✅ **Export** — CSV export for compliance audits, GDPR requests
✅ **Retention** — Automated pruning of old logs
✅ **Silent Failure** — Audit logging failure doesn't break the app

## Database

```sql
-- View 10 most recent changes
SELECT key, action, old_value, new_value, auditable_type, created_at 
FROM setting_audit_logs 
ORDER BY created_at DESC 
LIMIT 10;

-- Find who changed API keys
SELECT * FROM setting_audit_logs 
WHERE key LIKE 'api.%' 
ORDER BY created_at DESC;

-- Audit trail for a date range
SELECT * FROM setting_audit_logs 
WHERE created_at BETWEEN '2026-01-01' AND '2026-03-31'
ORDER BY created_at DESC;
```

## API Examples

```php
// Get full audit trail for a setting
$history = SettingAuditLog::forKey('mail.driver')
    ->orderBy('created_at', 'desc')
    ->get();

foreach ($history as $log) {
    echo sprintf(
        "%s: %s changed from %s to %s by %s\n",
        $log->created_at,
        $log->key,
        $log->old_value,
        $log->new_value,
        $log->auditable_type
    );
}

// Track compliance-critical settings
$criticalChanges = SettingAuditLog::whereIn('key', [
    'app.api_key',
    'payment.gateway',
    'auth.encryption_key'
])->orderBy('created_at', 'desc')->get();

// User's recent activity
$userChanges = SettingAuditLog::byUser(Auth::id())
    ->dateRange(now()->subMonth(), now())
    ->get();
```

## Next Steps

**Phase 3** — Admin UI (Livewire)
- Beautiful form builder for managing settings
- Type-aware rendering (select, toggle, textarea, etc.)
- Batch update operations
- Change preview before saving

---

**Installation:**
```bash
php artisan vendor:publish --tag=settings-migrations
php artisan migrate
```
