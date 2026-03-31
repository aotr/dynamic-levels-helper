<?php

namespace Aotr\DynamicLevelHelper\Tests\Unit;

use Aotr\DynamicLevelHelper\Models\Setting;
use Aotr\DynamicLevelHelper\Tests\PackageTestCase;

class SettingsHelperTest extends PackageTestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function settings_helper_returns_service_instance()
    {
        $service = settings();
        $this->assertInstanceOf(\Aotr\DynamicLevelHelper\Services\SettingsService::class, $service);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function settings_helper_get_retrieves_value()
    {
        Setting::create([
            'key' => 'app.name',
            'value' => 'My App',
            'group' => 'app',
            'type' => 'string',
        ]);

        $value = settings('app.name');

        $this->assertEquals('My App', $value);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function settings_helper_get_with_default()
    {
        $value = settings('non.existent', 'default');

        $this->assertEquals('default', $value);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function settings_helper_set_stores_value()
    {
        settings()->set('app.name', 'My App', 'app', 'string');

        $this->assertDatabaseHas('settings', [
            'key' => 'app.name',
            'value' => 'My App',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function settings_helper_all_retrieves_all_settings()
    {
        Setting::create([
            'key' => 'app.name',
            'value' => 'Name',
            'group' => 'app',
            'type' => 'string',
        ]);

        Setting::create([
            'key' => 'app.url',
            'value' => 'URL',
            'group' => 'app',
            'type' => 'string',
        ]);

        $all = settings()->all();

        $this->assertCount(2, $all);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function settings_helper_group_retrieves_group_settings()
    {
        Setting::create([
            'key' => 'app.name',
            'value' => 'Name',
            'group' => 'app',
            'type' => 'string',
        ]);

        Setting::create([
            'key' => 'mail.host',
            'value' => 'Host',
            'group' => 'mail',
            'type' => 'string',
        ]);

        $appSettings = settings()->group('app');

        $this->assertCount(1, $appSettings);
        $this->assertArrayHasKey('app.name', $appSettings);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function facade_retrieves_setting()
    {
        Setting::create([
            'key' => 'app.name',
            'value' => 'My App',
            'group' => 'app',
            'type' => 'string',
        ]);

        $value = \Aotr\DynamicLevelHelper\Facades\SettingsHelper::get('app.name');

        $this->assertEquals('My App', $value);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function facade_sets_setting()
    {
        \Aotr\DynamicLevelHelper\Facades\SettingsHelper::set('app.name', 'My App', 'app', 'string');

        $this->assertDatabaseHas('settings', [
            'key' => 'app.name',
            'value' => 'My App',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function facade_retrieves_all_settings()
    {
        Setting::create([
            'key' => 'app.name',
            'value' => 'Name',
            'group' => 'app',
            'type' => 'string',
        ]);

        $all = \Aotr\DynamicLevelHelper\Facades\SettingsHelper::all();

        $this->assertCount(1, $all);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function facade_retrieves_group_settings()
    {
        Setting::create([
            'key' => 'app.name',
            'value' => 'Name',
            'group' => 'app',
            'type' => 'string',
        ]);

        Setting::create([
            'key' => 'mail.host',
            'value' => 'Host',
            'group' => 'mail',
            'type' => 'string',
        ]);

        $appSettings = \Aotr\DynamicLevelHelper\Facades\SettingsHelper::group('app');

        $this->assertCount(1, $appSettings);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function facade_retrieves_history()
    {
        Setting::create([
            'key' => 'app.name',
            'value' => 'Original',
            'group' => 'app',
            'type' => 'string',
        ]);

        $history = \Aotr\DynamicLevelHelper\Facades\SettingsHelper::getHistory('app.name');

        $this->assertGreaterThanOrEqual(1, $history->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function facade_retrieves_audit_log()
    {
        Setting::create([
            'key' => 'app.name',
            'value' => 'Value',
            'group' => 'app',
            'type' => 'string',
        ]);

        $auditLog = \Aotr\DynamicLevelHelper\Facades\SettingsHelper::getAuditLog();

        $this->assertGreaterThanOrEqual(1, $auditLog->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function facade_invalidates_cache()
    {
        Setting::create([
            'key' => 'app.name',
            'value' => 'Value',
            'group' => 'app',
            'type' => 'string',
        ]);

        // Load into cache
        \Aotr\DynamicLevelHelper\Facades\SettingsHelper::get('app.name');

        // Invalidate
        \Aotr\DynamicLevelHelper\Facades\SettingsHelper::invalidate();

        // Cache should be cleared
        $this->assertNull(\Illuminate\Support\Facades\Cache::get('settings'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function facade_warms_cache()
    {
        Setting::create([
            'key' => 'app.name',
            'value' => 'Value',
            'group' => 'app',
            'type' => 'string',
        ]);

        \Illuminate\Support\Facades\Cache::flush();
        $this->assertNull(\Illuminate\Support\Facades\Cache::get('settings'));

        \Aotr\DynamicLevelHelper\Facades\SettingsHelper::warm();

        $cached = \Illuminate\Support\Facades\Cache::get('settings');
        $this->assertNotNull($cached);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function facade_prunes_audit_logs()
    {
        $user = $this->createUser();
        $this->actingAs($user);

        Setting::create([
            'key' => 'app.name',
            'value' => 'Value',
            'group' => 'app',
            'type' => 'string',
        ]);

        // Prune old logs
        \Aotr\DynamicLevelHelper\Facades\SettingsHelper::pruneAuditLogs(90);

        // This should complete without error
        $this->assertTrue(true);
    }

    /**
     * Create a test user.
     */
    protected function createUser()
    {
        return \App\Models\User::factory()->create();
    }
}
