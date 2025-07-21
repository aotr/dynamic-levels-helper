<?php

declare(strict_types=1);

namespace Aotr\DynamicLevelHelper\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Parameter Service Facade
 *
 * @method static string process(array|\Illuminate\Http\Request|string $data, array|null $sequence = null, string $delimiter = '^^')
 * @method static string processSimple(array|\Illuminate\Http\Request $request, array $sequence = [])
 * @method static string quick(array|\Illuminate\Http\Request $request, array $sequence = [])
 * @method static string fromValues(...$values)
 * @method static array split(string $parameterString, string $delimiter = '^^')
 * @method static bool validateRequired(array|\Illuminate\Http\Request $request, array $requiredKeys)
 * @method static array getMissingRequired(array|\Illuminate\Http\Request $request, array $requiredKeys)
 *
 * @see \Aotr\DynamicLevelHelper\Services\ParameterService
 */
class ParameterService extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'parameter-service';
    }
}
