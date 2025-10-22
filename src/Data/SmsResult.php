<?php

namespace YasserElgammal\LaraSms\Data;

class SmsResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $messageId = null,
        public readonly ?string $gateway = null,
        public readonly ?string $error = null,
        public readonly array $attempts = []
    ) {}

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'messageId' => $this->messageId,
            'gateway' => $this->gateway,
            'error' => $this->error,
            'attempts' => $this->attempts,
        ];
    }
}
