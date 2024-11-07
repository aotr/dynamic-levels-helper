<?php

namespace Aotr\DynamicLevelHelper\Providers;

use Aotr\DynamicLevelHelper\Services\SMS\Providers\InfobipSmsProvider;
use Aotr\DynamicLevelHelper\Services\SMS\Providers\MyValueFirstSmsProvider;
use Aotr\DynamicLevelHelper\Services\SMS\Providers\OnexSmsProvider;
use Aotr\DynamicLevelHelper\Services\SMS\Providers\SinfiniSmsProvider;
use Aotr\DynamicLevelHelper\Services\SMS\SmsProviderInterface;
use Illuminate\Support\ServiceProvider;

class SmsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function  register(): void
    {

        $this->app->bind(SmsProviderInterface::class, function ($app) {
            $providerKey = config('dynamic-levels-helper-sms.default_provider');
            $providerConfig = config('dynamic-levels-helper-sms.providers.'.$providerKey);

            switch ($providerKey) {
                case 'onex':
                    return new OnexSmsProvider($providerConfig);
                case 'myvaluefirst':
                    return new MyValueFirstSmsProvider($providerConfig);
                case 'infobip':
                    return new InfobipSmsProvider($providerConfig);
                default:
                    return new SinfiniSmsProvider($providerConfig);
            }
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
