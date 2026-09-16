<?php

namespace YasserElgammal\LaraSms\Gateways;

use YasserElgammal\LaraSms\Contracts\SmsGateway;
use YasserElgammal\LaraSms\Data\SmsMessage;
use YasserElgammal\LaraSms\Data\SmsResult;
use YasserElgammal\LaraSms\Network\AbstractHttpConnection;

class VonageGateway extends AbstractHttpConnection implements SmsGateway
{
    protected string $apiKey;
    protected string $apiSecret;
    protected string $sender;

    public function __construct($httpClient, array $config)
    {
        parent::__construct($httpClient, $config);

        $this->apiKey    = $config['api_key']    ?? '';
        $this->apiSecret = $config['api_secret'] ?? '';
        $this->sender    = $config['sender']     ?? 'VonageAPIs';
    }

    public function send(SmsMessage $message): SmsResult
    {
        try {
            if (!$this->apiKey || !$this->apiSecret) {
                throw new \YasserElgammal\LaraSms\Exceptions\InvalidConfigurationException("Vonage credentials not configured");
            }

            $url = 'https://rest.nexmo.com/sms/json';

            $payload = [
                'api_key'    => $this->apiKey,
                'api_secret' => $this->apiSecret,
                'from'       => $this->sender,
                'to'         => $message->to,
                'text'       => $message->text,
                'type'    => 'unicode',
            ];

            $headers = [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ];

            $response = $this->postForm($url, $payload, $headers);
            $msg = $response['messages'][0] ?? null;

            if (!$msg) {
                throw new \Exception('Unexpected Vonage response shape');
            }

            $status = $msg['status'] ?? null;

            if ((string)$status === '0') {
                $messageId = $msg['message-id'] ?? null;

                return new SmsResult(
                    success: true,
                    messageId: $messageId,
                    gateway: 'vonage'
                );
            }

            // فشل
            $errorText = $msg['error-text'] ?? 'Unknown error';

            return new SmsResult(
                success: false,
                gateway: 'vonage',
                error: $errorText
            );
        } catch (\Throwable $e) {

            return new SmsResult(
                success: false,
                gateway: 'vonage',
                error: $e->getMessage(),
                retryable: $e instanceof \YasserElgammal\LaraSms\Exceptions\RetryableException ? true : ($e instanceof \YasserElgammal\LaraSms\Exceptions\NonRetryableException ? false : null)
            );
        }
    }

    public function getName(): string
    {
        return 'vonage';
    }
}
