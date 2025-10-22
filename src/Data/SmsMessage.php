<?php

namespace YasserElgammal\LaraSms\Data;

class SmsMessage
{
    public function __construct(
        public readonly string $to,
        public readonly string $text,
        public readonly ?string $from = null,
        public readonly array $metadata = []
    ) {}
}
