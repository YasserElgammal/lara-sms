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
        if (!is_array($config['gateways'] ?? null) || !$config['gateways'] ||
            !is_string($config['default_fallback_strategy'] ?? null) ||
            FallbackStrategy::tryFrom($config['default_fallback_strategy']) === null ||
            !is_array($config['http'] ?? null)) {
            throw new \YasserElgammal\LaraSms\Exceptions\InvalidConfigurationException('Invalid SMS configuration');
        }
        foreach ($config['gateways'] as $gateway) {
            if (!is_array($gateway) || !is_string($gateway['class'] ?? null) ||
                !is_a($gateway['class'], SmsGateway::class, true) || !is_array($gateway['config'] ?? null)) {
                throw new \YasserElgammal\LaraSms\Exceptions\InvalidConfigurationException('Invalid gateway configuration');
            }
        }
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

        if (!$gatewayOrder || array_filter($gatewayOrder, fn ($name) => !is_string($name) || !isset($this->gateways[$name]))) {
            throw new \YasserElgammal\LaraSms\Exceptions\InvalidConfigurationException('Unknown or empty gateway order');
        }
        $attempts = [];
        $started = hrtime(true);
        $context = [
            'correlation_id' => bin2hex(random_bytes(16)),
            'recipient' => strlen($message->to) > 4 ? '***' . substr($message->to, -4) : '***',
            'strategy' => $strategy->value,
        ];
        Log::info('[LaraSms] send.started', $context);

        foreach ($gatewayOrder as $gatewayName) {
            $gateway = $this->gateways[$gatewayName];
            $gatewayStarted = hrtime(true);
            $gatewayContext = $context + ['gateway' => $gatewayName, 'gateway_attempt' => count($attempts) + 1];
            if ($gateway instanceof \YasserElgammal\LaraSms\Network\AbstractHttpConnection) {
                $result = $gateway->withTelemetry($gatewayContext, fn () => $this->attemptSend($gateway, $message));
            } else {
                $result = $this->attemptSend($gateway, $message);
            }
            Log::log($result->success ? 'info' : 'warning', '[LaraSms] gateway.completed', $gatewayContext + [
                'duration_ms' => round((hrtime(true) - $gatewayStarted) / 1e6, 3),
                'success' => $result->success,
                'error_classification' => $result->success ? null : ($result->retryable === null ? 'unknown' : ($result->retryable ? 'retryable' : 'permanent')),
            ]);
            $attempts[] = [
                'gateway' => $gatewayName, 'success' => $result->success,
                'error' => $result->error, 'retryable' => $result->retryable,
                'timestamp' => now()->toDateTimeString(),
            ];
            if ($result->success) {
                Log::info('[LaraSms] send.completed', $context + [
                    'success' => true, 'gateway_attempts' => count($attempts),
                    'duration_ms' => round((hrtime(true) - $started) / 1e6, 3),
                ]);
                return new SmsResult(success: true, messageId: $result->messageId, gateway: $gatewayName, attempts: $attempts);
            }


            // Fail fast continues only for explicitly retryable failures.
            if (
                $strategy === FallbackStrategy::FAIL_FAST &&
                $result->retryable !== true
            ) {
                break;
            }
        }

        Log::warning('[LaraSms] send.completed', $context + [
            'success' => false, 'gateway_attempts' => count($attempts),
            'duration_ms' => round((hrtime(true) - $started) / 1e6, 3),
        ]);
        return new SmsResult(
            success: false,
            error: "All gateways failed",
            attempts: $attempts,
            retryable: $result->retryable
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

            return new SmsResult(
                success: false,
                gateway: $gateway->getName(),
                error: $e->getMessage(),
                retryable: $e instanceof \YasserElgammal\LaraSms\Exceptions\RetryableException ? true : ($e instanceof \YasserElgammal\LaraSms\Exceptions\NonRetryableException ? false : null)
            );
        }
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
