<?php

namespace Aotr\DynamicLevelHelper\Providers;

use Aotr\DynamicLevelHelper\Services\EnhancedDBService;
use Illuminate\Support\ServiceProvider;

/**
 * Enhanced Database Service Provider
 *
 * Registers the EnhancedDBService as a singleton in the Laravel container
 */
class EnhancedDBServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        // Register EnhancedDBService as singleton
        $this->app->singleton(EnhancedDBService::class, function ($app) {
            return EnhancedDBService::getInstance();
        });

        // Create alias for easier access
        $this->app->alias(EnhancedDBService::class, 'enhanced.db.service');
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Register graceful shutdown to clean up connections
        if (function_exists('register_shutdown_function')) {
            register_shutdown_function(function () {
                if ($this->app->bound(EnhancedDBService::class)) {
                    EnhancedDBService::resetInstance();
                }
            });
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return [
            EnhancedDBService::class,
            'enhanced.db.service',
        ];
    }
}
