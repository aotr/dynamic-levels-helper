<?php

declare(strict_types=1);

namespace Aotr\DynamicLevelHelper\Providers;

use Aotr\DynamicLevelHelper\Console\Commands\DynamicLevelsMakeCommand;
use Aotr\DynamicLevelHelper\DynamicHelpersLoader;
use Aotr\DynamicLevelHelper\Macros\ResponseMacros;
use Aotr\DynamicLevelHelper\Middleware\BasicAuth;
use Illuminate\Support\ServiceProvider;

final class DynamicLevelHelperServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResponseMacros::register();
        $this->publishConfig();
        $this->registerMiddleware();
        $this->registerConsoleCommands();

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
