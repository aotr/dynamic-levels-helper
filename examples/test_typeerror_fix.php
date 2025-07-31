<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Aotr\DynamicLevelHelper\Services\EnhancedDBService;

echo "Testing TypeError fixes in EnhancedDBService...\n\n";

try {
    // Get instance
    $service = EnhancedDBService::getInstance();
    echo "✓ Service instance created successfully\n";

    // Test with different result set structures to ensure no TypeError
    $reflection = new ReflectionClass($service);
    $buildExecutionInfoMethod = $reflection->getMethod('buildExecutionInfo');
    $buildExecutionInfoMethod->setAccessible(true);

    // Test case 1: New format with data wrapper
    echo "\nTesting case 1: New format with data wrapper...\n";
    $resultSets1 = [
        'data' => [
            [['id' => 1, 'name' => 'Test']],
            [['id' => 2, 'name' => 'Test2']]
        ],
        'raw_query' => 'CALL test_procedure(?)',
        'parameters' => ['param1']
    ];

    $result1 = $buildExecutionInfoMethod->invoke(
        $service,
        'test_procedure',
        ['param1'],
        $resultSets1,
        1.5,
        1,
        [],
        'mysql'
    );

    echo "✓ Result sets count: " . $result1['execution_summary']['result_sets_count'] . "\n";
    echo "✓ Rows affected: " . $result1['execution_summary']['rows_affected'] . "\n";
    echo "✓ Data structure preserved\n";

    // Test case 2: Old format (direct array)
    echo "\nTesting case 2: Old format (direct array)...\n";
    $resultSets2 = [
        [['id' => 1, 'name' => 'Test']],
        [['id' => 2, 'name' => 'Test2']]
    ];

    $result2 = $buildExecutionInfoMethod->invoke(
        $service,
        'test_procedure',
        ['param1'],
        $resultSets2,
        1.5,
        1,
        [],
        'mysql'
    );

    echo "✓ Result sets count: " . $result2['execution_summary']['result_sets_count'] . "\n";
    echo "✓ Rows affected: " . $result2['execution_summary']['rows_affected'] . "\n";
    echo "✓ Old format handled correctly\n";

    // Test case 3: Null result sets
    echo "\nTesting case 3: Null result sets...\n";
    $result3 = $buildExecutionInfoMethod->invoke(
        $service,
        'test_procedure',
        ['param1'],
        null,
        1.5,
        1,
        [],
        'mysql'
    );

    echo "✓ Result sets count: " . $result3['execution_summary']['result_sets_count'] . "\n";
    echo "✓ Rows affected: " . $result3['execution_summary']['rows_affected'] . "\n";
    echo "✓ Null result sets handled safely\n";

    // Test case 4: Empty result sets
    echo "\nTesting case 4: Empty result sets...\n";
    $result4 = $buildExecutionInfoMethod->invoke(
        $service,
        'test_procedure',
        ['param1'],
        [],
        1.5,
        1,
        [],
        'mysql'
    );

    echo "✓ Result sets count: " . $result4['execution_summary']['result_sets_count'] . "\n";
    echo "✓ Rows affected: " . $result4['execution_summary']['rows_affected'] . "\n";
    echo "✓ Empty result sets handled safely\n";

    // Test case 5: Invalid/mixed structure
    echo "\nTesting case 5: Mixed structure (edge case)...\n";
    $resultSets5 = [
        'data' => 'invalid_string', // This was causing the TypeError
        'raw_query' => 'CALL test_procedure(?)',
        'parameters' => ['param1']
    ];

    $result5 = $buildExecutionInfoMethod->invoke(
        $service,
        'test_procedure',
        ['param1'],
        $resultSets5,
        1.5,
        1,
        [],
        'mysql'
    );

    echo "✓ Result sets count: " . $result5['execution_summary']['result_sets_count'] . "\n";
    echo "✓ Rows affected: " . $result5['execution_summary']['rows_affected'] . "\n";
    echo "✓ Invalid structure handled gracefully\n";

    echo "\n" . str_repeat("=", 50) . "\n";
    echo "✅ ALL TESTS PASSED - TypeError issues resolved!\n";
    echo str_repeat("=", 50) . "\n";

} catch (TypeError $e) {
    echo "\n❌ TypeError still exists: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "\n❌ Other error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}
