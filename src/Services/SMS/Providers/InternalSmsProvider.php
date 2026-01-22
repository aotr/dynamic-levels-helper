<?php

namespace Aotr\DynamicLevelHelper\Services\SMS\Providers;

use Aotr\DynamicLevelHelper\Services\SMS\SmsProviderInterface;
use Exception;
use Illuminate\Support\Facades\Log;

class InternalSmsProvider implements SmsProviderInterface
{
    protected $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function sendSms(string $phoneNumber, string $message, int $countryCode = 91): bool
    {
        try {
            $formattedData = $this->formatMessage($phoneNumber, $message, $countryCode);
            $response = $this->sendRequest($this->config['url'], $formattedData);

            return $this->handleResponse($response);
        } catch (Exception $e) {
            Log::channel('sms_error')->error('Failed to send SMS via Internal provider', [
                'phone' => $phoneNumber,
                'country_code' => $countryCode,
                'error' => $e->getMessage(),
                'provider' => 'internal',
            ]);

            return false;
        }
    }

    protected function formatMessage(string $phoneNumber, string $message, int $countryCode)
    {
        $formattedData = $this->config['format'];
        $formattedData['to'] = $countryCode.$phoneNumber;
        $formattedData['message'] = $message;

        return $formattedData;
    }

    protected function sendRequest(string $url, array $formattedData)
    {
        $fullUrl = $url.'?'.http_build_query($formattedData);
        $ch = curl_init($fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("cURL Error: {$error}");
        }

        curl_close($ch);

        return $response;
    }

    protected function handleResponse($response): bool
    {
        if (empty($response)) {
            return false;
        }

        // Add custom response handling logic here
        // For example, check for success status in the response
        return true;
    }
}
