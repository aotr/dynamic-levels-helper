<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Aotr\DynamicLevelHelper\Services\EnhancedDBService;

echo "Performance Benchmark for Optimized EnhancedDBService\n";
echo str_repeat("=", 60) . "\n\n";

/**
 * Benchmark function execution time
 */
function benchmark(string $name, callable $fn, int $iterations = 1000): float {
    // Warm up
    for ($i = 0; $i < 10; $i++) {
        $fn();
    }

    $start = microtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $fn();
    }
    $end = microtime(true);

    $totalTime = $end - $start;
    $avgTime = $totalTime / $iterations;

    echo sprintf("%-40s: %8.4fms avg (%d iterations, %.4fs total)\n",
        $name, $avgTime * 1000, $iterations, $totalTime);

    return $avgTime;
}

// Get service instance
$service = EnhancedDBService::getInstance();
$reflection = new ReflectionClass($service);

echo "Testing optimized methods:\n\n";

// 1. Test SQL caching optimization
$executeMethod = $reflection->getMethod('executeStoredProcedure');
$executeMethod->setAccessible(true);

$sqlCacheProperty = $reflection->getProperty('sqlCache');
$sqlCacheProperty->setAccessible(true);

echo "1. SQL Template Caching:\n";
benchmark('First call (cache miss)', function() use ($service, $reflection) {
    $prepareQueryMethod = $reflection->getMethod('prepareQuery');
    $prepareQueryMethod->setAccessible(true);
    return $prepareQueryMethod->invoke($service, 'CALL test_proc(?,?,?)', ['param1', 123, 'param3']);
}, 100);

benchmark('Subsequent calls (cache hit)', function() use ($service, $reflection) {
    $prepareQueryMethod = $reflection->getMethod('prepareQuery');
    $prepareQueryMethod->setAccessible(true);
    return $prepareQueryMethod->invoke($service, 'CALL test_proc(?,?,?)', ['param1', 123, 'param3']);
}, 1000);

echo "\n2. Optimized Array Operations:\n";

// Test array operations with different data sizes
$smallData = array_fill(0, 10, ['id' => 1, 'name' => 'test']);
$largeData = array_fill(0, 1000, ['id' => 1, 'name' => 'test']);

$buildInfoMethod = $reflection->getMethod('buildExecutionInfo');
$buildInfoMethod->setAccessible(true);

benchmark('Small dataset (10 rows)', function() use ($service, $buildInfoMethod, $smallData) {
    return $buildInfoMethod->invoke(
        $service,
        'test_proc',
        ['param1'],
        ['data' => [$smallData]],
        0.1,
        1,
        [['success' => true, 'attempt' => 1]],
        'mysql'
    );
}, 500);

benchmark('Large dataset (1000 rows)', function() use ($service, $buildInfoMethod, $largeData) {
    return $buildInfoMethod->invoke(
        $service,
        'test_proc',
        ['param1'],
        ['data' => [$largeData]],
        0.1,
        1,
        [['success' => true, 'attempt' => 1]],
        'mysql'
    );
}, 100);

echo "\n3. Logging Performance:\n";

$logMethod = $reflection->getMethod('log');
$logMethod->setAccessible(true);

benchmark('Logging when enabled', function() use ($service, $logMethod) {
    return $logMethod->invoke($service, 'info', 'Test message', ['key' => 'value']);
}, 500);

// Test with logging disabled
$config = $reflection->getProperty('config');
$config->setAccessible(true);
$originalConfig = $config->getValue($service);
$newConfig = $originalConfig;
$newConfig['logging']['enabled'] = false;
$config->setValue($service, $newConfig);

// Reset cached logging status
$loggingEnabled = $reflection->getProperty('loggingEnabled');
$loggingEnabled->setAccessible(true);
$loggingEnabled->setValue($service, null);

benchmark('Logging when disabled (early exit)', function() use ($service, $logMethod) {
    return $logMethod->invoke($service, 'info', 'Test message', ['key' => 'value']);
}, 1000);

// Restore original config
$config->setValue($service, $originalConfig);
$loggingEnabled->setValue($service, null);

echo "\n4. prepareQuery Optimization:\n";

$prepareQueryMethod = $reflection->getMethod('prepareQuery');
$prepareQueryMethod->setAccessible(true);

benchmark('No parameters (early return)', function() use ($service, $prepareQueryMethod) {
    return $prepareQueryMethod->invoke($service, 'CALL simple_proc()', []);
}, 1000);

benchmark('Few parameters (3)', function() use ($service, $prepareQueryMethod) {
    return $prepareQueryMethod->invoke($service, 'CALL test_proc(?,?,?)', ['param1', 123, 'param3']);
}, 1000);

benchmark('Many parameters (10)', function() use ($service, $prepareQueryMethod) {
    $params = array_fill(0, 10, 'param');
    $query = 'CALL complex_proc(' . str_repeat('?,', 9) . '?)';
    return $prepareQueryMethod->invoke($service, $query, $params);
}, 500);

echo "\n5. Configuration Loading:\n";

benchmark('getInstance() (singleton)', function() {
    return EnhancedDBService::getInstance();
}, 1000);

// Test new instance creation (for comparison)
EnhancedDBService::resetInstance();
benchmark('New instance creation', function() {
    return EnhancedDBService::getInstance();
}, 10);

echo "\n6. Performance Metrics Recording:\n";

$recordMetricsMethod = $reflection->getMethod('recordPerformanceMetrics');
$recordMetricsMethod->setAccessible(true);

// Enable profiling
$newConfig = $originalConfig;
$newConfig['performance']['enable_query_profiling'] = true;
$config->setValue($service, $newConfig);

benchmark('Metrics recording (enabled)', function() use ($service, $recordMetricsMethod) {
    return $recordMetricsMethod->invoke($service, 'test_proc', 0.1, 2);
}, 1000);

// Disable profiling
$newConfig['performance']['enable_query_profiling'] = false;
$config->setValue($service, $newConfig);

benchmark('Metrics recording (disabled)', function() use ($service, $recordMetricsMethod) {
    return $recordMetricsMethod->invoke($service, 'test_proc', 0.1, 2);
}, 1000);

// Restore config
$config->setValue($service, $originalConfig);

echo "\n" . str_repeat("=", 60) . "\n";
echo "✅ Performance benchmark completed!\n";
echo "\nKey optimizations implemented:\n";
echo "• Cached SQL template generation\n";
echo "• Replaced array_map/array_filter with direct loops\n";
echo "• Cached configuration lookups\n";
echo "• Optimized string operations in prepareQuery\n";
echo "• Early exit patterns for disabled features\n";
echo "• Cached Laravel facade availability checks\n";
echo "• Reduced function_exists() calls\n";
echo "• Optimized logging with lazy evaluation\n";
echo str_repeat("=", 60) . "\n";
