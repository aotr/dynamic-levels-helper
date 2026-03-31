<?php

namespace Aotr\DynamicLevelHelper\Observers;

use Aotr\DynamicLevelHelper\Models\Setting;
use Aotr\DynamicLevelHelper\Services\SettingsService;

class SettingObserver
{
    public function updated(Setting $setting)
    {
        app(SettingsService::class)->invalidate($this->resolveScope($setting));
    }

    public function created(Setting $setting)
    {
        app(SettingsService::class)->invalidate($this->resolveScope($setting));
    }

    public function deleted(Setting $setting)
    {
        app(SettingsService::class)->invalidate($this->resolveScope($setting));
    }

    protected function resolveScope(Setting $setting)
    {
        if ($setting->scope_type && $setting->scope_id) {
            return "{$setting->scope_type}:{$setting->scope_id}";
        }
        return null;
    }
}
