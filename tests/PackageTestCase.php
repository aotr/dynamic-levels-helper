<?php

declare(strict_types=1);

namespace Aotr\DynamicLevelHelper\Tests;

use Aotr\DynamicLevelHelper\Providers\PackageServiceProvider;
use Orchestra\Testbench\TestCase;

class PackageTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            PackageServiceProvider::class,
        ];
    }
}
