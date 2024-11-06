<?php

namespace Aotr\DynamicLevelHelper\Services\SMS;

use Exception;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected $provider;

    public function __construct(SmsProviderInterface $provider)
    {
        $this->provider = $provider;

    }

    public function sendSms(string $phoneNumber, string $message, int $countryCode = 91): bool
    {
        try {

            return $this->provider->sendSms($phoneNumber, $message, $countryCode);
        } catch (Exception $e) {
            Log::error('SMS sending failed: '.$e->getMessage());
            return false;
        }
    }
}
