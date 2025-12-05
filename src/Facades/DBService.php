<?php

namespace Aotr\DynamicLevelHelper\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Enhanced Database Service Facade
 *
 * @method static array callStoredProcedure(string $storedProcedureName, array $parameters = [], array $options = [])
 * @method static array getConnectionPoolStats()
 * @method static array getPerformanceMetrics()
 * @method static void clearPerformanceMetrics()
 * @method static void resetInstance()
 *
 * @see \Aotr\DynamicLevelHelper\Services\EnhancedDBService
 */
class EnhancedDBService extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'enhanced.db.service';
    }
}
