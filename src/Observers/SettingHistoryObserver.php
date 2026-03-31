<?php

namespace Aotr\DynamicLevelHelper\Observers;

use Aotr\DynamicLevelHelper\Models\Setting;
use Aotr\DynamicLevelHelper\Models\SettingAuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class SettingHistoryObserver
{
    /**
     * Handle the Setting "created" event.
     */
    public function created(Setting $setting)
    {
        $this->logChange($setting, 'created', null, $setting->value);
    }

    /**
     * Handle the Setting "updated" event.
     */
    public function updated(Setting $setting)
    {
        $original = $setting->getOriginal('value');

        // Only log if value actually changed
        if ($original !== $setting->value) {
            $this->logChange($setting, 'updated', $original, $setting->value);
        }
    }

    /**
     * Handle the Setting "deleted" event.
     */
    public function deleted(Setting $setting)
    {
        $this->logChange($setting, 'deleted', $setting->value, null);
    }

    /**
     * Log a setting change to the audit log.
     */
    protected function logChange(Setting $setting, string $action, $oldValue, $newValue)
    {
        try {
            $user = Auth::user();

            SettingAuditLog::create([
                'key' => $setting->key,
                'group' => $setting->group,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'auditable_type' => $user ? class_basename($user) : null,
                'auditable_id' => $user?->id,
                'action' => $action,
                'reason' => request('settings_change_reason'),
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ]);
        } catch (\Exception $e) {
            // Log silently to prevent audit logging from breaking the app
            report($e);
        }
    }
}
