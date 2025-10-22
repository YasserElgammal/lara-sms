<?php

namespace YasserElgammal\LaraSms\Services;

use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Facades\Log;
use YasserElgammal\LaraSms\Contracts\SmsGateway;
use YasserElgammal\LaraSms\Contracts\SmsSendBuilder;
use YasserElgammal\LaraSms\Data\SmsMessage;
use YasserElgammal\LaraSms\Data\SmsResult;
use YasserElgammal\LaraSms\Enums\FallbackStrategy;

class SmsManager
{
    protected array $gateways = [];
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->initializeGateways();
    }

    /**
     * Initialize all configured gateways
     */
    protected function initializeGateways(): void
    {
        foreach ($this->config['gateways'] as $name => $gatewayConfig) {
            $class = $gatewayConfig['class'];
            $this->gateways[$name] = new $class(
                app(HttpClient::class),
                array_merge($gatewayConfig['config'], $this->config['http'])
            );
        }
    }

    /**
     * Send SMS message
     * 
     * @param SmsMessage $message
     * @param FallbackStrategy|null $strategy
     * @param array|null $gatewayOrder
     * @return SmsResult
     */
    public function send(
        SmsMessage $message,
        ?FallbackStrategy $strategy = null,
        ?array $gatewayOrder = null
    ): SmsResult {
        $strategy = $strategy ?? FallbackStrategy::from($this->config['default_fallback_strategy']);
        $gatewayOrder = $gatewayOrder ?? array_keys($this->config['gateways']);

        $attempts = [];

        Log::info('[LaraSms] Sending SMS', [
            'to' => $message->to,
            'strategy' => $strategy->value,
            'gateways' => $gatewayOrder,
        ]);

        foreach ($gatewayOrder as $gatewayName) {
            if (!isset($this->gateways[$gatewayName])) {
                Log::warning("[LaraSms] Gateway not found: {$gatewayName}");
                continue;
            }

            $gateway = $this->gateways[$gatewayName];
            $result = $this->attemptSend($gateway, $message);

            $attempts[] = [
                'gateway' => $gatewayName,
                'success' => $result->success,
                'error' => $result->error,
                'timestamp' => now()->toDateTimeString(),
            ];

            if ($result->success) {
                Log::info('[LaraSms] SMS sent successfully', [
                    'to' => $message->to,
                    'gateway' => $gatewayName,
                    'message_id' => $result->messageId,
                    'attempts' => count($attempts),
                ]);

                return new SmsResult(
                    success: true,
                    messageId: $result->messageId,
                    gateway: $gatewayName,
                    attempts: $attempts
                );
            }

            // If fail fast strategy and this is a non-retryable error, stop
            if (
                $strategy === FallbackStrategy::FAIL_FAST &&
                $this->isNonRetryableError($result->error)
            ) {
                Log::warning('[LaraSms] Non-retryable error detected, stopping', [
                    'gateway' => $gatewayName,
                    'error' => $result->error,
                ]);
                break;
            }

            Log::warning('[LaraSms] Gateway failed, trying next', [
                'to' => $message->to,
                'gateway' => $gatewayName,
                'error' => $result->error,
            ]);
        }

        Log::error('[LaraSms] All gateways failed', [
            'to' => $message->to,
            'attempts' => $attempts,
        ]);

        return new SmsResult(
            success: false,
            error: "All gateways failed",
            attempts: $attempts
        );
    }

    /**
     * Attempt to send SMS via specific gateway
     * 
     * @param SmsGateway $gateway
     * @param SmsMessage $message
     * @return SmsResult
     */
    protected function attemptSend(SmsGateway $gateway, SmsMessage $message): SmsResult
    {
        try {
            return $gateway->send($message);
        } catch (\Throwable $e) {
            Log::debug('[LaraSms] Gateway attempt error', [
                'gateway' => $gateway->getName(),
                'error' => $e->getMessage(),
            ]);

            return new SmsResult(
                success: false,
                gateway: $gateway->getName(),
                error: $e->getMessage()
            );
        }
    }

    /**
     * Check if error is non-retryable
     * 
     * @param string|null $error
     * @return bool
     */
    protected function isNonRetryableError(?string $error): bool
    {
        if (!$error) return false;

        $nonRetryablePatterns = [
            'invalid phone number',
            'unauthorized',
            'forbidden',
            'bad request',
            'invalid credentials',
            'not configured',
            'missing',
        ];

        foreach ($nonRetryablePatterns as $pattern) {
            if (stripos($error, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get builder instance
     * 
     * @return SmsSendBuilder
     */
    public function builder(): SmsSendBuilder
    {
        return new SmsSendBuilder($this);
    }


    /**
     * Quick send SMS using default gateway
     * 
     * @param string $to
     * @param string $text
     * @param string|null $from
     * @return SmsResult
     */
    public function quickSend(string $to, string $text, ?string $from = null): SmsResult
    {
        $builder = $this->builder()
            ->to($to)
            ->text($text);

        if ($from) {
            $builder->from($from);
        }

        // Use default gateway from config
        if (isset($this->config['default_gateway'])) {
            $builder->gateway($this->config['default_gateway']);
        }

        return $builder->send();
    }

    /**
     * Get all available gateways
     * 
     * @return array
     */
    public function getAvailableGateways(): array
    {
        return array_keys($this->gateways);
    }

    /**
     * Get specific gateway
     * 
     * @param string $name
     * @return SmsGateway|null
     */
    public function getGateway(string $name): ?SmsGateway
    {
        return $this->gateways[$name] ?? null;
    }

    /**
     * Check if gateway exists
     * 
     * @param string $name
     * @return bool
     */
    public function hasGateway(string $name): bool
    {
        return isset($this->gateways[$name]);
    }
}
