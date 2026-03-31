<?php

namespace Aotr\DynamicLevelHelper\Tests\Feature;

use Aotr\DynamicLevelHelper\Models\Setting;
use Aotr\DynamicLevelHelper\Models\SettingAuditLog;
use Aotr\DynamicLevelHelper\Tests\PackageTestCase;
use Illuminate\Support\Facades\Cache;

class SettingsConsoleCommandsTest extends PackageTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function warm_settings_cache_command_populates_cache()
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

        // Cache should be empty
        $this->assertNull(Cache::get('settings'));

        // Run command
        $this->artisan('settings:warm')
            ->expectsOutput('Settings cache has been warmed.')
            ->assertExitCode(0);

        // Cache should contain settings
        $cached = Cache::get('settings:global');
        $this->assertNotNull($cached);
        $this->assertArrayHasKey('app.name', $cached);
        $this->assertArrayHasKey('app.url', $cached);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function settings_history_command_shows_all_history()
    {
        // TODO: This test requires the audit log observer to be triggered correctly in the test context
        //       For now, we're skipping this test until we can properly mock or verify the observer behavior
        $user = $this->createUser();
        $this->actingAs($user);

        // Create and modify a setting
        $setting = Setting::create([
            'key' => 'app.name',
            'value' => 'Original',
            'group' => 'app',
            'type' => 'string',
        ]);

        $setting->update(['value' => 'Updated']);

        // Run command to list history
        $this->artisan('settings:history')
            ->expectsOutput('app.name')
            ->expectsOutput('created')
            ->expectsOutput('updated')
            ->assertExitCode(0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function settings_history_command_filters_by_key()
    {
        $user = $this->createUser();
        $this->actingAs($user);

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

        // Run command with key filter
        $this->artisan('settings:history')
            ->argument('--key', 'app.name')
            ->expectsOutput('app.name')
            ->assertExitCode(0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function settings_history_command_filters_by_group()
    {
        $user = $this->createUser();
        $this->actingAs($user);

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

        // Run command with group filter
        $this->artisan('settings:history')
            ->argument('--group', 'app')
            ->expectsOutput('app.name')
            ->assertExitCode(0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function settings_history_command_filters_by_action()
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $setting = Setting::create([
            'key' => 'app.name',
            'value' => 'Name',
            'group' => 'app',
            'type' => 'string',
        ]);

        $setting->update(['value' => 'Updated']);

        // Run command with action filter
        $this->artisan('settings:history')
            ->argument('--action', 'updated')
            ->assertExitCode(0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function settings_history_command_limits_results()
    {
        $user = $this->createUser();
        $this->actingAs($user);

        for ($i = 0; $i < 5; $i++) {
            Setting::create([
                'key' => 'app.setting_' . $i,
                'value' => 'Value',
                'group' => 'app',
                'type' => 'string',
            ]);
        }

        // Run command with limit
        $this->artisan('settings:history')
            ->argument('--limit', '2')
            ->assertExitCode(0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function settings_history_command_exports_to_csv()
    {
        $user = $this->createUser();
        $this->actingAs($user);

        Setting::create([
            'key' => 'app.name',
            'value' => 'Name',
            'group' => 'app',
            'type' => 'string',
        ]);

        $this->artisan('settings:history')
            ->argument('--export', 'csv')
            ->assertExitCode(0);

        // CSV export should have been saved
        // You can add more specific filesystem assertions here
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function settings_history_command_prunes_old_entries()
    {
        $user = $this->createUser();
        $this->actingAs($user);

        Setting::create([
            'key' => 'app.name',
            'value' => 'Name',
            'group' => 'app',
            'type' => 'string',
        ]);

        $setting = Setting::first();
        $auditLog = SettingAuditLog::whereKey('app.name')->first();

        // Backdatethe audit log
        $auditLog->update(['created_at' => now()->subDays(100)]);

        // Run prune command
        $this->artisan('settings:history')
            ->argument('--prune', '90')
            ->assertExitCode(0);

        // Old entry should be pruned
        $this->assertDatabaseMissing('setting_audit_logs', [
            'id' => $auditLog->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function warm_settings_cache_handles_empty_database()
    {
        // No settings in database
        $this->artisan('settings:warm')
            ->expectsOutput('Settings cache has been warmed.')
            ->assertExitCode(0);

        // Cache should still be set (even if empty)
        $cached = Cache::get('settings:global');
        $this->assertNotNull($cached);
        $this->assertEmpty($cached);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function settings_history_shows_formatted_changes()
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $setting = Setting::create([
            'key' => 'app.name',
            'value' => 'This is a very long value that should be truncated to prevent excessive output in the CLI',
            'group' => 'app',
            'type' => 'string',
        ]);

        // Run command
        $this->artisan('settings:history')
            ->argument('--key', 'app.name')
            ->assertExitCode(0);

        // The output should contain truncated value
        // This is dependent on the actual output format
    }

    /**
     * Create a test user.
     */
    protected function createUser()
    {
        return \App\Models\User::factory()->create();
    }
}
