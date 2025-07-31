<?php

namespace Aotr\DynamicLevelHelper\Tests\Unit;

use Aotr\DynamicLevelHelper\Services\EnhancedDBService;
use Aotr\DynamicLevelHelper\Tests\PackageTestCase;
use Illuminate\Support\Facades\Config;
use ReflectionClass;
use ReflectionMethod;

class EnhancedDBServiceTest extends PackageTestCase
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
    }

    public function test_retry_delay_calculation()
    {
        $service = EnhancedDBService::getInstance();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('calculateRetryDelay');
        $method->setAccessible(true);

        // Test exponential backoff
        $delay1 = $method->invoke($service, 100, 1);
        $delay2 = $method->invoke($service, 100, 2);
        $delay3 = $method->invoke($service, 100, 3);

        $this->assertGreaterThan(0, $delay1);
        $this->assertGreaterThan(0, $delay2);
        $this->assertGreaterThan(0, $delay3);
        $this->assertLessThanOrEqual(30000, $delay3); // Max cap
    }

    public function test_execution_info_structure()
    {
        $service = EnhancedDBService::getInstance();

        // Test the buildExecutionInfo method structure
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildExecutionInfo');
        $method->setAccessible(true);

        $executionHistory = [
            [
                'attempt' => 1,
                'execution_time' => 0.1,
                'result_sets' => 1,
                'timestamp' => microtime(true),
                'success' => true,
            ]
        ];

        $resultSets = [['id' => 1, 'name' => 'test']];

        $executionInfo = $method->invoke(
            $service,
            'test_procedure',
            ['param1'],
            $resultSets,
            0.15,
            1,
            $executionHistory,
            'mysql'
        );

        // Test structure
        $this->assertArrayHasKey('success', $executionInfo);
        $this->assertArrayHasKey('stored_procedure', $executionInfo);
        $this->assertArrayHasKey('execution_summary', $executionInfo);
        $this->assertArrayHasKey('connection_pool', $executionInfo);
        $this->assertArrayHasKey('performance', $executionInfo);
        $this->assertArrayHasKey('retry_information', $executionInfo);
        $this->assertArrayHasKey('configuration', $executionInfo);
        $this->assertArrayHasKey('timestamp', $executionInfo);
        $this->assertArrayHasKey('data', $executionInfo);

        // Test execution summary
        $this->assertEquals('test_procedure', $executionInfo['stored_procedure']);
        $this->assertEquals(['param1'], $executionInfo['parameters']);
        $this->assertEquals(0.15, $executionInfo['execution_summary']['total_execution_time']);
        $this->assertEquals(1, $executionInfo['execution_summary']['total_attempts']);
        $this->assertEquals(1, $executionInfo['execution_summary']['result_sets_count']);
        $this->assertEquals($resultSets, $executionInfo['data']);
    }

    public function test_call_stored_procedure_with_info_method()
    {
        $service = EnhancedDBService::getInstance();

        // This method should exist and be callable
        $this->assertTrue(method_exists($service, 'callStoredProcedureWithInfo'));

        // The method should be public
        $reflection = new ReflectionMethod($service, 'callStoredProcedureWithInfo');
        $this->assertTrue($reflection->isPublic());
    }
}
