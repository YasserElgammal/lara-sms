<?php

namespace YasserElgammal\LaraSms\Gateways;


use YasserElgammal\LaraSms\Contracts\SmsGateway;
use YasserElgammal\LaraSms\Data\SmsMessage;
use YasserElgammal\LaraSms\Data\SmsResult;
use YasserElgammal\LaraSms\Network\AbstractHttpConnection;

class JawalyGateway extends AbstractHttpConnection implements SmsGateway
{
    protected string $apiKey;
    protected string $apiSecert;
    protected string $sender;

    public function __construct($httpClient, array $config)
    {
        parent::__construct($httpClient, $config);
        $this->apiKey = $config['api_key'] ?? '';
        $this->apiSecert = $config['api_secret'] ?? '';
        $this->sender = $config['sender'] ?? '';
    }

    public function send(SmsMessage $message): SmsResult
    {
        try {
            if (!$this->apiKey || !$this->apiSecert) {
                throw new \YasserElgammal\LaraSms\Exceptions\InvalidConfigurationException("Jawaly credentials not configured");
            }

            $appHash = base64_encode("{$this->apiKey}:{$this->apiSecert}");

            $payload = [
                "messages" => [
                    [
                        "text" => $message->text,
                        "numbers" => [$message->to],
                        "sender" => $message->from ?? $this->sender
                    ]
                ]
            ];

            $response = $this->post(
                'https://api-sms.4jawaly.com/api/v1/account/area/sms/send',
                $payload,
                [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => "Basic {$appHash}",
                ]
            );

            // Check if message has error
            if (isset($response['messages'][0]['err_text'])) {
                $error = $response['messages'][0]['err_text'];

                return new SmsResult(
                    success: false,
                    gateway: 'jawaly',
                    error: $error
                );
            }

            // Success response
            $messageId = $response['messages'][0]['id'] ?? null;

            return new SmsResult(
                success: true,
                messageId: $messageId,
                gateway: 'jawaly'
            );
        } catch (\Throwable $e) {

            return new SmsResult(
                success: false,
                gateway: 'jawaly',
                error: $e->getMessage(),
                retryable: $e instanceof \YasserElgammal\LaraSms\Exceptions\RetryableException ? true : ($e instanceof \YasserElgammal\LaraSms\Exceptions\NonRetryableException ? false : null)
            );
        }
    }

    public function getName(): string
    {
        return 'jawaly';
    }
}
