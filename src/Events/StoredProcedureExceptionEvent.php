<?php

namespace Aotr\DynamicLevelHelper\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Exception;

/**
 * Fired when a stored procedure execution throws an exception.
 *
 * This is the primary alert-worthy event — it fires for:
 * - PDO exceptions (connection loss, timeout, syntax error)
 * - RuntimeException (SP not found, prepare/execute failure)
 * - Trigger-caused exceptions (RAISERROR with severity ≥ 11, trigger THROW)
 *
 * Listeners can use this to send email, Slack, webhook, or any other alert.
 */
class StoredProcedureExceptionEvent
{
    use Dispatchable, SerializesModels;

    /** @var string Stored procedure name */
    public string $storedProcedureName;

    /** @var array Input parameters */
    public array $parameters;

    /** @var string Database connection name */
    public string $connection;

    /** @var Exception The caught exception */
    public Exception $exception;

    /** @var string The raw SQL CALL statement */
    public string $sql;

    /** @var int Number of attempts (including retries) before this exception */
    public int $attempt;

    /** @var int Maximum retry attempts configured */
    public int $maxAttempts;

    /** @var float Execution time of the failed attempt in seconds */
    public float $executionTime;

    /** @var bool Whether the error is classified as retryable */
    public bool $retryable;

    /** @var bool Whether the error is a connection-level error */
    public bool $connectionError;

    /** @var array Execution history (all attempts) */
    public array $executionHistory;

    /** @var bool Whether all retry attempts have been exhausted */
    public bool $retriesExhausted;

    public function __construct(
        string     $storedProcedureName,
        array      $parameters,
        string     $connection,
        Exception  $exception,
        string     $sql,
        int        $attempt,
        int        $maxAttempts,
        float      $executionTime,
        bool       $retryable,
        bool       $connectionError,
        array      $executionHistory = [],
        bool       $retriesExhausted = true
    ) {
        $this->storedProcedureName = $storedProcedureName;
        $this->parameters          = $parameters;
        $this->connection          = $connection;
        $this->exception           = $exception;
        $this->sql                 = $sql;
        $this->attempt             = $attempt;
        $this->maxAttempts         = $maxAttempts;
        $this->executionTime       = $executionTime;
        $this->retryable           = $retryable;
        $this->connectionError     = $connectionError;
        $this->executionHistory    = $executionHistory;
        $this->retriesExhausted    = $retriesExhausted;
    }

    // ── Convenience ────────────────────────────────────────────────

    public function getExceptionMessage(): string
    {
        return $this->exception->getMessage();
    }

    public function getExceptionCode(): int
    {
        return (int) $this->exception->getCode();
    }

    public function getExceptionClass(): string
    {
        return get_class($this->exception);
    }

    public function toArray(): array
    {
        return [
            'stored_procedure' => $this->storedProcedureName,
            'parameters'       => $this->parameters,
            'connection'       => $this->connection,
            'exception'        => [
                'message'   => $this->exception->getMessage(),
                'code'      => $this->exception->getCode(),
                'class'     => get_class($this->exception),
                'file'      => $this->exception->getFile(),
                'line'      => $this->exception->getLine(),
            ],
            'sql'              => $this->sql,
            'attempt'          => $this->attempt,
            'max_attempts'     => $this->maxAttempts,
            'execution_time'   => round($this->executionTime, 4),
            'retryable'        => $this->retryable,
            'connection_error' => $this->connectionError,
            'retries_exhausted'=> $this->retriesExhausted,
            'execution_history'=> $this->executionHistory,
            'timestamp'        => now()->toISOString(),
        ];
    }
}
