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
                
                // Store connection in pool
                self::$connectionPool[$connectionKey] = $pdo;
                self::$connectionUsage[$connectionKey] = time();
                self::$currentPoolSize++;
                
                $this->logConnectionEvent('connection_created', $connectionName, [
                    'pool_size' => self::$currentPoolSize,
                    'max_connections' => $this->config['max_connections'],
                ]);
                
                return $pdo;
                
            } catch (Exception $e) {
                $lastException = $e;
                $attempts++;
                
                if ($attempts < $this->config['retry_attempts']) {
                    usleep(100000); // Wait 100ms before retry
                }
            }
        }
        
        throw new RuntimeException(
            "Failed to create database connection after {$attempts} attempts: " . $lastException->getMessage(),
            0,
            $lastException
        );
    }

    /**
     * Close all connections and reset pool
     */
    public function closeAllConnections(): void
    {
        self::$connectionPool = [];
        self::$connectionUsage = [];
        self::$currentPoolSize = 0;
    }

    /**
     * Release a connection back to the pool
     *
     * @param string $connectionName
     */
    public function releaseConnection(string $connectionName): void
    {
        $connectionKey = $this->getConnectionKey($connectionName);
        
        if (isset(self::$connectionUsage[$connectionKey])) {
            self::$connectionUsage[$connectionKey] = time();
        }
    }

    /**
     * Clean up idle connections
     */
    public function cleanupIdleConnections(): void
    {
        $now = time();
        $idleTimeout = $this->config['idle_timeout'];
        
        foreach (self::$connectionUsage as $key => $lastUsed) {
            if (($now - $lastUsed) > $idleTimeout) {
                unset(self::$connectionPool[$key]);
                unset(self::$connectionUsage[$key]);
                self::$currentPoolSize--;
                
                $this->logConnectionEvent('connection_idle_cleanup', $key, [
                    'idle_time' => $now - $lastUsed,
                    'pool_size' => self::$currentPoolSize,
                ]);
            }
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
            'pool_utilization' => self::$currentPoolSize > 0 
                ? round((self::$currentPoolSize / $this->config['max_connections']) * 100, 2) 
                : 0,
        ];
    }

    /**
     * Update connection usage timestamp
     *
     * @param string $connectionKey
     */
    private function updateConnectionUsage(string $connectionKey): void
    {
        self::$connectionUsage[$connectionKey] = time();
    }

    /**
     * Generate connection key
     *
     * @param string $connectionName
     * @return string
     */
    private function getConnectionKey(string $connectionName): string
    {
        return $connectionName . '_' . getmypid();
    }

    /**
     * Log connection pool events
     *
     * @param string $event
     * @param string $connection
     * @param array $context
     * @return void
     */
    private function logConnectionEvent(string $event, string $connection, array $context = []): void
    {
        try {
            if (function_exists('config') && config('dynamic-levels-helper.db_service.logging.enabled', true)) {
                $channel = config('dynamic-levels-helper.db_service.logging.channel', 'stp');

                Log::channel($channel)->info("DB Connection Pool - {$event}", array_merge([
                    'connection' => $connection,
                    'event' => $event,
                    'timestamp' => now()->toISOString(),
                ], $context));
            }
        } catch (\Exception $e) {
            // Silently fail for logging issues
        }
    }
}
