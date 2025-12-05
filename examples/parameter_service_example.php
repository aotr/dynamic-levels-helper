<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Aotr\DynamicLevelHelper\Services\ParameterService;
use Illuminate\Http\Request;

echo "=== Enhanced ParameterService Examples ===\n\n";

// Example 1: Basic array processing with your simple approach
echo "1. Simple array processing (your approach):\n";
$data = ['param1' => 'value1', 'param2' => 'value2', 'param3' => 'value3'];
$result = ParameterService::processSimple($data);
echo "Input: " . json_encode($data) . "\n";
echo "Output: $result\n\n";

// Example 2: With sequence (your approach)
echo "2. With custom sequence (your approach):\n";
$sequence = ['param3', 'param1'];
$result = ParameterService::processSimple($data, $sequence);
echo "Sequence: " . json_encode($sequence) . "\n";
echo "Output: $result\n\n";

// Example 3: Using the enhanced process method
echo "3. Enhanced process method with custom delimiter:\n";
$result = ParameterService::process($data, null, '|');
echo "With '|' delimiter: $result\n\n";

// Example 4: Quick method (alias)
echo "4. Quick method (alias for processSimple):\n";
$result = ParameterService::quick($data);
echo "Quick result: $result\n\n";

// Example 5: From individual values
echo "5. Creating from individual values:\n";
$result = ParameterService::fromValues('user123', 'action_type', 42, 'status_active');
echo "From values: $result\n\n";

// Example 6: Splitting parameter string back
echo "6. Splitting parameter string:\n";
$paramString = 'value1^^value2^^value3';
$splitResult = ParameterService::split($paramString);
echo "Original: $paramString\n";
echo "Split: " . json_encode($splitResult) . "\n\n";

// Example 7: Validation
echo "7. Parameter validation:\n";
$testData = ['user_id' => '123', 'action' => 'update', 'status' => ''];
$required = ['user_id', 'action', 'status'];
$isValid = ParameterService::validateRequired($testData, $required);
$missing = ParameterService::getMissingRequired($testData, $required);
echo "Data: " . json_encode($testData) . "\n";
echo "Required: " . json_encode($required) . "\n";
echo "Valid: " . ($isValid ? 'Yes' : 'No') . "\n";
echo "Missing: " . json_encode($missing) . "\n\n";

// Example 8: Nested array access (enhanced feature)
echo "8. Nested array access:\n";
$complexData = [
    'user' => [
        'id' => 123,
        'profile' => [
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]
    ],
    'action' => 'update_profile'
];
$nestedSequence = ['user.id', 'user.profile.name', 'action'];
$result = ParameterService::process($complexData, $nestedSequence);
echo "Complex data: " . json_encode($complexData, JSON_PRETTY_PRINT) . "\n";
echo "Sequence: " . json_encode($nestedSequence) . "\n";
echo "Result: $result\n\n";

// Example 9: Handling non-scalar values
echo "9. Handling non-scalar values:\n";
$mixedData = [
    'param1' => 'string_value',
    'param2' => ['array', 'value'],
    'param3' => 123,
    'param4' => (object) ['nested' => 'object'],
    'param5' => 'another_string'
];
$result = ParameterService::processSimple($mixedData);
echo "Mixed data: " . json_encode($mixedData) . "\n";
echo "Result (non-scalar values become empty): $result\n\n";

// Example 10: Request simulation
echo "10. Simulating Laravel Request:\n";
$requestData = ['user_id' => '456', 'action' => 'delete', 'confirm' => 'yes'];
$request = new Request($requestData);
$result = ParameterService::processSimple($request, ['user_id', 'action']);
echo "Request data: " . json_encode($requestData) . "\n";
echo "Selected params: $result\n\n";

echo "=== Usage Comparison ===\n";
echo "Your original approach:\n";
echo "  ParameterService::processSimple(\$request, ['key1', 'key2'])\n\n";
echo "Enhanced approaches:\n";
echo "  ParameterService::process(\$data, ['key1', 'key2'], '^^')  // Full control\n";
echo "  ParameterService::quick(\$request, ['key1', 'key2'])       // Simple alias\n";
echo "  ParameterService::fromValues('val1', 'val2', 123)          // Direct values\n";
echo "  ParameterService::validateRequired(\$data, ['key1'])       // Validation\n\n";

echo "=== For Laravel Usage ===\n";
echo "// Add to config/app.php aliases:\n";
echo "'ParameterService' => Aotr\\DynamicLevelHelper\\Facades\\ParameterService::class,\n\n";
echo "// Then use anywhere:\n";
echo "use ParameterService;\n";
echo "\$params = ParameterService::quick(\$request, ['user_id', 'action']);\n";
