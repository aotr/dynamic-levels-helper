<?php

namespace Aotr\DynamicLevelHelper\Services\WhatsAppSdk;

use Aotr\DynamicLevelHelper\Interface\WhatsApp\WhatsAppMessageInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class WhatsAppService
{
    protected $apiUrl;
    protected $apiToken;
    protected $fromNumber;

    public function __construct(string $apiUrl = null, string $apiToken = null, string $fromNumber = null)
    {
        $providerKey = config('dynamic-levels-helper-whatsapp.default_provider');
        $providerConfig = config('dynamic-levels-helper-whatsapp.'.$providerKey);
        $this->apiUrl = $apiUrl ?? $providerConfig['api_url'] ?? '';
        $this->apiToken = $apiToken ?? $providerConfig['api_token'] ?? '';
        $this->fromNumber = $fromNumber ?? $providerConfig['from_number'] ?? '';
    }

    /**
     * Sends a WhatsApp message.
     *
     * @param MessageInterface $message
     * @return array
     * @throws Exception
     */
    public function send(WhatsAppMessageInterface $message): array
    {
        $payload = $message->preparePayload();
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiToken,
            'Content-Type' => 'application/json',
        ])->post($this->apiUrl, $payload);

        if ($response->failed()) {
            $this->logError('Failed to send WhatsApp message', ['response' => $response->body()]);
            throw new Exception('Failed to send WhatsApp message: ' . $response->body());
        }

        return $response->json();
    }

    protected function logError(string $message, array $context = []): void
    {
        Log::channel('whatsapp')->error($message, $context);
    }
}
