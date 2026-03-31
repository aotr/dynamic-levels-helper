# Settings Management UI — Tailwind + Livewire + BasicAuth

**Purpose**: Provide optional Tailwind-styled UI component for managing settings, protected by BasicAuth middleware.

---

## Package Configuration

**File**: `config/dynamic-levels-helper.php`

Add settings UI configuration options:

```php
return [
    // ... existing config ...

    // Settings Management UI (Optional)
    'settings_ui' => [
        'enabled' => env('SETTINGS_UI_ENABLED', false), // Opt-in feature
        'route_prefix' => 'admin/settings',
        'middleware' => ['auth:sanctum', Aotr\DynamicLevelHelper\Middleware\BasicAuth::class],
        'tailwind_theme' => 'dark', // 'light' or 'dark'
    ],

    // BasicAuth credentials for Settings UI access
    'settings_ui_username' => env('SETTINGS_UI_USERNAME', 'admin'),
    'settings_ui_password' => env('SETTINGS_UI_PASSWORD', 'password'),
];
```

**Usage in `.env`**:

```bash
# Enable Settings UI
SETTINGS_UI_ENABLED=true

# BasicAuth credentials
SETTINGS_UI_USERNAME=admin
SETTINGS_UI_PASSWORD=your_secure_password
```

---

## Livewire Component: Settings Manager

**File**: `app/Http/Livewire/SettingsManager.php`

```php
<?php

namespace App\Http\Livewire;

use App\Models\Setting;
use App\Models\SettingAuditLog;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;

class SettingsManager extends Component
{
    use WithPagination;

    public ?string $searchKey = null;
    public ?string $filterGroup = null;
    public string $sortBy = 'key';
    public string $sortOrder = 'asc';

    // Modal states
    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public bool $showHistoryModal = false;
    public ?int $editingSettingId = null;
    public ?int $historySettingId = null;

    // Form data
    public string $formKey = '';
    public string $formGroup = '';
    public string $formDisplayName = '';
    public $formValue = '';
    public string $formType = 'text';
    public string $formReason = '';
    public ?array $formMeta = null;

    // UI state
    public int $perPage = 15;
    public array $expandedRows = [];

    protected $rules = [
        'formKey' => 'required|string|min:1',
        'formGroup' => 'required|string',
        'formDisplayName' => 'required|string',
        'formValue' => 'required',
        'formType' => 'required|in:text,integer,boolean,json,select,textarea',
    ];

    public function mount()
    {
        if (!config('dynamic-levels-helper.settings_ui.enabled')) {
            abort(404);
        }
    }

    public function render()
    {
        $query = Setting::query();

        // Search
        if ($this->searchKey) {
            $query->where('key', 'like', "%{$this->searchKey}%")
                ->orWhere('display_name', 'like', "%{$this->searchKey}%");
        }

        // Filter by group
        if ($this->filterGroup) {
            $query->where('group', $this->filterGroup);
        }

        // Sort
        $query->orderBy($this->sortBy, $this->sortOrder);

        // Paginate
        $settings = $query->paginate($this->perPage);

        // Get available groups
        $groups = Setting::distinct('group')->pluck('group');

        // Get history data if modal open
        $history = null;
        if ($this->historySettingId) {
            $setting = Setting::find($this->historySettingId);
            if ($setting) {
                $history = SettingAuditLog::forKey($setting->key)
                    ->orderBy('changed_at', 'desc')
                    ->limit(10)
                    ->get();
            }
        }

        return view('livewire.settings-manager', [
            'settings' => $settings,
            'groups' => $groups,
            'history' => $history,
        ]);
    }

    /**
     * Open create modal
     */
    public function openCreateModal()
    {
        $this->reset(['formKey', 'formGroup', 'formDisplayName', 'formValue', 'formType', 'formReason']);
        $this->showCreateModal = true;
    }

    /**
     * Create new setting
     */
    public function createSetting()
    {
        $this->validate();

        try {
            Setting::create([
                'key' => $this->formKey,
                'group' => $this->formGroup,
                'display_name' => $this->formDisplayName,
                'value' => $this->castValue($this->formValue, $this->formType),
                'type' => $this->formType,
                'meta' => $this->formMeta,
            ]);

            // Log in history
            SettingAuditLog::create([
                'setting_key' => $this->formKey,
                'group' => $this->formGroup,
                'action' => 'created',
                'old_value' => null,
                'new_value' => $this->formValue,
                'type' => $this->formType,
                'user_id' => Auth::id(),
                'user_name' => Auth::check() ? Auth::user()->name : 'System',
                'reason' => $this->formReason ?: 'Created via UI',
                'ip_address' => request()->ip(),
            ]);

            $this->dispatch('notify', message: 'Setting created successfully', type: 'success');
            $this->showCreateModal = false;
            $this->resetPage();
        } catch (\Exception $e) {
            $this->dispatch('notify', message: 'Error: ' . $e->getMessage(), type: 'error');
        }
    }

    /**
     * Open edit modal
     */
    public function openEditModal(int $settingId)
    {
        $setting = Setting::find($settingId);
        if (!$setting) {
            return;
        }

        $this->editingSettingId = $settingId;
        $this->formKey = $setting->key;
        $this->formGroup = $setting->group;
        $this->formDisplayName = $setting->display_name;
        $this->formValue = $setting->value;
        $this->formType = $setting->type;
        $this->formMeta = $setting->meta;
        $this->formReason = '';

        $this->showEditModal = true;
    }

    /**
     * Update setting
     */
    public function updateSetting()
    {
        $this->validate();

        try {
            $setting = Setting::find($this->editingSettingId);
            if (!$setting) {
                $this->dispatch('notify', message: 'Setting not found', type: 'error');
                return;
            }

            $oldValue = $setting->value;
            $newValue = $this->castValue($this->formValue, $this->formType);

            // Update setting
            $setting->update([
                'display_name' => $this->formDisplayName,
                'value' => $newValue,
                'type' => $this->formType,
                'meta' => $this->formMeta,
            ]);

            // Log in history
            SettingAuditLog::create([
                'setting_key' => $setting->key,
                'group' => $setting->group,
                'action' => 'updated',
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'type' => $this->formType,
                'user_id' => Auth::id(),
                'user_name' => Auth::check() ? Auth::user()->name : 'System',
                'reason' => $this->formReason ?: 'Updated via UI',
                'ip_address' => request()->ip(),
            ]);

            $this->dispatch('notify', message: 'Setting updated successfully', type: 'success');
            $this->showEditModal = false;
        } catch (\Exception $e) {
            $this->dispatch('notify', message: 'Error: ' . $e->getMessage(), type: 'error');
        }
    }

    /**
     * Delete setting
     */
    public function deleteSetting(int $settingId)
    {
        try {
            $setting = Setting::find($settingId);
            if (!$setting) {
                return;
            }

            // Log deletion
            SettingAuditLog::create([
                'setting_key' => $setting->key,
                'group' => $setting->group,
                'action' => 'deleted',
                'old_value' => $setting->value,
                'new_value' => null,
                'type' => $setting->type,
                'user_id' => Auth::id(),
                'user_name' => Auth::check() ? Auth::user()->name : 'System',
                'reason' => 'Deleted via UI',
                'ip_address' => request()->ip(),
            ]);

            $setting->delete();
            $this->dispatch('notify', message: 'Setting deleted successfully', type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('notify', message: 'Error: ' . $e->getMessage(), type: 'error');
        }
    }

    /**
     * Show history modal
     */
    public function showHistory(int $settingId)
    {
        $this->historySettingId = $settingId;
        $this->showHistoryModal = true;
    }

    /**
     * Close history modal
     */
    public function closeHistoryModal()
    {
        $this->historySettingId = null;
        $this->showHistoryModal = false;
    }

    /**
     * Sort by column
     */
    public function sortBy(string $column)
    {
        if ($this->sortBy === $column) {
            $this->sortOrder = $this->sortOrder === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortOrder = 'asc';
        }
        $this->resetPage();
    }

    /**
     * Toggle row expansion
     */
    public function toggleRow(int $settingId)
    {
        if (in_array($settingId, $this->expandedRows)) {
            $this->expandedRows = array_filter(
                $this->expandedRows,
                fn($id) => $id !== $settingId
            );
        } else {
            $this->expandedRows[] = $settingId;
        }
    }

    /**
     * Cast value to correct type
     */
    protected function castValue($value, string $type)
    {
        return match ($type) {
            'boolean' => in_array($value, [true, 'true', '1', 1], true),
            'integer' => (int) $value,
            'json' => is_array($value) ? $value : json_decode($value, true),
            default => $value,
        };
    }
}
```

---

## Blade Template: Settings Manager UI

**File**: `resources/views/livewire/settings-manager.blade.php`

```blade
<div class="min-h-screen bg-gray-50 dark:bg-gray-900 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                        ⚙️ Settings Manager
                    </h1>
                    <p class="mt-2 text-gray-600 dark:text-gray-400">
                        Manage application settings with audit trail
                    </p>
                </div>
                <button
                    wire:click="openCreateModal"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                >
                    + New Setting
                </button>
            </div>
        </div>

        <!-- Search & Filter -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Search -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Search Settings
                    </label>
                    <input
                        type="text"
                        wire:model.live="searchKey"
                        placeholder="Search by key or name..."
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>

                <!-- Filter by Group -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Filter Group
                    </label>
                    <select
                        wire:model.live="filterGroup"
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">All Groups</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group }}">{{ ucfirst($group) }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Per Page -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Per Page
                    </label>
                    <select
                        wire:model.live="perPage"
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="10">10</option>
                        <option value="15">15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Settings Table -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-100 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-600" wire:click="sortBy('key')">
                            Key
                            @if ($sortBy === 'key')
                                <span>{{ $sortOrder === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-600" wire:click="sortBy('group')">
                            Group
                            @if ($sortBy === 'group')
                                <span>{{ $sortOrder === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                            Value
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                            Type
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($settings as $setting)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                {{ $setting->key }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                <span class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-xs font-medium">
                                    {{ $setting->group }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                                <code class="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded text-xs">
                                    {{ Str::limit((string) $setting->value, 30) }}
                                </code>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                <span class="px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded-full text-xs font-medium">
                                    {{ $setting->type }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm space-x-2">
                                <button
                                    wire:click="openEditModal({{ $setting->id }})"
                                    class="px-3 py-1 bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 rounded hover:bg-yellow-200 transition text-xs font-medium"
                                >
                                    ✏️ Edit
                                </button>
                                <button
                                    wire:click="showHistory({{ $setting->id }})"
                                    class="px-3 py-1 bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200 rounded hover:bg-purple-200 transition text-xs font-medium"
                                >
                                    📜 History
                                </button>
                                <button
                                    wire:click="deleteSetting({{ $setting->id }})"
                                    wire:confirm="Are you sure you want to delete this setting?"
                                    class="px-3 py-1 bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 rounded hover:bg-red-200 transition text-xs font-medium"
                                >
                                    🗑️ Delete
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                No settings found
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="bg-white dark:bg-gray-800 px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                {{ $settings->links() }}
            </div>
        </div>
    </div>

    <!-- Create Modal -->
    @if ($showCreateModal)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Create Setting</h2>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Key</label>
                        <input
                            type="text"
                            wire:model="formKey"
                            placeholder="e.g., api.key"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        @error('formKey') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Group</label>
                        <input
                            type="text"
                            wire:model="formGroup"
                            placeholder="e.g., api"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Display Name</label>
                        <input
                            type="text"
                            wire:model="formDisplayName"
                            placeholder="e.g., API Key"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type</label>
                        <select
                            wire:model="formType"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="text">Text</option>
                            <option value="textarea">Textarea</option>
                            <option value="integer">Integer</option>
                            <option value="boolean">Boolean</option>
                            <option value="json">JSON</option>
                            <option value="select">Select</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Value</label>
                        @if ($formType === 'textarea')
                            <textarea
                                wire:model="formValue"
                                rows="4"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                            ></textarea>
                        @elseif ($formType === 'boolean')
                            <select
                                wire:model="formValue"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="">Select...</option>
                                <option value="true">True</option>
                                <option value="false">False</option>
                            </select>
                        @else
                            <input
                                type="text"
                                wire:model="formValue"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        @endif
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reason</label>
                        <input
                            type="text"
                            wire:model="formReason"
                            placeholder="Optional: Why this change?"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button
                        wire:click="$set('showCreateModal', false)"
                        class="px-4 py-2 text-gray-700 dark:text-gray-300 bg-gray-200 dark:bg-gray-700 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition"
                    >
                        Cancel
                    </button>
                    <button
                        wire:click="createSetting"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                    >
                        Create
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Edit Modal (Similar structure) -->
    @if ($showEditModal && $editingSettingId)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Edit Setting</h2>

                <div class="space-y-4">
                    <!-- Display name only (key is not editable) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Key</label>
                        <input
                            type="text"
                            value="{{ $formKey }}"
                            disabled
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white bg-gray-100 cursor-not-allowed"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Display Name</label>
                        <input
                            type="text"
                            wire:model="formDisplayName"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Value</label>
                        @if ($formType === 'textarea')
                            <textarea
                                wire:model="formValue"
                                rows="4"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                            ></textarea>
                        @elseif ($formType === 'boolean')
                            <select
                                wire:model="formValue"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="">Select...</option>
                                <option value="true">True</option>
                                <option value="false">False</option>
                            </select>
                        @else
                            <input
                                type="text"
                                wire:model="formValue"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        @endif
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reason</label>
                        <input
                            type="text"
                            wire:model="formReason"
                            placeholder="Why this change?"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button
                        wire:click="$set('showEditModal', false)"
                        class="px-4 py-2 text-gray-700 dark:text-gray-300 bg-gray-200 dark:bg-gray-700 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition"
                    >
                        Cancel
                    </button>
                    <button
                        wire:click="updateSetting"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                    >
                        Update
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- History Modal -->
    @if ($showHistoryModal && $history)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full p-6">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">📜 Setting History</h2>

                <div class="space-y-3 max-h-96 overflow-y-auto">
                    @forelse ($history as $log)
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <div class="flex justify-between items-start mb-2">
                                <span class="font-medium text-gray-900 dark:text-white">
                                    {{ $log->getActionLabel() }}
                                </span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $log->changed_at->format('Y-m-d H:i:s') }}
                                </span>
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                <p><strong>By:</strong> {{ $log->user_name ?? 'System' }}</p>
                                @if ($log->old_value)
                                    <p><strong>Before:</strong> <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded text-xs">{{ Str::limit($log->old_value, 40) }}</code></p>
                                @endif
                                @if ($log->new_value)
                                    <p><strong>After:</strong> <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded text-xs">{{ Str::limit($log->new_value, 40) }}</code></p>
                                @endif
                                @if ($log->reason)
                                    <p><strong>Reason:</strong> {{ $log->reason }}</p>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-center text-gray-500 dark:text-gray-400">No history found</p>
                    @endforelse
                </div>

                <div class="flex justify-end mt-6">
                    <button
                        wire:click="closeHistoryModal"
                        class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
```

---

## Routes & Configuration

**File**: `routes/web.php` (In your Laravel app)

```php
Route::middleware(config('dynamic-levels-helper.settings_ui.middleware'))
    ->prefix(config('dynamic-levels-helper.settings_ui.route_prefix'))
    ->group(function () {
        Route::get('/', fn() => view('settings'))->name('settings.index');
    });
```

**In Blade Layout** (`resources/views/settings.blade.php`):

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings Manager</title>
    @vite('resources/css/app.css')
    @livewireStyles
</head>
<body>
    @livewire('settings-manager')
    @livewireScripts
</body>
</html>
```

---

## Usage

### Access the UI

```
http://your-app.local/admin/settings
```

**BasicAuth Prompt**:
- Username: `admin` (from `SETTINGS_UI_USERNAME`)
- Password: Your password (from `SETTINGS_UI_PASSWORD`)

### Enable in `.env`

```bash
SETTINGS_UI_ENABLED=true
SETTINGS_UI_USERNAME=admin
SETTINGS_UI_PASSWORD=your_secure_password
```

### Features

✅ **Create Settings** — Full form validation  
✅ **Edit Settings** — With before/after audit trail  
✅ **Delete Settings** — With confirmation  
✅ **View History** — See all changes, who made them, when, and why  
✅ **Search & Filter** — Find settings by key, group  
✅ **Responsive Design** — Dark mode support with Tailwind CSS  
✅ **BasicAuth Protected** — Uses existing middleware  

---

## Optional: Publish Configuration

If you want users to customize settings UI config:

```bash
php artisan vendor:publish --tag=settings-ui-config
```

This creates `config/dynamic-levels-helper-settings-ui.php` for customization.

