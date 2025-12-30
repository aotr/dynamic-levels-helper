<?php

declare(strict_types=1);

namespace Aotr\DynamicLevelHelper\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * ToonService Facade
 * 
 * @method static array getThermostatInfo()
 * @method static array setTemperature(float $temperature)
 * @method static array getEnergyUsage()
 * @method static array getEnergyUsageByPeriod(string $period)
 * @method static array getAgreements()
 * @method static array getDeviceInfo()
 * @method static \Illuminate\Support\Collection getAllDevicesStatus()
 * @method static array setProgram(int $programId)
 * @method static array getCurrentProgram()
 * @method static \Illuminate\Support\Collection getPrograms()
 * @method static array getSmartPlugData(string $deviceId)
 * @method static array setSmartPlugState(string $deviceId, bool $state)
 * @method static array getSolarData()
 * @method static array getGasUsage()
 * @method static bool isAvailable()
 * @method static array getDashboardData()
 * 
 * @see \Aotr\DynamicLevelHelper\Services\ToonService
 */
class ToonService extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'toon-service';
    }
}