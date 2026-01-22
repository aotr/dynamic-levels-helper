<?php

namespace Aotr\DynamicLevelHelper\Services\SMS;

use Exception;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected $providers = [];
    protected $config;

    public function __construct(array $providers, array $config)
    {
        $this->providers = $providers;
        $this->config = $config;
    }

    public function sendSms(string $phoneNumber, string $message, int $countryCode = 91): bool
    {
        try {
            // Validate country code against whitelist if enabled
            if ($this->shouldValidateCountryCode() && !$this->isCountryCodeWhitelisted($countryCode)) {
                Log::channel('sms_error')->warning('Country code not whitelisted', [
                    'country_code' => $countryCode,
                    'phone' => $phoneNumber,
                    'whitelist' => $this->config['whitelist_country_codes'] ?? [],
                ]);
                return false;
            }

            // Select provider based on country code
            $providerKey = $this->selectProvider($countryCode);

            if (!isset($this->providers[$providerKey])) {
                Log::channel('sms_error')->error('Provider not found', [
                    'provider_key' => $providerKey,
                    'country_code' => $countryCode,
                    'available_providers' => array_keys($this->providers),
                ]);
                return false;
            }

            $provider = $this->providers[$providerKey];

            // Validate provider supports this country code
            if (!$this->providerSupportsCountry($providerKey, $countryCode)) {
                Log::channel('sms_error')->error('Provider does not support country code', [
                    'provider' => $providerKey,
                    'country_code' => $countryCode,
                    'expected_countries' => $this->getProviderExpectedCountries($providerKey),
                ]);
                return false;
            }

            Log::channel('sms_error')->info('Sending SMS', [
                'provider' => $providerKey,
                'country_code' => $countryCode,
                'phone' => $phoneNumber,
            ]);

            return $provider->sendSms($phoneNumber, $message, $countryCode);
        } catch (Exception $e) {
            Log::channel('sms_error')->error('SMS sending failed', [
                'error' => $e->getMessage(),
                'country_code' => $countryCode,
                'phone' => $phoneNumber,
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    protected function selectProvider(int $countryCode): string
    {
        // Check country_mappings for specific country code
        $countryMappings = $this->config['country_mappings'] ?? [];

        if (isset($countryMappings[$countryCode])) {
            return $countryMappings[$countryCode];
        }

        // Fallback to default provider
        return $this->config['default_provider'] ?? 'myvaluefirst';
    }

    protected function providerSupportsCountry(string $providerKey, int $countryCode): bool
    {
        $expectedCountries = $this->getProviderExpectedCountries($providerKey);

        // If expected_countries is empty, provider supports all countries
        if (empty($expectedCountries)) {
            return true;
        }

        // Check if country code is in the expected list
        return in_array($countryCode, $expectedCountries);
    }

    protected function getProviderExpectedCountries(string $providerKey): array
    {
        $providerConfig = $this->config['providers'][$providerKey] ?? [];
        return $providerConfig['expected_countries'] ?? [];
    }

    protected function shouldValidateCountryCode(): bool
    {
        return $this->config['validate_country_codes'] ?? false;
    }

    protected function isCountryCodeWhitelisted(int $countryCode): bool
    {
        $whitelist = $this->config['whitelist_country_codes'] ?? [];
        return in_array($countryCode, $whitelist);
    }
}
