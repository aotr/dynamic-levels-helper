<?php

namespace Aotr\DynamicLevelHelper\Services\SMS;

interface SmsProviderInterface
{
    public function sendSms(string $phoneNumber, string $message, int $countryCode = 91): bool;
}
