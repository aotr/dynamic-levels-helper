<?php

namespace Aotr\DynamicLevelHelper\Services\SMS\Providers;

use Aotr\DynamicLevelHelper\Services\SMS\SmsProviderInterface;
use Exception;
use Illuminate\Support\Facades\Log;

class SinfiniSmsProvider implements SmsProviderInterface
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
            Log::error('Failed to send SMS via Sinfini: '.$e->getMessage());

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
        $fullUrl = $url.http_build_query($formattedData);
        $ch = curl_init($fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }

    protected function handleResponse($response): bool
    {
        return ! empty($response); // You can add custom response handling logic here.
    }
}
