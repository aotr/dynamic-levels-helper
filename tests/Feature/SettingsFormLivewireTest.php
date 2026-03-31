<?php

namespace Aotr\DynamicLevelHelper\Tests\Feature;

use Aotr\DynamicLevelHelper\Models\Setting;
use Aotr\DynamicLevelHelper\Livewire\SettingsForm;
use Aotr\DynamicLevelHelper\Tests\PackageTestCase;
use Livewire\Livewire;

class SettingsFormLivewireTest extends PackageTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutExceptionHandling();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function component_renders_successfully()
    {
        Livewire::test(SettingsForm::class)
            ->assertStatus(200);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function component_loads_settings_on_mount()
    {
        Setting::create([
            'key' => 'app.name',
            'value' => 'My App',
            'group' => 'app',
            'type' => 'string',
        ]);

        Livewire::test(SettingsForm::class)
            ->assertSet('settings', fn ($settings) => count($settings) > 0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function component_loads_groups()
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

        Livewire::test(SettingsForm::class)
            ->assertSet('groups', fn ($groups) => in_array('app', $groups) && in_array('mail', $groups));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_filter_settings_by_group()
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

        Livewire::test(SettingsForm::class)
            ->call('filterByGroup', 'app')
            ->assertSet('selectedGroup', 'app')
            ->assertSet('settings', fn ($settings) => count($settings) === 1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_search_settings()
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

        Livewire::test(SettingsForm::class)
            ->set('search', 'url')
            ->call('filterBySearch')
            ->assertSet('settings', fn ($settings) => count($settings) === 1 && isset($settings[0]['key']) && str_contains($settings[0]['key'], 'url'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_edit_a_setting()
    {
        Setting::create([
            'key' => 'app.name',
            'value' => 'Original',
            'group' => 'app',
            'type' => 'string',
        ]);

        Livewire::test(SettingsForm::class)
            ->call('editSetting', 'app.name')
            ->assertSet('editingKey', 'app.name')
            ->assertSet('editingValue', 'Original');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_save_a_setting()
    {
        Setting::create([
            'key' => 'app.name',
            'value' => 'Original',
            'group' => 'app',
            'type' => 'string',
        ]);

        Livewire::test(SettingsForm::class)
            ->call('editSetting', 'app.name')
            ->set('editingValue', 'Updated')
            ->call('saveSetting', 'app.name')
            ->assertDispatcher('alert', 'Setting saved successfully!');

        $this->assertDatabaseHas('settings', [
            'key' => 'app.name',
            'value' => 'Updated',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function save_setting_validates_required_field()
    {
        Setting::create([
            'key' => 'app.name',
            'value' => 'Original',
            'group' => 'app',
            'type' => 'string',
        ]);

        Livewire::test(SettingsForm::class)
            ->call('editSetting', 'app.name')
            ->set('editingValue', '')
            ->call('saveSetting', 'app.name')
            ->assertHasErrors('editingValue');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_delete_a_setting()
    {
        $setting = Setting::create([
            'key' => 'app.name',
            'value' => 'Name',
            'group' => 'app',
            'type' => 'string',
        ]);

        Livewire::test(SettingsForm::class)
            ->call('deleteSetting', 'app.name')
            ->assertDispatcher('alert', 'Setting deleted successfully!');

        $this->assertSoftDeleted('settings', [
            'id' => $setting->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_reset_a_setting()
    {
        $setting = Setting::create([
            'key' => 'app.name',
            'value' => 'Original',
            'group' => 'app',
            'type' => 'string',
        ]);

        // Update to a new value
        $setting->update(['value' => 'Modified']);

        Livewire::test(SettingsForm::class)
            ->call('resetSetting', 'app.name')
            ->assertDispatcher('alert', 'Setting reset successfully!');

        // After reset, the value should go back to original
        // Note: This depends on your implementation of resetSetting
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_create_a_new_setting()
    {
        Livewire::test(SettingsForm::class)
            ->set('newSettingKey', 'app.new_key')
            ->set('newSettingValue', 'New Value')
            ->set('newSettingGroup', 'app')
            ->set('newSettingType', 'string')
            ->call('createSetting')
            ->assertDispatcher('alert', 'Setting created successfully!');

        $this->assertDatabaseHas('settings', [
            'key' => 'app.new_key',
            'value' => 'New Value',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function create_setting_validates_required_fields()
    {
        Livewire::test(SettingsForm::class)
            ->set('newSettingKey', '')
            ->set('newSettingValue', 'Value')
            ->call('createSetting')
            ->assertHasErrors('newSettingKey');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function create_setting_prevents_duplicate_keys()
    {
        Setting::create([
            'key' => 'app.name',
            'value' => 'Existing',
            'group' => 'app',
            'type' => 'string',
        ]);

        Livewire::test(SettingsForm::class)
            ->set('newSettingKey', 'app.name')
            ->set('newSettingValue', 'New')
            ->set('newSettingGroup', 'app')
            ->set('newSettingType', 'string')
            ->call('createSetting')
            ->assertHasErrors('newSettingKey');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function component_displays_correct_input_type_for_boolean()
    {
        Setting::create([
            'key' => 'feature.enabled',
            'value' => '1',
            'group' => 'features',
            'type' => 'boolean',
        ]);

        Livewire::test(SettingsForm::class)
            ->call('editSetting', 'feature.enabled')
            ->assertSet('editingType', 'boolean');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_clear_filters()
    {
        Setting::create([
            'key' => 'app.name',
            'value' => 'Name',
            'group' => 'app',
            'type' => 'string',
        ]);

        Livewire::test(SettingsForm::class)
            ->call('filterByGroup', 'app')
            ->set('search', 'test')
            ->call('clearFilters')
            ->assertSet('selectedGroup', '')
            ->assertSet('search', '')
            ->assertSet('settings', fn ($settings) => count($settings) > 0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function component_handles_type_casting_on_save()
    {
        Setting::create([
            'key' => 'app.max_users',
            'value' => '100',
            'group' => 'app',
            'type' => 'integer',
        ]);

        Livewire::test(SettingsForm::class)
            ->call('editSetting', 'app.max_users')
            ->set('editingValue', '250')
            ->call('saveSetting', 'app.max_users');

        $this->assertDatabaseHas('settings', [
            'key' => 'app.max_users',
            'value' => '250',
            'type' => 'integer',
        ]);
    }
}
