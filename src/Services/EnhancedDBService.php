<?php

namespace Aotr\DynamicLevelHelper\Services;

use Aotr\DynamicLevelHelper\Services\DBConnectionPool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Exception;

/**
 * Enhanced Database Service with Singleton Pattern, Connection Pooling, and Configurable Logging
 *
 * Features:
 * - Singleton pattern for efficient resource management
 * - Connection pooling with automatic cleanup
 * - Configurable logging levels and channels
 * - Performance monitoring and slow query detection
 * - Cached stored procedure existence checks
 * - Automatic retry logic for failed connections
 */
class EnhancedDBService
{
    /**
     * @var EnhancedDBService|null Singleton instance
     */
    private static ?EnhancedDBService $instance = null;

    /**
     * @var DBConnectionPool Connection pool manager
     */
    private DBConnectionPool $connectionPool;

    /**
     * @var array Service configuration
     */
    private array $config;

    /**
     * @var string Default database connection
     */
    private string $defaultConnection;

    /**
     * @var array Performance metrics
     */
    private array $performanceMetrics = [];

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct()
    {
        $this->loadConfiguration();
        $this->connectionPool = new DBConnectionPool($this->config['connection_pool']);
        $this->defaultConnection = $this->config['default_connection'];
    }

    /**
     * Get singleton instance
     *
     * @return EnhancedDBService
     */
    public static function getInstance(): EnhancedDBService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Reset singleton instance (useful for testing)
     *
     * @return void
     */
    public static function resetInstance(): void
    {
        if (self::$instance !== null) {
            self::$instance->connectionPool->closeAllConnections();
            self::$instance = null;
        }
    }

    /**
     * Load configuration from Laravel config
     *
     * @return void
     */
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
            // Try to load from Laravel config if available
            if (function_exists('config')) {
                $this->config = config('dynamic-levels-helper.enhanced_db_service', $defaultConfig);
            } else {
                $this->config = $defaultConfig;
            }
        } catch (\Exception $e) {
            // Fall back to default config if Laravel config is not available
            $this->config = $defaultConfig;
        }
    }

    /**
     * Call a stored procedure with enhanced features
     *
     * @param string $storedProcedureName The name of the stored procedure to call
     * @param array $parameters Parameters to pass to the stored procedure
     * @param array $options Configuration options for the method
     *   - `connection` (string): Database connection to use
     *   - `checkStoredProcedure` (bool): Whether to check if the stored procedure exists
     *   - `enableLogging` (bool): Override logging configuration
     *   - `timeout` (int): Query timeout in seconds
     *   - `retryAttempts` (int): Number of retry attempts for transient failures
     *   - `retryDelay` (int): Base delay between retries in milliseconds
     *   - `returnExecutionInfo` (bool): Whether to return execution metadata (default: false)
     * @return array An array of result sets, or execution info if returnExecutionInfo is true
     * @throws RuntimeException If execution fails after all retries
     */
    public function callStoredProcedure(string $storedProcedureName, array $parameters = [], array $options = []): array
    {
        $options = array_merge([
            'connection' => $this->defaultConnection,
            'checkStoredProcedure' => false,
            'enableLogging' => null,
            'timeout' => 30,
            'retryAttempts' => null, // Use config default if not specified
            'retryDelay' => 100, // milliseconds
            'returnExecutionInfo' => false, // New option to return execution metadata
        ], $options);

        $connection = $options['connection'];
        $retryAttempts = $options['retryAttempts'] ?? $this->config['connection_pool']['retry_attempts'];
        $retryDelay = $options['retryDelay'];
        $returnExecutionInfo = $options['returnExecutionInfo'];

        $attempt = 0;
        $lastException = null;
        $totalStartTime = microtime(true);
        $executionHistory = [];

        while ($attempt <= $retryAttempts) {
            try {
                $result = $this->executeStoredProcedure($storedProcedureName, $parameters, $options, $attempt, $executionHistory);

                if ($returnExecutionInfo) {
                    $totalExecutionTime = microtime(true) - $totalStartTime;
                    return $this->buildExecutionInfo($storedProcedureName, $parameters, $result, $totalExecutionTime, $attempt, $executionHistory, $connection);
                }

                return $result;

            } catch (Exception $e) {
                $lastException = $e;
                $attempt++;

                // Record this attempt in execution history
                $executionHistory[] = [
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'retryable' => $this->isRetryableError($e),
                    'timestamp' => microtime(true),
                ];

                // Check if this is a retryable error
                if ($attempt <= $retryAttempts && $this->isRetryableError($e)) {
                    $this->logRetryAttempt($storedProcedureName, $parameters, $e, $attempt, $retryAttempts);

                    // Calculate delay with exponential backoff
                    $delay = $this->calculateRetryDelay($retryDelay, $attempt);
                    usleep($delay * 1000); // Convert to microseconds

                    // Reset connection pool if connection issue
                    if ($this->isConnectionError($e)) {
                        $this->connectionPool->closeAllConnections();
                    }

                    continue;
                }

                // Not retryable or max attempts reached
                break;
            }
        }

        // All retries failed
        $totalExecutionTime = microtime(true) - $totalStartTime;
        $errorMessage = "Database error in stored procedure '{$storedProcedureName}' after {$attempt} attempts: {$lastException->getMessage()}";
        $this->logError($errorMessage, $storedProcedureName, $parameters, '', $connection, $lastException);

        if ($returnExecutionInfo) {
            return $this->buildExecutionInfo($storedProcedureName, $parameters, null, $totalExecutionTime, $attempt, $executionHistory, $connection, $lastException);
        }

        throw new RuntimeException($errorMessage, 0, $lastException);
    }

    /**
     * Execute stored procedure (internal method for retry logic)
     *
     * @param string $storedProcedureName
     * @param array $parameters
     * @param array $options
     * @param int $attempt
     * @param array &$executionHistory
     * @return array
     * @throws Exception
     */
    private function executeStoredProcedure(string $storedProcedureName, array $parameters, array $options, int $attempt, array &$executionHistory = []): array
    {
        $connection = $options['connection'];
        $startTime = microtime(true);
        $queryId = $this->generateQueryId() . "_attempt_{$attempt}";

        // Check stored procedure existence if required
        if ($options['checkStoredProcedure'] && !$this->checkStoredProcedure($storedProcedureName, $connection)) {
            $errorMessage = "Stored Procedure '{$storedProcedureName}' does not exist";
            $this->logError($errorMessage, $storedProcedureName, $parameters, '', $connection);
            throw new RuntimeException($errorMessage);
        }

        // Prepare SQL
        $placeholders = implode(',', array_fill(0, count($parameters), '?'));
        $sql = "CALL {$storedProcedureName}({$placeholders})";

        // Get connection from pool
        $pdo = $this->connectionPool->getConnection($connection);

        // Log query start
        $this->logQueryStart($queryId, $sql, $parameters, $connection);

        try {
            // Execute query with timeout
            $stmt = $pdo->prepare($sql);

            if (!$stmt) {
                throw new RuntimeException("Failed to prepare statement for stored procedure '{$storedProcedureName}'");
            }

            // Set query timeout if enabled and supported by the driver
            if ($this->config['performance']['enable_query_timeout'] ?? true) {
                $this->setQueryTimeout($pdo, $stmt, $options['timeout']);
            }

            $result = $stmt->execute($parameters);

            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                throw new RuntimeException("Failed to execute stored procedure '{$storedProcedureName}': " . ($errorInfo[2] ?? 'Unknown error'));
            }

            // Fetch all result sets
            $resultSets = [];
            do {
                $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                if (!empty($resultSet) || empty($resultSets)) {
                    $resultSets[] = $resultSet;
                }
            } while ($stmt->nextRowset());

        } catch (\PDOException $e) {
            // Release connection back to pool on error
            $this->connectionPool->releaseConnection($connection);
            throw $e;
        } catch (\Exception $e) {
            // Release connection back to pool on error
            $this->connectionPool->releaseConnection($connection);
            throw $e;
        }

        // Calculate execution time
        $executionTime = microtime(true) - $startTime;
        
        // Store original result sets count before wrapping
        $originalResultCount = is_array($resultSets) ? count($resultSets) : 0;
        
        // Wrap result sets with additional metadata
        $resultSets = ["data"=>$resultSets,"raw_query"=>$sql,"parameters"=>$parameters];
        
        // Record successful execution in history
        $executionHistory[] = [
            'attempt' => $attempt + 1,
            'execution_time' => $executionTime,
            'result_sets' => $originalResultCount,
            'timestamp' => microtime(true),
            'success' => true,
        ];

        // Release connection back to pool
        $this->connectionPool->releaseConnection($connection);

        // Log successful execution - count the actual data array, not the wrapper
        $resultCount = isset($resultSets['data']) && is_array($resultSets['data']) ? count($resultSets['data']) : 0;
        $this->logQueryComplete($queryId, $sql, $parameters, $executionTime, $connection, $resultCount);

        // Check for slow queries
        if ($executionTime > $this->config['performance']['slow_query_threshold']) {
            $this->logSlowQuery($sql, $parameters, $executionTime, $connection);
        }

        // Store performance metrics - use the actual result count
        $this->recordPerformanceMetrics($storedProcedureName, $executionTime, $resultCount);

        // Log to Laravel's query log for Telescope compatibility
        if ($this->shouldLogToQueryLog()) {
            $this->safeLogToQueryLog($connection, $sql, $parameters, $executionTime);
        }

        return $resultSets;
    }

    /**
     * Build comprehensive execution information
     *
     * @param string $storedProcedureName
     * @param array $parameters
     * @param array|null $resultSets
     * @param float $totalExecutionTime
     * @param int $totalAttempts
     * @param array $executionHistory
     * @param string $connection
     * @param Exception|null $finalException
     * @return array
     */
    private function buildExecutionInfo(string $storedProcedureName, array $parameters, ?array $resultSets, float $totalExecutionTime, int $totalAttempts, array $executionHistory, string $connection, ?Exception $finalException = null): array
    {
        // Get current pool stats
        $poolStats = $this->getConnectionPoolStats();

        // Get procedure performance metrics
        $procedureMetrics = $this->performanceMetrics[$storedProcedureName] ?? null;

        // Calculate execution statistics
        $successfulAttempts = array_filter($executionHistory, fn($h) => $h['success'] ?? false);
        $failedAttempts = array_filter($executionHistory, fn($h) => !($h['success'] ?? false));

        // Safely extract result data and calculate counts
        $resultData = null;
        $resultSetsCount = 0;
        $rowsAffected = 0;

        if ($resultSets && is_array($resultSets)) {
            if (isset($resultSets['data']) && is_array($resultSets['data'])) {
                // New format: {data: [...], raw_query: "...", parameters: [...]}
                $resultData = $resultSets['data'];
                $resultSetsCount = count($resultData);
                $rowsAffected = array_sum(array_map(function($set) {
                    return is_array($set) ? count($set) : 0;
                }, $resultData));
            } else {
                // Old format: direct array of result sets
                $resultData = $resultSets;
                $resultSetsCount = count($resultSets);
                $rowsAffected = array_sum(array_map(function($set) {
                    return is_array($set) ? count($set) : 0;
                }, $resultSets));
            }
        }

        $executionInfo = [
            'success' => $finalException === null,
            'stored_procedure' => $storedProcedureName,
            'parameters' => $parameters,
            'connection' => $connection,
            'execution_summary' => [
                'total_execution_time' => round($totalExecutionTime, 4),
                'total_attempts' => $totalAttempts,
                'successful_attempts' => count($successfulAttempts),
                'failed_attempts' => count($failedAttempts),
                'result_sets_count' => $resultSetsCount,
                'rows_affected' => $rowsAffected,
            ],
            'connection_pool' => [
                'stats' => $poolStats,
                'connection_used' => $connection,
            ],
            'performance' => [
                'is_slow_query' => $totalExecutionTime > $this->config['performance']['slow_query_threshold'],
                'slow_query_threshold' => $this->config['performance']['slow_query_threshold'],
                'procedure_metrics' => $procedureMetrics,
            ],
            'retry_information' => [
                'retry_enabled' => $totalAttempts > 0,
                'max_retry_attempts' => $this->config['connection_pool']['retry_attempts'],
                'retry_base_delay' => $this->config['connection_pool']['retry_delay'],
                'execution_history' => $executionHistory,
            ],
            'configuration' => [
                'timeout' => $this->config['connection_pool']['pool_timeout'],
                'max_connections' => $this->config['connection_pool']['max_connections'],
                'logging_enabled' => $this->config['logging']['enabled'],
                'cache_enabled' => $this->config['cache']['enabled'],
            ],
            'timestamp' => [
                'started_at' => date('Y-m-d H:i:s', (int)(time() - $totalExecutionTime)),
                'completed_at' => date('Y-m-d H:i:s'),
                'timezone' => date_default_timezone_get(),
            ],
        ];

        // Add result data if successful
        if ($resultData !== null) {
            $executionInfo['data'] = $resultData;
            
            // Add raw query and parameters if available from new format
            if ($resultSets && is_array($resultSets)) {
                if (isset($resultSets['raw_query'])) {
                    $executionInfo['raw_query'] = $resultSets['raw_query'];
                }
                if (isset($resultSets['parameters'])) {
                    $executionInfo['query_parameters'] = $resultSets['parameters'];
                }
            }
        } else {
            $executionInfo['data'] = [];
        }

        // Add error information if failed
        if ($finalException !== null) {
            $executionInfo['error'] = [
                'message' => $finalException->getMessage(),
                'code' => $finalException->getCode(),
                'type' => get_class($finalException),
                'retryable' => $this->isRetryableError($finalException),
                'connection_error' => $this->isConnectionError($finalException),
            ];
        }

        return $executionInfo;
    }

    /**
     * Call stored procedure and return detailed execution information
     *
     * @param string $storedProcedureName
     * @param array $parameters
     * @param array $options
     * @return array Detailed execution information including results, timing, pool stats, etc.
     * @throws RuntimeException
     */
    public function callStoredProcedureWithInfo(string $storedProcedureName, array $parameters = [], array $options = []): array
    {
        $options['returnExecutionInfo'] = true;
        return $this->callStoredProcedure($storedProcedureName, $parameters, $options);
    }

    /**
     * Check if a stored procedure exists with caching
     *
     * @param string $procedureName
     * @param string $connection
     * @return bool
     */
    private function checkStoredProcedure(string $procedureName, string $connection): bool
    {
        if (!$this->config['cache']['enabled']) {
            return $this->checkStoredProcedureInDatabase($procedureName, $connection);
        }

        try {
            $cacheKey = "enhanced_sp_exists_{$connection}_{$procedureName}";

            if (class_exists('\Illuminate\Support\Facades\Cache')) {
                return Cache::remember($cacheKey, $this->config['cache']['procedure_exists_ttl'], function () use ($procedureName, $connection) {
                    return $this->checkStoredProcedureInDatabase($procedureName, $connection);
                });
            } else {
                // Fall back to direct database check if Cache facade is not available
                return $this->checkStoredProcedureInDatabase($procedureName, $connection);
            }
        } catch (\Exception $e) {
            // Fall back to direct database check if caching fails
            $this->log('warning', 'Cache check failed, falling back to database check', [
                'error' => $e->getMessage(),
                'procedure' => $procedureName,
                'connection' => $connection
            ]);
            return $this->checkStoredProcedureInDatabase($procedureName, $connection);
        }
    }

    /**
     * Check stored procedure existence in database
     *
     * @param string $procedureName
     * @param string $connection
     * @return bool
     */
    private function checkStoredProcedureInDatabase(string $procedureName, string $connection): bool
    {
        try {
            if (!class_exists('\Illuminate\Support\Facades\DB')) {
                $this->log('warning', 'Laravel DB facade not available for stored procedure check', [
                    'procedure' => $procedureName,
                    'connection' => $connection
                ]);
                return true; // Assume procedure exists if we can't check
            }

            return DB::connection($connection)
                ->table('information_schema.routines')
                ->where('SPECIFIC_NAME', $procedureName)
                ->where('ROUTINE_SCHEMA', DB::connection($connection)->getDatabaseName())
                ->exists();
        } catch (Exception $e) {
            $this->logError("Failed to check stored procedure existence: {$e->getMessage()}", $procedureName, [], '', $connection, $e);
            return true; // Assume procedure exists if check fails to avoid blocking execution
        }
    }

    /**
     * Get connection pool statistics
     *
     * @return array
     */
    public function getConnectionPoolStats(): array
    {
        return $this->connectionPool->getPoolStats();
    }

    /**
     * Get performance metrics
     *
     * @return array
     */
    public function getPerformanceMetrics(): array
    {
        return $this->performanceMetrics;
    }

    /**
     * Clear performance metrics
     *
     * @return void
     */
    public function clearPerformanceMetrics(): void
    {
        $this->performanceMetrics = [];
    }

    /**
     * Generate unique query ID for tracking
     *
     * @return string
     */
    private function generateQueryId(): string
    {
        return uniqid('enhanced_query_', true);
    }

    /**
     * Record performance metrics
     *
     * @param string $procedureName
     * @param float $executionTime
     * @param int $resultSets
     * @return void
     */
    private function recordPerformanceMetrics(string $procedureName, float $executionTime, int $resultSets): void
    {
        if (!$this->config['performance']['enable_query_profiling']) {
            return;
        }

        if (!isset($this->performanceMetrics[$procedureName])) {
            $this->performanceMetrics[$procedureName] = [
                'total_calls' => 0,
                'total_time' => 0,
                'avg_time' => 0,
                'min_time' => PHP_FLOAT_MAX,
                'max_time' => 0,
                'total_result_sets' => 0,
            ];
        }

        $metrics = &$this->performanceMetrics[$procedureName];
        $metrics['total_calls']++;
        $metrics['total_time'] += $executionTime;
        $metrics['avg_time'] = $metrics['total_time'] / $metrics['total_calls'];
        $metrics['min_time'] = min($metrics['min_time'], $executionTime);
        $metrics['max_time'] = max($metrics['max_time'], $executionTime);
        $metrics['total_result_sets'] += $resultSets;
    }

    /**
     * Log query start
     *
     * @param string $queryId
     * @param string $sql
     * @param array $parameters
     * @param string $connection
     * @return void
     */
    private function logQueryStart(string $queryId, string $sql, array $parameters, string $connection): void
    {
        if (!$this->shouldLog() || !$this->config['logging']['log_queries']) {
            return;
        }

        $this->log('info', 'Enhanced Query started', [
            'query_id' => $queryId,
            'sql' => $sql,
            'parameters' => $parameters,
            'connection' => $connection,
            'timestamp' => $this->getTimestamp(),
        ]);
    }

    /**
     * Log query completion
     *
     * @param string $queryId
     * @param string $sql
     * @param array $parameters
     * @param float $executionTime
     * @param string $connection
     * @param int $resultSets
     * @return void
     */
    private function logQueryComplete(string $queryId, string $sql, array $parameters, float $executionTime, string $connection, int $resultSets): void
    {
        if (!$this->shouldLog() || !$this->config['logging']['log_execution_time']) {
            return;
        }

        $this->log('info', 'Enhanced Query completed', [
            'query_id' => $queryId,
            'sql' => $sql,
            'parameters' => $parameters,
            'execution_time' => round($executionTime, 4),
            'result_sets' => $resultSets,
            'connection' => $connection,
            'timestamp' => $this->getTimestamp(),
        ]);
    }

    /**
     * Log query error
     *
     * @param string $queryId
     * @param string $sql
     * @param array $parameters
     * @param float $executionTime
     * @param string $connection
     * @param Exception $exception
     * @return void
     */
    private function logQueryError(string $queryId, string $sql, array $parameters, float $executionTime, string $connection, Exception $exception): void
    {
        if (!$this->shouldLog() || !$this->config['logging']['log_errors']) {
            return;
        }

        $this->log('error', 'Enhanced Query failed', [
            'query_id' => $queryId,
            'sql' => $sql,
            'parameters' => $parameters,
            'execution_time' => round($executionTime, 4),
            'connection' => $connection,
            'error_message' => $exception->getMessage(),
            'error_code' => $exception->getCode(),
            'timestamp' => $this->getTimestamp(),
        ]);
    }

    /**
     * Log slow query
     *
     * @param string $sql
     * @param array $parameters
     * @param float $executionTime
     * @param string $connection
     * @return void
     */
    private function logSlowQuery(string $sql, array $parameters, float $executionTime, string $connection): void
    {
        if (!$this->shouldLog()) {
            return;
        }

        $this->log('warning', 'Enhanced Slow query detected', [
            'sql' => $sql,
            'parameters' => $parameters,
            'execution_time' => round($executionTime, 4),
            'threshold' => $this->config['performance']['slow_query_threshold'],
            'connection' => $connection,
            'timestamp' => $this->getTimestamp(),
        ]);
    }

    /**
     * Log error messages
     *
     * @param string $message
     * @param string $storedProcedureName
     * @param array $parameters
     * @param string $sql
     * @param string $connection
     * @param Exception|null $exception
     * @return void
     */
    private function logError(string $message, string $storedProcedureName, array $parameters, string $sql = '', string $connection = '', Exception $exception = null): void
    {
        if (!$this->shouldLog() || !$this->config['logging']['log_errors']) {
            return;
        }

        $context = [
            'service' => 'EnhancedDBService',
            'message' => $message,
            'stored_procedure_name' => $storedProcedureName,
            'parameters' => $parameters,
            'sql' => $sql,
            'connection' => $connection,
            'user_session' => $this->getSafeSessionId(),
            'ip' => $this->getSafeIpAddress(),
            'timestamp' => $this->getTimestamp(),
        ];

        if ($exception) {
            $context['exception'] = [
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ];
        }

        $this->log('critical', $message, $context);
    }

    /**
     * Generic logging method
     *
     * @param string $level
     * @param string $message
     * @param array $context
     * @return void
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if (!$this->shouldLog()) {
            return;
        }

        try {
            if (!class_exists('\Illuminate\Support\Facades\Log')) {
                // Fall back to error_log if Laravel Log facade is not available
                $logMessage = sprintf('[%s] %s %s', strtoupper($level), $message, json_encode($context));
                error_log($logMessage);
                return;
            }

            $channel = $this->config['logging']['channel'] ?? 'single';
            Log::channel($channel)->{$level}($message, $context);
        } catch (\Exception $e) {
            // Fall back to error_log if Laravel logging fails
            $logMessage = sprintf('[%s] %s %s (Laravel logging failed: %s)', 
                strtoupper($level), $message, json_encode($context), $e->getMessage());
            error_log($logMessage);
        }
    }

    /**
     * Check if logging is enabled
     *
     * @return bool
     */
    private function shouldLog(): bool
    {
        return $this->config['logging']['enabled'] ?? true;
    }

    /**
     * Check if should log to Laravel query log
     *
     * @return bool
     */
    private function shouldLogToQueryLog(): bool
    {
        return $this->config['logging']['log_queries'] ?? true;
    }

    /**
     * Set query timeout with driver compatibility checks
     *
     * @param \PDO $pdo
     * @param \PDOStatement $stmt
     * @param int $timeout
     * @return void
     */
    private function setQueryTimeout(\PDO $pdo, \PDOStatement $stmt, int $timeout): void
    {
        try {
            $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

            // Only set timeout for drivers that support it
            switch ($driver) {
                case 'mysql':
                    // MySQL supports query timeout through connection options
                    // Set it on the connection level if not already set
                    $this->setMySQLTimeout($pdo, $timeout);
                    break;

                case 'pgsql':
                    // PostgreSQL supports statement timeout
                    if (method_exists($stmt, 'setAttribute')) {
                        try {
                            $stmt->setAttribute(\PDO::ATTR_TIMEOUT, $timeout);
                        } catch (\PDOException $e) {
                            // Silently ignore if not supported
                        }
                    }
                    break;

                case 'sqlite':
                    // SQLite doesn't need query timeout for stored procedures
                    break;

                default:
                    // For other drivers, try to set timeout but catch exceptions
                    if (method_exists($stmt, 'setAttribute')) {
                        try {
                            $stmt->setAttribute(\PDO::ATTR_TIMEOUT, $timeout);
                        } catch (\PDOException $e) {
                            // Log warning but don't fail
                            $this->log('warning', 'Could not set query timeout for driver: ' . $driver, [
                                'driver' => $driver,
                                'timeout' => $timeout,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                    break;
            }
        } catch (\Exception $e) {
            // If we can't determine driver or set timeout, log but continue
            $this->log('warning', 'Failed to set query timeout', [
                'timeout' => $timeout,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Set MySQL specific timeout options
     *
     * @param \PDO $pdo
     * @param int $timeout
     * @return void
     */
    private function setMySQLTimeout(\PDO $pdo, int $timeout): void
    {
        try {
            // Set MySQL session timeout variables
            $pdo->exec("SET SESSION wait_timeout = {$timeout}");
            $pdo->exec("SET SESSION interactive_timeout = {$timeout}");
        } catch (\PDOException $e) {
            // Log warning but don't fail
            $this->log('warning', 'Could not set MySQL timeout variables', [
                'timeout' => $timeout,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check if an error is retryable
     *
     * @param Exception $e
     * @return bool
     */
    private function isRetryableError(Exception $e): bool
    {
        $message = strtolower($e->getMessage());
        $code = $e->getCode();

        // Common retryable error patterns
        $retryablePatterns = [
            // Connection issues
            'connection lost',
            'connection timeout',
            'connection refused',
            'connection reset',
            'server has gone away',
            'lost connection to mysql server',
            'mysql server has gone away',

            // Lock timeouts
            'lock wait timeout exceeded',
            'table is locked',
            'deadlock found',
            'deadlock detected',

            // Temporary unavailability
            'too many connections',
            'max_connections',
            'service temporarily unavailable',
            'resource temporarily unavailable',

            // Network issues
            'network error',
            'timeout expired',
            'operation timed out',
            'broken pipe',

            // Transaction conflicts
            'serialization failure',
            'could not serialize',
            'restart transaction',
        ];

        // Check message patterns
        foreach ($retryablePatterns as $pattern) {
            if (strpos($message, $pattern) !== false) {
                return true;
            }
        }

        // Check specific error codes (MySQL/MariaDB)
        $retryableCodes = [
            1040, // ER_CON_COUNT_ERROR - Too many connections
            1053, // ER_SERVER_SHUTDOWN - Server shutdown in progress
            1205, // ER_LOCK_WAIT_TIMEOUT - Lock wait timeout exceeded
            1213, // ER_LOCK_DEADLOCK - Deadlock found when trying to get lock
            2002, // CR_CONNECTION_ERROR - Can't connect to local MySQL server
            2003, // CR_CONN_HOST_ERROR - Can't connect to MySQL server
            2006, // CR_SERVER_GONE_ERROR - MySQL server has gone away
            2013, // CR_SERVER_LOST - Lost connection to MySQL server during query
        ];

        if (in_array($code, $retryableCodes)) {
            return true;
        }

        return false;
    }

    /**
     * Check if an error is a connection-related error
     *
     * @param Exception $e
     * @return bool
     */
    private function isConnectionError(Exception $e): bool
    {
        $message = strtolower($e->getMessage());
        $code = $e->getCode();

        $connectionPatterns = [
            'connection lost',
            'connection timeout',
            'connection refused',
            'connection reset',
            'server has gone away',
            'lost connection to mysql server',
            'mysql server has gone away',
            'broken pipe',
        ];

        foreach ($connectionPatterns as $pattern) {
            if (strpos($message, $pattern) !== false) {
                return true;
            }
        }

        $connectionCodes = [2002, 2003, 2006, 2013];
        return in_array($code, $connectionCodes);
    }

    /**
     * Calculate retry delay with exponential backoff
     *
     * @param int $baseDelay Base delay in milliseconds
     * @param int $attempt Current attempt number
     * @return int Delay in milliseconds
     */
    private function calculateRetryDelay(int $baseDelay, int $attempt): int
    {
        // Exponential backoff: baseDelay * 2^(attempt-1) with jitter
        $delay = $baseDelay * pow(2, $attempt - 1);

        // Add jitter (random factor between 0.5 and 1.5 to prevent thundering herd)
        $jitter = 0.5 + (mt_rand() / mt_getrandmax());
        $delay = (int)($delay * $jitter);

        // Cap maximum delay at 30 seconds
        return min($delay, 30000);
    }

    /**
     * Log retry attempt
     *
     * @param string $storedProcedureName
     * @param array $parameters
     * @param Exception $exception
     * @param int $attempt
     * @param int $maxAttempts
     */
    private function logRetryAttempt(string $storedProcedureName, array $parameters, Exception $exception, int $attempt, int $maxAttempts): void
    {
        if (!$this->shouldLog()) {
            return;
        }

        $context = [
            'stored_procedure' => $storedProcedureName,
            'parameters' => json_encode($parameters),
            'attempt' => $attempt,
            'max_attempts' => $maxAttempts,
            'error_message' => $exception->getMessage(),
            'error_code' => $exception->getCode(),
            'retryable' => $this->isRetryableError($exception),
            'connection_error' => $this->isConnectionError($exception),
        ];

        $this->log('warning',
            "Retrying stored procedure '{$storedProcedureName}' (attempt {$attempt}/{$maxAttempts}) due to: {$exception->getMessage()}",
            $context
        );
    }

    /**
     * Prevent cloning of singleton
     */
    public function __clone()
    {
        throw new RuntimeException('Cannot clone singleton EnhancedDBService');
    }

    /**
     * Prevent unserialization of singleton
     */
    public function __wakeup()
    {
        throw new RuntimeException('Cannot unserialize singleton EnhancedDBService');
    }

    /**
     * Clean up connections on destruction
     */
    public function __destruct()
    {
        if ($this->connectionPool) {
            $this->connectionPool->closeAllConnections();
        }
    }

    /**
     * Get safe timestamp string
     *
     * @return string
     */
    private function getTimestamp(): string
    {
        try {
            // Try to use Laravel's now() helper if available
            if (function_exists('now')) {
                return now()->toISOString();
            }
        } catch (\Exception $e) {
            // Fall back if Laravel helpers fail
        }
        
        // Use standard PHP date formatting
        return date('Y-m-d\TH:i:s.v\Z');
    }

    /**
     * Get safe session ID
     *
     * @return string
     */
    private function getSafeSessionId(): string
    {
        try {
            // Try to use Laravel's session() helper if available
            if (function_exists('session') && session() && method_exists(session(), 'getId')) {
                return session()->getId() ?? 'N/A';
            }
        } catch (\Exception $e) {
            // Fall back if Laravel helpers fail
        }
        
        // Try PHP session
        try {
            if (session_status() === PHP_SESSION_ACTIVE) {
                return session_id() ?: 'N/A';
            }
        } catch (\Exception $e) {
            // Ignore session errors
        }
        
        return 'N/A';
    }

    /**
     * Get safe IP address
     *
     * @return string
     */
    private function getSafeIpAddress(): string
    {
        try {
            // Try to use Laravel's request() helper if available
            if (function_exists('request') && request() && method_exists(request(), 'ip')) {
                return request()->ip() ?? 'N/A';
            }
        } catch (\Exception $e) {
            // Fall back if Laravel helpers fail
        }
        
        // Try standard PHP methods
        try {
            if (isset($_SERVER['HTTP_CLIENT_IP'])) {
                return $_SERVER['HTTP_CLIENT_IP'];
            } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                return $_SERVER['HTTP_X_FORWARDED_FOR'];
            } elseif (isset($_SERVER['REMOTE_ADDR'])) {
                return $_SERVER['REMOTE_ADDR'];
            }
        } catch (\Exception $e) {
            // Ignore server variable errors
        }
        
        return 'N/A';
    }

    /**
     * Safe wrapper for Laravel's DB query logging
     *
     * @param string $connection
     * @param string $sql
     * @param array $parameters
     * @param float $executionTime
     * @return void
     */
    private function safeLogToQueryLog(string $connection, string $sql, array $parameters, float $executionTime): void
    {
        try {
            if (class_exists('\Illuminate\Support\Facades\DB') && method_exists(DB::class, 'connection')) {
                DB::connection($connection)->logQuery($sql, $parameters, $executionTime * 1000);
            }
        } catch (\Exception $e) {
            // Silently ignore if Laravel DB logging fails
            $this->log('warning', 'Failed to log query to Laravel query log', [
                'error' => $e->getMessage(),
                'sql' => $sql,
                'connection' => $connection
            ]);
        }
    }
}
