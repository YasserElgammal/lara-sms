<?php

namespace YasserElgammal\LaraSms\Contracts;

use YasserElgammal\LaraSms\Data\SmsMessage;
use YasserElgammal\LaraSms\Data\SmsResult;
use YasserElgammal\LaraSms\Enums\FallbackStrategy;
use YasserElgammal\LaraSms\Services\SmsManager;

class SmsSendBuilder
{
    protected ?string $to = null;
    protected ?string $from = null;
    protected ?string $text = null;
    protected array $metadata = [];
    protected ?FallbackStrategy $fallbackStrategy = null;
    protected ?array $gatewayOrder = null;

    public function __construct(protected SmsManager $manager) {}

    public static function make(SmsManager $manager): self
    {
        return new self($manager);
    }

    public function to(string $phoneNumber): self
    {
        $this->to = $phoneNumber;
        return $this;
    }

    public function from(?string $sender): self
    {
        $this->from = $sender;
        return $this;
    }

    public function text(string $message): self
    {
        $this->text = $message;
        return $this;
    }

    public function metadata(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);
        return $this;
    }

    public function addMeta(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    public function useFallback(FallbackStrategy $strategy): self
    {
        $this->fallbackStrategy = $strategy;
        return $this;
    }

    public function gateways(array $gatewayOrder): self
    {
        $this->gatewayOrder = $gatewayOrder;
        return $this;
    }

    public function gateway(string $gatewayName): self
    {
        $this->gatewayOrder = [$gatewayName];
        $this->fallbackStrategy = FallbackStrategy::FAIL_FAST;
        return $this;
    }

    public function failFast(): self
    {
        $this->fallbackStrategy = FallbackStrategy::FAIL_FAST;
        return $this;
    }

    public function tryAll(): self
    {
        $this->fallbackStrategy = FallbackStrategy::TRY_ALL;
        return $this;
    }

    public function build(): SmsMessage
    {
        if (!$this->to || !$this->text) {
            throw new \InvalidArgumentException('Phone number (to) and message text are required');
        }

        return new SmsMessage(
            to: $this->to,
            text: $this->text,
            from: $this->from,
            metadata: $this->metadata
        );
    }

    public function send(): SmsResult
    {
        $message = $this->build();

        return $this->manager->send(
            $message,
            $this->fallbackStrategy,
            $this->gatewayOrder
        );
    }

    public function getState(): array
    {
        return [
            'to' => $this->to,
            'from' => $this->from,
            'text' => $this->text,
            'metadata' => $this->metadata,
            'fallback_strategy' => $this->fallbackStrategy?->value,
            'gateway_order' => $this->gatewayOrder,
        ];
    }
}

