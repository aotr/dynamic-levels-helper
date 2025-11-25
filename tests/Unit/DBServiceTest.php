<?php

namespace Aotr\DynamicLevelHelper\Tests\Unit;

use Aotr\DynamicLevelHelper\Services\DBService;
use Aotr\DynamicLevelHelper\Tests\PackageTestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class DBServiceTest extends PackageTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set up test configuration
        Config::set('dynamic-levels-helper.enhanced_db_service', [
            'default_connection' => 'testing',
            'logging' => [
                'enabled' => true,
                'channel' => 'testing',
                'log_queries' => true,
                'log_errors' => true,
                'log_execution_time' => true,
            ],
            'connection_pool' => [
                'max_connections' => 5,
                'pool_timeout' => 10,
                'idle_timeout' => 60,
                'retry_attempts' => 2,
                'retry_delay' => 100,
            ],
            'cache' => [
                'procedure_exists_ttl' => 3600,
                'enabled' => true,
            ],
            'performance' => [
                'slow_query_threshold' => 1.0,
                'enable_query_profiling' => true,
            ],
        ]);
    }

    protected function tearDown(): void
    {
        // Reset singleton instance after each test
        EnhancedDBService::resetInstance();
        parent::tearDown();
    }

    public function test_singleton_instance_creation()
    {
        $instance1 = EnhancedDBService::getInstance();
        $instance2 = EnhancedDBService::getInstance();

        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(EnhancedDBService::class, $instance1);
    }

    public function test_singleton_cannot_be_cloned()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot clone singleton EnhancedDBService');

        $instance = EnhancedDBService::getInstance();
        clone $instance;
    }

    public function test_connection_pool_stats()
    {
        $dbService = EnhancedDBService::getInstance();
        $stats = $dbService->getConnectionPoolStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('current_pool_size', $stats);
        $this->assertArrayHasKey('max_connections', $stats);
        $this->assertArrayHasKey('active_connections', $stats);
        $this->assertArrayHasKey('pool_utilization', $stats);

        $this->assertEquals(5, $stats['max_connections']);
    }

    public function test_performance_metrics_initialization()
    {
        $dbService = EnhancedDBService::getInstance();
        $metrics = $dbService->getPerformanceMetrics();

        $this->assertIsArray($metrics);
        $this->assertEmpty($metrics); // Should be empty initially
    }

    public function test_performance_metrics_clear()
    {
        $dbService = EnhancedDBService::getInstance();
        $dbService->clearPerformanceMetrics();
        $metrics = $dbService->getPerformanceMetrics();

        $this->assertEmpty($metrics);
    }

    public function test_facade_binding()
    {
        $this->assertTrue($this->app->bound(EnhancedDBService::class));
        $this->assertTrue($this->app->bound('enhanced.db.service'));
    }

    public function test_service_provider_registration()
    {
        $dbService1 = $this->app->make(EnhancedDBService::class);
        $dbService2 = $this->app->make('enhanced.db.service');
        $dbService3 = EnhancedDBService::getInstance();

        $this->assertSame($dbService1, $dbService2);
        $this->assertSame($dbService2, $dbService3);
    }

    public function test_reset_instance()
    {
        $instance1 = EnhancedDBService::getInstance();
        EnhancedDBService::resetInstance();
        $instance2 = EnhancedDBService::getInstance();

        $this->assertNotSame($instance1, $instance2);
    }

    public function test_configuration_loading()
    {
        $dbService = EnhancedDBService::getInstance();
        $stats = $dbService->getConnectionPoolStats();

        // Verify configuration was loaded correctly
        $this->assertEquals(5, $stats['max_connections']);
    }

    public function test_retry_configuration()
    {
        // Test default retry configuration
        $dbService = EnhancedDBService::getInstance();

        // Access the config through reflection to test retry settings
        $reflection = new \ReflectionClass($dbService);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $config = $configProperty->getValue($dbService);

        $this->assertEquals(2, $config['connection_pool']['retry_attempts']);
        $this->assertEquals(100, $config['connection_pool']['retry_delay']);
    }

    public function test_retry_error_detection()
    {
        $dbService = EnhancedDBService::getInstance();
        $reflection = new \ReflectionClass($dbService);

        // Test isRetryableError method
        $isRetryableErrorMethod = $reflection->getMethod('isRetryableError');
        $isRetryableErrorMethod->setAccessible(true);

        // Test connection timeout error
        $timeoutException = new \Exception('Connection timeout', 2002);
        $this->assertTrue($isRetryableErrorMethod->invoke($dbService, $timeoutException));

        // Test deadlock error
        $deadlockException = new \Exception('Deadlock found when trying to get lock', 1213);
        $this->assertTrue($isRetryableErrorMethod->invoke($dbService, $deadlockException));

        // Test non-retryable error
        $syntaxException = new \Exception('Syntax error in SQL', 1064);
        $this->assertFalse($isRetryableErrorMethod->invoke($dbService, $syntaxException));

        // Test connection error detection
        $isConnectionErrorMethod = $reflection->getMethod('isConnectionError');
        $isConnectionErrorMethod->setAccessible(true);

        $connectionException = new \Exception('MySQL server has gone away', 2006);
        $this->assertTrue($isConnectionErrorMethod->invoke($dbService, $connectionException));
    }

    public function test_retry_delay_calculation()
    {
        $dbService = EnhancedDBService::getInstance();
        $reflection = new \ReflectionClass($dbService);

        $calculateRetryDelayMethod = $reflection->getMethod('calculateRetryDelay');
        $calculateRetryDelayMethod->setAccessible(true);

        // Test exponential backoff
        $delay1 = $calculateRetryDelayMethod->invoke($dbService, 100, 1);
        $delay2 = $calculateRetryDelayMethod->invoke($dbService, 100, 2);
        $delay3 = $calculateRetryDelayMethod->invoke($dbService, 100, 3);

        $this->assertGreaterThan(0, $delay1);
        $this->assertGreaterThan($delay1, $delay2);
        $this->assertGreaterThan($delay2, $delay3);

        // Test maximum delay cap (30 seconds = 30000ms)
        $delayLarge = $calculateRetryDelayMethod->invoke($dbService, 100, 20);
        $this->assertLessThanOrEqual(30000, $delayLarge);
    }
}
