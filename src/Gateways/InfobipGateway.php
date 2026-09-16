<?php

namespace YasserElgammal\LaraSms\Gateways;

use YasserElgammal\LaraSms\Contracts\SmsGateway;
use YasserElgammal\LaraSms\Data\SmsMessage;
use YasserElgammal\LaraSms\Data\SmsResult;
use YasserElgammal\LaraSms\Network\AbstractHttpConnection;

class InfobipGateway extends AbstractHttpConnection implements SmsGateway
{
    protected string $apiKey;
    protected string $sender;
    protected string $baseUrl;

    /**
     * @param mixed $httpClient
     * @param array $config
     */
    public function __construct($httpClient, array $config)
    {
        parent::__construct($httpClient, $config);

        $this->apiKey  = $config['api_key']  ?? '';
        $this->sender  = $config['sender']   ?? 'InfoSMS';
        // e.g. https://3828nj.api.infobip.com  (no trailing slash)
        $this->baseUrl = rtrim($config['base_url'] ?? 'https://api.infobip.com', '/');
    }

    public function send(SmsMessage $message): SmsResult
    {
        try {
            if (!$this->apiKey) {
                throw new \YasserElgammal\LaraSms\Exceptions\InvalidConfigurationException('Infobip API key not configured');
            }

            $url = $this->baseUrl . '/sms/3/messages';

            $payload = [
                'messages' => [
                    [
                        'from'         => $this->sender,
                        'destinations' => [
                            ['to' => $message->to],
                        ],
                        'content'      => [
                            'text' => $message->text,
                        ],
                    ],
                ],
            ];

            $headers = [
                'Authorization' => 'App ' . $this->apiKey,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ];

            $response = $this->postJson($url, $payload, $headers);

            $msg = $response['messages'][0] ?? null;

            if (!$msg) {
                $err   = $response['requestError']['serviceException']['text'] ?? 'Unexpected Infobip response shape';
                throw new \Exception($err);
            }

            $status      = $msg['status'] ?? [];
            $groupId     = (int)($status['groupId'] ?? -1);
            $statusName  = $status['name'] ?? null;          // e.g. PENDING_ACCEPTED
            $description = $status['description'] ?? null;   // e.g. Message accepted
            $messageId   = $msg['messageId'] ?? null;

            // Infobip marks accepted/queued sends as PENDING (groupId = 1).
            // Treat groupId=1 as success at send-time.
            if ($groupId === 1) {

                return new SmsResult(
                    success: true,
                    messageId: $messageId,
                    gateway: 'infobip'
                );
            }

            // Anything else => fail now with the provided description/name.
            $errorText = $description ?: $statusName ?: 'Unknown Infobip error';

            return new SmsResult(
                success: false,
                gateway: 'infobip',
                error: $errorText
            );
        } catch (\Throwable $e) {

            return new SmsResult(
                success: false,
                gateway: 'infobip',
                error: $e->getMessage(),
                retryable: $e instanceof \YasserElgammal\LaraSms\Exceptions\RetryableException ? true : ($e instanceof \YasserElgammal\LaraSms\Exceptions\NonRetryableException ? false : null)
            );
        }
    }

    public function getName(): string
    {
        return 'infobip';
    }
}
