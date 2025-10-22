<?php

namespace YasserElgammal\LaraSms\Gateways;


use YasserElgammal\LaraSms\Contracts\SmsGateway;
use YasserElgammal\LaraSms\Data\SmsMessage;
use YasserElgammal\LaraSms\Data\SmsResult;
use YasserElgammal\LaraSms\Network\AbstractHttpConnection;
use Illuminate\Support\Facades\Log;

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
                throw new \Exception("Jawaly credentials not configured");
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

                Log::error('Jawaly SMS failed', [
                    'to' => $message->to,
                    'error' => $error,
                    'response' => $response,
                ]);

                return new SmsResult(
                    success: false,
                    gateway: 'jawaly',
                    error: $error
                );
            }

            // Success response
            $messageId = $response['messages'][0]['id'] ?? null;

            Log::info('Jawaly SMS sent successfully', [
                'to' => $message->to,
                'message_id' => $messageId,
            ]);

            return new SmsResult(
                success: true,
                messageId: $messageId,
                gateway: 'jawaly'
            );
        } catch (\Throwable $e) {
            Log::error('Jawaly SMS error', [
                'to' => $message->to,
                'error' => $e->getMessage(),
            ]);

            return new SmsResult(
                success: false,
                gateway: 'jawaly',
                error: $e->getMessage()
            );
        }
    }

    public function getName(): string
    {
        return 'jawaly';
    }
}
