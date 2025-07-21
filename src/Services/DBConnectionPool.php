<?php

namespace Aotr\DynamicLevelHelper\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use RuntimeException;

/**
 * Database Connection Pool Manager
 *
 * Manages database connections with pooling, retry logic, and performance monitoring.
 */
class DBConnectionPool
{
    /**
     * @var array Pool of database connections
     */
    private static array $connectionPool = [];

    /**
     * @var array Connection usage tracking
     */
    private static array $connectionUsage = [];

    /**
     * @var array Configuration
     */
    private array $config;

    /**
     * @var int Current pool size
     */
    private static int $currentPoolSize = 0;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'max_connections' => 10,
            'pool_timeout' => 30,
            'idle_timeout' => 300,
            'retry_attempts' => 3,
        ], $config);
    }

    /**
     * Get a connection from the pool with validation
     *
     * @param string $connectionName
     * @return \PDO
     * @throws RuntimeException
     */
    public function getConnection(string $connectionName): \PDO
    {
        $connectionKey = $this->getConnectionKey($connectionName);

        // Clean up idle connections first
        $this->cleanupIdleConnections();

        // Try to get existing connection and validate it
        if (isset(self::$connectionPool[$connectionKey])) {
            $connection = self::$connectionPool[$connectionKey];

            // Validate connection is still alive
            if ($this->validateConnection($connection)) {
                $this->updateConnectionUsage($connectionKey);
                return $connection;
            } else {
                // Remove dead connection
                $this->removeConnection($connectionKey);
            }
        }

        // Create new connection if pool not full
        if (self::$currentPoolSize < $this->config['max_connections']) {
            return $this->createConnection($connectionName, $connectionKey);
        }

        // Wait for available connection or timeout
        $startTime = time();
        while ((time() - $startTime) < $this->config['pool_timeout']) {
            $this->cleanupIdleConnections();

            if (self::$currentPoolSize < $this->config['max_connections']) {
                return $this->createConnection($connectionName, $connectionKey);
            }

            usleep(100000); // Wait 100ms before retry
        }

        throw new RuntimeException('Connection pool timeout: Unable to acquire database connection');
    }

    /**
     * Validate if a connection is still alive
     *
     * @param \PDO $connection
     * @return bool
     */
    private function validateConnection(\PDO $connection): bool
    {
        try {
            // Simple ping query to check if connection is alive
            $stmt = $connection->query('SELECT 1');
            return $stmt !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Remove a connection from the pool
     *
     * @param string $connectionKey
     */
    private function removeConnection(string $connectionKey): void
    {
        if (isset(self::$connectionPool[$connectionKey])) {
            unset(self::$connectionPool[$connectionKey]);
            unset(self::$connectionUsage[$connectionKey]);
            self::$currentPoolSize--;
        }
    }

    /**
     * Create a new database connection
     *
     * @param string $connectionName
     * @param string $connectionKey
     * @return \PDO
     * @throws RuntimeException
     */
    private function createConnection(string $connectionName, string $connectionKey): \PDO
    {
        $attempts = 0;
        $lastException = null;

        while ($attempts < $this->config['retry_attempts']) {
            try {
                $pdo = DB::connection($connectionName)->getPdo();

                // Store in pool
                self::$connectionPool[$connectionKey] = $pdo;
                self::$connectionUsage[$connectionKey] = time();
                self::$currentPoolSize++;

                $this->logConnectionEvent('connection_created', $connectionName, [
                    'pool_size' => self::$currentPoolSize,
                    'attempt' => $attempts + 1
                ]);

                return $pdo;

            } catch (Exception $e) {
                $lastException = $e;
                $attempts++;

                if ($attempts < $this->config['retry_attempts']) {
                    // Exponential backoff
                    $delay = pow(2, $attempts) * 100000; // microseconds
                    usleep($delay);
                }
            }
        }

        throw new RuntimeException(
            "Failed to create database connection after {$attempts} attempts: " .
            ($lastException ? $lastException->getMessage() : 'Unknown error')
        );
    }

    /**
     * Release a connection back to the pool
     *
     * @param string $connectionName
     * @return void
     */
    public function releaseConnection(string $connectionName): void
    {
        $connectionKey = $this->getConnectionKey($connectionName);

        if (isset(self::$connectionPool[$connectionKey])) {
            $this->updateConnectionUsage($connectionKey);
        }
    }

    /**
     * Clean up idle connections
     *
     * @return void
     */
    private function cleanupIdleConnections(): void
    {
        $currentTime = time();
        $removedConnections = 0;

        foreach (self::$connectionUsage as $connectionKey => $lastUsed) {
            if (($currentTime - $lastUsed) > $this->config['idle_timeout']) {
                unset(self::$connectionPool[$connectionKey]);
                unset(self::$connectionUsage[$connectionKey]);
                self::$currentPoolSize--;
                $removedConnections++;
            }
        }

        if ($removedConnections > 0) {
            $this->logConnectionEvent('idle_cleanup', 'pool', [
                'removed_connections' => $removedConnections,
                'pool_size' => self::$currentPoolSize
            ]);
        }
    }

    /**
     * Get connection pool statistics
     *
     * @return array
     */
    public function getPoolStats(): array
    {
        return [
            'current_pool_size' => self::$currentPoolSize,
            'max_connections' => $this->config['max_connections'],
            'active_connections' => count(self::$connectionPool),
            'connection_usage' => self::$connectionUsage,
            'pool_utilization' => round((self::$currentPoolSize / $this->config['max_connections']) * 100, 2),
        ];
    }

    /**
     * Force close all connections in the pool
     *
     * @return void
     */
    public function closeAllConnections(): void
    {
        $closedConnections = count(self::$connectionPool);

        self::$connectionPool = [];
        self::$connectionUsage = [];
        self::$currentPoolSize = 0;

        $this->logConnectionEvent('pool_reset', 'pool', [
            'closed_connections' => $closedConnections
        ]);
    }

    /**
     * Generate connection key
     *
     * @param string $connectionName
     * @return string
     */
    private function getConnectionKey(string $connectionName): string
    {
        return 'db_pool_' . $connectionName . '_' . getmypid();
    }

    /**
     * Update connection usage timestamp
     *
     * @param string $connectionKey
     * @return void
     */
    private function updateConnectionUsage(string $connectionKey): void
    {
        self::$connectionUsage[$connectionKey] = time();
    }

    /**
     * Log connection events
     *
     * @param string $event
     * @param string $connection
     * @param array $context
     * @return void
     */
    private function logConnectionEvent(string $event, string $connection, array $context = []): void
    {
        if (config('dynamic-levels-helper.db_service.logging.enabled', true)) {
            $channel = config('dynamic-levels-helper.db_service.logging.channel', 'stp');

            Log::channel($channel)->info("DB Connection Pool - {$event}", array_merge([
                'connection' => $connection,
                'event' => $event,
                'timestamp' => now()->toISOString(),
            ], $context));
        }
    }
}
