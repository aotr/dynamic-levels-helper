<?php

namespace Aotr\DynamicLevelHelper\Tests\Unit;

use Aotr\DynamicLevelHelper\Models\Setting;
use Aotr\DynamicLevelHelper\Services\SettingsService;
use Aotr\DynamicLevelHelper\Tests\PackageTestCase;
use Illuminate\Support\Facades\Cache;

class SettingsServiceTest extends PackageTestCase
{
    protected SettingsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SettingsService::class);
        // Clear cache before each test
        Cache::flush();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_retrieves_setting_by_key()
    {
        Setting::create([
            'key' => 'app.name',
            'value' => 'My App',
            'group' => 'app',
            'type' => 'string',
        ]);

        $value = $this->service->get('app.name');

        $this->assertEquals('My App', $value);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_null_for_missing_key()
    {
        $value = $this->service->get('non.existent.key');
        $this->assertNull($value);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_default_value_for_missing_key()
    {
        $value = $this->service->get('non.existent.key', 'default');
        $this->assertEquals('default', $value);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_sets_a_new_setting()
    {
        $this->service->set('new.key', 'new value', 'group', 'string');

        $this->assertDatabaseHas('settings', [
            'key' => 'new.key',
            'value' => 'new value',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_updates_existing_setting()
    {
        Setting::create([
            'key' => 'app.name',
            'value' => 'Old Name',
            'group' => 'app',
            'type' => 'string',
        ]);

        $this->service->set('app.name', 'New Name');

        $this->assertDatabaseHas('settings', [
            'key' => 'app.name',
            'value' => 'New Name',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_casts_boolean_type()
    {
        Setting::create([
            'key' => 'feature.enabled',
            'value' => '1',
            'group' => 'features',
            'type' => 'boolean',
        ]);

        $value = $this->service->get('feature.enabled');

        $this->assertTrue($value);
        $this->assertIsBool($value);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_casts_integer_type()
    {
        Setting::create([
            'key' => 'app.max_users',
            'value' => '100',
            'group' => 'app',
            'type' => 'integer',
        ]);

        $value = $this->service->get('app.max_users');

        $this->assertEquals(100, $value);
        $this->assertIsInt($value);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_casts_float_type()
    {
        Setting::create([
            'key' => 'app.tax_rate',
            'value' => '0.18',
            'group' => 'app',
            'type' => 'float',
        ]);

        $value = $this->service->get('app.tax_rate');

        $this->assertEquals(0.18, $value);
        $this->assertIsFloat($value);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_json_type()
    {
        $jsonValue = ['key1' => 'value1', 'key2' => 'value2'];
        Setting::create([
            'key' => 'app.config',
            'value' => json_encode($jsonValue),
            'group' => 'app',
            'type' => 'json',
        ]);

        $value = $this->service->get('app.config');

        $this->assertEquals($jsonValue, $value);
        $this->assertIsArray($value);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_retrieves_all_settings()
    {
        Setting::create([
            'key' => 'app.name',
            'value' => 'My App',
            'group' => 'app',
            'type' => 'string',
        ]);

        Setting::create([
            'key' => 'app.url',
            'value' => 'https://example.com',
            'group' => 'app',
            'type' => 'string',
        ]);

        $all = $this->service->all();

        $this->assertCount(2, $all);
        $this->assertEquals('My App', $all['app.name']);
        $this->assertEquals('https://example.com', $all['app.url']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_retrieves_settings_by_group()
    {
        Setting::create([
            'key' => 'app.name',
            'value' => 'My App',
            'group' => 'app',
            'type' => 'string',
        ]);

        Setting::create([
            'key' => 'mail.host',
            'value' => 'smtp.mailgun.org',
            'group' => 'mail',
            'type' => 'string',
        ]);

        $appSettings = $this->service->group('app');

        $this->assertCount(1, $appSettings);
        $this->assertEquals('My App', $appSettings['app.name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_caches_settings()
    {
        Setting::create([
            'key' => 'app.name',
            'value' => 'My App',
            'group' => 'app',
            'type' => 'string',
        ]);

        // First call loads from database
        $value1 = $this->service->get('app.name');

        // Check cache exists
        $cached = Cache::get('settings');
        $this->assertNotNull($cached);

        // Second call should come from cache
        $value2 = $this->service->get('app.name');

        $this->assertEquals($value1, $value2);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_invalidates_cache_on_refresh()
    {
        Setting::create([
            'key' => 'app.name',
            'value' => 'Original',
            'group' => 'app',
            'type' => 'string',
        ]);

        // Load into cache
        $this->service->get('app.name');
        $this->assertNotNull(Cache::get('settings'));

        // Invalidate
        $this->service->invalidate();

        // Cache should be cleared
        $this->assertNull(Cache::get('settings'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_merges_scoped_settings()
    {
        // Global setting
        Setting::create([
            'key' => 'app.name',
            'value' => 'Global App',
            'group' => 'app',
            'type' => 'string',
            'model_type' => null,
            'model_id' => null,
        ]);

        // User-scoped setting (same key)
        $user = $this->createUser();
        Setting::create([
            'key' => 'app.name',
            'value' => 'User App',
            'group' => 'app',
            'type' => 'string',
            'model_type' => 'App\Models\User',
            'model_id' => $user->id,
        ]);

        // User scope should override global
        $value = $this->service->get('app.name', null, $user);

        $this->assertEquals('User App', $value);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_supports_string_scope_syntax()
    {
        $user = $this->createUser();
        Setting::create([
            'key' => 'app.name',
            'value' => 'User App',
            'group' => 'app',
            'type' => 'string',
            'model_type' => 'App\Models\User',
            'model_id' => $user->id,
        ]);

        $scope = 'App\Models\User:' . $user->id;
        $value = $this->service->get('app.name', null, $scope);

        $this->assertEquals('User App', $value);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_deletes_a_setting()
    {
        $setting = Setting::create([
            'key' => 'app.name',
            'value' => 'My App',
            'group' => 'app',
            'type' => 'string',
        ]);

        $this->service->delete('app.name');

        $this->assertDatabaseMissing('settings', [
            'id' => $setting->id,
            'deleted_at' => null,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_warms_cache()
    {
        Setting::create([
            'key' => 'app.name',
            'value' => 'My App',
            'group' => 'app',
            'type' => 'string',
        ]);

        Cache::flush();
        $this->assertNull(Cache::get('settings'));

        $this->service->warm();

        $cached = Cache::get('settings');
        $this->assertNotNull($cached);
        $this->assertArrayHasKey('app.name', $cached);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_retrieves_setting_history()
    {
        $setting = Setting::create([
            'key' => 'app.name',
            'value' => 'Original',
            'group' => 'app',
            'type' => 'string',
        ]);

        $setting->update(['value' => 'Updated']);

        $history = $this->service->getHistory('app.name');

        // Should have at least 2 entries (create + update)
        $this->assertGreaterThanOrEqual(2, $history->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_retrieves_audit_log_filtered_by_key()
    {
        Setting::create(['key' => 'app.name', 'value' => 'Name', 'group' => 'app', 'type' => 'string']);
        Setting::create(['key' => 'app.url', 'value' => 'URL', 'group' => 'app', 'type' => 'string']);

        $auditLog = $this->service->getAuditLog(['key' => 'app.name']);

        $this->assertTrue($auditLog->pluck('key')->every(fn ($key) => $key === 'app.name'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_settings_by_key_pattern()
    {
        Setting::create(['key' => 'app.name', 'value' => 'Name', 'group' => 'app', 'type' => 'string']);
        Setting::create(['key' => 'app.url', 'value' => 'URL', 'group' => 'app', 'type' => 'string']);
        Setting::create(['key' => 'mail.host', 'value' => 'Host', 'group' => 'mail', 'type' => 'string']);

        $appSettings = $this->service->where('key', 'LIKE', 'app.%')->get();

        $this->assertCount(2, $appSettings);
    }

    /**
     * Create a test user model.
     */
    protected function createUser()
    {
        return \Illuminate\Foundation\Testing\Concerns\InteractsWithDatabase::class;
    }
}
