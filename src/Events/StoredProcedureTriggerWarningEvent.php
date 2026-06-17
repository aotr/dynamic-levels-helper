<?php

namespace Aotr\DynamicLevelHelper\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a DB trigger raises a warning during stored procedure execution.
 *
 * Trigger warnings are non-fatal issues detected in the database output —
 * for example: deprecated column usage, constraint warnings, data truncation
 * notices, or custom RAISERROR (severity < 11) from SQL Server triggers.
 *
 * This event is dispatched even when the SP itself succeeds, because
 * trigger warnings do NOT cause the SP to fail.
 */
class StoredProcedureTriggerWarningEvent
{
    use Dispatchable, SerializesModels;

    /** @var string Stored procedure name */
    public string $storedProcedureName;

    /** @var array Input parameters that were passed */
    public array $parameters;

    /** @var string Database connection name */
    public string $connection;

    /** @var array The parsed trigger warnings */
    public array $warnings;

    /** @var string|null The raw SQL that was executed */
    public ?string $sql;

    /** @var float Execution time in seconds */
    public float $executionTime;

    /** @var array Full result-set metadata */
    public array $resultMeta;

    public function __construct(
        string  $storedProcedureName,
        array   $parameters,
        string  $connection,
        array   $warnings,
        ?string $sql = null,
        float   $executionTime = 0.0,
        array   $resultMeta = []
    ) {
        $this->storedProcedureName = $storedProcedureName;
        $this->parameters          = $parameters;
        $this->connection          = $connection;
        $this->warnings            = $warnings;
        $this->sql                 = $sql;
        $this->executionTime       = $executionTime;
        $this->resultMeta          = $resultMeta;
    }

    public function toArray(): array
    {
        return [
            'stored_procedure' => $this->storedProcedureName,
            'parameters'       => $this->parameters,
            'connection'       => $this->connection,
            'warnings'         => $this->warnings,
            'sql'              => $this->sql,
            'execution_time'   => round($this->executionTime, 4),
            'result_meta'      => $this->resultMeta,
            'timestamp'        => now()->toISOString(),
        ];
    }
}
