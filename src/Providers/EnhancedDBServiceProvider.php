<?php

declare(strict_types=1);

namespace Aotr\DynamicLevelHelper\Providers;

use Aotr\DynamicLevelHelper\Events\StoredProcedureCompletedEvent;
use Aotr\DynamicLevelHelper\Events\StoredProcedureExceptionEvent;
use Aotr\DynamicLevelHelper\Events\StoredProcedureTriggerWarningEvent;
use Aotr\DynamicLevelHelper\Listeners\AlertStoredProcedureException;
use Aotr\DynamicLevelHelper\Listeners\LogStoredProcedureEvent;
use Aotr\DynamicLevelHelper\Listeners\NotifyTriggerWarnings;
use Aotr\DynamicLevelHelper\Services\EnhancedDBService;
use Illuminate\Support\Facades\Event;
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
        // Register event listeners for stored procedure events
        $this->registerEventListeners();

        // Register graceful shutdown handler
        if ($this->app->runningInConsole()) {
            $this->registerShutdownHandler();
        }
    }

    /**
     * Register event listeners for stored procedure lifecycle events.
     *
     * Event map:
     * - StoredProcedureCompletedEvent       → LogStoredProcedureEvent
     * - StoredProcedureTriggerWarningEvent  → NotifyTriggerWarnings
     * - StoredProcedureExceptionEvent       → AlertStoredProcedureException
     */
    private function registerEventListeners(): void
    {
        // ── StoredProcedureCompletedEvent ──
        // Logs every SP completion (success, trigger warnings, trigger errors)
        Event::listen(
            StoredProcedureCompletedEvent::class,
            [LogStoredProcedureEvent::class, 'handle']
        );

        // ── StoredProcedureTriggerWarningEvent ──
        // Sends notifications when triggers raise warnings
        Event::listen(
            StoredProcedureTriggerWarningEvent::class,
            [NotifyTriggerWarnings::class, 'handle']
        );

        // ── StoredProcedureExceptionEvent ──
        // Sends alerts (email / Slack / webhook) for exceptions.
        // Note: AlertStoredProcedureException also logs the exception via its logException() method,
        // so there is no need for a separate LogStoredProcedureEvent registration here.
        Event::listen(
            StoredProcedureExceptionEvent::class,
            [AlertStoredProcedureException::class, 'handle']
        );
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
