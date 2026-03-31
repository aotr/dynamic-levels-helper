<?php

use Aotr\DynamicLevelsHelper\Services\SettingsService;

if (!function_exists('settings')) {
    /**
     * Get or set a settings value
     *
     * Usage:
     *   settings('key')                              // Get value
     *   settings('key', 'default')                   // Get with default
     *   settings('key', 'value', 'User:1')          // Set with scope
     *   settings()                                   // Get all settings
     *   settings(scope: 'User:1')                   // Get all for scope
     *
     * @param string|null $key
     * @param mixed $default
     * @param string|null $scope
     * @return mixed
     */
    function settings($key = null, $default = null, $scope = null)
    {
        $service = app(SettingsService::class);

        if (is_null($key)) {
            return $service->all($scope);
        }

        return $service->get($key, $default, $scope);
    }
}
