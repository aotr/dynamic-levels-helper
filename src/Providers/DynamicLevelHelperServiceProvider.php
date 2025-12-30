<?php

declare(strict_types=1);

namespace Aotr\DynamicLevelHelper\Providers;

use Aotr\DynamicLevelHelper\Console\Commands\DynamicLevelsMakeCommand;
use Aotr\DynamicLevelHelper\Console\Commands\EnhancedDBServiceCommand;
use Aotr\DynamicLevelHelper\Console\Commands\GeoDataScriptCommand;
use Aotr\DynamicLevelHelper\Console\Commands\LucideCacheCommand;
use Aotr\DynamicLevelHelper\Console\Commands\SyncCountriesAndStatesJsonFilesCommand;
use Aotr\DynamicLevelHelper\DynamicHelpersLoader;
use Aotr\DynamicLevelHelper\Services\LucideIconService;
use Aotr\DynamicLevelHelper\Services\ToonService;
use Aotr\DynamicLevelHelper\View\Components\Lucide\DynamicIcon;
use Illuminate\Support\Facades\Blade;
use Aotr\DynamicLevelHelper\Macros\ResponseMacros;
use Aotr\DynamicLevelHelper\Middleware\BasicAuth;
use Aotr\DynamicLevelHelper\Providers\EnhancedDBServiceProvider;
use Aotr\DynamicLevelHelper\Services\SMS\SmsProviderInterface;
use Aotr\DynamicLevelHelper\Services\SMS\SmsService;
use Illuminate\Support\ServiceProvider;

final class DynamicLevelHelperServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResponseMacros::register();
        app()->register(SmsServiceProvider::class);
        app()->register(EnhancedDBServiceProvider::class);

        $this->publishConfig();
        $this->registerMiddleware();
        $this->registerConsoleCommands();
        $this->registerBladeComponents();
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerHelpers();
        $this->registerFacade();
        $this->mergeConfig();
        $this->mergeLoggingConfig();
        $this->app->bind(SmsProviderInterface::class, SmsServiceProvider::class);
    }

    /**
     * Publishes the configuration file.
     */
    protected function publishConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../config/dynamic-levels-helper.php' => config_path('dynamic-levels-helper.php'),
        ], 'dynamic-levels-helper-config');
        $this->publishes([
            __DIR__ . '/../config/dynamic-levels-helper-stp.php' => config_path('dynamic-levels-helper-stp.php'),
        ], 'dynamic-levels-helper-stp-config');
        $this->publishes([
            __DIR__ . '/../config/dynamic-levels-helper-sms.php' => config_path('dynamic-levels-helper-sms.php'),
        ], 'dynamic-levels-helper-sms-config');

        $this->publishes([
            __DIR__ . '/../config/dynamic-levels-helper-whatsapp.php' => config_path('dynamic-levels-helper-whatsapp.php'),
        ], 'dynamic-levels-helper-whatsapp-config');

        // Publish the shell script for users with memory constraints
        $this->publishes([
            __DIR__ . '/../../scripts/sync-geo-data.sh' => base_path('sync-geo-data.sh'),
        ], 'dynamic-levels-helper-scripts');

        // Publish Lucide icon config
        $this->publishes([
            __DIR__ . '/../config/lucide.php' => config_path('lucide.php'),
        ], 'lucide-config');

        // Publish Toon config
        $this->publishes([
            __DIR__ . '/../config/toon.php' => config_path('toon.php'),
        ], 'toon-config');
    }

    /**
     * Registers the BasicAuth middleware.
     */
    protected function registerMiddleware(): void
    {
        $this->app['router']->aliasMiddleware('dynamic.basic.auth', BasicAuth::class);
    }

    /**
     * Registers console commands if running in the console.
     */
    protected function registerConsoleCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                DynamicLevelsMakeCommand::class,
                EnhancedDBServiceCommand::class,
                SyncCountriesAndStatesJsonFilesCommand::class,
                GeoDataScriptCommand::class,
                LucideCacheCommand::class,
            ]);
        }
    }

    /**
     * Registers helper functions from the helpers file.
     */
    protected function registerHelpers(): void
    {
        $file = __DIR__ . '/../helpers.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }

    /**
     * Registers the facade binding.
     */
    protected function registerFacade(): void
    {
        $this->app->singleton('dynamic-helpers', function () {
            return new DynamicHelpersLoader();
        });

        $this->app->singleton('parameter-service', function () {
            return new \Aotr\DynamicLevelHelper\Services\ParameterService();
        });

        $this->app->singleton('geo-data-service', function () {
            return new \Aotr\DynamicLevelHelper\Services\GeoDataService();
        });

        $this->app->singleton(LucideIconService::class, function () {
            return new LucideIconService();
        });

        $this->app->alias(LucideIconService::class, 'lucide-icon-service');

        $this->app->singleton('toon-service', function () {
            return new ToonService();
        });
    }

    /**
     * Merges the package configuration file.
     */
    protected function mergeConfig(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/dynamic-levels-helper.php',
            'dynamic-levels-helper'
        );
        $this->mergeConfigFrom(
            __DIR__ . '/../config/dynamic-levels-helper-sms.php',
            'dynamic-levels-helper-sms'
        );
        $this->mergeConfigFrom(
            __DIR__ . '/../config/dynamic-levels-helper-whatsapp.php',
            'dynamic-levels-helper-whatsapp'
        );
        $this->mergeConfigFrom(
            __DIR__ . '/../config/lucide.php',
            'lucide'
        );
        $this->mergeConfigFrom(
            __DIR__ . '/../config/toon.php',
            'toon'
        );
    }

    /**
     * Registers Blade components.
     */
    protected function registerBladeComponents(): void
    {
        // Register the component, but handle conflicts gracefully
        // If a user already has a 'lucide-icon' component, this will silently fail
        try {
            Blade::component('lucide-icon', DynamicIcon::class);
        } catch (\Throwable $e) {
            // Component already exists, skip registration
            report($e);
        }
    }

    /**
     * Conditionally merges custom log channels.
     * Only merges channels if they don't already exist in the app config.
     */
    protected function mergeLoggingConfig(): void
    {
        // Load custom log channels from the package config
        $logConfig = require __DIR__ . '/../config/logging.php';

        // Retrieve existing logging channels from the app config
        $currentChannels = $this->app['config']->get('logging.channels', []);

        // Only add custom channels if they don't already exist
        foreach ($logConfig['channels'] as $key => $channel) {
            if (!isset($currentChannels[$key])) {
                $currentChannels[$key] = $channel;
            }
        }

        // Set the modified logging configuration back to the app config
        $this->app['config']->set('logging.channels', $currentChannels);
    }
}
