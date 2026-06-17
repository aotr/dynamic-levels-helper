<?php

namespace Aotr\DynamicLevelHelper\Services;

use Aotr\DynamicLevelHelper\Events\StoredProcedureCompletedEvent;
use Aotr\DynamicLevelHelper\Events\StoredProcedureTriggerWarningEvent;
use Aotr\DynamicLevelHelper\Events\StoredProcedureExceptionEvent;
use Aotr\DynamicLevelHelper\Services\DBConnectionPool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Exception;

/**
 * Enhanced Database Service V1 — Optimized Multi-Result-Set Handling
 *
 * Key improvements over V1 (EnhancedDBService):
 * - ALWAYS preserves every result set (including empty) — array index stability
 * - Adds per-result-set metadata: row counts, empty flags, total set count
 * - Uses `finally` for reliable connection cleanup
 * - Adds safe result-set accessors: getResultSet(), getNonEmptyResultSets(), etc.
 * - Full backward compatibility: existing callStoredProcedure() / callStoredProcedureWithInfo() signatures preserved
 */
class EnhancedDBServiceV1
{
    /** @var EnhancedDBServiceV1|null Singleton instance */
    private static ?EnhancedDBServiceV1 $instance = null;

    /** @var DBConnectionPool */
    private DBConnectionPool $connectionPool;

    /** @var array Service configuration */
    private array $config;

    /** @var string Default database connection */
    private string $defaultConnection;

    /** @var array Performance metrics */
    private array $performanceMetrics = [];

    // ──────────────────────────────────────────────────────────────────
    //  Singleton
    // ──────────────────────────────────────────────────────────────────

    private function __construct()
    {
        $this->loadConfiguration();
        $this->connectionPool = new DBConnectionPool($this->config['connection_pool']);
        $this->defaultConnection = $this->config['default_connection'];
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function resetInstance(): void
    {
        if (self::$instance !== null) {
            self::$instance->connectionPool->closeAllConnections();
            self::$instance = null;
        }
    }

    public function __clone()
    {
        throw new RuntimeException('Cannot clone singleton ' . static::class);
    }

    public function __wakeup()
    {
        throw new RuntimeException('Cannot unserialize singleton ' . static::class);
    }

    public function __destruct()
    {
        if ($this->connectionPool) {
            $this->connectionPool->closeAllConnections();
        }
    }

    // ──────────────────────────────────────────────────────────────────
    //  Configuration
    // ──────────────────────────────────────────────────────────────────

    private function loadConfiguration(): void
    {
        $defaultConfig = [
            'default_connection' => 'mysql',
            'logging' => [
                'enabled' => true,
                'channel' => 'stp',
                'log_queries' => true,
                'log_errors' => true,
                'log_execution_time' => true,
            ],
            'connection_pool' => [
                'max_connections' => 10,
                'pool_timeout' => 30,
                'idle_timeout' => 300,
                'retry_attempts' => 3,
                'retry_delay' => 100,
            ],
            'cache' => [
                'procedure_exists_ttl' => 86400,
                'enabled' => true,
            ],
            'performance' => [
                'slow_query_threshold' => 2.0,
                'enable_query_profiling' => false,
                'enable_query_timeout' => true,
            ],
        ];

        try {
            $this->config = function_exists('config')
                ? config('dynamic-levels-helper.enhanced_db_service', $defaultConfig)
                : $defaultConfig;
        } catch (\Exception $e) {
            $this->config = $defaultConfig;
        }
    }

    // ══════════════════════════════════════════════════════════════════
    //  PUBLIC API
    // ══════════════════════════════════════════════════════════════════

    /**
     * Call a stored procedure with enhanced features.
     *
     * @param  string  $storedProcedureName
     * @param  array   $parameters
     * @param  array   $options  connection, checkStoredProcedure, timeout, retryAttempts, retryDelay, returnExecutionInfo
     * @return array
     * @throws RuntimeException
     */
    public function callStoredProcedure(string $storedProcedureName, array $parameters = [], array $options = []): array
    {
        $options = array_merge([
            'connection'           => $this->defaultConnection,
            'checkStoredProcedure' => false,
            'enableLogging'        => null,
            'timeout'              => 30,
            'retryAttempts'        => null,
            'retryDelay'           => 100,
            'returnExecutionInfo'  => false,
        ], $options);

        $connection          = $options['connection'];
        $retryAttempts       = $options['retryAttempts'] ?? $this->config['connection_pool']['retry_attempts'];
        $retryDelay          = $options['retryDelay'];
        $returnExecutionInfo = $options['returnExecutionInfo'];

        $attempt          = 0;
        $lastException    = null;
        $totalStartTime   = microtime(true);
        $executionHistory = [];

        while ($attempt <= $retryAttempts) {
            try {
                $result = $this->executeStoredProcedure(
                    $storedProcedureName, $parameters, $options, $attempt, $executionHistory
                );

                if ($returnExecutionInfo) {
                    return $this->buildExecutionInfo(
                        $storedProcedureName, $parameters, $result,
                        microtime(true) - $totalStartTime, $attempt,
                        $executionHistory, $connection
                    );
                }

                return $result;

            } catch (Exception $e) {
                $lastException = $e;
                $attempt++;

                $executionHistory[] = [
                    'attempt'    => $attempt,
                    'error'      => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'retryable'  => $this->isRetryableError($e),
                    'timestamp'  => microtime(true),
                ];

                if ($attempt <= $retryAttempts && $this->isRetryableError($e)) {
                    $this->logRetryAttempt($storedProcedureName, $parameters, $e, $attempt, $retryAttempts);
                    usleep($this->calculateRetryDelay($retryDelay, $attempt) * 1000);

                    if ($this->isConnectionError($e)) {
                        $this->connectionPool->closeAllConnections();
                    }
                    continue;
                }

                break;
            }
        }

        $totalExecutionTime = microtime(true) - $totalStartTime;

        // ── Dispatch exception event before throwing ──
        try {
            if (class_exists(StoredProcedureExceptionEvent::class)) {
                $retryable       = $this->isRetryableError($lastException);
                $connectionError = $this->isConnectionError($lastException);
                $sql             = "CALL {$storedProcedureName}("
                    . implode(',', array_fill(0, count($parameters), '?')) . ")";

                StoredProcedureExceptionEvent::dispatch(
                    $storedProcedureName,
                    $parameters,
                    $connection,
                    $lastException,
                    $sql,
                    $attempt,               // current attempt number (already incremented)
                    $retryAttempts,          // max attempts configured
                    $totalExecutionTime,
                    $retryable,
                    $connectionError,
                    $executionHistory,
                    true                     // retriesExhausted
                );
            }
        } catch (\Exception $e) {
            // Swallow — don't let a dispatcher crash mask the original error
            $this->log('warning', 'Failed to dispatch exception event', [
                'error' => $e->getMessage(), 'procedure' => $storedProcedureName,
            ]);
        }

        $errorMessage = "Database error in stored procedure '{$storedProcedureName}' "
            . "after {$attempt} attempts: {$lastException->getMessage()}";
        $this->logError($errorMessage, $storedProcedureName, $parameters, '', $connection, $lastException);

        if ($returnExecutionInfo) {
            return $this->buildExecutionInfo(
                $storedProcedureName, $parameters, null,
                $totalExecutionTime, $attempt,
                $executionHistory, $connection, $lastException
            );
        }

        throw new RuntimeException($errorMessage, 0, $lastException);
    }

    /**
     * Convenience alias — same as callStoredProcedure with returnExecutionInfo = true.
     */
    public function callStoredProcedureWithInfo(string $storedProcedureName, array $parameters = [], array $options = []): array
    {
        $options['returnExecutionInfo'] = true;
        return $this->callStoredProcedure($storedProcedureName, $parameters, $options);
    }

    // ══════════════════════════════════════════════════════════════════
    //  RESULT-SET ACCESSORS (safe helpers for consumers)
    // ══════════════════════════════════════════════════════════════════

    /**
     * Safely retrieve a single result set by its positional index.
     *
     * @param  array  $resultSets  The wrapper returned by callStoredProcedure()
     * @param  int    $index       0-based position
     * @param  mixed  $default     Returned when index is out of range
     * @return array  The result-set rows (or $default)
     */
    public static function getResultSet(array $resultSets, int $index, mixed $default = []): array
    {
        $data = $resultSets['data'] ?? [];

        if (isset($data[$index]) && is_array($data[$index])) {
            return $data[$index];
        }

        return $default;
    }

    /**
     * Return only non-empty result sets (preserving their original keys).
     *
     * @param  array $resultSets  The wrapper returned by callStoredProcedure()
     * @return array<int, array>  Keyed by original positional index
     */
    public static function getNonEmptyResultSets(array $resultSets): array
    {
        $data   = $resultSets['data'] ?? [];
        $result = [];

        foreach ($data as $idx => $set) {
            if (!empty($set)) {
                $result[$idx] = $set;
            }
        }

        return $result;
    }

    /**
     * Return the meta array attached during execution.
     */
    public static function getMeta(array $resultSets): array
    {
        return $resultSets['_meta'] ?? [];
    }

    /**
     * Return the row count for a specific result-set index.
     */
    public static function getResultSetRowCount(array $resultSets, int $index): int
    {
        $meta = $resultSets['_meta'] ?? [];
        return $meta['row_counts'][$index] ?? 0;
    }

    // ══════════════════════════════════════════════════════════════════
    //  INTERNAL — Execute & collect result sets
    // ══════════════════════════════════════════════════════════════════

    /**
     * Execute a stored procedure and collect ALL result sets.
     *
     * **V1 FIX:** Every result set is always pushed to the array — even when empty.
     * This guarantees index stability: consumers who expect `$data[0]` to be the
     * first SELECT of the SP, `$data[1]` the second, etc., will never see shifted
     * indices when an intermediate result set happens to be blank.
     */
    private function executeStoredProcedure(
        string $storedProcedureName,
        array  $parameters,
        array  $options,
        int    $attempt,
        array &$executionHistory = []
    ): array {
        $connection = $options['connection'];
        $startTime  = microtime(true);
        $queryId    = $this->generateQueryId() . "_attempt_{$attempt}";

        if ($options['checkStoredProcedure']
            && !$this->checkStoredProcedure($storedProcedureName, $connection)) {
            $this->logError(
                "Stored Procedure '{$storedProcedureName}' does not exist",
                $storedProcedureName, $parameters, '', $connection
            );
            throw new RuntimeException("Stored Procedure '{$storedProcedureName}' does not exist");
        }

        $placeholders = implode(',', array_fill(0, count($parameters), '?'));
        $sql          = "CALL {$storedProcedureName}({$placeholders})";
        $pdo          = $this->connectionPool->getConnection($connection);

        $this->logQueryStart($queryId, $sql, $parameters, $connection);

        $resultSets = [];

        try {
            $stmt = $pdo->prepare($sql);

            if (!$stmt) {
                throw new RuntimeException(
                    "Failed to prepare statement for stored procedure '{$storedProcedureName}'"
                );
            }

            if ($this->config['performance']['enable_query_timeout'] ?? true) {
                $this->setQueryTimeout($pdo, $stmt, $options['timeout']);
            }

            if (!$stmt->execute($parameters)) {
                $errorInfo = $stmt->errorInfo();
                throw new RuntimeException(
                    "Failed to execute stored procedure '{$storedProcedureName}': "
                    . ($errorInfo[2] ?? 'Unknown error')
                );
            }

            // ────────────────────────────────────────────────────────
            //  V1 CORE FIX  —  always push, never skip
            // ────────────────────────────────────────────────────────
            $resultSets  = [];
            $rowCounts   = [];
            $emptyFlags  = [];
            do {
                $resultSet   = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                $row_count   = is_array($resultSet) ? count($resultSet) : 0;

                // Always preserve position — even when the result set is blank
                $resultSets[] = $resultSet;

                // Collect per-set metadata
                $rowCounts[]     = $row_count;
                $emptyFlags[]    = ($row_count === 0);

            } while ($stmt->nextRowset());

        } catch (\PDOException|\Exception $e) {
            // Connection release is handled by the finally block below
            throw $e;
        } finally {
            // Ensure connection is always returned to the pool
            $this->connectionPool->releaseConnection($connection);
        }

        $executionTime  = microtime(true) - $startTime;
        $totalSetsCount = count($resultSets);
        $totalRowCount  = array_sum($rowCounts);
        $nonEmptyCount  = $totalSetsCount - count(array_filter($emptyFlags));

        // ── Build metadata block ──
        $meta = [
            'total_result_sets' => $totalSetsCount,
            'non_empty_sets'    => $nonEmptyCount,
            'empty_sets'        => $totalSetsCount - $nonEmptyCount,
            'total_rows'        => $totalRowCount,
            'row_counts'        => $rowCounts,       // [0 => 12, 1 => 0, 2 => 5]
            'empty_flags'       => $emptyFlags,       // [0 => false, 1 => true, 2 => false]
        ];

        // ── Wrap result sets ──
        $wrappedResultSets = [
            'data'       => $resultSets,
            '_meta'      => $meta,
            'raw_query'  => $sql,
            'parameters' => $parameters,
        ];

        // ── Check for trigger warnings / errors ──
        // PDO errorInfo after the loop may contain warnings from triggers
        $triggerWarnings = [];
        $triggerErrors   = [];
        $errorInfo       = $stmt->errorInfo();
        $sqlState        = $errorInfo[0] ?? '00000';

        if ($sqlState !== '00000' && $sqlState !== '0000') {
            // SQLSTATE codes starting with '01' are warnings
            if (str_starts_with($sqlState, '01')) {
                $triggerWarnings[] = [
                    'sqlstate' => $sqlState,
                    'code'     => $errorInfo[1] ?? 0,
                    'message'  => $errorInfo[2] ?? 'Unknown trigger warning',
                ];
            } else {
                $triggerErrors[] = [
                    'sqlstate' => $sqlState,
                    'code'     => $errorInfo[1] ?? 0,
                    'message'  => $errorInfo[2] ?? 'Unknown trigger error',
                ];
            }
        }

        // ── Dispatch events ──
        try {
            if (!empty($triggerWarnings) && class_exists(StoredProcedureTriggerWarningEvent::class)) {
                StoredProcedureTriggerWarningEvent::dispatch(
                    $storedProcedureName,
                    $parameters,
                    $connection,
                    $triggerWarnings,
                    $sql,
                    $executionTime,
                    $meta
                );
            }

            if (class_exists(StoredProcedureCompletedEvent::class)) {
                StoredProcedureCompletedEvent::dispatch(
                    $storedProcedureName,
                    $parameters,
                    $connection,
                    true,                  // success
                    $executionTime,
                    $wrappedResultSets,
                    $sql,
                    $triggerWarnings,
                    $triggerErrors,
                    $meta
                );
            }
        } catch (\Exception $e) {
            // Swallow event dispatch errors — don't let a listener crash the SP result
            $this->log('warning', 'Event dispatch failed in executeStoredProcedure', [
                'error' => $e->getMessage(),
                'procedure' => $storedProcedureName,
            ]);
        }

        // ── History / logging ──
        $executionHistory[] = [
            'attempt'       => $attempt + 1,
            'execution_time'=> $executionTime,
            'result_sets'   => $totalSetsCount,
            'total_rows'    => $totalRowCount,
            'timestamp'     => microtime(true),
            'success'       => true,
        ];

        $this->logQueryComplete($queryId, $sql, $parameters, $executionTime, $connection, $totalSetsCount);

        if ($executionTime > $this->config['performance']['slow_query_threshold']) {
            $this->logSlowQuery($sql, $parameters, $executionTime, $connection);
        }

        $this->recordPerformanceMetrics($storedProcedureName, $executionTime, $totalSetsCount);

        if ($this->shouldLogToQueryLog()) {
            $this->safeLogToQueryLog($connection, $sql, $parameters, $executionTime);
        }

        return $wrappedResultSets;
    }

    // ══════════════════════════════════════════════════════════════════
    //  Build execution info (for callStoredProcedureWithInfo)
    // ══════════════════════════════════════════════════════════════════

    private function buildExecutionInfo(
        string         $storedProcedureName,
        array          $parameters,
        ?array         $resultSets,
        float          $totalExecutionTime,
        int            $totalAttempts,
        array          $executionHistory,
        string         $connection,
        ?Exception     $finalException = null
    ): array {
        $poolStats         = $this->getConnectionPoolStats();
        $procedureMetrics  = $this->performanceMetrics[$storedProcedureName] ?? null;

        $successfulAttempts = array_filter($executionHistory, fn($h) => $h['success'] ?? false);
        $failedAttempts     = array_filter($executionHistory, fn($h) => !($h['success'] ?? false));

        $resultData    = null;
        $resultSetsCount = 0;
        $rowsAffected  = 0;

        if ($resultSets && is_array($resultSets)) {
            $dataKey = $resultSets['data'] ?? $resultSets;

            if (is_array($dataKey)) {
                $resultData    = $dataKey;
                $resultSetsCount = count($dataKey);
                $rowsAffected  = array_sum(array_map(
                    fn($s) => is_array($s) ? count($s) : 0,
                    $dataKey
                ));
            }
        }

        // Use V1 meta if available
        $meta = $resultSets['_meta'] ?? null;

        $executionInfo = [
            'success'             => $finalException === null,
            'stored_procedure'    => $storedProcedureName,
            'parameters'          => $parameters,
            'connection'          => $connection,
            'execution_summary'   => [
                'total_execution_time' => round($totalExecutionTime, 4),
                'total_attempts'       => $totalAttempts,
                'successful_attempts'  => count($successfulAttempts),
                'failed_attempts'      => count($failedAttempts),
                'result_sets_count'    => $meta['total_result_sets'] ?? $resultSetsCount,
                'rows_affected'        => $meta['total_rows'] ?? $rowsAffected,
                'non_empty_sets'       => $meta['non_empty_sets'] ?? null,
                'empty_sets'           => $meta['empty_sets'] ?? null,
                'row_counts'           => $meta['row_counts'] ?? null,
            ],
            'connection_pool'     => [
                'stats'           => $poolStats,
                'connection_used' => $connection,
            ],
            'performance'         => [
                'is_slow_query'          => $totalExecutionTime > $this->config['performance']['slow_query_threshold'],
                'slow_query_threshold'   => $this->config['performance']['slow_query_threshold'],
                'procedure_metrics'      => $procedureMetrics,
            ],
            'retry_information'   => [
                'retry_enabled'       => $totalAttempts > 0,
                'max_retry_attempts'  => $this->config['connection_pool']['retry_attempts'],
                'retry_base_delay'    => $this->config['connection_pool']['retry_delay'],
                'execution_history'   => $executionHistory,
            ],
            'configuration'       => [
                'timeout'           => $this->config['connection_pool']['pool_timeout'],
                'max_connections'   => $this->config['connection_pool']['max_connections'],
                'logging_enabled'   => $this->config['logging']['enabled'],
                'cache_enabled'     => $this->config['cache']['enabled'],
            ],
            'timestamp'           => [
                'started_at'    => date('Y-m-d H:i:s', (int)(time() - $totalExecutionTime)),
                'completed_at'  => date('Y-m-d H:i:s'),
                'timezone'      => date_default_timezone_get(),
            ],
        ];

        if ($resultData !== null) {
            $executionInfo['data'] = $resultData;

            if (isset($resultSets['raw_query'])) {
                $executionInfo['raw_query'] = $resultSets['raw_query'];
            }
            if (isset($resultSets['parameters'])) {
                $executionInfo['query_parameters'] = $resultSets['parameters'];
            }
        } else {
            $executionInfo['data'] = [];
        }

        if ($finalException !== null) {
            $executionInfo['error'] = [
                'message'          => $finalException->getMessage(),
                'code'             => $finalException->getCode(),
                'type'             => get_class($finalException),
                'retryable'        => $this->isRetryableError($finalException),
                'connection_error' => $this->isConnectionError($finalException),
            ];
        }

        return $executionInfo;
    }

    // ══════════════════════════════════════════════════════════════════
    //  Stored-procedure existence check (with caching)
    // ══════════════════════════════════════════════════════════════════

    private function checkStoredProcedure(string $procedureName, string $connection): bool
    {
        if (!$this->config['cache']['enabled']) {
            return $this->checkStoredProcedureInDatabase($procedureName, $connection);
        }

        try {
            $cacheKey = "enhanced_sp_exists_{$connection}_{$procedureName}";
            if (class_exists(Cache::class)) {
                return Cache::remember(
                    $cacheKey,
                    $this->config['cache']['procedure_exists_ttl'],
                    fn() => $this->checkStoredProcedureInDatabase($procedureName, $connection)
                );
            }
            return $this->checkStoredProcedureInDatabase($procedureName, $connection);
        } catch (\Exception $e) {
            $this->log('warning', 'Cache check failed, falling back to database check', [
                'error'     => $e->getMessage(),
                'procedure' => $procedureName,
                'connection'=> $connection,
            ]);
            return $this->checkStoredProcedureInDatabase($procedureName, $connection);
        }
    }

    private function checkStoredProcedureInDatabase(string $procedureName, string $connection): bool
    {
        try {
            if (!class_exists(DB::class)) {
                $this->log('warning', 'Laravel DB facade not available for SP check', [
                    'procedure' => $procedureName, 'connection' => $connection,
                ]);
                return true;
            }
            return DB::connection($connection)
                ->table('information_schema.routines')
                ->where('SPECIFIC_NAME', $procedureName)
                ->where('ROUTINE_SCHEMA', DB::connection($connection)->getDatabaseName())
                ->exists();
        } catch (Exception $e) {
            $this->logError(
                "Failed to check stored procedure existence: {$e->getMessage()}",
                $procedureName, [], '', $connection, $e
            );
            return true;
        }
    }

    // ══════════════════════════════════════════════════════════════════
    //  Public accessors
    // ══════════════════════════════════════════════════════════════════

    public function getConnectionPoolStats(): array
    {
        return $this->connectionPool->getPoolStats();
    }

    public function getPerformanceMetrics(): array
    {
        return $this->performanceMetrics;
    }

    public function clearPerformanceMetrics(): void
    {
        $this->performanceMetrics = [];
    }

    // ══════════════════════════════════════════════════════════════════
    //  Performance metrics
    // ══════════════════════════════════════════════════════════════════

    private function generateQueryId(): string
    {
        return uniqid('enhanced_query_', true);
    }

    private function recordPerformanceMetrics(string $procedureName, float $executionTime, int $resultSets): void
    {
        if (!$this->config['performance']['enable_query_profiling']) {
            return;
        }

        if (!isset($this->performanceMetrics[$procedureName])) {
            $this->performanceMetrics[$procedureName] = [
                'total_calls'      => 0,
                'total_time'       => 0,
                'avg_time'         => 0,
                'min_time'         => PHP_FLOAT_MAX,
                'max_time'         => 0,
                'total_result_sets'=> 0,
            ];
        }

        $m = &$this->performanceMetrics[$procedureName];
        $m['total_calls']++;
        $m['total_time'] += $executionTime;
        $m['avg_time']    = $m['total_time'] / $m['total_calls'];
        $m['min_time']    = min($m['min_time'], $executionTime);
        $m['max_time']    = max($m['max_time'], $executionTime);
        $m['total_result_sets'] += $resultSets;
    }

    // ══════════════════════════════════════════════════════════════════
    //  Logging helpers
    // ══════════════════════════════════════════════════════════════════

    private function shouldLog(): bool
    {
        return $this->config['logging']['enabled'] ?? true;
    }

    private function shouldLogToQueryLog(): bool
    {
        return $this->config['logging']['log_queries'] ?? true;
    }

    private function logQueryStart(string $queryId, string $sql, array $parameters, string $connection): void
    {
        if (!$this->shouldLog() || !$this->config['logging']['log_queries']) return;
        $this->log('info', 'Enhanced V1 Query started', [
            'query_id' => $queryId, 'sql' => $sql,
            'parameters' => $parameters, 'connection' => $connection,
            'timestamp' => $this->getTimestamp(),
        ]);
    }

    private function logQueryComplete(string $queryId, string $sql, array $parameters, float $executionTime, string $connection, int $resultSets): void
    {
        if (!$this->shouldLog() || !$this->config['logging']['log_execution_time']) return;
        $this->log('info', 'Enhanced V1 Query completed', [
            'query_id' => $queryId, 'sql' => $sql,
            'parameters' => $parameters, 'execution_time' => round($executionTime, 4),
            'result_sets' => $resultSets, 'connection' => $connection,
            'timestamp' => $this->getTimestamp(),
        ]);
    }

    private function logSlowQuery(string $sql, array $parameters, float $executionTime, string $connection): void
    {
        if (!$this->shouldLog()) return;
        $this->log('warning', 'Enhanced V1 Slow query detected', [
            'sql' => $sql, 'parameters' => $parameters,
            'execution_time' => round($executionTime, 4),
            'threshold' => $this->config['performance']['slow_query_threshold'],
            'connection' => $connection, 'timestamp' => $this->getTimestamp(),
        ]);
    }

    private function logError(string $message, string $storedProcedureName, array $parameters, string $sql = '', string $connection = '', ?Exception $exception = null): void
    {
        if (!$this->shouldLog() || !$this->config['logging']['log_errors']) return;

        $context = [
            'service'               => 'EnhancedDBServiceV1',
            'message'               => $message,
            'stored_procedure_name' => $storedProcedureName,
            'parameters'            => $parameters,
            'sql'                   => $sql,
            'connection'            => $connection,
            'user_session'          => $this->getSafeSessionId(),
            'ip'                    => $this->getSafeIpAddress(),
            'timestamp'             => $this->getTimestamp(),
        ];

        if ($exception) {
            $context['exception'] = [
                'message' => $exception->getMessage(),
                'code'    => $exception->getCode(),
                'file'    => $exception->getFile(),
                'line'    => $exception->getLine(),
                'trace'   => $exception->getTraceAsString(),
            ];
        }

        $this->log('critical', $message, $context);
    }

    private function logRetryAttempt(string $storedProcedureName, array $parameters, Exception $exception, int $attempt, int $maxAttempts): void
    {
        if (!$this->shouldLog()) return;
        $this->log('warning',
            "Retrying stored procedure '{$storedProcedureName}' (attempt {$attempt}/{$maxAttempts}) due to: {$exception->getMessage()}",
            [
                'stored_procedure' => $storedProcedureName,
                'parameters' => json_encode($parameters),
                'attempt' => $attempt, 'max_attempts' => $maxAttempts,
                'error_message' => $exception->getMessage(),
                'error_code' => $exception->getCode(),
                'retryable' => $this->isRetryableError($exception),
                'connection_error' => $this->isConnectionError($exception),
            ]
        );
    }

    private function log(string $level, string $message, array $context = []): void
    {
        if (!$this->shouldLog()) return;

        try {
            if (class_exists(Log::class)) {
                $channel = $this->config['logging']['channel'] ?? 'single';
                Log::channel($channel)->{$level}($message, $context);
            } else {
                error_log(sprintf('[%s] %s %s', strtoupper($level), $message, json_encode($context)));
            }
        } catch (\Exception $e) {
            error_log(sprintf('[%s] %s %s (Log failed: %s)',
                strtoupper($level), $message, json_encode($context), $e->getMessage()));
        }
    }

    // ══════════════════════════════════════════════════════════════════
    //  Timeout / retry / error helpers
    // ══════════════════════════════════════════════════════════════════

    private function setQueryTimeout(\PDO $pdo, \PDOStatement $stmt, int $timeout): void
    {
        try {
            $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

            if ($driver === 'mysql') {
                $this->setMySQLTimeout($pdo, $timeout);
            } elseif ($driver !== 'sqlite' && method_exists($stmt, 'setAttribute')) {
                try {
                    $stmt->setAttribute(\PDO::ATTR_TIMEOUT, $timeout);
                } catch (\PDOException $e) { /* unsupported — ignore */ }
            }
        } catch (\Exception $e) {
            $this->log('warning', 'Failed to set query timeout', [
                'timeout' => $timeout, 'error' => $e->getMessage(),
            ]);
        }
    }

    private function setMySQLTimeout(\PDO $pdo, int $timeout): void
    {
        try {
            $pdo->exec("SET SESSION wait_timeout = {$timeout}");
            $pdo->exec("SET SESSION interactive_timeout = {$timeout}");
        } catch (\PDOException $e) {
            $this->log('warning', 'Could not set MySQL timeout variables', [
                'timeout' => $timeout, 'error' => $e->getMessage(),
            ]);
        }
    }

    private function isRetryableError(Exception $e): bool
    {
        $message = strtolower($e->getMessage());
        $code    = $e->getCode();

        $retryablePatterns = [
            'connection lost', 'connection timeout', 'connection refused',
            'connection reset', 'server has gone away',
            'lost connection to mysql server', 'mysql server has gone away',
            'lock wait timeout exceeded', 'table is locked',
            'deadlock found', 'deadlock detected',
            'too many connections', 'max_connections',
            'service temporarily unavailable', 'resource temporarily unavailable',
            'network error', 'timeout expired', 'operation timed out', 'broken pipe',
            'serialization failure', 'could not serialize', 'restart transaction',
        ];

        foreach ($retryablePatterns as $pattern) {
            if (str_contains($message, $pattern)) return true;
        }

        $retryableCodes = [1040, 1053, 1205, 1213, 2002, 2003, 2006, 2013];
        return in_array($code, $retryableCodes);
    }

    private function isConnectionError(Exception $e): bool
    {
        $message = strtolower($e->getMessage());
        $code    = $e->getCode();

        $connectionPatterns = [
            'connection lost', 'connection timeout', 'connection refused',
            'connection reset', 'server has gone away',
            'lost connection to mysql server', 'mysql server has gone away', 'broken pipe',
        ];

        foreach ($connectionPatterns as $pattern) {
            if (str_contains($message, $pattern)) return true;
        }

        return in_array($code, [2002, 2003, 2006, 2013]);
    }

    private function calculateRetryDelay(int $baseDelay, int $attempt): int
    {
        $delay = $baseDelay * pow(2, $attempt - 1);
        $jitter = 0.5 + (mt_rand() / mt_getrandmax());
        return min((int)($delay * $jitter), 30000);
    }

    // ══════════════════════════════════════════════════════════════════
    //  Utility
    // ══════════════════════════════════════════════════════════════════

    private function getTimestamp(): string
    {
        try {
            if (function_exists('now')) return now()->toISOString();
        } catch (\Exception $e) { /* fall through */ }
        return date('Y-m-d\TH:i:s.v\Z');
    }

    private function getSafeSessionId(): string
    {
        try {
            if (function_exists('session') && session() && method_exists(session(), 'getId')) {
                return session()->getId() ?? 'N/A';
            }
        } catch (\Exception $e) { /* ignore */ }
        try {
            if (session_status() === PHP_SESSION_ACTIVE) return session_id() ?: 'N/A';
        } catch (\Exception $e) { /* ignore */ }
        return 'N/A';
    }

    private function getSafeIpAddress(): string
    {
        try {
            if (function_exists('request') && request() && method_exists(request(), 'ip')) {
                return request()->ip() ?? 'N/A';
            }
        } catch (\Exception $e) { /* ignore */ }
        try {
            if (isset($_SERVER['HTTP_CLIENT_IP']))      return $_SERVER['HTTP_CLIENT_IP'];
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
            if (isset($_SERVER['REMOTE_ADDR']))          return $_SERVER['REMOTE_ADDR'];
        } catch (\Exception $e) { /* ignore */ }
        return 'N/A';
    }

    private function safeLogToQueryLog(string $connection, string $sql, array $parameters, float $executionTime): void
    {
        try {
            if (class_exists(DB::class)) {
                DB::connection($connection)->logQuery($sql, $parameters, $executionTime * 1000);
            }
        } catch (\Exception $e) {
            $this->log('warning', 'Failed to log query to Laravel query log', [
                'error' => $e->getMessage(), 'sql' => $sql, 'connection' => $connection,
            ]);
        }
    }
}
