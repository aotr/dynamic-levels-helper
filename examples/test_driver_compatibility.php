<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Aotr\DynamicLevelHelper\Services\EnhancedDBService;
use Illuminate\Support\Facades\DB;

echo "=== Testing Enhanced DB Service with Driver Compatibility ===\n\n";

try {
    // Get the enhanced DB service instance
    $enhancedDb = EnhancedDBService::getInstance();
    echo "✓ EnhancedDBService instance created successfully\n";

    // Test with a simple procedure call
    echo "Testing stored procedure call...\n";
    
    // This should work without the PDO attribute error
    $result = $enhancedDb->callStoredProcedure('STP_VS_API', [], [
        'connection' => 'mysql',
        'timeout' => 30,
        'retryAttempts' => 1,
        'enableLogging' => true
    ]);
    
    echo "✓ Stored procedure call completed successfully\n";
    echo "Result: " . (empty($result) ? 'No data returned' : count($result) . ' result set(s)') . "\n";

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Error code: " . $e->getCode() . "\n";
    
    // Check if it's still the attribute error
    if (strpos($e->getMessage(), "Driver does not support this function") !== false) {
        echo "\n❌ The PDO attribute error still exists. The driver doesn't support setting timeout attributes.\n";
        echo "Recommendation: Add ENHANCED_DB_SERVICE_ENABLE_QUERY_TIMEOUT=false to your .env file\n";
    } elseif (strpos($e->getMessage(), "STP_VS_API") !== false && strpos($e->getMessage(), "does not exist") !== false) {
        echo "\n✓ Good! The PDO attribute error is fixed. This is just a procedure existence error.\n";
        echo "The stored procedure 'STP_VS_API' doesn't exist in your database, but the driver compatibility issue is resolved.\n";
    } else {
        echo "\n⚠️  Different error occurred: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Driver Compatibility Information ===\n";

try {
    // Get PDO connection info
    $pdo = DB::connection()->getPdo();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $version = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    
    echo "Database Driver: " . $driver . "\n";
    echo "Server Version: " . $version . "\n";
    
    // Test timeout attribute support
    try {
        $stmt = $pdo->prepare("SELECT 1");
        $stmt->setAttribute(PDO::ATTR_TIMEOUT, 30);
        echo "✓ Driver supports PDO::ATTR_TIMEOUT\n";
    } catch (Exception $e) {
        echo "✗ Driver does NOT support PDO::ATTR_TIMEOUT: " . $e->getMessage() . "\n";
        echo "  This is why the original error occurred.\n";
    }
    
} catch (Exception $e) {
    echo "Could not get driver information: " . $e->getMessage() . "\n";
}

echo "\n=== Configuration Recommendations ===\n";
echo "Add these to your .env file for optimal compatibility:\n\n";
echo "# Disable query timeout if your driver doesn't support it\n";
echo "ENHANCED_DB_SERVICE_ENABLE_QUERY_TIMEOUT=false\n\n";
echo "# Or keep it enabled (default) if your driver supports it\n";
echo "ENHANCED_DB_SERVICE_ENABLE_QUERY_TIMEOUT=true\n\n";
echo "# Other useful settings:\n";
echo "ENHANCED_DB_SERVICE_RETRY_ATTEMPTS=3\n";
echo "ENHANCED_DB_SERVICE_RETRY_DELAY=100\n";
echo "ENHANCED_DB_SERVICE_LOGGING_ENABLED=true\n";
