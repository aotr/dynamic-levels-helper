# Dynamic Levels Helper

A comprehensive Laravel package that provides tools for managing dynamic levels, database operations, SMS services, WhatsApp integration, and more.

## Features

- **Enhanced Database Service**: Singleton pattern with connection pooling and configurable logging
- **SMS Service**: Multiple provider support (Sinfini, Onex, MyValueFirst, Infobip)
- **WhatsApp Integration**: Structured messaging with GoInfinito provider
- **Response Macros**: Standardized API response formats
- **Caching Service**: Advanced caching with invalidation and refresh capabilities
- **Parameter Service**: Efficient parameter processing and validation
- **Basic Authentication Middleware**: Simple HTTP basic auth protection
- **Console Commands**: Management and monitoring tools

## Installation

```bash
composer require aotr/dynamic-levels-helper
```

## Configuration

Publish the configuration files:

```bash
php artisan vendor:publish --provider="Aotr\DynamicLevelHelper\Providers\DynamicLevelHelperServiceProvider"
```

## Enhanced Database Service

The DBService has been completely rewritten with modern patterns and enterprise features:

### Key Improvements

- ✅ **Singleton Pattern**: Single instance for efficient resource management
- ✅ **Connection Pooling**: Automatic connection management with configurable pool size
- ✅ **Configurable Logging**: Flexible logging to different channels with granular control
- ✅ **Performance Monitoring**: Query execution tracking and slow query detection
- ✅ **Cached Procedure Checks**: Efficient stored procedure existence validation
- ✅ **Automatic Retry Logic**: Built-in retry mechanism for failed connections
- ✅ **Console Management**: Commands for monitoring and managing the service

### Usage

```php
use Aotr\DynamicLevelHelper\Services\DBService;
use Aotr\DynamicLevelHelper\Facades\DBService as DBServiceFacade;

// Singleton instance
$dbService = DBService::getInstance();
$results = $dbService->callStoredProcedure('my_procedure', [1, 'param2']);

// Using facade
$results = DBServiceFacade::callStoredProcedure('my_procedure', [1, 'param2']);

// Advanced options
$results = $dbService->callStoredProcedure('my_procedure', [1, 'param2'], [
    'connection' => 'custom_connection',
    'checkStoredProcedure' => true,
    'timeout' => 60,
]);
```

### Monitoring

```bash
# View connection pool statistics
php artisan db-service:manage stats

# Monitor performance metrics
php artisan db-service:manage performance

# View current configuration
php artisan db-service:manage config

# Reset service connections
php artisan db-service:manage reset
```

## SMS Service

```php
use Aotr\DynamicLevelHelper\Services\SMS\SmsService;

$smsService = app(SmsService::class);
$success = $smsService->sendSms('1234567890', 'Hello World!', 91);
```

## WhatsApp Service

```php
use Aotr\DynamicLevelHelper\Services\WhatsAppSdk\WhatsAppService;

$whatsAppService = new WhatsAppService();
$response = $whatsAppService->send($message);
```

## Response Macros

```php
// Success response
return response()->api($data);

// Processed response (filters and transforms data)
return response()->apiProcess($data);

// Error response
return response()->apiError('Error message', 1001);
```

## Environment Variables

```env
# Database Service
DB_SERVICE_DEFAULT_CONNECTION=mysql
DB_SERVICE_LOGGING_ENABLED=true
DB_SERVICE_LOGGING_CHANNEL=stp
DB_SERVICE_MAX_CONNECTIONS=10
DB_SERVICE_POOL_TIMEOUT=30
DB_SERVICE_SLOW_QUERY_THRESHOLD=2.0

# SMS Configuration
SMS_PROVIDER=myvaluefirst
MYVALUEFIRST_URL=https://api.myvaluefirst.com
MYVALUEFIRST_USERNAME=your_username
MYVALUEFIRST_PASSWORD=your_password

# WhatsApp Configuration
WHATSAPP_PROVIDER=goinfinito
GOINFINITO_API_URL=https://api.goinfinito.com/unified/v2/send
GOINFINITO_API_TOKEN=your_token
GOINFINITO_FROM_NUMBER=your_number

# Basic Auth
BASIC_AUTH_USERNAME=admin
BASIC_AUTH_PASSWORD=secret
```

## Configuration Files

- `config/dynamic-levels-helper.php` - Main configuration
- `config/dynamic-levels-helper-sms.php` - SMS providers configuration
- `config/dynamic-levels-helper-whatsapp.php` - WhatsApp configuration
- `config/dynamic-levels-helper-stp.php` - Stored procedure mappings

## Advanced Features

### Connection Pool Configuration

```php
'connection_pool' => [
    'max_connections' => env('DB_SERVICE_MAX_CONNECTIONS', 10),
    'pool_timeout' => env('DB_SERVICE_POOL_TIMEOUT', 30),
    'idle_timeout' => env('DB_SERVICE_IDLE_TIMEOUT', 300),
    'retry_attempts' => env('DB_SERVICE_RETRY_ATTEMPTS', 3),
],
```

### Logging Configuration

```php
'logging' => [
    'enabled' => env('DB_SERVICE_LOGGING_ENABLED', true),
    'channel' => env('DB_SERVICE_LOGGING_CHANNEL', 'stp'),
    'log_queries' => env('DB_SERVICE_LOG_QUERIES', true),
    'log_errors' => env('DB_SERVICE_LOG_ERRORS', true),
    'log_execution_time' => env('DB_SERVICE_LOG_EXECUTION_TIME', true),
],
```

### Performance Monitoring

```php
'performance' => [
    'slow_query_threshold' => env('DB_SERVICE_SLOW_QUERY_THRESHOLD', 2.0),
    'enable_query_profiling' => env('DB_SERVICE_ENABLE_PROFILING', false),
],
```

## Migration from Previous Version

### DBService Changes

**Before:**
```php
$dbService = new DBService($connection);
$result = $dbService->callStoredProcedure('procedure', $params, ['connection' => $connection]);
```

**After:**
```php
$dbService = DBService::getInstance();
$result = $dbService->callStoredProcedure('procedure', $params, ['connection' => $connection]);
```

The trait `DBDataService` automatically uses the singleton, so no changes needed there.

## Testing

```bash
vendor/bin/pest
```

## License

This package is open-source software licensed under the MIT license.

## Changelog

### v2.0.0
- Complete rewrite of DBService with singleton pattern
- Added connection pooling with automatic management
- Enhanced logging with configurable channels and levels
- Added performance monitoring and metrics collection
- Introduced console commands for service management
- Added comprehensive test suite
- Improved error handling and retry logic
- Added facade for easier access

### v1.x
- Initial release with basic functionality
