<?php

namespace Aotr\DynamicLevelHelper\Livewire;

use Aotr\DynamicLevelHelper\Models\Setting;
use Aotr\DynamicLevelHelper\Services\SettingsService;
use Livewire\Component;

class SettingsForm extends Component
{
    public $group = null;
    public $settings = [];
    public $editingKey = null;
    public $formData = [];
    public $search = '';
    public $groups = [];

    #[Validate('required|string')]
    public $newKey = '';

    #[Validate('required|string')]
    public $newValue = '';

    #[Validate('required|in:string,number,integer,decimal,boolean,select')]
    public $newType = 'string';

    public function mount()
    {
        $this->loadGroups();
        $this->loadSettings();
    }

    public function loadGroups()
    {
        $this->groups = Setting::distinct()
            ->pluck('group')
            ->sort()
            ->toArray();
    }

    public function loadSettings()
    {
        $query = Setting::query();

        if ($this->group) {
            $query->where('group', $this->group);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('key', 'like', "%{$this->search}%")
                  ->orWhere('display_name', 'like', "%{$this->search}%");
            });
        }

        $this->settings = $query->orderBy('group')
            ->orderBy('order')
            ->get()
            ->map(fn($s) => [
                'id' => $s->id,
                'key' => $s->key,
                'group' => $s->group,
                'display_name' => $s->display_name,
                'value' => $s->value,
                'type' => $s->type,
                'meta' => $s->meta ?? [],
            ])
            ->toArray();

        $this->formData = [];
    }

    public function filterByGroup($group)
    {
        $this->group = $group === 'all' ? null : $group;
        $this->search = '';
        $this->loadSettings();
    }

    public function searchSettings()
    {
        $this->loadSettings();
    }

    public function editSetting($key)
    {
        $setting = Setting::where('key', $key)->first();
        if ($setting) {
            $this->editingKey = $key;
            $this->formData = [
                'value' => $setting->value,
                'type' => $setting->type,
            ];
        }
    }

    public function saveSetting($key)
    {
        $setting = Setting::where('key', $key)->first();
        if ($setting && isset($this->formData['value'])) {
            // Cast value based on type
            $value = $this->castValue($this->formData['value'], $this->formData['type']);

            $setting->update(['value' => $value]);

            $this->dispatch('notify', message: "✓ Setting '{$key}' saved successfully");
            $this->editingKey = null;
            $this->loadSettings();
        }
    }

    public function createSetting()
    {
        $this->validate([
            'newKey' => 'required|string|unique:settings,key',
            'newValue' => 'required|string',
            'newType' => 'required|in:string,number,integer,decimal,boolean,select',
        ]);

        Setting::create([
            'key' => $this->newKey,
            'group' => $this->group ?? 'general',
            'display_name' => ucfirst(str_replace('.', ' ', $this->newKey)),
            'value' => $this->castValue($this->newValue, $this->newType),
            'type' => $this->newType,
        ]);

        $this->dispatch('notify', message: "✓ Setting '{$this->newKey}' created successfully");
        $this->newKey = '';
        $this->newValue = '';
        $this->newType = 'string';
        $this->loadSettings();
    }

    public function deleteSetting($key)
    {
        Setting::where('key', $key)->delete();
        $this->dispatch('notify', message: "✓ Setting '{$key}' deleted");
        $this->loadSettings();
    }

    public function resetSetting($key)
    {
        // Reset to default or null
        $setting = Setting::where('key', $key)->first();
        if ($setting) {
            $setting->update(['value' => null]);
            $this->dispatch('notify', message: "✓ Setting '{$key}' reset to default");
            $this->loadSettings();
        }
    }

    protected function castValue($value, $type)
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'number', 'integer' => (int) $value,
            'decimal' => (float) $value,
            default => $value,
        };
    }

    public function render()
    {
        return view('livewire.settings-form', [
            'settings' => $this->settings,
            'groups' => $this->groups,
            'editingKey' => $this->editingKey,
        ]);
    }
}
