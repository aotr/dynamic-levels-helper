<?php

declare(strict_types=1);

namespace Aotr\DynamicLevelHelper\Providers;

use Aotr\DynamicLevelHelper\Services\EnhancedDBService;
use Illuminate\Support\ServiceProvider;

class EnhancedDBServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register EnhancedDBService as singleton
        $this->app->singleton(EnhancedDBService::class, function ($app) {
            return EnhancedDBService::getInstance();
        });

        // Register with alias for facade
        $this->app->singleton('enhanced.db.service', function ($app) {
            return $app->make(EnhancedDBService::class);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register graceful shutdown handler
        if ($this->app->runningInConsole()) {
            $this->registerShutdownHandler();
        }
    }

    /**
     * Register graceful shutdown handler for connection pool cleanup
     */
    private function registerShutdownHandler(): void
    {
        register_shutdown_function(function () {
            if ($this->app->bound(EnhancedDBService::class)) {
                try {
                    $service = $this->app->make(EnhancedDBService::class);
                    // The destructor will handle cleanup automatically
                } catch (\Exception $e) {
                    // Silently handle any shutdown errors
                }
            }
        });
    }
}
