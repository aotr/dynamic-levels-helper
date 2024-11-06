<?php

namespace Aotr\DynamicLevelHelper\Services\SMS\Providers;

use Aotr\DynamicLevelHelper\Services\SMS\SmsProviderInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Exception;

class MyValueFirstSmsProvider implements SmsProviderInterface
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
            // Log any exception
            Log::error('Failed to send SMS via MyValueFirst: ' . $e->getMessage());
            return false;
        }
    }

    protected function formatMessage(string $phoneNumber, string $message, int $countryCode)
    {
        $formattedData = $this->config['format'];
        $formattedData['to'] = '+'.$countryCode . $phoneNumber;
        $formattedData['text'] = $message;

        // MyValueFirst specific fields
        $formattedData['dlr-mask'] = $this->config['format']['dlr-mask'];

        return $formattedData;
    }

    protected function sendRequest(string $url, array $formattedData)
    {
        try {
            $formattedData = http_build_query($formattedData);

            $url .= $formattedData;

            // Send the request using Laravel's Http client
            $response = Http::get($url);
            // dd($response->body());
            // Check if the request failed
            if ($response->failed()) {
                throw new Exception('HTTP request failed.');
            }

            return $response;
        } catch (Exception $e) {
            return null;
        }
    }

    protected function handleResponse($response): bool
    {
        // Handle the response from MyValueFirst API, you can add custom logic here
        if (!empty($response)) {
            // Assume non-empty response means success, modify this based on API specs
            return true;
        }

        return false;
    }
}
