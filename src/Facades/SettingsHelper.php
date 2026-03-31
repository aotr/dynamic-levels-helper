<?php

namespace Aotr\DynamicLevelsHelper\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed get(string $key, mixed $default = null, string|null $scope = null)
 * @method static void set(string $key, mixed $value, string|null $scope = null)
 * @method static array all(string|null $scope = null)
 * @method static \Illuminate\Support\Collection group(string $group, string|null $scope = null)
 * @method static void warm()
 * @method static void flush()
 * @method static void invalidate(string|null $scope = null)
 * @method static \Illuminate\Database\Eloquent\Collection getHistory(string $key, int $limit = 50)
 * @method static \Illuminate\Database\Eloquent\Collection getAuditLog(array $filters = [], int $limit = 100)
 * @method static int pruneAuditLogs(int $days = 90)
 */
class SettingsHelper extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Aotr\DynamicLevelsHelper\Services\SettingsService::class;
    }
}
