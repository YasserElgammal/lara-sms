<?php

namespace YasserElgammal\LaraSms\Contracts;

use YasserElgammal\LaraSms\Data\SmsMessage;
use YasserElgammal\LaraSms\Data\SmsResult;

interface SmsGateway
{
    public function send(SmsMessage $message): SmsResult;
    public function getName(): string;
}
