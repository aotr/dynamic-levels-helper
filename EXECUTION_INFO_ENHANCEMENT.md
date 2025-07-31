# Enhanced DB Service - Execution Information Feature

## Overview

The `EnhancedDBService` has been enhanced to return comprehensive execution information including execution time, connection pool status, retry history, performance metrics, and more. This provides complete transparency into database operations for monitoring, debugging, and performance optimization.

## New Features Added

### 1. Detailed Execution Information

The service can now return extensive metadata about stored procedure execution:

```php
// Method 1: Dedicated method
$executionInfo = $enhancedDbService->callStoredProcedureWithInfo('my_procedure', $params);

// Method 2: Option parameter
$executionInfo = $enhancedDbService->callStoredProcedure('my_procedure', $params, [
    'returnExecutionInfo' => true
]);
```

### 2. Comprehensive Data Structure

The execution information includes:

- **Success Status**: Whether the execution succeeded or failed
- **Execution Summary**: Timing, attempts, result counts, rows affected
- **Connection Pool Status**: Pool statistics, active connections, pool health
- **Performance Metrics**: Slow query detection, procedure-specific metrics
- **Retry Information**: Retry history, attempt details, configuration
- **Configuration Details**: Current service settings
- **Timestamp Information**: Start/end times with timezone
- **Error Analysis**: Detailed error information with categorization

### 3. Backward Compatibility

- **No Breaking Changes**: Existing code continues to work unchanged
- **Optional Feature**: Execution information is only returned when requested
- **Same Result Format**: Standard calls return the same result format as before

## Execution Information Structure

```php
[
    'success' => bool,                    // True if execution succeeded
    'stored_procedure' => string,         // Procedure name
    'parameters' => array,                // Input parameters
    'connection' => string,               // Database connection used
    'data' => array,                      // Result sets (when successful)
    
    'execution_summary' => [
        'total_execution_time' => float, // Total time in seconds
        'total_attempts' => int,          // Number of attempts made
        'successful_attempts' => int,     // Successful attempts
        'failed_attempts' => int,         // Failed attempts
        'result_sets_count' => int,       // Number of result sets
        'rows_affected' => int,           // Total rows in all result sets
    ],
    
    'connection_pool' => [
        'stats' => [                      // Current pool statistics
            'total_connections' => int,
            'active_connections' => int,
            'idle_connections' => int,
            'pool_size' => int,
            // ... more pool stats
        ],
        'connection_used' => string,      // Connection name used
    ],
    
    'performance' => [
        'is_slow_query' => bool,          // Whether query exceeded threshold
        'slow_query_threshold' => float,  // Configured threshold
        'procedure_metrics' => [          // Historical procedure metrics
            'total_calls' => int,
            'avg_time' => float,
            'min_time' => float,
            'max_time' => float,
            'total_result_sets' => int,
        ],
    ],
    
    'retry_information' => [
        'retry_enabled' => bool,          // Whether retries were attempted
        'max_retry_attempts' => int,      // Configured max attempts
        'retry_base_delay' => int,        // Base delay in milliseconds
        'execution_history' => [          // Detailed attempt history
            [
                'attempt' => int,
                'execution_time' => float,
                'result_sets' => int,
                'success' => bool,
                'timestamp' => float,
                'error' => string,        // If attempt failed
                'error_code' => int,      // If attempt failed
            ],
            // ... more attempts
        ],
    ],
    
    'configuration' => [
        'timeout' => int,
        'max_connections' => int,
        'logging_enabled' => bool,
        'cache_enabled' => bool,
    ],
    
    'timestamp' => [
        'started_at' => string,           // ISO format start time
        'completed_at' => string,         // ISO format completion time
        'timezone' => string,             // Timezone used
    ],
    
    'error' => [                          // Only present if execution failed
        'message' => string,
        'code' => int,
        'type' => string,
        'retryable' => bool,
        'connection_error' => bool,
    ],
]
```

## Usage Examples

### Basic Usage (Unchanged)

```php
// Existing code continues to work unchanged
$results = $enhancedDbService->callStoredProcedure('GetUserData', [123]);
```

### Getting Execution Information

```php
// Method 1: Dedicated method
$info = $enhancedDbService->callStoredProcedureWithInfo('GetUserData', [123]);

// Method 2: Option parameter
$info = $enhancedDbService->callStoredProcedure('GetUserData', [123], [
    'returnExecutionInfo' => true,
    'retryAttempts' => 5,
]);
```

### Processing Execution Information

```php
$info = $enhancedDbService->callStoredProcedureWithInfo('GetUserData', [123]);

if ($info['success']) {
    // Access the actual data
    $userData = $info['data'][0];
    
    // Check performance
    $executionTime = $info['execution_summary']['total_execution_time'];
    if ($info['performance']['is_slow_query']) {
        error_log("Slow query detected: {$executionTime}s");
    }
    
    // Monitor connection pool
    $poolStats = $info['connection_pool']['stats'];
    $poolUsage = $poolStats['active_connections'] / $poolStats['pool_size'];
    if ($poolUsage > 0.8) {
        error_log("High connection pool usage: " . ($poolUsage * 100) . "%");
    }
    
    // Check retry history
    if ($info['execution_summary']['failed_attempts'] > 0) {
        error_log("Procedure required retries: " . 
            $info['execution_summary']['failed_attempts'] . " failed attempts");
    }
    
} else {
    // Handle failure
    $error = $info['error'];
    error_log("Procedure failed: " . $error['message']);
    
    // Analyze retry attempts
    $attempts = count($info['retry_information']['execution_history']);
    error_log("Failed after {$attempts} attempts");
}
```

### Monitoring and Alerting

```php
function monitorDatabaseHealth($procedureName, $params) {
    $info = EnhancedDBService::getInstance()
        ->callStoredProcedureWithInfo($procedureName, $params);
    
    // Performance monitoring
    if ($info['performance']['is_slow_query']) {
        alert("Slow query: {$procedureName} took {$info['execution_summary']['total_execution_time']}s");
    }
    
    // Connection pool monitoring
    $poolStats = $info['connection_pool']['stats'];
    $usage = $poolStats['active_connections'] / $poolStats['pool_size'];
    if ($usage > 0.9) {
        alert("High connection pool usage: " . ($usage * 100) . "%");
    }
    
    // Retry monitoring
    if ($info['execution_summary']['failed_attempts'] > 0) {
        alert("Database retries occurred for {$procedureName}");
    }
    
    return $info['success'] ? $info['data'] : null;
}
```

## Benefits

1. **Complete Transparency**: Full visibility into database operations
2. **Performance Monitoring**: Automatic slow query detection and metrics
3. **Connection Health**: Real-time connection pool monitoring
4. **Retry Analysis**: Detailed retry history for debugging
5. **Error Diagnosis**: Comprehensive error information and categorization
6. **Historical Metrics**: Procedure-specific performance tracking over time
7. **Debugging Support**: Timestamp tracking and execution history
8. **Monitoring Integration**: Rich data for monitoring systems and alerts

## Implementation Details

### Code Changes

1. **Enhanced `callStoredProcedure()`**: Added `returnExecutionInfo` option
2. **New `callStoredProcedureWithInfo()`**: Dedicated method for execution info
3. **New `buildExecutionInfo()`**: Constructs comprehensive execution data
4. **Enhanced retry tracking**: Detailed execution history recording
5. **Improved error handling**: Better connection cleanup and error reporting

### Performance Impact

- **Minimal Overhead**: Information collection adds minimal processing time
- **Optional Feature**: No impact when not requested
- **Efficient Collection**: Data is collected during normal execution flow
- **Memory Efficient**: Execution history is limited and cleaned up automatically

## Testing

All existing tests continue to pass, plus new tests added for:
- ✅ Execution information structure validation
- ✅ New method availability and accessibility
- ✅ Backward compatibility confirmation
- ✅ Error handling with execution information

## Migration

### No Changes Required
Existing code continues to work without any modifications:

```php
// This continues to work exactly as before
$results = $enhancedDbService->callStoredProcedure('my_procedure', $params);
```

### Optional Enhancement
To get execution information, simply change to one of these approaches:

```php
// Option 1: Use new method
$info = $enhancedDbService->callStoredProcedureWithInfo('my_procedure', $params);
$results = $info['data'];

// Option 2: Add option parameter
$info = $enhancedDbService->callStoredProcedure('my_procedure', $params, [
    'returnExecutionInfo' => true
]);
$results = $info['data'];
```

This enhancement provides comprehensive database operation visibility while maintaining full backward compatibility and minimal performance overhead.
