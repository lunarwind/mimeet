<?php

namespace App\Services\Sms;

interface SmsDriverInterface
{
    public function send(string $phone, string $body): bool;
}
