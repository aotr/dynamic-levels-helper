<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Aotr\DynamicLevelHelper\Services\EnhancedDBService;

// Example demonstrating retry functionality
class RetryExample
{
    public function demonstrateRetry()
    {
        // Get enhanced database service instance
        $dbService = EnhancedDBService::getInstance();

        try {
            echo "=== Enhanced DB Service Retry Functionality Demo ===\n\n";

            // Example 1: Basic call with default retry settings
            echo "1. Basic call with default retry (3 attempts):\n";
            $result = $dbService->callStoredProcedure('example_procedure', ['param1', 'param2']);
            echo "Success: " . json_encode($result) . "\n\n";

        } catch (Exception $e) {
            echo "Failed after retries: " . $e->getMessage() . "\n\n";
        }

        try {
            // Example 2: Custom retry configuration
            echo "2. Custom retry configuration (5 attempts, 200ms base delay):\n";
            $result = $dbService->callStoredProcedure('example_procedure', ['param1', 'param2'], [
                'retryAttempts' => 5,
                'retryDelay' => 200,
                'timeout' => 60,
            ]);
            echo "Success: " . json_encode($result) . "\n\n";

        } catch (Exception $e) {
            echo "Failed after custom retries: " . $e->getMessage() . "\n\n";
        }

        // Example 3: Show connection pool stats
        echo "3. Connection Pool Statistics:\n";
        $stats = $dbService->getConnectionPoolStats();
        foreach ($stats as $key => $value) {
            echo "  {$key}: {$value}\n";
        }
        echo "\n";

        // Example 4: Show performance metrics
        echo "4. Performance Metrics:\n";
        $metrics = $dbService->getPerformanceMetrics();
        if (empty($metrics)) {
            echo "  No metrics available (no procedures executed yet)\n";
        } else {
            foreach ($metrics as $procedure => $data) {
                echo "  {$procedure}: {$data['avg_execution_time']}ms average\n";
            }
        }
        echo "\n";

        echo "=== Demo Complete ===\n";
    }

    public function demonstrateRetryErrorTypes()
    {
        echo "\n=== Retry Error Types Demo ===\n\n";

        $dbService = EnhancedDBService::getInstance();

        // Access private methods using reflection for demonstration
        $reflection = new ReflectionClass($dbService);
        $isRetryableMethod = $reflection->getMethod('isRetryableError');
        $isRetryableMethod->setAccessible(true);

        $calculateDelayMethod = $reflection->getMethod('calculateRetryDelay');
        $calculateDelayMethod->setAccessible(true);

        // Test different error types
        $errors = [
            ['msg' => 'MySQL server has gone away', 'code' => 2006, 'type' => 'Connection Error'],
            ['msg' => 'Lock wait timeout exceeded', 'code' => 1205, 'type' => 'Lock Timeout'],
            ['msg' => 'Deadlock found when trying to get lock', 'code' => 1213, 'type' => 'Deadlock'],
            ['msg' => 'Too many connections', 'code' => 1040, 'type' => 'Resource Limit'],
            ['msg' => 'Syntax error in SQL statement', 'code' => 1064, 'type' => 'Syntax Error (Not Retryable)'],
        ];

        echo "Error Type Analysis:\n";
        foreach ($errors as $error) {
            $exception = new Exception($error['msg'], $error['code']);
            $isRetryable = $isRetryableMethod->invoke($dbService, $exception);
            echo "  {$error['type']}: " . ($isRetryable ? 'RETRYABLE' : 'NOT RETRYABLE') . "\n";
        }

        echo "\nRetry Delay Calculation (Exponential Backoff):\n";
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $delay = $calculateDelayMethod->invoke($dbService, 100, $attempt);
            echo "  Attempt {$attempt}: {$delay}ms delay\n";
        }

        echo "\n=== Error Types Demo Complete ===\n";
    }
}

// Uncomment to run the examples
// (new RetryExample())->demonstrateRetry();
// (new RetryExample())->demonstrateRetryErrorTypes();

echo "Retry functionality has been successfully added to EnhancedDBService!\n";
echo "The service now includes:\n";
echo "- Automatic retry logic for transient failures\n";
echo "- Exponential backoff with jitter\n";
echo "- Connection validation and recovery\n";
echo "- Configurable retry attempts and delays\n";
echo "- Smart error detection (retryable vs non-retryable)\n";
echo "- Enhanced logging for retry attempts\n\n";
echo "Use the examples above to test the functionality.\n";
