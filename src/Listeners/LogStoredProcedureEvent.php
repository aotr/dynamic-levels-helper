<?php

namespace Aotr\DynamicLevelHelper\Listeners;

use Aotr\DynamicLevelHelper\Events\StoredProcedureCompletedEvent;
use Illuminate\Support\Facades\Log;

/**
 * Logs every stored procedure completion — success, trigger warnings, and trigger errors.
 *
 * Channel: 'stp' (configurable via enhanced_db_service.logging.channel)
 *
 * This listener provides a complete audit trail:
 * - Every SP call is logged with parameters, timing, and result counts
 * - Trigger warnings/errors are highlighted at WARNING / ERROR level
 * - The full result-set meta (row_counts, empty_flags) is included
 */
class LogStoredProcedureEvent
{
    public function handle(StoredProcedureCompletedEvent $event): void
    {
        $channel = config('dynamic-levels-helper.enhanced_db_service.logging.channel', 'stp');

        // ── Build the base context array ──
        $context = [
            'stored_procedure' => $event->storedProcedureName,
            'parameters'       => $event->parameters,
            'connection'       => $event->connection,
            'success'          => $event->success,
            'execution_time'   => round($event->executionTime, 4),
            'sql'              => $event->sql,
            'meta'             => $event->meta,
        ];

        // ── Log trigger errors ──
        if ($event->hasTriggerErrors()) {
            $context['trigger_errors'] = $event->triggerErrors;

            Log::channel($channel)->error(
                "⚠️  Trigger ERROR(s) in [{$event->storedProcedureName}] — {$event->getTriggerSummary()}",
                $context
            );
        }

        // ── Log trigger warnings ──
        if ($event->hasTriggerWarnings()) {
            $context['trigger_warnings'] = $event->triggerWarnings;

            Log::channel($channel)->warning(
                "⚠️  Trigger WARNING(s) in [{$event->storedProcedureName}] — {$event->getTriggerSummary()}",
                $context
            );
        }

        // ── Log success / failure ──
        if ($event->success) {
            Log::channel($channel)->info(
                "✅ Stored procedure [{$event->storedProcedureName}] completed successfully",
                $context
            );
        } else {
            $context['error_code']    = $event->errorCode;
            $context['error_message'] = $event->errorMessage;

            Log::channel($channel)->error(
                "❌ Stored procedure [{$event->storedProcedureName}] FAILED — {$event->errorMessage}",
                $context
            );
        }
    }
}
