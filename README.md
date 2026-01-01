# Dynamic Levels Helper

A comprehensive Laravel package that provides enterprise-grade tools for database operations, SMS services, WhatsApp integration, geo-data management, icon systems, and more.

## 🚀 Features

### Core Services
- **Enhanced Database Service**: Singleton pattern with connection pooling, performance monitoring, and configurable logging
- **SMS Service**: Multi-provider support (Sinfini, Onex, MyValueFirst, Infobip)
- **WhatsApp Integration**: Structured messaging with GoInfinito provider
- **Geo Data Service**: Automatic syncing of countries, states, and cities from GitHub repository
- **Lucide Icon System**: SVG icon caching and Blade component integration
- **Parameter Service**: Efficient request parameter processing with multiple formats
- **Amount Format Helper**: Indian currency formatting with comprehensive options and Blade directives
- **Cache Service**: Advanced caching with invalidation and refresh capabilities

### Developer Tools
- **Response Macros**: Standardized API response formats
- **Basic Auth Middleware**: Simple HTTP basic auth protection
- **Console Commands**: Comprehensive management and monitoring tools
- **Blade Components**: Ready-to-use UI components


## 📋 Requirements

- PHP 8.1 or higher
- Laravel 10.x, 11.x, or 12.x
- Composer

## 📦 Installation

```bash
composer require aotr/dynamic-levels-helper
```

## ⚙️ Configuration

Publish all configuration files:

```bash
# Publish all configs at once
php artisan vendor:publish --provider="Aotr\DynamicLevelHelper\Providers\DynamicLevelHelperServiceProvider"

# Or publish specific configs
php artisan vendor:publish --tag=dynamic-levels-helper-config
php artisan vendor:publish --tag=dynamic-levels-helper-sms-config
php artisan vendor:publish --tag=dynamic-levels-helper-whatsapp-config
php artisan vendor:publish --tag=lucide-config
php artisan vendor:publish --tag=dynamic-levels-helper-scripts
```

---

## 📖 Feature Documentation

### 🗄️ Enhanced Database Service

Enterprise-grade database service with singleton pattern, connection pooling, and performance monitoring.

#### Key Features
- ✅ **Singleton Pattern**: Single instance for efficient resource management
- ✅ **Connection Pooling**: Automatic connection management with configurable pool size
- ✅ **Configurable Logging**: Flexible logging to different channels with granular control
- ✅ **Performance Monitoring**: Query execution tracking and slow query detection
- ✅ **Cached Procedure Checks**: Efficient stored procedure existence validation
- ✅ **Automatic Retry Logic**: Built-in retry mechanism for failed connections
- ✅ **Console Management**: Commands for monitoring and managing the service

#### Basic Usage

```php
use Aotr\DynamicLevelHelper\Services\EnhancedDBService;
use Aotr\DynamicLevelHelper\Facades\EnhancedDBService as DBServiceFacade;

// Singleton instance
$dbService = EnhancedDBService::getInstance();
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

#### Monitoring & Management

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

#### Configuration

```php
// config/enhanced-db-service.php

return [
    'default_connection' => env('DB_SERVICE_DEFAULT_CONNECTION', 'mysql'),
    
    'connection_pool' => [
        'max_connections' => env('DB_SERVICE_MAX_CONNECTIONS', 10),
        'pool_timeout' => env('DB_SERVICE_POOL_TIMEOUT', 30),
        'idle_timeout' => env('DB_SERVICE_IDLE_TIMEOUT', 300),
        'retry_attempts' => env('DB_SERVICE_RETRY_ATTEMPTS', 3),
    ],
    
    'logging' => [
        'enabled' => env('DB_SERVICE_LOGGING_ENABLED', true),
        'channel' => env('DB_SERVICE_LOGGING_CHANNEL', 'stp'),
        'log_queries' => env('DB_SERVICE_LOG_QUERIES', true),
        'log_errors' => env('DB_SERVICE_LOG_ERRORS', true),
        'log_execution_time' => env('DB_SERVICE_LOG_EXECUTION_TIME', true),
    ],
    
    'performance' => [
        'slow_query_threshold' => env('DB_SERVICE_SLOW_QUERY_THRESHOLD', 2.0),
        'enable_query_profiling' => env('DB_SERVICE_ENABLE_PROFILING', false),
    ],
];
```

#### Using DBDataService Trait

```php
use Aotr\DynamicLevelHelper\Traits\DBDataService;

class UserRepository
{
    use DBDataService;
    
    protected string $dbConnection = 'mysql';
    protected string $stpConfigPath = 'dynamic-levels-helper-stp';
    
    public function getUserData($userId)
    {
        return $this->getData('Stp_GetUserData', [
            'user_id' => $userId
        ]);
    }
}
```

---

### � Amount Format Helper

**Purpose**: Comprehensive Indian currency formatting with extensive customization options.

The `amount_format()` helper provides professional-grade currency formatting following Indian numbering conventions (lakhs, crores) with flexible configuration for various display requirements.

#### Key Features

- **Indian Numbering System**: Proper comma placement (1,00,000 for 1 lakh, 1,00,00,000 for 1 crore)
- **Currency Symbol Control**: Customizable symbol with position and spacing options
- **Smart Decimal Handling**: Option to hide .00, configurable decimal places
- **Negative Formatting**: Support for minus (-) or brackets () format
- **Exception-Free**: Robust error handling with safe fallbacks
- **Multiple Input Types**: Handles int, float, string, null gracefully
- **Blade Directives**: Ready-to-use directives for templates

#### Basic Usage

```php
// Basic formatting
amount_format(1000)           // ₹1,000
amount_format(1234567.89)     // ₹12,34,567.89
amount_format(1000.00)        // ₹1,000 (hides .00)

// Indian numbering
amount_format(100000)         // ₹1,00,000 (1 Lakh)
amount_format(1000000)        // ₹10,00,000 (10 Lakhs)
amount_format(10000000)       // ₹1,00,00,000 (1 Crore)

// Negative amounts
amount_format(-5000)                                    // -₹5,000
amount_format(-5000, ['negative_format' => 'brackets']) // (₹5,000)
```

#### Advanced Configuration

```php
// Custom symbol and positioning
amount_format(1000, ['symbol' => 'Rs.', 'symbol_space' => true])        // Rs. 1,000
amount_format(1000, ['symbol' => 'USD', 'symbol_position' => 'after'])  // 1,000USD
amount_format(1000, ['symbol' => ''])                                   // 1,000 (no symbol)

// Decimal control
amount_format(1000.00, ['hide_zero_decimals' => false])  // ₹1,000.00
amount_format(1234.56, ['decimals' => 0])                // ₹1,235 (rounded)
amount_format(1234.5678, ['decimals' => 3])              // ₹1,234.568

// Custom separators
amount_format(1234.56, [
    'decimal_separator' => ',',
    'thousands_separator' => '.'
])  // ₹1.234,56

// Complex configuration
$options = [
    'symbol' => 'USD',
    'decimals' => 3,
    'symbol_position' => 'after',
    'symbol_space' => true,
    'negative_format' => 'brackets',
    'hide_zero_decimals' => false
];
amount_format(1234.567, $options)   // 1,234.567 USD
amount_format(-1234.000, $options)  // (1,234.000 USD)
```

#### Blade Directives

The package includes convenient Blade directives for template usage:

```blade
{{-- Basic currency formatting --}}
@currency($amount)                    {{-- ₹1,000 --}}
@rupee($amount)                       {{-- Alias for @currency --}}
@amount($amount)                      {{-- 1,000 (no symbol) --}}
@currencyWhole($amount)               {{-- ₹1,000 (no decimals) --}}
@currencyWithOptions($amount, $opts)  {{-- With custom options --}}

{{-- Examples in templates --}}
<span class="price">@currency($product->price)</span>
<div class="total">Total: @currency($order->total)</div>
<p>Savings: @currency($discount, ['symbol' => '', 'decimals' => 0])</p>
```

#### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `symbol` | string | '₹' | Currency symbol (use '' for none) |
| `decimals` | int | 2 | Number of decimal places |
| `hide_zero_decimals` | bool | true | Hide .00 decimals |
| `decimal_separator` | string | '.' | Decimal separator character |
| `thousands_separator` | string | ',' | Thousands separator character |
| `symbol_position` | string | 'before' | 'before' or 'after' |
| `symbol_space` | bool | false | Add space between symbol and amount |
| `negative_format` | string | 'minus' | 'minus' (-₹1,000) or 'brackets' ((₹1,000)) |

#### Error Handling

```php
// All inputs are handled safely
amount_format(null)        // ₹0
amount_format('')          // ₹0
amount_format('invalid')   // ₹0
amount_format('1000.50')   // ₹1,000.50 (string parsing)
amount_format('₹1,000')    // ₹1,000 (already formatted)
```

#### Use Cases

- **E-commerce**: Product pricing, order totals, discounts
- **Financial Applications**: Account balances, transaction amounts
- **Reports**: Financial statements, invoices, receipts  
- **Dashboards**: Revenue displays, KPI formatting
- **APIs**: Consistent currency formatting in responses

#### Performance

- Optimized for high-frequency usage
- Exception-free with comprehensive error handling
- Minimal memory footprint
- Fast Indian numbering algorithm

---

### �📱 SMS Service

Multi-provider SMS service with support for major SMS gateways.

#### Supported Providers
- **Sinfini** - SMS gateway provider
- **Onex** - SMS service provider
- **MyValueFirst** - Enterprise SMS solutions
- **Infobip** - Global communications platform

#### Basic Usage

```php
use Aotr\DynamicLevelHelper\Services\SMS\SmsService;

$smsService = app(SmsService::class);

// Send SMS
$success = $smsService->sendSms(
    phoneNumber: '1234567890',
    message: 'Your OTP is 123456',
    countryCode: 91
);

if ($success) {
    echo "SMS sent successfully!";
}
```

#### Configuration

```php
// config/dynamic-levels-helper-sms.php

return [
    'default_provider' => env('SMS_PROVIDER', 'myvaluefirst'),
    
    'providers' => [
        'sinfini' => [
            'url' => env('SINFINI_URL'),
            'format' => [
                'api_key' => env('SINFINI_API_KEY'),
                'sender' => env('SINFINI_SENDER'),
                // ... more config
            ],
        ],
        
        'myvaluefirst' => [
            'url' => env('MYVALUEFIRST_URL'),
            'format' => [
                'username' => env('MYVALUEFIRST_USERNAME'),
                'password' => env('MYVALUEFIRST_PASSWORD'),
                'from' => env('MYVALUEFIRST_FROM'),
                'dlr-mask' => env('MYVALUEFIRST_DLR_MASK', '19'),
            ],
        ],
        
        'infobip' => [
            'url' => env('INFOBIP_URL'),
            'format' => [
                'username' => env('INFOBIP_USERNAME'),
                'password' => env('INFOBIP_PASSWORD'),
                'indiaDltContentTemplateId' => env('INFOBIP_DLT_TEMPLATE_ID'),
                'indiaDltPrincipalEntityId' => env('INFOBIP_DLT_ENTITY_ID'),
            ],
        ],
    ],
];
```

#### Environment Variables

```env
SMS_PROVIDER=myvaluefirst

# MyValueFirst Configuration
MYVALUEFIRST_URL=https://api.myvaluefirst.com/psms/servlet/psms.Sendmsg?
MYVALUEFIRST_USERNAME=your_username
MYVALUEFIRST_PASSWORD=your_password
MYVALUEFIRST_FROM=SENDERID
MYVALUEFIRST_DLR_MASK=19

# Infobip Configuration
INFOBIP_URL=https://api.infobip.com/sms/1/text/single
INFOBIP_USERNAME=your_username
INFOBIP_PASSWORD=your_password
INFOBIP_DLT_TEMPLATE_ID=your_template_id
INFOBIP_DLT_ENTITY_ID=your_entity_id
```

---

### 💬 WhatsApp Service

Structured WhatsApp messaging integration with GoInfinito provider.

#### Basic Usage

```php
use Aotr\DynamicLevelHelper\Services\WhatsAppSdk\WhatsAppService;
use Aotr\DynamicLevelHelper\Services\WhatsAppSdk\Messages\TextMessage;

$whatsAppService = new WhatsAppService();

// Create a text message
$message = new TextMessage(
    to: '919876543210',
    message: 'Hello from Laravel!'
);

// Send the message
$response = $whatsAppService->send($message);
```

#### Message Types

```php
// Text Message
$textMessage = new TextMessage(
    to: '919876543210',
    message: 'Your order has been confirmed!'
);

// Template Message (if supported)
$templateMessage = new TemplateMessage(
    to: '919876543210',
    templateId: 'order_confirmation',
    parameters: ['John', 'ORD123']
);
```

#### Configuration

```php
// config/dynamic-levels-helper-whatsapp.php

return [
    'default_provider' => env('WHATSAPP_PROVIDER', 'goinfinito'),
    
    'goinfinito' => [
        'api_url' => env('GOINFINITO_API_URL', 'https://api.goinfinito.com/unified/v2/send'),
        'api_token' => env('GOINFINITO_API_TOKEN'),
        'from_number' => env('GOINFINITO_FROM_NUMBER'),
    ],
];
```

#### Environment Variables

```env
WHATSAPP_PROVIDER=goinfinito
GOINFINITO_API_URL=https://api.goinfinito.com/unified/v2/send
GOINFINITO_API_TOKEN=your_api_token
GOINFINITO_FROM_NUMBER=your_whatsapp_number
```

---

### 🌍 Geo Data Service

Automatic syncing and caching of geographical data (countries, states, cities) from GitHub repository.

#### Features
- ✅ Automatic download of geo JSON files
- ✅ Gzip fallback for large files
- ✅ Streaming downloads for memory efficiency
- ✅ Built-in caching for fast access
- ✅ Multiple data formats (countries, states, cities, regions)
- ✅ Bash script alternative for memory-constrained environments

#### Basic Usage

```php
use Aotr\DynamicLevelHelper\Services\GeoDataService;
use Aotr\DynamicLevelHelper\Facades\GeoDataService as GeoData;

$geoService = app(GeoDataService::class);

// Sync all data from GitHub
$geoService->sync();

// Get countries
$countries = $geoService->getCountries();

// Get countries with states
$countriesStates = $geoService->getCountriesWithStates();

// Get cities
$cities = $geoService->getCities();

// Get countries with cities
$countriesCities = $geoService->getCountriesWithCities();

// Get complete data
$allData = $geoService->getCountriesStatesCities();

// Get regions
$regions = $geoService->getRegions();

// Get states
$states = $geoService->getStates();
```

#### Artisan Commands

```bash
# Sync geo data files
php artisan sync:countries-states-json

# Manage geo data sync script
php artisan geo:script install   # Install bash script
php artisan geo:script run       # Run the sync script
php artisan geo:script status    # Check script status
php artisan geo:script uninstall # Remove script
```

#### Memory-Efficient Bash Script

For servers with limited memory, use the bash script:

```bash
# Install the script
php artisan geo:script install

# Run the script directly
bash scripts/sync-geo-data.sh

# Or via artisan
php artisan geo:script run
```

#### Data Structure Example

```php
// Countries data structure
[
    [
        'id' => 1,
        'name' => 'India',
        'iso2' => 'IN',
        'iso3' => 'IND',
        'phone_code' => '91',
        'capital' => 'New Delhi',
        'currency' => 'INR',
        'states' => [...] // When using getCountriesWithStates()
    ],
    // ... more countries
]
```

---

### 🎨 Lucide Icon System

SVG icon caching system with Blade component integration for [Lucide Icons](https://lucide.dev/).

#### Features
- ✅ Automatic icon fetching from CDN
- ✅ Local caching for performance
- ✅ Blade component integration
- ✅ Customizable attributes
- ✅ Artisan commands for management
- ✅ Graceful conflict handling

#### Basic Usage

```blade
{{-- Basic icon --}}
<x-lucide-icon name="check" />

{{-- Icon with size --}}
<x-lucide-icon name="arrow-right" size="24" />

{{-- Icon with Tailwind classes --}}
<x-lucide-icon name="alert-circle" class="w-6 h-6 text-red-500" />

{{-- Icon with custom attributes --}}
<x-lucide-icon 
    name="settings" 
    size="32" 
    stroke="#333" 
    stroke-width="1.5" 
    class="hover:text-blue-500"
/>

{{-- Icon with color alias --}}
<x-lucide-icon name="heart" color="red" size="20" />
```

#### Programmatic Usage

```php
use Aotr\DynamicLevelHelper\Services\LucideIconService;

$iconService = app(LucideIconService::class);

// Get icon with attributes
$svg = $iconService->getIcon('check', [
    'class' => 'w-6 h-6',
    'stroke' => 'currentColor'
]);

// Cache icons
$iconService->cache('check');
$iconService->cache('arrow-right', force: true);

// Cache multiple icons
$results = $iconService->cacheMany(['check', 'download', 'upload']);

// Check if icon exists
if ($iconService->exists('check')) {
    // Icon is cached
}

// Get all cached icons
$cachedIcons = $iconService->getCachedIcons();

// Clear cache
$iconService->clearCache();
```

#### Artisan Commands

```bash
# Cache single icon
php artisan lucide:cache check

# Cache multiple icons
php artisan lucide:cache --list=check,download,arrow-right

# Force re-download
php artisan lucide:cache check --force

# Show cache status
php artisan lucide:cache --status

# Clear all cached icons
php artisan lucide:cache --clear
```

#### Configuration

```php
// config/lucide.php

return [
    'icon_storage_disk' => env('LUCIDE_STORAGE_DISK', 'local'),
    'icon_storage_path' => env('LUCIDE_STORAGE_PATH', 'lucide/icons'),
    'remote_source' => env('LUCIDE_REMOTE_SOURCE', 
        'https://unpkg.com/lucide-static@latest/icons/{icon}.svg'
    ),
    'cache_ttl' => env('LUCIDE_CACHE_TTL', 86400),
    
    'default_attributes' => [
        'stroke' => 'currentColor',
        'stroke-width' => '2',
        'stroke-linecap' => 'round',
        'stroke-linejoin' => 'round',
        'fill' => 'none',
    ],
];
```

#### Available Attributes

- `name` (required) - Icon name in kebab-case
- `size` - Sets both width and height
- `stroke` or `color` - Stroke color
- `stroke-width` or `strokeWidth` - Stroke width
- `stroke-linecap` or `strokeLinecap` - Stroke linecap style
- `stroke-linejoin` or `strokeLinejoin` - Stroke linejoin style
- `fill` - Fill color
- `class` - CSS classes

---

### 🔧 Parameter Service

Efficient parameter processing service for converting request data into formatted strings.

#### Basic Usage

```php
use Aotr\DynamicLevelHelper\Services\ParameterService;
use Aotr\DynamicLevelHelper\Facades\ParameterService as Params;

// Process request data with default delimiter (^^)
$params = ParameterService::process($request);

// Process with custom delimiter
$params = ParameterService::process($request, delimiter: '||');

// Process specific keys in order
$params = ParameterService::process($request, sequence: ['user_id', 'email', 'phone']);

// Simple processing (quick method)
$params = ParameterService::quick($request, ['user_id', 'email']);

// Using facade
$params = Params::process($request->all());
```

#### Advanced Examples

```php
// From array data
$data = [
    'user_id' => 123,
    'email' => 'user@example.com',
    'phone' => '1234567890'
];

$formatted = ParameterService::process($data);
// Output: "123^^user@example.com^^1234567890"

// With specific sequence
$formatted = ParameterService::process($data, sequence: ['phone', 'email']);
// Output: "1234567890^^user@example.com"

// Custom delimiter
$formatted = ParameterService::process($data, delimiter: '|');
// Output: "123|user@example.com|1234567890"

// From Laravel Request
$formatted = ParameterService::process($request, 
    sequence: ['name', 'email', 'phone'],
    delimiter: '^^'
);

// Handle nested keys
$formatted = ParameterService::process([
    'user' => ['id' => 1, 'name' => 'John'],
    'order' => ['id' => 100]
], sequence: ['user.id', 'user.name', 'order.id']);
// Output: "1^^John^^100"
```

#### Use Cases

```php
// For stored procedures
$params = ParameterService::process($request, 
    sequence: ['country_code', 'phone', 'platform', 'device_id']
);
$result = $dbService->callStoredProcedure('Stp_Request_OTP', [$params]);

// For API integrations
$apiParams = ParameterService::process($data, delimiter: '&');

// For CSV-like formats
$csvRow = ParameterService::process($data, delimiter: ',');
```

---

### 💾 Cache Service

Advanced caching service with TTL management and cache invalidation.

#### Basic Usage

```php
use Aotr\DynamicLevelHelper\Services\CacheService;

$cacheService = new CacheService();

// Generate cache key from data
$key = $cacheService->generateCacheKey([
    'user_id' => 123,
    'type' => 'profile'
]);

// Store data
$cacheService->putCache($key, $data, ttl: 3600);

// Retrieve data
$cachedData = $cacheService->getCache($key);

// Invalidate cache
$cacheService->invalidateCache($key);

// Refresh cache with new data
$cacheService->refreshCache($key, $newData, ttl: 7200);
```

---

### 📡 Response Macros

Standardized API response formats for consistent API responses.

#### Basic Usage

```php
// Success response
return response()->api($data);
// Output: { "error": 0, "errmsg": "", "response": {...} }

// Processed response (filters and transforms)
return response()->apiProcess($data);

// Error response
return response()->apiError('User not found', 1001);
// Output: { "error": 1001, "errmsg": "User not found", "response": [] }

// Custom error response
return response()->apiError(
    message: 'Validation failed',
    errorCode: 422,
    data: ['field' => 'email', 'error' => 'Invalid email']
);
```

#### Response Structure

All responses follow this structure:
```json
{
  "error": 0,
  "errmsg": "",
  "response": {},
  "request": {}
}
```

---

### 🔐 Basic Auth Middleware

Simple HTTP basic authentication middleware.

#### Usage

```php
// In routes/web.php or routes/api.php
Route::middleware('dynamic.basic.auth')->group(function () {
    Route::get('/admin/dashboard', [AdminController::class, 'index']);
    Route::get('/admin/users', [AdminController::class, 'users']);
});

// Single route
Route::get('/admin/settings', [AdminController::class, 'settings'])
    ->middleware('dynamic.basic.auth');
```

#### Configuration

```php
// config/dynamic-levels-helper.php
return [
    'basic_auth_username' => env('BASIC_AUTH_USERNAME', 'admin'),
    'basic_auth_password' => env('BASIC_AUTH_PASSWORD', 'secret'),
];
```

```env
BASIC_AUTH_USERNAME=admin
BASIC_AUTH_PASSWORD=your_secure_password
```

---

### 🎯 Console Commands

#### Dynamic Quotes

Display inspirational quotes from GMCKS:

```bash
php artisan dynamic:quote
```

#### Database Service Management

```bash
# View statistics
php artisan db-service:manage stats

# View performance metrics
php artisan db-service:manage performance

# View configuration
php artisan db-service:manage config

# Reset connections
php artisan db-service:manage reset
```

#### Geo Data Management

```bash
# Sync geo data
php artisan sync:countries-states-json

# Script management
php artisan geo:script install
php artisan geo:script run
php artisan geo:script status
php artisan geo:script uninstall
```

#### Lucide Icons

```bash
# Cache icons
php artisan lucide:cache check
php artisan lucide:cache --list=check,download,arrow-right
php artisan lucide:cache --force

# Cache status
php artisan lucide:cache --status

# Clear cache
php artisan lucide:cache --clear
```

---

## 🔧 Environment Variables

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
MYVALUEFIRST_URL=https://api.myvaluefirst.com/psms/servlet/psms.Sendmsg?
MYVALUEFIRST_USERNAME=your_username
MYVALUEFIRST_PASSWORD=your_password
MYVALUEFIRST_FROM=SENDERID

# WhatsApp Configuration
WHATSAPP_PROVIDER=goinfinito
GOINFINITO_API_URL=https://api.goinfinito.com/unified/v2/send
GOINFINITO_API_TOKEN=your_token
GOINFINITO_FROM_NUMBER=your_number

# Lucide Icons
LUCIDE_STORAGE_DISK=local
LUCIDE_STORAGE_PATH=lucide/icons
LUCIDE_CACHE_TTL=86400

# Basic Auth
BASIC_AUTH_USERNAME=admin
BASIC_AUTH_PASSWORD=secret
```

---

## 🧪 Testing

```bash
# Run all tests
vendor/bin/pest

# Run specific test suite
vendor/bin/pest --filter=DBService
vendor/bin/pest --filter=Lucide
vendor/bin/pest --filter=Parameter

# Run with coverage
vendor/bin/pest --coverage
```

---

## 📝 Migration Guide

### From v1.x to v2.x

#### DBService Changes

**Before:**
```php
$dbService = new DBService($connection);
$result = $dbService->callStoredProcedure('procedure', $params);
```

**After:**
```php
$dbService = EnhancedDBService::getInstance();
$result = $dbService->callStoredProcedure('procedure', $params);
```

The `DBDataService` trait automatically uses the new service, so no changes needed in your repository classes.

---

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

---

## 📄 License

This package is open-source software licensed under the MIT license.

---

## 📚 Changelog

### v3.0.0 (Latest)
- ✨ Added Lucide Icon System with Blade components
- ✨ Added Geo Data Service with automatic syncing
- ✨ Added bash script alternative for geo data sync
- ✨ Enhanced Parameter Service with multiple processing methods
- 🔧 Improved error handling across all services
- 🔧 Added graceful component conflict handling
- 📝 Comprehensive documentation and examples

### v2.0.0
- 🎉 Complete rewrite of DBService with singleton pattern
- ✨ Added connection pooling with automatic management
- ✨ Enhanced logging with configurable channels
- ✨ Added performance monitoring and metrics collection
- ✨ Introduced console commands for service management
- ✨ Added comprehensive test suite
- 🔧 Improved error handling and retry logic
- 📝 Added facade for easier access

### v1.x
- 🎉 Initial release with basic functionality

---

## 🆘 Support

For issues, questions, or contributions, please visit the [GitHub repository](https://github.com/aotr/dynamic-levels-helper).

---

## 👨‍💻 Author

**Animesh Chakraborty**
- GitHub: [@aotr](https://github.com/aotr)
- Email: animesh.aotr@gmail.com

---

Made with ❤️ for the Laravel community
