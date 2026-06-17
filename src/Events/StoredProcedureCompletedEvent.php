<?php

namespace Aotr\DynamicLevelHelper\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after every stored procedure execution (success or failure).
 *
 * Listeners can inspect `success`, `triggerWarnings`, `triggerErrors`,
 * and `resultSets` to decide whether to alert.
 */
class StoredProcedureCompletedEvent
{
    use Dispatchable, SerializesModels;

    /** @var string Stored procedure name */
    public string $storedProcedureName;

    /** @var array Input parameters */
    public array $parameters;

    /** @var string Database connection used */
    public string $connection;

    /** @var bool Whether the SP executed without throwing */
    public bool $success;

    /** @var float Total execution time in seconds */
    public float $executionTime;

    /** @var array|null The wrapped result sets */
    public ?array $resultSets;

    /** @var string The raw SQL CALL statement */
    public string $sql;

    /** @var array Parsed trigger warnings (if any) found in result meta */
    public array $triggerWarnings;

    /** @var array Parsed trigger errors (if any) found in result meta */
    public array $triggerErrors;

    /** @var array Additional metadata (attempt count, retry info, etc.) */
    public array $meta;

    /** @var int|null Exception code when $success is false */
    public ?int $errorCode;

    /** @var string|null Exception message when $success is false */
    public ?string $errorMessage;

    public function __construct(
        string  $storedProcedureName,
        array   $parameters,
        string  $connection,
        bool    $success,
        float   $executionTime,
        ?array  $resultSets,
        string  $sql,
        array   $triggerWarnings = [],
        array   $triggerErrors   = [],
        array   $meta            = [],
        ?int    $errorCode       = null,
        ?string $errorMessage    = null
    ) {
        $this->storedProcedureName = $storedProcedureName;
        $this->parameters          = $parameters;
        $this->connection          = $connection;
        $this->success             = $success;
        $this->executionTime       = $executionTime;
        $this->resultSets          = $resultSets;
        $this->sql                 = $sql;
        $this->triggerWarnings     = $triggerWarnings;
        $this->triggerErrors       = $triggerErrors;
        $this->meta                = $meta;
        $this->errorCode           = $errorCode;
        $this->errorMessage        = $errorMessage;
    }

    // ── Convenience accessors ──────────────────────────────────────

    public function hasTriggerWarnings(): bool
    {
        return !empty($this->triggerWarnings);
    }

    public function hasTriggerErrors(): bool
    {
        return !empty($this->triggerErrors);
    }

    public function hasAnyTriggerIssues(): bool
    {
        return $this->hasTriggerWarnings() || $this->hasTriggerErrors();
    }

    public function getTriggerSummary(): string
    {
        $parts = [];

        if (!empty($this->triggerErrors)) {
            $parts[] = count($this->triggerErrors) . ' trigger error(s)';
        }
        if (!empty($this->triggerWarnings)) {
            $parts[] = count($this->triggerWarnings) . ' trigger warning(s)';
        }

        return $parts ? implode(', ', $parts) : 'No trigger issues';
    }

    /**
     * Return a flat array suitable for logging / serialization.
     */
    public function toArray(): array
    {
        return [
            'stored_procedure' => $this->storedProcedureName,
            'parameters'       => $this->parameters,
            'connection'       => $this->connection,
            'success'          => $this->success,
            'execution_time'   => round($this->executionTime, 4),
            'sql'              => $this->sql,
            'trigger_warnings' => $this->triggerWarnings,
            'trigger_errors'   => $this->triggerErrors,
            'meta'             => $this->meta,
            'error_code'       => $this->errorCode,
            'error_message'    => $this->errorMessage,
            'timestamp'        => now()->toISOString(),
        ];
    }
}
