<?php

namespace Aotr\DynamicLevelHelper\Providers;

use Aotr\DynamicLevelHelper\Services\SMS\Providers\InfobipSmsProvider;
use Aotr\DynamicLevelHelper\Services\SMS\Providers\InternalSmsProvider;
use Aotr\DynamicLevelHelper\Services\SMS\Providers\MyValueFirstSmsProvider;
use Aotr\DynamicLevelHelper\Services\SMS\Providers\OnexSmsProvider;
use Aotr\DynamicLevelHelper\Services\SMS\Providers\SinfiniSmsProvider;
use Aotr\DynamicLevelHelper\Services\SMS\SmsProviderInterface;
use Aotr\DynamicLevelHelper\Services\SMS\SmsService;
use Illuminate\Support\ServiceProvider;

class SmsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register SmsService with all providers
        $this->app->singleton(SmsService::class, function ($app) {
            $config = config('dynamic-levels-helper-sms');
            $providers = [];

            // Initialize all providers
            foreach ($config['providers'] as $key => $providerConfig) {
                $providers[$key] = $this->createProvider($key, $providerConfig);
            }

            return new SmsService($providers, $config);
        });

        // Maintain backward compatibility: bind default provider to SmsProviderInterface
        $this->app->bind(SmsProviderInterface::class, function ($app) {
            $providerKey = config('dynamic-levels-helper-sms.default_provider');
            $providerConfig = config('dynamic-levels-helper-sms.providers.'.$providerKey);

            return $this->createProvider($providerKey, $providerConfig);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Create a provider instance based on the provider key
     */
    protected function createProvider(string $providerKey, array $providerConfig): SmsProviderInterface
    {
        switch ($providerKey) {
            case 'onex':
                return new OnexSmsProvider($providerConfig);
            case 'myvaluefirst':
                return new MyValueFirstSmsProvider($providerConfig);
            case 'infobip':
                return new InfobipSmsProvider($providerConfig);
            case 'internal':
                return new InternalSmsProvider($providerConfig);
            case 'sinfini':
            default:
                return new SinfiniSmsProvider($providerConfig);
        }
    }
}
