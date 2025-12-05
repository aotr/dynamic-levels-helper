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
            // Example 2: Call with detailed execution information
            echo "2. Call with detailed execution information:\n";
            $executionInfo = $dbService->callStoredProcedureWithInfo('example_procedure', ['param1', 'param2'], [
                'retryAttempts' => 3,
                'retryDelay' => 100,
                'timeout' => 30,
            ]);

            echo "Execution Summary:\n";
            echo "  - Success: " . ($executionInfo['success'] ? 'Yes' : 'No') . "\n";
            echo "  - Total Time: " . $executionInfo['execution_summary']['total_execution_time'] . "s\n";
            echo "  - Total Attempts: " . $executionInfo['execution_summary']['total_attempts'] . "\n";
            echo "  - Result Sets: " . $executionInfo['execution_summary']['result_sets_count'] . "\n";
            echo "  - Rows Affected: " . $executionInfo['execution_summary']['rows_affected'] . "\n";
            echo "  - Connection: " . $executionInfo['connection'] . "\n";

            echo "\nConnection Pool Status:\n";
            foreach ($executionInfo['connection_pool']['stats'] as $key => $value) {
                echo "  - {$key}: {$value}\n";
            }

            echo "\nPerformance Information:\n";
            echo "  - Is Slow Query: " . ($executionInfo['performance']['is_slow_query'] ? 'Yes' : 'No') . "\n";
            echo "  - Threshold: " . $executionInfo['performance']['slow_query_threshold'] . "s\n";

            if ($executionInfo['performance']['procedure_metrics']) {
                $metrics = $executionInfo['performance']['procedure_metrics'];
                echo "  - Total Calls: " . $metrics['total_calls'] . "\n";
                echo "  - Average Time: " . round($metrics['avg_time'], 4) . "s\n";
                echo "  - Min Time: " . round($metrics['min_time'], 4) . "s\n";
                echo "  - Max Time: " . round($metrics['max_time'], 4) . "s\n";
            }

            echo "\nRetry Information:\n";
            echo "  - Retry Enabled: " . ($executionInfo['retry_information']['retry_enabled'] ? 'Yes' : 'No') . "\n";
            echo "  - Max Attempts: " . $executionInfo['retry_information']['max_retry_attempts'] . "\n";
            echo "  - Base Delay: " . $executionInfo['retry_information']['retry_base_delay'] . "ms\n";

            if (!empty($executionInfo['retry_information']['execution_history'])) {
                echo "  - Execution History:\n";
                foreach ($executionInfo['retry_information']['execution_history'] as $i => $history) {
                    echo "    Attempt " . ($i + 1) . ": ";
                    if ($history['success'] ?? false) {
                        echo "Success ({$history['execution_time']}s, {$history['result_sets']} result sets)\n";
                    } else {
                        echo "Failed - {$history['error']} (Code: {$history['error_code']})\n";
                    }
                }
            }

            echo "\n";

        } catch (Exception $e) {
            echo "Failed with detailed info: " . $e->getMessage() . "\n\n";
        }

        try {
            // Example 3: Custom retry configuration with execution info
            echo "3. Custom retry configuration with execution info (5 attempts, 200ms base delay):\n";
            $executionInfo = $dbService->callStoredProcedure('example_procedure', ['param1', 'param2'], [
                'retryAttempts' => 5,
                'retryDelay' => 200,
                'timeout' => 60,
                'returnExecutionInfo' => true, // Enable detailed info
            ]);

            echo "Custom Retry Execution:\n";
            echo "  - Success: " . ($executionInfo['success'] ? 'Yes' : 'No') . "\n";
            echo "  - Total Time: " . $executionInfo['execution_summary']['total_execution_time'] . "s\n";
            echo "  - Successful Attempts: " . $executionInfo['execution_summary']['successful_attempts'] . "\n";
            echo "  - Failed Attempts: " . $executionInfo['execution_summary']['failed_attempts'] . "\n";
            echo "\n";

        } catch (Exception $e) {
            echo "Failed after custom retries: " . $e->getMessage() . "\n\n";
        }

        // Example 4: Show connection pool stats
        echo "4. Connection Pool Statistics:\n";
        $stats = $dbService->getConnectionPoolStats();
        foreach ($stats as $key => $value) {
            echo "  {$key}: {$value}\n";
        }
        echo "\n";

        // Example 5: Show performance metrics
        echo "5. Performance Metrics:\n";
        $metrics = $dbService->getPerformanceMetrics();
        if (empty($metrics)) {
            echo "  No metrics available (no procedures executed yet)\n";
        } else {
            foreach ($metrics as $procedure => $data) {
                echo "  {$procedure}:\n";
                echo "    - Total calls: {$data['total_calls']}\n";
                echo "    - Average time: " . round($data['avg_time'], 4) . "s\n";
                echo "    - Min time: " . round($data['min_time'], 4) . "s\n";
                echo "    - Max time: " . round($data['max_time'], 4) . "s\n";
                echo "    - Total result sets: {$data['total_result_sets']}\n";
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

echo "Enhanced DB Service with Comprehensive Execution Information!\n";
echo "The service now includes:\n";
echo "- Automatic retry logic for transient failures\n";
echo "- Exponential backoff with jitter\n";
echo "- Connection validation and recovery\n";
echo "- Configurable retry attempts and delays\n";
echo "- Smart error detection (retryable vs non-retryable)\n";
echo "- Enhanced logging for retry attempts\n";
echo "- Detailed execution information including:\n";
echo "  * Execution time and performance metrics\n";
echo "  * Connection pool status and statistics\n";
echo "  * Retry history and attempt details\n";
echo "  * Configuration information\n";
echo "  * Error analysis and categorization\n";
echo "  * Result set information and row counts\n\n";
echo "Usage examples:\n";
echo "// Basic call (returns only result data)\n";
echo "\$result = \$dbService->callStoredProcedure('procedure_name', \$params);\n\n";
echo "// Call with execution information\n";
echo "\$info = \$dbService->callStoredProcedureWithInfo('procedure_name', \$params);\n";
echo "// Access: \$info['data'], \$info['execution_summary'], \$info['connection_pool']\n\n";
echo "// Call with execution info option\n";
echo "\$info = \$dbService->callStoredProcedure('procedure_name', \$params, [\n";
echo "    'returnExecutionInfo' => true,\n";
echo "    'retryAttempts' => 5\n";
echo "]);\n";
