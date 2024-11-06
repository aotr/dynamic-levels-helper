<?php

namespace Aotr\DynamicLevelHelper\Providers;

use Illuminate\Support\ServiceProvider;
use Aotr\DynamicLevelHelper\Macros\ResponseMacros;

class MacroServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // Register the DynamicLevelHelperServiceProvider within MacroServiceProvider
        $this->app->register(DynamicLevelHelperServiceProvider::class);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        ResponseMacros::register();
    }
}
