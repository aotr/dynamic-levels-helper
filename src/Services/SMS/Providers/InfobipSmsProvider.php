<?php

namespace Aotr\DynamicLevelHelper\Services\SMS\Providers;

use Aotr\DynamicLevelHelper\Services\SMS\SmsProviderInterface;
use Illuminate\Support\Facades\Log;
use Exception;

class InfobipSmsProvider implements SmsProviderInterface
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
            Log::error('Failed to send SMS via Infobip: ' . $e->getMessage());
            return false;
        }
    }

    protected function formatMessage(string $phoneNumber, string $message, int $countryCode)
    {
        $formattedData = $this->config['format'];
        $formattedData['to'] = $countryCode . $phoneNumber;
        $formattedData['text'] = $message;

        // Add Infobip-specific fields, if necessary
        $formattedData['indiaDltContentTemplateId'] = $this->config['format']['indiaDltContentTemplateId'];
        $formattedData['indiaDltPrincipalEntityId'] = $this->config['format']['indiaDltPrincipalEntityId'];

        return $formattedData;
    }

    protected function sendRequest(string $url, array $formattedData)
    {
        $fullUrl = $url . http_build_query($formattedData);
        $ch = curl_init($fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    protected function handleResponse($response): bool
    {
        // Handle the response from Infobip API, you can customize this part based on the API documentation
        return !empty($response);
    }
}
