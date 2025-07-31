# Enhanced DBService with Singleton Pattern

The `EnhancedDBService` provides a singleton implementation with connection pooling, configurable logging, and performance monitoring, while keeping the original `DBService` intact for backward compatibility.

## Two Database Services

### Original DBService
- **Unchanged**: Maintains all existing functionality
- **Backward Compatible**: No breaking changes
- **Simple**: Basic stored procedure calls
- **Usage**: Continue using as before

### New EnhancedDBService
## Key Features

### Enhanced Database Service Features

- **Singleton Pattern**: Ensures single instance across application
- **Connection Pooling**: Manages database connections efficiently
- **Configurable Logging**: Enhanced logging with multiple channels
- **Performance Monitoring**: Query execution tracking and metrics
- **Automatic Retry Logic**: Handles transient database failures
- **Connection Validation**: Ensures healthy database connections
- **Cache Integration**: Caches stored procedure existence checks
- **Laravel Integration**: Full Laravel ecosystem compatibility

### Retry Functionality

The Enhanced Database Service includes intelligent retry logic for handling:

- **Connection Issues**: Server gone away, connection timeout, network errors
- **Lock Conflicts**: Deadlocks, lock wait timeouts, table locks  
- **Resource Constraints**: Too many connections, temporary unavailability
- **Transaction Conflicts**: Serialization failures, transaction restarts

**Retry Features:**
- Exponential backoff with jitter to prevent thundering herd
- Configurable retry attempts and base delay
- Automatic connection pool reset on connection errors
- Detailed retry logging for debugging
- Smart error detection to only retry transient failures

## Configuration

Add the following to your `.env` file:

```env
# Enhanced DBService Configuration
ENHANCED_DB_SERVICE_DEFAULT_CONNECTION=mysql
ENHANCED_DB_SERVICE_LOGGING_ENABLED=true
ENHANCED_DB_SERVICE_LOGGING_CHANNEL=stp
ENHANCED_DB_SERVICE_LOG_QUERIES=true
ENHANCED_DB_SERVICE_LOG_ERRORS=true
ENHANCED_DB_SERVICE_LOG_EXECUTION_TIME=true

# Connection Pool Settings
ENHANCED_DB_SERVICE_MAX_CONNECTIONS=10
ENHANCED_DB_SERVICE_POOL_TIMEOUT=30
ENHANCED_DB_SERVICE_IDLE_TIMEOUT=300
ENHANCED_DB_SERVICE_RETRY_ATTEMPTS=3
ENHANCED_DB_SERVICE_RETRY_DELAY=100

# Cache Settings
ENHANCED_DB_SERVICE_CACHE_PROCEDURE_TTL=86400
ENHANCED_DB_SERVICE_CACHE_ENABLED=true

# Performance Settings
ENHANCED_DB_SERVICE_SLOW_QUERY_THRESHOLD=2.0
ENHANCED_DB_SERVICE_ENABLE_PROFILING=false
ENHANCED_DB_SERVICE_ENABLE_QUERY_TIMEOUT=true
```

## Usage Examples

### Original DBService (Unchanged)

```php
use Aotr\DynamicLevelHelper\Services\DBService;

// Still works exactly as before
$dbService = new DBService('mysql');
$results = $dbService->callStoredProcedure('my_procedure', [1, 'param2']);
```

### Enhanced DBService (New)

```php
use Aotr\DynamicLevelHelper\Services\EnhancedDBService;

// Get singleton instance
$enhancedDbService = EnhancedDBService::getInstance();
$results = $enhancedDbService->callStoredProcedure('my_procedure', [1, 'param2']);
```

### Using Enhanced Facade

```php
use Aotr\DynamicLevelHelper\Facades\EnhancedDBService;

// Direct facade usage
$results = EnhancedDBService::callStoredProcedure('my_procedure', [1, 'param2']);
```

### Advanced Usage with Options

```php
$results = $enhancedDbService->callStoredProcedure('my_procedure', [1, 'param2'], [
    'connection' => 'custom_connection',
    'checkStoredProcedure' => true,
    'enableLogging' => false,
    'timeout' => 60,
    'retryAttempts' => 5,        // Custom retry attempts
    'retryDelay' => 200,         // Base delay in milliseconds
]);
```

### Retry Configuration Examples

```php
// High-reliability operation with aggressive retry
$results = $enhancedDbService->callStoredProcedure('critical_operation', $params, [
    'retryAttempts' => 10,
    'retryDelay' => 50,          // Start with 50ms delay
    'timeout' => 120,            // 2 minute timeout
]);

// Quick operation with minimal retry
$results = $enhancedDbService->callStoredProcedure('quick_lookup', $params, [
    'retryAttempts' => 1,        // Only one retry
    'retryDelay' => 100,
    'timeout' => 10,             // 10 second timeout
]);
```

### Using with Dependency Injection

```php
use Aotr\DynamicLevelHelper\Services\EnhancedDBService;

class MyController extends Controller
{
    public function __construct(private EnhancedDBService $enhancedDbService)
    {
        // EnhancedDBService will be automatically injected as singleton
    }
    
    public function getData()
    {
        return $this->enhancedDbService->callStoredProcedure('get_data', []);
    }
}
```

## Trait Options

### Original Trait (Unchanged)

```php
use Aotr\DynamicLevelHelper\Traits\DBDataService;

class MyService
{
    use DBDataService; // Uses original DBService
    
    public function fetchUserData($userId)
    {
        return $this->getData('GetUser', ['user_id' => $userId]);
    }
}
```

### Enhanced Trait (New)

```php
use Aotr\DynamicLevelHelper\Traits\EnhancedDBDataService;

class MyAdvancedService
{
    use EnhancedDBDataService; // Uses EnhancedDBService with all features
    
    public function fetchUserData($userId)
    {
        return $this->getData('GetUser', ['user_id' => $userId]);
    }
    
    public function getStats()
    {
        return $this->getConnectionPoolStats();
    }
}
```

## Monitoring and Management

### Console Commands

```bash
# Show connection pool statistics
php artisan enhanced-db:manage stats

# Show performance metrics
php artisan enhanced-db:manage performance

# Show configuration
php artisan enhanced-db:manage config

# Reset service (closes all connections)
php artisan enhanced-db:manage reset

# Get JSON output
php artisan enhanced-db:manage stats --format=json
```

### Getting Statistics Programmatically

```php
$enhancedDbService = EnhancedDBService::getInstance();

// Get connection pool stats
$poolStats = $enhancedDbService->getConnectionPoolStats();

// Get performance metrics
$metrics = $enhancedDbService->getPerformanceMetrics();

// Clear performance metrics
$enhancedDbService->clearPerformanceMetrics();
```

## Migration Strategy

You have two options:

### Option 1: Keep Using Original (No Changes Required)
- Continue using `DBService` as before
- No code changes needed
- No new features

### Option 2: Migrate to Enhanced Features
- Replace `DBService` with `EnhancedDBService`
- Replace `DBDataService` trait with `EnhancedDBDataService`
- Update environment configuration
- Gain all new features

### Example Migration

**Before:**
```php
use Aotr\DynamicLevelHelper\Services\DBService;
use Aotr\DynamicLevelHelper\Traits\DBDataService;

class MyService
{
    use DBDataService;
    
    public function someMethod()
    {
        $db = new DBService('mysql');
        return $db->callStoredProcedure('procedure', $params);
    }
}
```

**After:**
```php
use Aotr\DynamicLevelHelper\Services\EnhancedDBService;
use Aotr\DynamicLevelHelper\Traits\EnhancedDBDataService;

class MyService
{
    use EnhancedDBDataService;
    
    public function someMethod()
    {
        $db = EnhancedDBService::getInstance();
        return $db->callStoredProcedure('procedure', $params, [
            'connection' => 'mysql'
        ]);
    }
}
```

## Key Differences

| Feature | Original DBService | EnhancedDBService |
|---------|-------------------|-------------------|
| Pattern | Constructor instantiation | Singleton |
| Connection Management | Per instance | Pooled |
| Logging | Basic | Configurable |
| Performance Monitoring | No | Yes |
| Caching | Basic | Advanced |
| Retry Logic | No | Yes |
| Console Commands | No | Yes |
| Configuration | Limited | Extensive |
| Backward Compatibility | N/A | ✅ |

## Enhanced ParameterService

The package includes a comprehensive `ParameterService` for processing request data into delimited parameter strings, commonly used for stored procedure parameters.

### Key Features

- **Multiple Processing Methods**: Simple and advanced parameter processing
- **Flexible Delimiters**: Configurable separators (default: '^^')
- **Nested Array Support**: Access nested values using dot notation (e.g., 'user.profile.name')
- **Type Safety**: Proper handling of scalar and non-scalar values
- **Validation Helpers**: Built-in parameter validation and missing parameter detection
- **Laravel Integration**: Works with both arrays and Laravel Request objects
- **Facade Support**: Easy access through Laravel facades

### ParameterService Usage Examples

#### Simple Processing (Your Original Approach)
```php
use Aotr\DynamicLevelHelper\Services\ParameterService;

// Process all parameters
$params = ParameterService::processSimple($request);
// Result: "value1^^value2^^value3"

// Process specific parameters in order
$params = ParameterService::processSimple($request, ['user_id', 'action', 'status']);
// Result: "123^^update^^active"
```

#### Advanced Processing
```php
// Custom delimiter
$params = ParameterService::process($data, null, '|');
// Result: "value1|value2|value3"

// Nested array access
$data = ['user' => ['id' => 123, 'name' => 'John']];
$params = ParameterService::process($data, ['user.id', 'user.name']);
// Result: "123^^John"
```

#### Direct Value Creation
```php
// From individual values
$params = ParameterService::fromValues('user123', 'update', 42);
// Result: "user123^^update^^42"
```

#### Parameter Validation
```php
// Check required parameters
$isValid = ParameterService::validateRequired($request, ['user_id', 'action']);

// Get missing parameters
$missing = ParameterService::getMissingRequired($request, ['user_id', 'action', 'status']);
// Returns: ['status'] if status is missing
```

#### Utility Methods
```php
// Split parameter string back into array
$values = ParameterService::split('value1^^value2^^value3');
// Returns: ['value1', 'value2', 'value3']

// Quick method (alias for processSimple)
$params = ParameterService::quick($request, ['key1', 'key2']);
```

#### Facade Usage
```php
use Aotr\DynamicLevelHelper\Facades\ParameterService;

// After registering facade in config/app.php
$params = ParameterService::quick($request, ['user_id', 'action']);
```

### Integration with Database Services

```php
use Aotr\DynamicLevelHelper\Services\EnhancedDBService;
use Aotr\DynamicLevelHelper\Services\ParameterService;

// Process request parameters and call stored procedure
$enhancedDb = EnhancedDBService::getInstance();
$params = ParameterService::processSimple($request, ['user_id', 'action', 'data']);
$results = $enhancedDb->callStoredProcedure('UpdateUserData', [$params]);
```

## Benefits of Enhanced Version

1. **Resource Efficiency**: Connection pooling reduces database overhead
2. **Scalability**: Configurable pool sizes based on application needs
3. **Monitoring**: Built-in statistics and performance tracking
4. **Maintainability**: Clean code with proper separation of concerns
5. **Flexibility**: Extensive configuration options
6. **Reliability**: Automatic retry logic and error recovery
7. **Developer Experience**: Console commands for monitoring and management
8. **Backward Compatibility**: Original service remains untouched

## Troubleshooting

### Driver Compatibility Issues

If you encounter errors like "Driver does not support this function: This driver doesn't support setting attributes", this means your PDO driver doesn't support query timeout attributes.

**Solution:**
Add this to your `.env` file to disable query timeout setting:
```env
ENHANCED_DB_SERVICE_ENABLE_QUERY_TIMEOUT=false
```

**Common scenarios:**
- **MySQL with older drivers**: Some MySQL PDO drivers don't support `PDO::ATTR_TIMEOUT`
- **Custom PDO drivers**: Third-party or custom drivers may not implement all PDO attributes
- **Shared hosting**: Some shared hosting providers use limited PDO implementations

**Alternative timeout methods:**
- MySQL: The service will automatically use `SET SESSION wait_timeout` instead
- PostgreSQL: Falls back to connection-level timeouts
- SQLite: Timeout not needed for stored procedures

### Connection Issues

If you get "server has gone away" or connection timeout errors:

1. **Increase retry attempts**:
   ```env
   ENHANCED_DB_SERVICE_RETRY_ATTEMPTS=5
   ENHANCED_DB_SERVICE_RETRY_DELAY=200
   ```

2. **Check connection pool settings**:
   ```env
   ENHANCED_DB_SERVICE_MAX_CONNECTIONS=5
   ENHANCED_DB_SERVICE_POOL_TIMEOUT=60
   ```

3. **Enable detailed logging**:
   ```env
   ENHANCED_DB_SERVICE_LOGGING_ENABLED=true
   ENHANCED_DB_SERVICE_LOG_ERRORS=true
   ```

### Performance Issues

For slow stored procedures:

1. **Monitor slow queries**:
   ```env
   ENHANCED_DB_SERVICE_SLOW_QUERY_THRESHOLD=1.0
   ENHANCED_DB_SERVICE_ENABLE_PROFILING=true
   ```

2. **Check performance metrics**:
   ```php
   $metrics = EnhancedDBService::getInstance()->getPerformanceMetrics();
   $poolStats = EnhancedDBService::getInstance()->getConnectionPoolStats();
   ```

3. **Use console commands**:
   ```bash
   php artisan enhanced-db:manage performance
   php artisan enhanced-db:manage stats
   ```
