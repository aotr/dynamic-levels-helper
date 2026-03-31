# Settings System — Future Enhancements (Phase 6+)

**Purpose**: Post-MVP features for production robustness, compliance, and advanced use cases.

---

## 6.1 Activity Logging & Audit Trail

**Why?** Compliance, debugging, understanding who changed what and when.

```php
// Migration: create_setting_audits_table.php
Schema::create('setting_audits', function (Blueprint $table) {
    $table->id();
    $table->foreignId('setting_id')->constrained('settings')->onDelete('cascade');
    $table->string('key');
    $table->json('old_value')->nullable();
    $table->json('new_value');
    $table->string('changed_by'); // user email or id
    $table->string('reason')->nullable(); // why changed
    $table->string('ip_address')->nullable();
    $table->timestamps();
    
    $table->index('setting_id');
    $table->index('created_at');
});

// Observer: Auto-log changes
class SettingObserver
{
    public function updated(Setting $setting)
    {
        SettingAudit::create([
            'setting_id' => $setting->id,
            'key' => $setting->key,
            'old_value' => $setting->getOriginal('value'),
            'new_value' => $setting->value,
            'changed_by' => auth()->user()?->email ?? 'system',
            'reason' => cache('setting_change_reason'),
            'ip_address' => request()->ip(),
        ]);
    }
}
```

---

## 6.2 Settings Encryption at Rest

**Why?** Sensitive configs (API keys, encryption keys, passwords) should never be stored in plaintext.

```php
class SettingsService
{
    public function getEncrypted($key, $default = null, $scope = null)
    {
        $encrypted = $this->get($key, null, $scope);
        
        if (!$encrypted) {
            return $default;
        }
        
        return Crypt::decryptString($encrypted);
    }
    
    public function setEncrypted($key, $value, $scope = null)
    {
        $encrypted = Crypt::encryptString(json_encode($value));
        $this->set($key, $encrypted, $scope);
    }
}

// Usage:
$secretKey = settings_encrypted('api.stripe.secret_key'); // Auto-decrypts
```

---

## 6.3 Real-Time Settings Sync (Multi-Instance)

**Why?** In load-balanced environments, all app instances need to see config changes immediately.

```php
class SettingObserver
{
    public function updated(Setting $setting)
    {
        // Invalidate local cache
        Cache::forget($setting->cacheKey());
        
        // Broadcast to all instances via Redis pub/sub
        broadcast(new SettingUpdated($setting))->toOthers();
    }
}

// Event: events/SettingUpdated.php
class SettingUpdated implements ShouldBroadcast
{
    public function __construct(public Setting $setting) {}
    
    public function broadcastOn()
    {
        return new Channel('settings-sync');
    }
}

// JavaScript listener: invalidate cache on remote changes
echo.channel('settings-sync')
    .listen('SettingUpdated', (data) => {
        localStorage.removeItem('app_settings_' + data.setting.key);
    });
```

---

## 6.4 Settings Backup & Restore

**Why?** Bad config changes can break the app. Easy rollback is critical for production.

```php
class SettingsBackupService
{
    public function backup($label = null)
    {
        $backup = [
            'label' => $label ?? now()->toDateTimeString(),
            'timestamp' => now()->timestamp,
            'settings' => Setting::all()->toArray(),
            'created_by' => auth()->user()?->email ?? 'system',
        ];
        
        Storage::disk('backup')->put(
            "settings/backup-{$backup['timestamp']}.json",
            json_encode($backup, JSON_PRETTY_PRINT)
        );
        
        return $backup;
    }
    
    public function listBackups()
    {
        return collect(Storage::disk('backup')->files('settings'))
            ->map(fn($file) => json_decode(
                Storage::disk('backup')->get($file), 
                true
            ))
            ->sortByDesc('timestamp')
            ->values();
    }
    
    public function restore($timestamp, $dryRun = true)
    {
        $backup = json_decode(
            Storage::disk('backup')->get("settings/backup-{$timestamp}.json"),
            true
        );
        
        if ($dryRun) {
            return ['would_restore' => count($backup['settings'])];
        }
        
        // Actual restore
        Setting::truncate();
        foreach ($backup['settings'] as $setting) {
            Setting::create($setting);
        }
        
        Cache::flush();
        return ['restored' => count($backup['settings'])];
    }
}

// Scheduled backup (every 6 hours)
Schedule::call(function () {
    app(SettingsBackupService::class)->backup('auto-backup-' . now());
})->everyThirtyMinutes();
```

---

## 6.5 SQLite Fallback Database

**Why?** Production DB down? Settings still work via local SQLite for critical stability.

```php
class SettingsService
{
    protected bool $dbAvailable = true;
    
    private function isDatabaseAvailable(): bool
    {
        if (!$this->dbAvailable) {
            return false;
        }
        
        try {
            DB::connection()->getPdo();
            return true;
        } catch (Exception $e) {
            Log::warning('Primary DB unavailable, falling back to SQLite');
            return $this->tryFallbackDatabase();
        }
    }
    
    private function tryFallbackDatabase(): bool
    {
        try {
            $fallback = DB::connection('settings_fallback');
            $fallback->getPdo(); // Test connection
            
            Log::info('Switched to SQLite fallback database');
            DB::setDefaultConnection('settings_fallback');
            return true;
        } catch (Exception $e) {
            Log::critical('All databases down, using env/file fallback');
            return false;
        }
    }
}

// config/database.php
'connections' => [
    // ... existing connections
    
    'settings_fallback' => [
        'driver' => 'sqlite',
        'database' => storage_path('app/settings.sqlite'),
        'prefix' => '',
    ],
],

// Scheduled sync: Copy primary DB → SQLite every 5 minutes
Schedule::call(function () {
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
        Log::warning('Failed to sync settings to fallback', ['error' => $e->getMessage()]);
    }
})->everyFiveMinutes();
```

---

## 6.6 Settings Versioning & Diff

**Why?** Understand what changed between versions, easy rollback to specific versions.

```php
class SettingVersionService
{
    public function diff($before, $after)
    {
        return [
            'added' => array_diff_assoc($after, $before),
            'removed' => array_diff_assoc($before, $after),
            'changed' => array_diff_assoc($before, $after),
        ];
    }
    
    public function rollbackToAudit($auditId)
    {
        $audit = SettingAudit::find($auditId);
        $setting = Setting::find($audit->setting_id);
        
        $setting->update(['value' => $audit->old_value]);
        
        SettingAudit::create([
            'setting_id' => $setting->id,
            'key' => $setting->key,
            'old_value' => $audit->new_value,
            'new_value' => $audit->old_value,
            'changed_by' => auth()->user()?->email ?? 'system',
            'reason' => "Rollback to audit #{$auditId}",
        ]);
    }
    
    public function timeline($settingId, $days = 30)
    {
        return SettingAudit::where('setting_id', $settingId)
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($audit) => [
                'timestamp' => $audit->created_at,
                'old_value' => $audit->old_value,
                'new_value' => $audit->new_value,
                'changed_by' => $audit->changed_by,
                'reason' => $audit->reason,
            ]);
    }
}
```

---

## 6.7 Feature Flags Evaluator

**Why?** Decouple feature rollout from code deploys (critical for CI/CD).

```php
class SettingsService
{
    public function isFeatureEnabled($feature, $scope = null)
    {
        $enabled = $this->get("features.{$feature}.enabled", false, $scope);
        
        // Optional: percentage rollout (canary releases)
        if (is_array($enabled) && isset($enabled['percentage'])) {
            return rand(1, 100) <= $enabled['percentage'];
        }
        
        return (bool) $enabled;
    }
}

// Usage in code:
if (settings_feature('new_dashboard')) {
    // Show new dashboard UI
}

// config/dynamic-settings.json
{
  "features": {
    "new_dashboard": {
      "enabled": false
    },
    "experimental_api": {
      "enabled": true,
      "percentage": 25
    }
  }
}
```

---

## 6.8 A/B Testing Integration

**Why?** Run experiments with settings-based variant selection.

```php
class SettingsABTestService
{
    public function getVariant($testName, $userId)
    {
        $test = settings("experiments.{$testName}");
        
        if (!$test || !$test['enabled']) {
            return $test['control'] ?? 'control';
        }
        
        // Consistent hashing: same user always gets same variant
        $hash = crc32("{$userId}:{$testName}") % 100;
        $cumulative = 0;
        
        foreach ($test['variants'] as $variant => $percentage) {
            $cumulative += $percentage;
            if ($hash < $cumulative) {
                return $variant;
            }
        }
        
        return $test['control'] ?? 'control';
    }
}

// config/dynamic-settings.json
{
  "experiments": {
    "new_checkout_flow": {
      "enabled": true,
      "control": "old_checkout",
      "variants": {
        "new_checkout": 50,
        "experimental_checkout": 50
      }
    }
  }
}

// Usage:
$variant = app(SettingsABTestService::class)->getVariant('new_checkout_flow', $user->id);
if ($variant === 'new_checkout') {
    // Use new checkout
}
```

---

## Implementation Priority

| Feature | Difficulty | Impact | Phase | Timeline |
|---------|-----------|--------|-------|----------|
| Audit Logging | Medium | High | 6 | Week 3 |
| Settings Backup | Easy | High | 6 | Week 2 |
| SQLite Fallback | Medium | Critical | 6 | Week 1 |
| Encryption | Medium | High | 6 | Week 2 |
| Real-time Sync | Hard | Medium | 7 | Week 4 |
| Feature Flags | Easy | High | 6 | Week 1 |
| A/B Testing | Medium | Medium | 7 | Week 5 |
| Versioning | Medium | Medium | 7 | Week 4 |

---

## References
- [Laravel Crypt](https://laravel.com/docs/10.x/encryption)
- [Laravel Broadcasting](https://laravel.com/docs/10.x/broadcasting)
- [Laravel Storage](https://laravel.com/docs/10.x/filesystem)
