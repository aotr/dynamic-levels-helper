<?php

/**
 * SMS Service Country-Based Routing Example
 *
 * This example demonstrates how to use the SMS service with
 * country-based provider routing and validation.
 */

require_once __DIR__.'/../vendor/autoload.php';

use Aotr\DynamicLevelHelper\Services\SMS\SmsService;

// Note: In a real Laravel app, you would use dependency injection:
// $smsService = app(SmsService::class);

// For this example, we'll show the usage patterns
echo "=== SMS Service Country-Based Routing Examples ===\n\n";

// Example 1: Send to India (Country Code 91)
// Based on config, this uses 'internal' provider
echo "1. Sending SMS to India (91):\n";
echo "   Provider: internal (automatically selected)\n";
echo "   Code:\n";
echo "   \$smsService->sendSms('9876543210', 'Your OTP is 123456', 91);\n\n";

// Example 2: Send to USA (Country Code 1)
// Based on config, this uses 'onex' provider
echo "2. Sending SMS to USA (1):\n";
echo "   Provider: onex (automatically selected)\n";
echo "   Code:\n";
echo "   \$smsService->sendSms('2025551234', 'Hello from Laravel!', 1);\n\n";

// Example 3: Send to UK (Country Code 44)
// Based on config, this uses 'infobip' provider
echo "3. Sending SMS to UK (44):\n";
echo "   Provider: infobip (automatically selected)\n";
echo "   Code:\n";
echo "   \$smsService->sendSms('7700900123', 'Your order is confirmed', 44);\n\n";

// Example 4: Send to unmapped country
// Falls back to default_provider
echo "4. Sending SMS to Singapore (65) - unmapped:\n";
echo "   Provider: myvaluefirst (default_provider)\n";
echo "   Code:\n";
echo "   \$smsService->sendSms('98765432', 'Welcome message', 65);\n\n";

// Example 5: Provider restriction validation
echo "5. Provider Restrictions:\n";
echo "   If 'internal' provider is configured with expected_countries = [91],\n";
echo "   attempting to send to country code 1 via 'internal' will fail.\n";
echo "   The service automatically validates and logs the error.\n\n";

// Example 6: Whitelist validation
echo "6. Country Code Whitelist:\n";
echo "   When validate_country_codes = true:\n";
echo "   - Country codes in whitelist: allowed\n";
echo "   - Country codes not in whitelist: rejected and logged\n\n";

// Configuration Example
echo "=== Configuration Examples ===\n\n";

echo "Basic country mapping:\n";
echo <<<'PHP'
'country_mappings' => [
    91 => 'internal',       // India
    1 => 'onex',           // USA
    44 => 'infobip',       // UK
],
PHP;
echo "\n\n";

echo "Provider restrictions:\n";
echo <<<'PHP'
'providers' => [
    'internal' => [
        // ... config
        'expected_countries' => [91],  // Only India
    ],
    'infobip' => [
        // ... config
        'expected_countries' => [1, 44, 65],  // USA, UK, Singapore
    ],
    'myvaluefirst' => [
        // ... config
        'expected_countries' => [],  // All countries
    ],
],
PHP;
echo "\n\n";

echo "Whitelist validation:\n";
echo <<<'PHP'
'whitelist_country_codes' => [91, 1, 44, 65, 60, 971, 966],
'validate_country_codes' => true,
PHP;
echo "\n\n";

// Actual Usage Example (uncomment to test in Laravel app)
echo "=== Actual Usage in Laravel App ===\n\n";
echo <<<'PHP'
<?php

use Aotr\DynamicLevelHelper\Services\SMS\SmsService;

class OtpController extends Controller
{
    protected $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    public function sendOtp(Request $request)
    {
        $phoneNumber = $request->input('phone');
        $countryCode = $request->input('country_code');
        $otp = rand(100000, 999999);

        // Provider is automatically selected based on country_code
        $success = $this->smsService->sendSms(
            phoneNumber: $phoneNumber,
            message: "Your OTP is {$otp}. Valid for 5 minutes.",
            countryCode: $countryCode
        );

        if ($success) {
            return response()->json([
                'success' => true,
                'message' => 'OTP sent successfully'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to send OTP'
        ], 500);
    }
}
PHP;
echo "\n\n";

// Error Logging Example
echo "=== Error Logging ===\n\n";
echo "All SMS errors are logged to: storage/logs/sms/error.log\n\n";
echo "View logs:\n";
echo "  tail -f storage/logs/sms/error.log\n\n";
echo "Example log entries:\n";
echo "  - Provider selection decisions\n";
echo "  - Country code validation failures\n";
echo "  - Provider not found errors\n";
echo "  - Provider restriction violations\n";
echo "  - SMS sending failures\n\n";

// Advanced Usage
echo "=== Advanced Usage ===\n\n";

echo "1. Multiple SMS to different countries:\n";
echo <<<'PHP'
$recipients = [
    ['phone' => '9876543210', 'country' => 91],  // India -> internal
    ['phone' => '2025551234', 'country' => 1],   // USA -> onex
    ['phone' => '7700900123', 'country' => 44],  // UK -> infobip
];

foreach ($recipients as $recipient) {
    $smsService->sendSms(
        $recipient['phone'],
        'Your message here',
        $recipient['country']
    );
}
PHP;
echo "\n\n";

echo "2. Conditional provider usage:\n";
echo <<<'PHP'
// The service handles this automatically, but you can check:
$countryCode = 91;

// This will use 'internal' provider for country code 91
// No need to manually select provider
$success = $smsService->sendSms($phone, $message, $countryCode);
PHP;
echo "\n\n";

echo "=== Environment Variables ===\n\n";
echo <<<'ENV'
# Default provider
SMS_PROVIDER=myvaluefirst

# Country-specific overrides
SMS_PROVIDER_91=internal
SMS_PROVIDER_1=onex
SMS_PROVIDER_44=infobip

# Internal provider config
INTERNAL_SMS_URL=https://your-internal-api.com/sms
INTERNAL_SMS_API_KEY=your_api_key
INTERNAL_SMS_SENDER=YOURBRAND

# Validation
SMS_VALIDATE_COUNTRY_CODES=false
ENV;
echo "\n\n";

echo "=== Summary ===\n\n";
echo "✅ Automatic provider selection based on country code\n";
echo "✅ Provider restrictions by country\n";
echo "✅ Country code whitelist validation\n";
echo "✅ Comprehensive error logging to dedicated channel\n";
echo "✅ Backward compatible with existing code\n";
echo "✅ Easy configuration via env variables\n";
