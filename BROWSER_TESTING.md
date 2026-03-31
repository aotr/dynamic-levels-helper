# Browser Testing Guide

Complete guide for testing the dynamic-levels-helper UI components in a browser using Playwright.

## Overview

This package includes:
- **Automated Browser Testing** with Playwright
- **Livewire Component Testing**
- **Responsive Design Testing** (mobile, tablet, desktop)
- **Accessibility Testing**
- **Performance Testing**

## Prerequisites

### For Your Laravel Application

1. **Laravel 10+** with Livewire installed
2. **PHP 8.1+**
3. **Node.js 16+** and npm/yarn

### Installation

```bash
# Install the package in your Laravel app
composer require aotr/dynamic-levels-helper

# Publish assets and configuration
php artisan vendor:publish --provider="Aotr\DynamicLevelHelper\Providers\DynamicLevelHelperServiceProvider"
```

## Quick Start - Browser Testing

### 1. Install Playwright

```bash
npm install
# or
yarn install
```

### 2. Configure Your Laravel App

Create a test route or page that displays the SettingsForm component. For example:

**routes/web.php:**
```php
Route::get('/settings', function () {
    return view('settings.index');
});
```

**resources/views/settings/index.blade.php:**
```blade
<x-app-layout>
    <div class="container">
        @livewire('settings-form')
    </div>
</x-app-layout>
```

### 3. Update Test URLs

Edit `playwright.config.ts` if your routes differ:

```typescript
use: {
    baseURL: process.env.BASE_URL || 'http://localhost:8000',
    // ...
}
```

### 4. Run Tests

```bash
# Run all tests
npm test

# Run tests with UI (interactive)
npm run test:ui

# Run tests in headed mode (see browser)
npm run test:headed

# Run specific browser
npm run test:chrome
npm run test:firefox
npm run test:webkit

# Mobile viewport testing
npm run test:mobile

# Debug a test
npm run test:debug
```

## Test HTML Attributes

For the browser tests to work, ensure your Livewire components include test IDs. Update your SettingsForm view:

```blade
<!-- Form container -->
<div data-testid="settings-form" class="settings-form">

    <!-- List -->
    <div data-testid="settings-list" class="settings-list">
        @foreach($settings as $setting)
            <div data-testid="setting-row" 
                 data-group="{{ $setting->group }}"
                 data-type="{{ $setting->type }}"
                 class="setting-row">
                
                <span class="setting-key">{{ $setting->key }}</span>
                
                <!-- Input by type -->
                @if($setting->type === 'boolean')
                    <input type="checkbox" 
                           data-testid="value-input"
                           wire:model="settings.{{ $setting->key }}"
                           value="{{ $setting->value }}">
                @elseif($setting->type === 'number')
                    <input type="number"
                           data-testid="value-input"
                           wire:model="settings.{{ $setting->key }}"
                           value="{{ $setting->value }}">
                @else
                    <input type="text"
                           data-testid="value-input"
                           wire:model="settings.{{ $setting->key }}"
                           value="{{ $setting->value }}">
                @endif
                
                <!-- Actions -->
                <button data-testid="edit-button" wire:click="edit({{ $setting->id }})">Edit</button>
                <button data-testid="delete-button" wire:click="delete({{ $setting->id }})">Delete</button>
                <button data-testid="reset-button" wire:click="reset({{ $setting->key }})">Reset</button>
            </div>
        @endforeach
    </div>

    <!-- Filters -->
    <div data-testid="filters" class="filters">
        <select data-testid="group-filter" wire:model="filters.group">
            <option value="">All Groups</option>
            @foreach($groups as $group)
                <option data-testid="group-option-{{ $group }}" value="{{ $group }}">
                    {{ ucfirst($group) }}
                </option>
            @endforeach
        </select>
        
        <input type="text"
               data-testid="search-input"
               wire:model.debounce="filters.search"
               placeholder="Search settings...">
    </div>

    <!-- Create/Edit Forms -->
    @if($editingId)
        <div data-testid="edit-form" class="edit-form">
            <!-- Form fields -->
            <input type="text" 
                   data-testid="value-input"
                   wire:model="editingValue">
            
            <button data-testid="save-button" wire:click="save">Save</button>
            <button data-testid="cancel-button" wire:click="cancel">Cancel</button>
        </div>
    @endif

    @if($creating)
        <div data-testid="create-form" class="create-form">
            <!-- Form fields -->
            <input type="text" 
                   data-testid="value-input"
                   wire:model="newValue">
            
            <button data-testid="save-button" wire:click="create">Create</button>
            <button data-testid="cancel-button" wire:click="cancelCreate">Cancel</button>
        </div>
    @endif

    <!-- Create button -->
    <button data-testid="create-setting-button" wire:click="startCreate">
        Create New Setting
    </button>

    <!-- Empty state -->
    @if($settings->isEmpty())
        <div data-testid="empty-state" class="empty-state">
            <p>No settings found. Create one to get started.</p>
        </div>
    @endif

    <!-- Loading state -->
    @if($loading)
        <div data-testid="loading" role="status">
            <p>Loading settings...</p>
        </div>
    @endif
</div>
```

## Manual Testing in Browser

If you prefer manual testing:

### 1. Start your Laravel server

```bash
php artisan serve
```

### 2. Start the Livewire development server (if using Livewire assets from CDN)

If using local Livewire assets:
```bash
npm run dev
```

### 3. Open in browser

Visit: `http://localhost:8000/settings`

### 4. Test the following features

- **Creating Settings**: Click "Create" button, fill form, verify it appears
- **Editing Settings**: Click "Edit", modify value, save and verify
- **Deleting Settings**: Click "Delete", confirm, verify removal
- **Filtering**: Use group filter dropdown, verify list updates
- **Searching**: Type in search box, verify filtering works
- **Type Display**: Create settings of different types (boolean, string, number), verify correct inputs
- **Validation**: Try invalid data, verify errors
- **Responsive**: Resize window to test mobile, tablet, desktop layouts
- **Accessibility**: Use keyboard navigation (Tab, Enter, Escape)

## Integration in Your App

### Update Livewire Component

Ensure your SettingsForm Livewire component includes:

```php
<?php

namespace App\Livewire;

use Livewire\Component;
use Aotr\DynamicLevelHelper\Models\Setting;

class SettingsForm extends Component
{
    public $settings;
    public $filters = ['group' => '', 'search' => ''];
    public $editingId = null;
    public $editingValue = '';
    public $creating = false;
    public $newValue = '';
    public $loading = false;

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

    public function edit($id)
    {
        $setting = Setting::find($id);
        $this->editingId = $id;
        $this->editingValue = $setting->value;
    }

    public function save()
    {
        $setting = Setting::find($this->editingId);
        $setting->update(['value' => $this->editingValue]);
        
        $this->editingId = null;
        $this->editingValue = '';
        $this->loadSettings();
    }

    public function cancel()
    {
        $this->editingId = null;
        $this->editingValue = '';
    }

    public function delete($id)
    {
        Setting::find($id)->delete();
        $this->loadSettings();
    }

    public function reset($key)
    {
        // Reset to default logic here
        $this->loadSettings();
    }

    public function startCreate()
    {
        $this->creating = true;
    }

    public function create()
    {
        Setting::create([
            'key' => $this->newValue,
            'value' => '',
            'type' => 'string',
            'group' => 'general',
        ]);
        
        $this->creating = false;
        $this->newValue = '';
        $this->loadSettings();
    }

    public function cancelCreate()
    {
        $this->creating = false;
        $this->newValue = '';
    }

    #[\Livewire\Attributes\On('setting-updated')]
    public function onSettingUpdated()
    {
        $this->loadSettings();
    }

    public function render()
    {
        return view('livewire.settings-form');
    }
}
```

## Debugging Tests

### 1. Use Playwright Inspector

```bash
npm run test:debug
```

This opens an interactive inspector where you can:
- Step through tests
- Inspect DOM elements
- Modify the test on the fly

### 2. View Test Report

```bash
npm run test:report
```

Opens an HTML report of test runs with:
- Screenshots on failure
- Video recordings
- Detailed error messages

### 3. Run Single Test

```bash
npx playwright test tests/Browser/settings-form.spec.ts -g "should render"
```

### 4. Codegen New Tests

Playwright has a built-in recorder:

```bash
npm run codegen
```

This opens a browser where you can interact with your app, and Playwright records the test code automatically.

## CI/CD Integration

### GitHub Actions Example

```yaml
name: E2E Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_DATABASE: test
          MYSQL_ROOT_PASSWORD: root
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=3

    steps:
      - uses: actions/checkout@v3
      
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      
      - uses: actions/setup-node@v3
        with:
          node-version: '18'
      
      - name: Install PHP dependencies
        run: composer install
      
      - name: Install Node dependencies
        run: npm install
      
      - name: Install Playwright browsers
        run: npx playwright install --with-deps
      
      - name: Setup test database
        run: php artisan migrate:fresh
      
      - name: Run browser tests
        run: npm test
      
      - name: Upload test report
        if: always()
        uses: actions/upload-artifact@v3
        with:
          name: playwright-report
          path: playwright-report/
```

## Browser Coverage

Tests run across:
- **Desktop**: Chrome, Firefox, Safari
- **Mobile**: Pixel 5 (Android), iPhone 12 (iOS)
- **Viewports**: 320px to 1920px widths

## Best Practices

1. **Use test IDs**: Use `data-testid` attributes instead of selectors
2. **Wait for content**: Don't assume instant loads, use proper waits
3. **Test user flows**: Test complete workflows, not just individual clicks
4. **Mobile first**: Design responsive layouts with mobile constraints
5. **Accessibility**: Always test keyboard navigation and screen readers
6. **Keep tests isolated**: Each test should be independent
7. **Use fixtures**: Pre-seed test data consistently
8. **Monitor performance**: Track Core Web Vitals in tests

## Troubleshooting

### Tests timeout connecting to localhost

```bash
# Ensure Laravel server is running
php artisan serve

# In another terminal, run tests
npm test
```

### Elements not found in tests

1. Check that `data-testid` attributes are in your Blade templates
2. Increase timeout: `await page.waitForSelector('[data-testid="foo"]', { timeout: 5000 })`
3. Use Playwright Inspector: `npm run test:debug`

### Flaky tests

- Use `page.waitForURL()` instead of `page.goto()` for navigation
- Use `expect(element).toBeVisible()` instead of checking visibility with JS
- Avoid fixed delays; use proper wait conditions

### Database state issues

- Use `php artisan migrate:fresh` before tests
- Reset the database in test setup
- Use transactions and rollback per test

## Next Steps

1. ✅ Update your Livewire component with test IDs
2. ✅ Create test routes in your app
3. ✅ Run: `npm install && npm test`
4. ✅ Fix any failing tests
5. ✅ Add to CI/CD pipeline
6. ✅ Run tests locally before commits

For more info: https://playwright.dev
