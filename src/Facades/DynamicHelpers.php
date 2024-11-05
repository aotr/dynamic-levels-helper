<?php

namespace Aotr\DynamicLevelHelper\Facades;

use Illuminate\Support\Facades\Facade;

class DynamicHelpers extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'dynamic-helpers';
    }
}
