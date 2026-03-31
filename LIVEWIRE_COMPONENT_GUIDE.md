# Livewire Component Update Guide

To make your Livewire SettingsForm component testable with Playwright, add the test IDs shown below.

## Complete Example Component

```blade
<div data-testid="settings-form" class="bg-white rounded-lg shadow">
    {{-- Header --}}
    <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="text-2xl font-bold text-gray-900">Settings Management</h2>
    </div>

    {{-- Filters Section --}}
    <div data-testid="filters" class="px-6 py-4 bg-gray-50 border-b border-gray-200">
        <div class="grid md:grid-cols-2 gap-4">
            {{-- Group Filter --}}
            <div>
                <label for="group-filter" class="block text-sm font-medium text-gray-700">
                    Filter by Group
                </label>
                <select 
                    id="group-filter"
                    data-testid="group-filter"
                    wire:model.live="filters.group"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    <option value="">All Groups</option>
                    @foreach($availableGroups as $group)
                        <option 
                            data-testid="group-option-{{ $group }}"
                            value="{{ $group }}">
                            {{ ucfirst($group) }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Search --}}
            <div>
                <label for="search-input" class="block text-sm font-medium text-gray-700">
                    Search Settings
                </label>
                <input 
                    id="search-input"
                    type="text"
                    data-testid="search-input"
                    wire:model.debounce="filters.search"
                    placeholder="Search by key or name..."
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            </div>
        </div>
    </div>

    {{-- Loading State --}}
    @if($loading)
        <div data-testid="loading" role="status" class="px-6 py-12 text-center">
            <div class="inline-block animate-spin">
                <svg class="h-8 w-8 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
            <p class="mt-4 text-gray-600">Loading settings...</p>
        </div>
    @endif

    {{-- Empty State --}}
    @if(!$loading && $settings->isEmpty())
        <div data-testid="empty-state" class="px-6 py-12 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m0 0h6"></path>
            </svg>
            <h3 class="mt-2 text-lg font-medium text-gray-900">No settings found</h3>
            <p class="mt-1 text-gray-500">Create a new setting to get started.</p>
            <button 
                data-testid="create-setting-button"
                wire:click="startCreate"
                class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                Create First Setting
            </button>
        </div>
    @endif

    {{-- Settings List --}}
    @if(!$loading && $settings->isNotEmpty())
        <div data-testid="settings-list" class="divide-y divide-gray-200">
            @foreach($settings as $setting)
                <div 
                    data-testid="setting-row"
                    data-group="{{ $setting->group }}"
                    data-type="{{ $setting->type }}"
                    class="px-6 py-4 hover:bg-gray-50">
                    
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <h4 class="text-sm font-medium text-gray-900">
                                <span class="setting-key">{{ $setting->key }}</span>
                            </h4>
                            <p class="text-xs text-gray-500">{{ $setting->display_name }}</p>
                        </div>

                        {{-- Input by Type --}}
                        <div class="flex-1 mx-4">
                            @if($setting->type === 'boolean')
                                <label class="flex items-center">
                                    <input 
                                        type="checkbox"
                                        data-testid="value-input"
                                        wire:model.live="settings.{{ $setting->key }}"
                                        @checked($setting->value)
                                        class="rounded border-gray-300">
                                    <span class="ml-2 text-sm text-gray-600">
                                        {{ $setting->value ? 'Enabled' : 'Disabled' }}
                                    </span>
                                </label>
                            @elseif($setting->type === 'number')
                                <input 
                                    type="number"
                                    data-testid="value-input"
                                    wire:model.live="settings.{{ $setting->key }}"
                                    value="{{ $setting->value }}"
                                    class="block w-full px-3 py-2 rounded-md border-gray-300">
                            @elseif($setting->type === 'json')
                                <textarea 
                                    data-testid="value-input"
                                    wire:model.live="settings.{{ $setting->key }}"
                                    rows="2"
                                    class="block w-full px-3 py-2 rounded-md border-gray-300 font-mono text-sm">{{ json_encode($setting->value, JSON_PRETTY_PRINT) }}</textarea>
                            @else
                                <input 
                                    type="text"
                                    data-testid="value-input"
                                    wire:model.live="settings.{{ $setting->key }}"
                                    value="{{ $setting->value }}"
                                    class="block w-full px-3 py-2 rounded-md border-gray-300">
                            @endif
                        </div>

                        {{-- Actions --}}
                        <div class="flex gap-2">
                            @if($editingId === $setting->id)
                                <button 
                                    data-testid="save-button"
                                    wire:click="save"
                                    class="px-3 py-1 bg-green-600 text-white rounded text-sm hover:bg-green-700">
                                    Save
                                </button>
                                <button 
                                    data-testid="cancel-button"
                                    wire:click="cancel"
                                    class="px-3 py-1 bg-gray-400 text-white rounded text-sm hover:bg-gray-500">
                                    Cancel
                                </button>
                            @else
                                <button 
                                    data-testid="edit-button"
                                    wire:click="edit({{ $setting->id }})"
                                    class="px-3 py-1 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">
                                    Edit
                                </button>
                                <button 
                                    data-testid="reset-button"
                                    wire:click="reset('{{ $setting->key }}')"
                                    class="px-3 py-1 bg-yellow-600 text-white rounded text-sm hover:bg-yellow-700">
                                    Reset
                                </button>
                                <button 
                                    data-testid="delete-button"
                                    wire:click="confirmDelete({{ $setting->id }})"
                                    class="px-3 py-1 bg-red-600 text-white rounded text-sm hover:bg-red-700">
                                    Delete
                                </button>
                            @endif
                        </div>
                    </div>

                    {{-- Edit Form (inline) --}}
                    @if($editingId === $setting->id)
                        <div data-testid="edit-form" class="mt-4 p-4 bg-blue-50 rounded">
                            <input 
                                type="text"
                                data-testid="value-input"
                                wire:model="editingValue"
                                placeholder="New value..."
                                class="block w-full px-3 py-2 rounded-md border-gray-300">
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Create Button --}}
        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
            @if(!$creating)
                <button 
                    data-testid="create-setting-button"
                    wire:click="startCreate"
                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    Create New Setting
                </button>
            @endif
        </div>
    @endif

    {{-- Create Form Modal --}}
    @if($creating)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div data-testid="create-form" class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Create New Setting</h3>
                
                <div class="space-y-4">
                    <div>
                        <label for="new-key" class="block text-sm font-medium text-gray-700">
                            Setting Key
                        </label>
                        <input 
                            id="new-key"
                            type="text"
                            data-testid="key-input"
                            wire:model="newSetting.key"
                            placeholder="e.g., app.name"
                            class="mt-1 block w-full rounded-md border-gray-300">
                        @error('newSetting.key')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="new-value" class="block text-sm font-medium text-gray-700">
                            Value
                        </label>
                        <input 
                            id="new-value"
                            type="text"
                            data-testid="value-input"
                            wire:model="newSetting.value"
                            class="mt-1 block w-full rounded-md border-gray-300">
                        @error('newSetting.value')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="new-group" class="block text-sm font-medium text-gray-700">
                            Group
                        </label>
                        <select 
                            id="new-group"
                            data-testid="group-input"
                            wire:model="newSetting.group"
                            class="mt-1 block w-full rounded-md border-gray-300">
                            <option value="general">General</option>
                            <option value="app">App</option>
                            <option value="features">Features</option>
                        </select>
                    </div>

                    <div class="flex gap-2 justify-end">
                        <button 
                            data-testid="cancel-button"
                            wire:click="cancelCreate"
                            class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                            Cancel
                        </button>
                        <button 
                            data-testid="save-button"
                            wire:click="create"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            Create
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Delete Confirmation Modal --}}
    @if($confirmingDelete)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl p-6 max-w-sm w-full">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Delete Setting?</h3>
                <p class="text-gray-600 mb-6">
                    Are you sure you want to delete <strong>{{ $deletingKey }}</strong>? This action cannot be undone.
                </p>
                
                <div class="flex gap-2 justify-end">
                    <button 
                        data-testid="cancel-button"
                        wire:click="cancelDelete"
                        class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                        Cancel
                    </button>
                    <button 
                        data-testid="confirm-delete"
                        wire:click="delete"
                        class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                        Delete
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Success/Error Messages --}}
    @if(session('success'))
        <div role="alert" class="fixed top-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div role="alert" class="fixed top-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            {{ session('error') }}
        </div>
    @endif
</div>
```

## Key Test Attributes Added

| Attribute | Purpose |
|-----------|---------|
| `data-testid="settings-form"` | Main form container |
| `data-testid="settings-list"` | Settings list container |
| `data-testid="setting-row"` | Individual setting row |
| `data-testid="value-input"` | Settings value input |
| `data-testid="group-filter"` | Group filter dropdown |
| `data-testid="search-input"` | Search input field |
| `data-testid="edit-button"` | Edit button |
| `data-testid="delete-button"` | Delete button |
| `data-testid="save-button"` | Save button |
| `data-testid="cancel-button"` | Cancel button |
| `data-testid="reset-button"` | Reset to default button |
| `data-testid="create-setting-button"` | Create new setting button |
| `data-testid="edit-form"` | Edit form container |
| `data-testid="create-form"` | Create form modal |
| `data-testid="empty-state"` | Empty state message |
| `data-testid="loading"` | Loading indicator |
| `data-testid="confirm-delete"` | Delete confirmation button |

## Livewire Component Methods to Implement

```php
<?php

namespace App\Livewire;

use Livewire\Component;
use Aotr\DynamicLevelHelper\Models\Setting;

class SettingsForm extends Component
{
    public $settings = [];
    public $filters = ['group' => '', 'search' => ''];
    public $loading = false;
    public $editingId = null;
    public $editingValue = '';
    public $creating = false;
    public $newSetting = ['key' => '', 'value' => '', 'group' => 'general'];
    public $confirmingDelete = false;
    public $deletingKey = '';

    protected $rules = [
        'newSetting.key' => 'required|string|unique:settings,key',
        'newSetting.value' => 'required|string',
        'newSetting.group' => 'required|string',
    ];

    public function mount()
    {
        $this->loadSettings();
    }

    public function loadSettings()
    {
        $this->loading = true;
        
        $query = Setting::query();
        
        if ($this->filters['group']) {
            $query->where('group', $this->filters['group']);
        }
        
        if ($this->filters['search']) {
            $query->where('key', 'like', '%' . $this->filters['search'] . '%');
        }
        
        $this->settings = $query->get();
        $this->loading = false;
    }

    #[\Livewire\Attributes\On('filter')]
    public function filterChanged()
    {
        $this->loadSettings();
    }

    public function edit($id)
    {
        $setting = $this->settings->find($id);
        $this->editingId = $id;
        $this->editingValue = $setting->value;
    }

    public function save()
    {
        $setting = Setting::find($this->editingId);
        $setting->update(['value' => $this->editingValue]);
        
        $this->editingId = null;
        $this->loadSettings();
        
        session()->flash('success', 'Setting updated successfully.');
    }

    public function cancel()
    {
        $this->editingId = null;
        $this->editingValue = '';
    }

    public function confirmDelete($id)
    {
        $setting = Setting::find($id);
        $this->deletingKey = $setting->key;
        $this->confirmingDelete = true;
    }

    public function delete()
    {
        Setting::where('key', $this->deletingKey)->delete();
        $this->confirmingDelete = false;
        $this->deletingKey = '';
        $this->loadSettings();
        
        session()->flash('success', 'Setting deleted successfully.');
    }

    public function cancelDelete()
    {
        $this->confirmingDelete = false;
        $this->deletingKey = '';
    }

    public function reset($key)
    {
        Setting::where('key', $key)->delete();
        $this->loadSettings();
        
        session()->flash('success', 'Setting reset successfully.');
    }

    public function startCreate()
    {
        $this->creating = true;
    }

    public function create()
    {
        $this->validate();
        
        Setting::create([
            'key' => $this->newSetting['key'],
            'value' => $this->newSetting['value'],
            'group' => $this->newSetting['group'],
            'type' => 'string',
            'display_name' => ucfirst(str_replace('.', ' ', $this->newSetting['key'])),
        ]);
        
        $this->creating = false;
        $this->newSetting = ['key' => '', 'value' => '', 'group' => 'general'];
        $this->loadSettings();
        
        session()->flash('success', 'Setting created successfully.');
    }

    public function cancelCreate()
    {
        $this->creating = false;
        $this->newSetting = ['key' => '', 'value' => '', 'group' => 'general'];
    }

    public function render()
    {
        return view('livewire.settings-form', [
            'availableGroups' => Setting::distinct()->pluck('group'),
        ]);
    }
}
```

This provides all the test IDs and component methods needed for comprehensive browser testing with Playwright.
