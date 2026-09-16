<?php

namespace YasserElgammal\LaraSms\Gateways;

use YasserElgammal\LaraSms\Contracts\SmsGateway;
use YasserElgammal\LaraSms\Data\SmsMessage;
use YasserElgammal\LaraSms\Data\SmsResult;
use YasserElgammal\LaraSms\Network\AbstractHttpConnection;

class UnifonicGateway extends AbstractHttpConnection implements SmsGateway
{
    protected string $appSid;
    protected ?string $senderId = null;

    public function __construct($httpClient, array $config)
    {
        parent::__construct($httpClient, $config);

        $this->appSid   = $config['app_sid'] ?? '';
        $this->senderId = $config['sender_id'] ?? null;
    }

    /**
     * Send SMS via Unifonic API
     *
     * @param  SmsMessage  $message
     * @return SmsResult
     */
    public function send(SmsMessage $message): SmsResult
    {
        try {
            if (!$this->appSid) {
                throw new \YasserElgammal\LaraSms\Exceptions\InvalidConfigurationException('Unifonic AppSid not configured');
            }

            $payload = [
                'AppSid'    => $this->appSid,
                'Body'      => $message->text,
                'Recipient' => $message->to,
            ];

            if ($this->senderId || $message->from) {
                $payload['SenderID'] = $message->from ?? $this->senderId;
            }

            $response = $this->post('https://api.unifonic.com/rest/Messages/Send', $payload);

            $status = strtolower($response['status'] ?? '');
            $messageId = $response['messageID'] ?? null;

            if (in_array($status, ['queued', 'sent'], true)) {

                return new SmsResult(
                    success: true,
                    messageId: $messageId,
                    gateway: 'unifonic'
                );
            }

            return new SmsResult(
                success: false,
                gateway: 'unifonic',
                error: $response['errorMessage'] ?? 'Unknown error'
            );
        } catch (\Throwable $e) {

            return new SmsResult(
                success: false,
                gateway: 'unifonic',
                error: $e->getMessage(),
                retryable: $e instanceof \YasserElgammal\LaraSms\Exceptions\RetryableException ? true : ($e instanceof \YasserElgammal\LaraSms\Exceptions\NonRetryableException ? false : null)
            );
        }
    }

    public function getName(): string
    {
        return 'unifonic';
    }
}
