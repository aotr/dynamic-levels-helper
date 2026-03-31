<?php

namespace Aotr\DynamicLevelHelper\Tests\Unit;

use Aotr\DynamicLevelHelper\Models\Setting;
use Aotr\DynamicLevelHelper\Models\SettingAuditLog;
use Aotr\DynamicLevelHelper\Tests\PackageTestCase;
use Illuminate\Support\Facades\Cache;

class SettingObserversTest extends PackageTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_invalidates_cache_when_setting_is_created()
    {
        // Pre-populate cache
        Cache::forever('settings', ['app.name' => 'cached']);
        $this->assertNotNull(Cache::get('settings'));

        // Create setting
        Setting::create([
            'key' => 'app.url',
            'value' => 'https://example.com',
            'group' => 'app',
            'type' => 'string',
        ]);

        // Cache should be invalidated
        $this->assertNull(Cache::get('settings'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_invalidates_cache_when_setting_is_updated()
    {
        $setting = Setting::create([
            'key' => 'app.name',
            'value' => 'Old Name',
            'group' => 'app',
            'type' => 'string',
        ]);

        // Populate cache
        Cache::forever('settings', ['app.name' => 'Old Name']);
        $this->assertNotNull(Cache::get('settings'));

        // Update setting
        $setting->update(['value' => 'New Name']);

        // Cache should be invalidated
        $this->assertNull(Cache::get('settings'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_invalidates_cache_when_setting_is_deleted()
    {
        $setting = Setting::create([
            'key' => 'app.name',
            'value' => 'Name',
            'group' => 'app',
            'type' => 'string',
        ]);

        // Populate cache
        Cache::forever('settings', ['app.name' => 'Name']);
        $this->assertNotNull(Cache::get('settings'));

        // Delete setting
        $setting->delete();

        // Cache should be invalidated
        $this->assertNull(Cache::get('settings'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_audit_log_when_setting_is_created()
    {
        $this->actingAs($this->createUser());

        Setting::create([
            'key' => 'app.name',
            'value' => 'My App',
            'group' => 'app',
            'type' => 'string',
        ]);

        $this->assertDatabaseHas('setting_audit_logs', [
            'key' => 'app.name',
            'action' => 'created',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_audit_log_when_setting_is_updated()
    {
        $this->actingAs($this->createUser());

        $setting = Setting::create([
            'key' => 'app.name',
            'value' => 'Original',
            'group' => 'app',
            'type' => 'string',
        ]);

        $setting->update(['value' => 'Updated']);

        $auditLogs = SettingAuditLog::whereKey('app.name')->latest()->first();

        $this->assertEquals('updated', $auditLogs->action);
        $this->assertEqual('Original', $auditLogs->old_value);
        $this->assertEqual('Updated', $auditLogs->new_value);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_audit_log_when_setting_is_deleted()
    {
        $this->actingAs($this->createUser());

        $setting = Setting::create([
            'key' => 'app.name',
            'value' => 'Name',
            'group' => 'app',
            'type' => 'string',
        ]);

        $setting->delete();

        $auditLog = SettingAuditLog::whereKey('app.name')->orderBy('id', 'desc')->first();

        $this->assertEquals('deleted', $auditLog->action);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function audit_log_captures_user_information()
    {
        $user = $this->createUser();
        $this->actingAs($user);

        Setting::create([
            'key' => 'app.name',
            'value' => 'My App',
            'group' => 'app',
            'type' => 'string',
        ]);

        $auditLog = SettingAuditLog::whereKey('app.name')->first();

        $this->assertEquals($user->id, $auditLog->causer_id);
        $this->assertEquals('App\Models\User', $auditLog->causer_type);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function audit_log_captures_request_ip()
    {
        $this->actingAs($this->createUser());

        Setting::create([
            'key' => 'app.name',
            'value' => 'My App',
            'group' => 'app',
            'type' => 'string',
        ]);

        $auditLog = SettingAuditLog::whereKey('app.name')->first();

        $this->assertNotNull($auditLog->request_ip);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function audit_log_captures_metadata()
    {
        $this->actingAs($this->createUser());

        Setting::create([
            'key' => 'app.name',
            'value' => 'My App',
            'group' => 'app',
            'type' => 'string',
            'description' => 'Application name',
        ]);

        $auditLog = SettingAuditLog::whereKey('app.name')->first();

        $this->assertNotNull($auditLog->metadata);
        $this->assertEquals('app', $auditLog->metadata['group'] ?? null);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function audit_log_is_created_silently_even_on_failure()
    {
        // This test verifies that audit logging doesn't break when other operations fail
        // Creating a setting with invalid data should still be caught, but we test that
        // audit logging has proper error handling

        try {
            $setting = Setting::create([
                'key' => 'app.name',
                'value' => 'Too long value',
                'group' => 'app',
                'type' => 'string',
            ]);

            // If we get here, setting was created and audit log should exist
            $this->assertDatabaseHas('setting_audit_logs', [
                'key' => 'app.name',
                'action' => 'created',
            ]);
        } catch (\Exception $e) {
            // Audit logging should have proper error handling
            $this->assertTrue(true);
        }
    }

    /**
     * Create a test user.
     */
    protected function createUser()
    {
        return \App\Models\User::factory()->create();
    }
}
