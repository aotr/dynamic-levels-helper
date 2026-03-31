<div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Settings Manager</h1>
            <p class="text-gray-600 mt-1">Manage your application settings in one place</p>
        </div>
        <div class="flex gap-2">
            <button class="px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition"
                    wire:click="$toggle('showCreateForm')">
                ➕ New Setting
            </button>
        </div>
    </div>

    <!-- Search & Filter -->
    <div class="bg-white rounded-lg shadow-sm p-4 space-y-4">
        <div class="flex gap-4 flex-wrap">
            <!-- Search Box -->
            <div class="flex-1 min-w-64">
                <label class="block text-sm font-medium text-gray-700 mb-2">Search Settings</label>
                <input type="text" wire:model.live="search" placeholder="Find settings by key or name..."
                       class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Group Filter -->
            <div class="flex-1 min-w-64">
                <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Group</label>
                <select wire:model.live="group" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Groups</option>
                    @foreach($groups as $g)
                        <option value="{{ $g }}">{{ ucfirst($g) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <!-- Settings Table -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        @if(count($settings) > 0)
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Key</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Display Name</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Group</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Type</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Value</th>
                            <th class="px-6 py-3 text-right text-sm font-semibold text-gray-900">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($settings as $setting)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm font-mono text-gray-900">{{ $setting['key'] }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600">{{ $setting['display_name'] }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="px-2 py-1 bg-blue-50 text-blue-700 rounded text-xs font-medium">
                                        {{ ucfirst($setting['group']) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    <code class="bg-gray-100 px-2 py-1 rounded text-xs">{{ $setting['type'] }}</code>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    @if($editingKey === $setting['key'])
                                        <!-- Edit Mode -->
                                        <div class="flex gap-2 items-center">
                                            @if($setting['type'] === 'boolean')
                                                <select wire:model="formData.value" class="px-2 py-1 border border-gray-200 rounded text-sm">
                                                    <option value="0">False</option>
                                                    <option value="1">True</option>
                                                </select>
                                            @elseif($setting['type'] === 'select')
                                                <select wire:model="formData.value" class="px-2 py-1 border border-gray-200 rounded text-sm">
                                                    @foreach($setting['meta']['options'] ?? [] as $opt)
                                                        <option value="{{ $opt }}">{{ $opt }}</option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <input type="text" wire:model="formData.value" class="px-2 py-1 border border-gray-200 rounded text-sm flex-1">
                                            @endif
                                        </div>
                                    @else
                                        <!-- View Mode -->
                                        <code class="text-gray-700 truncate max-w-xs inline-block">
                                            {{ is_array($setting['value']) ? json_encode($setting['value']) : ($setting['value'] ?? '(null)') }}
                                        </code>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right text-sm space-x-2">
                                    @if($editingKey === $setting['key'])
                                        <button wire:click="saveSetting('{{ $setting['key'] }}')" class="text-green-600 hover:text-green-800 font-medium">💾 Save</button>
                                        <button wire:click="$set('editingKey', null)" class="text-gray-600 hover:text-gray-800">✕ Cancel</button>
                                    @else
                                        <button wire:click="editSetting('{{ $setting['key'] }}')" class="text-blue-600 hover:text-blue-800">✎ Edit</button>
                                        <button wire:click="resetSetting('{{ $setting['key'] }}')" class="text-amber-600 hover:text-amber-800">↺ Reset</button>
                                        <button wire:click="deleteSetting('{{ $setting['key'] }}')" class="text-red-600 hover:text-red-800">🗑 Delete</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="px-6 py-12 text-center">
                <p class="text-gray-500">No settings found</p>
            </div>
        @endif
    </div>

    <!-- Responsive Info -->
    <div class="text-sm text-gray-500">
        Showing {{ count($settings) }} setting(s)
        @if($group) in group "{{ $group }}" @endif
        @if($search) matching "{{ $search }}" @endif
    </div>
</div>

@script
<script>
    $wire.on('notify', ({ message }) => {
        // Show toast notification
        document.dispatchEvent(new CustomEvent('toast', { detail: { message } }));
    });
</script>
@endscript
