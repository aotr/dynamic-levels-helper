<?php

declare(strict_types=1);

namespace Aotr\DynamicLevelHelper\Tests;

use Aotr\DynamicLevelHelper\Providers\DynamicLevelHelperServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;

class PackageTestCase extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            DynamicLevelHelperServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Use SQLite database for tests
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Ensure migrations are run during tests
        $app['config']->set('app.url', 'http://localhost');
    }

    protected function defineDatabaseMigrations(): void
    {
        // Run all migrations from the package
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
