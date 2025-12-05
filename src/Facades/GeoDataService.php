<?php

declare(strict_types=1);

namespace Aotr\DynamicLevelHelper\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Geo Data Service Facade
 *
 * @method static void sync()
 * @method static array|null getCountries()
 * @method static array|null getCountriesWithStates()
 * @method static array|null getCities()
 * @method static array|null getCountriesWithCities()
 * @method static array|null getCountriesStatesCities()
 * @method static array|null getRegions()
 * @method static array|null getStatesWithCities()
 * @method static array|null getStates()
 * @method static array|null getSubregions()
 *
 * @see \Aotr\DynamicLevelHelper\Services\GeoDataService
 */
class GeoDataService extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'geo-data-service';
    }
}
