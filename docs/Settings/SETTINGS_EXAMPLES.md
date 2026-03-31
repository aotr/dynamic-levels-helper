# Settings System - Real-World Examples

Practical examples for common use cases.

---

## Example 1: Application Configuration

Replace static `config/app.php` with dynamic settings:

```php
// Instead of this (static):
return [
    'name' => env('APP_NAME', 'Laravel'),
    'url' => env('APP_URL', 'http://localhost'),
];

// Use settings (dynamic):
// Once: 
settings()->set('app.name', 'My SaaS', 'app', 'string');
settings()->set('app.url', 'https://myapp.com', 'app', 'string');

// Everywhere:
echo settings('app.name');           // 'My SaaS'
echo config('settings.app.name');    // Same value (if you alias)
```

---

## Example 2: Feature Flags

Enable/disable features without deployment:

```php
// Initialize feature flags
$features = ['newUI', 'analytics', 'premium', 'darkMode'];
foreach ($features as $feature) {
    settings()->set("feature.{$feature}", false, 'features', 'boolean');
}

// In your app:
class FeatureToggle {
    public static function enabled($feature) {
        return settings("feature.{$feature}", false);
    }
}

// Usage:
if (FeatureToggle::enabled('newUI')) {
    // Serve new interface
}

if (FeatureToggle::enabled('premium') && Auth::check()) {
    // Show premium features
}

// Toggle via admin UI without code changes or deployment
```

---

## Example 3: Per-User Preferences

Store user-specific settings:

```php
// In UserController or settings page:
public function updatePreferences(Request $request) {
    $user = Auth::user();
    
    // User theme preference
    settings()->set('ui.theme', 
        $request->theme, 
        'ui', 
        'string', 
        $user
    );
    
    // Notification preferences
    settings()->set('notifications.email', 
        $request->email_notifications, 
        'notifications', 
        'boolean', 
        $user
    );
    
    settings()->set('notifications.sms', 
        $request->sms_notifications, 
        'notifications', 
        'boolean', 
        $user
    );
}

// In UserProfile blade:
<select name="theme">
    <option value="light" @selected(settings('ui.theme', 'light', Auth::user()) === 'light')>
        Light
    </option>
    <option value="dark" @selected(settings('ui.theme', 'light', Auth::user()) === 'dark')>
        Dark
    </option>
</select>

// In middleware/boot:
class AppServiceProvider extends ServiceProvider {
    public function boot() {
        if (Auth::check()) {
            // Apply user theme globally
            $theme = settings('ui.theme', 'light', Auth::user());
            config(['theme.current' => $theme]);
        }
    }
}
```

---

## Example 4: Email Configuration

Email settings that users can customize:

```php
// Initialize email settings
$emailDefaults = [
    'host' => 'smtp.mailtrap.io',
    'port' => '587',
    'username' => 'user@example.com',
    'password' => 'encrypted_token',
    'from_address' => 'noreply@example.com',
    'from_name' => 'My App',
];

foreach ($emailDefaults as $key => $value) {
    settings()->set("mail.{$key}", $value, 'mail', 'string');
}

// Get mail config from settings
class MailConfigService {
    public static function getConfig() {
        return [
            'host' => settings('mail.host'),
            'port' => settings('mail.port'),
            'username' => settings('mail.username'),
            'password' => decrypt(settings('mail.password')),
            'from' => [
                'address' => settings('mail.from_address'),
                'name' => settings('mail.from_name'),
            ],
        ];
    }
}

// In config/mail.php:
'host' => MailConfigService::getConfig()['host'],

// Or use event listener:
class MailConfigListener {
    public function handle(SendMailMessage $event) {
        config([
            'mail.host' => settings('mail.host'),
            'mail.port' => settings('mail.port'),
            'mail.username' => settings('mail.username'),
            'mail.from.address' => settings('mail.from_address'),
        ]);
    }
}
```

---

## Example 5: API Rate Limiting based on Settings

```php
// Define rate limits in settings
settings()->set('api.rate_limit_requests', 100, 'api', 'integer');
settings()->set('api.rate_limit_window', 60, 'api', 'integer');  // seconds

// Middleware
class ApiRateLimitMiddleware {
    public function handle($request, $next) {
        $limit = settings('api.rate_limit_requests', 100);
        $window = settings('api.rate_limit_window', 60);
        
        return RateLimiter::attempt(
            Auth::id(),
            $limit,
            function() use ($next, $request) {
                return $next($request);
            },
            $window,
        );
    }
}

// Change limits anytime without deploy
```

---

## Example 6: Multi-Tenancy Settings

```php
// Global settings (all tenants)
settings()->set('app.version', '1.2.3', 'app', 'string');

// Per-tenant settings
$tenant = Tenant::find(1);
settings()->set('app.branding_color', '#007bff', 'app', 'string', $tenant);
settings()->set('app.logo_url', 'https://...', 'app', 'string', $tenant);
settings()->set('features.advanced_reports', true, 'features', 'boolean', $tenant);

// In view (with tenant middleware):
<div style="color: {{ settings('app.branding_color', '#000', currentTenant()) }}">
    Welcome to {{ settings('app.name', 'App', currentTenant()) }}
</div>

// In middleware (auto-apply tenant branding):
class TenantSettingsMiddleware {
    public function handle($request, $next) {
        if ($tenant = $request->tenant()) {
            $branding = settings()->all($tenant);
            View::share('tenantSettings', $branding);
        }
        return $next($request);
    }
}
```

---

## Example 7: Payment Settings

```php
// Stripe configuration
settings()->set('payment.stripe_key', 'sk_test_...', 'payment', 'string');
settings()->set('payment.stripe_secret', 'sk_secret_...', 'payment', 'string');

// Tax configuration
settings()->set('payment.tax_rate', 0.18, 'payment', 'float');
settings()->set('payment.currency', 'USD', 'payment', 'string');

// Pricing tiers
$tiers = [
    'basic' => ['monthly' => 29, 'annual' => 290],
    'pro' => ['monthly' => 99, 'annual' => 990],
    'enterprise' => ['monthly' => 299, 'annual' => 2990],
];
settings()->set('payment.pricing_tiers', json_encode($tiers), 'payment', 'json');

// In CheckoutController:
class CheckoutController {
    public function process(Request $request) {
        \Stripe\Stripe::setApiKey(settings('payment.stripe_secret'));
        
        $taxRate = settings('payment.tax_rate', 0);
        $currency = settings('payment.currency', 'USD');
        
        $intent = \Stripe\PaymentIntent::create([
            'amount' => $request->amount * 100,
            'currency' => strtolower($currency),
            'tax' => (int)($request->amount * $taxRate * 100),
        ]);
    }
}
```

---

## Example 8: Audit Trail with Change Notifications

```php
// When someone changes important settings, notify admins
class SettingChangeNotification {
    public static function notifyIfImportant($setting) {
        $importantKeys = ['app.url', 'payment.stripe_key', 'mail.host'];
        
        if (in_array($setting->key, $importantKeys)) {
            $admins = User::whereRole('admin')->get();
            
            Notification::send($admins, new SettingChanged($setting));
        }
    }
}

// In observer:
class SettingHistoryObserver {
    public function updated(Setting $setting) {
        SettingChangeNotification::notifyIfImportant($setting);
    }
}

// View change history in admin
class AdminController {
    public function viewAuditLog() {
        $log = settings()->getAuditLog([
            'date_from' => now()->subDays(7),
        ]);
        
        return view('admin.audit-log', [
            'entries' => $log,
            'stats' => [
                'total_changes' => $log->count(),
                'by_user' => $log->groupBy('causer_id')->count(),
                'by_action' => $log->groupBy('action'),
            ],
        ]);
    }
}
```

---

## Example 9: API Documentation Links Based on Settings

```php
// Settings
settings()->set('docs.api_url', 'https://docs.example.com', 'docs', 'string');
settings()->set('docs.version', 'v2', 'docs', 'string');

// In blade:
<a href="{{ settings('docs.api_url') }}/{{ settings('docs.version') }}">
    API Documentation
</a>

// In controller:
class ApiController {
    public function help() {
        return redirect(
            settings('docs.api_url') . '/' . settings('docs.version')
        );
    }
}
```

---

## Example 10: Localization/Translation Preferences

```php
// Global settings
settings()->set('app.locales', json_encode(['en', 'es', 'fr']), 'app', 'json');
settings()->set('app.default_locale', 'en', 'app', 'string');

// Per-user language preference
public function updateLanguage(Request $request) {
    settings()->set(
        'user.locale',
        $request->locale,
        'user',
        'string',
        Auth::user()
    );
}

// In middleware:
class SetLocaleMiddleware {
    public function handle($request, $next) {
        if (Auth::check()) {
            $locale = settings('user.locale', null, Auth::user())
                ?? settings('app.default_locale', 'en');
            
            App::setLocale($locale);
        }
        
        return $next($request);
    }
}

// In blade:
Current language:
<select onchange="updateLanguage(this.value)">
    @foreach(json_decode(settings('app.locales', '[]')) as $locale)
        <option value="{{ $locale }}" 
            @selected(settings('user.locale', null, Auth::user()) === $locale)>
            {{ trans('languages.' . $locale) }}
        </option>
    @endforeach
</select>
```

---

## Example 11: Analytics & Tracking Settings

```php
// Google Analytics
settings()->set('analytics.google_ua', 'UA-xxxxxxxx-x', 'analytics', 'string');

// Mixpanel
settings()->set('analytics.mixpanel_token', 'token_here', 'analytics', 'string');

// Privacy settings
settings()->set('analytics.enabled', true, 'analytics', 'boolean');
settings()->set('analytics.track_user_id', true, 'analytics', 'boolean');

// In layout:
@if(settings('analytics.enabled'))
    <!-- Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ settings('analytics.google_ua') }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        gtag('js', new Date());
        gtag('config', '{{ settings('analytics.google_ua') }}');
    </script>
@endif
```

---

## Example 12: Backup & Export Settings

```php
// Export all settings for backup
class AdminController {
    public function exportSettings() {
        $logs = settings()->getAuditLog(['limit' => 99999]);
        
        return response()->streamDownload(function () use ($logs) {
            $handle = fopen('php://output', 'w');
            
            // Write headers
            fputcsv($handle, ['Key', 'Old Value', 'New Value', 'Action', 'User', 'Date']);
            
            // Write data
            foreach ($logs as $entry) {
                fputcsv($handle, [
                    $entry->key,
                    $entry->old_value,
                    $entry->new_value,
                    $entry->action,
                    $entry->causer->name ?? 'System',
                    $entry->created_at->format('Y-m-d H:i:s'),
                ]);
            }
            
            fclose($handle);
        }, 'settings-backup-' . now()->format('Y-m-d-His') . '.csv');
    }
}

// Or via CLI:
// php artisan settings:history --export=csv > settings_backup.csv

// Import/restore from backup:
// Process CSV and re-apply settings
```

---

## Example 13: Dynamic Pricing Based on Settings

```php
// Define pricing tier settings
$tiers = [
    'basic' => ['price' => 29, 'users' => 1, 'storage' => 5],
    'pro' => ['price' => 99, 'users' => 10, 'storage' => 100],
    'enterprise' => ['price' => 'custom', 'users' => 'unlimited', 'storage' => 'unlimited'],
];

foreach ($tiers as $name => $config) {
    settings()->set("pricing.{$name}", json_encode($config), 'pricing', 'json');
}

// In plan comparison:
@foreach(['basic', 'pro', 'enterprise'] as $plan)
    @php
        $config = json_decode(settings("pricing.{$plan}"));
    @endphp
    <div class="pricing-card">
        <h3>{{ ucfirst($plan) }}</h3>
        <p class="price">${{ $config->price }}</p>
        <ul>
            <li>Users: {{ $config->users }}</li>
            <li>Storage: {{ $config->storage }}GB</li>
        </ul>
    </div>
@endforeach
```

---

## Example 14: Scheduled Jobs Controlled by Settings

```php
// Enable/disable scheduled tasks via settings
settings()->set('schedule.email_reports.enabled', true, 'schedule', 'boolean');
settings()->set('schedule.email_reports.time', '09:00', 'schedule', 'string');
settings()->set('schedule.cleanup.enabled', true, 'schedule', 'boolean');
settings()->set('schedule.cleanup.days', 30, 'schedule', 'integer');

// In console/Kernel.php:
protected function schedule(Schedule $schedule) {
    // Only run if enabled in settings
    if (settings('schedule.email_reports.enabled', false)) {
        $schedule->call(SendWeeklyReports::class)
            ->dailyAt(settings('schedule.email_reports.time', '09:00'));
    }
    
    if (settings('schedule.cleanup.enabled', false)) {
        $schedule->call(CleanupOldRecords::class)
            ->daily()
            ->appendOutputTo(storage_path('logs/cleanup.log'));
    }
}

// CLI to manage:
// Admin goes to settings UI and enables/changes times
// No code changes needed
```

---

## Example 15: A/B Testing variants

```php
// Define A/B tests
settings()->set('ab_test.new_homepage.enabled', true, 'ab_tests', 'boolean');
settings()->set('ab_test.new_homepage.percent', 50, 'ab_tests', 'integer');  // % of users

// In middleware:
class ABTestMiddleware {
    public function handle($request, $next) {
        if (Auth::check() && settings('ab_test.new_homepage.enabled')) {
            $percent = settings('ab_test.new_homepage.percent', 0);
            $hash = crc32(Auth::id());
            $bucket = ($hash % 100);
            
            if ($bucket < $percent) {
                View::share('ab_test_variant', 'new_homepage');
            }
        }
        
        return $next($request);
    }
}

// In views:
// Show new design to 50% of users, collect feedback
@if(View::shared('ab_test_variant') === 'new_homepage')
    @include('homepage.new')
@else
    @include('homepage.current')
@endif
```

---

These examples demonstrate how settings can replace hardcoded config and enable dynamic behavior without code changes or deployment.

**See Also:** [Quick Start Guide](SETTINGS_QUICK_START.md) | [API Reference](SETTINGS_API_REFERENCE.md)
