<?php

namespace YasserElgammal\LaraSms\Gateways;

use YasserElgammal\LaraSms\Contracts\SmsGateway;
use YasserElgammal\LaraSms\Data\SmsMessage;
use YasserElgammal\LaraSms\Data\SmsResult;
use YasserElgammal\LaraSms\Network\AbstractHttpConnection;

class TwilioGateway extends AbstractHttpConnection implements SmsGateway
{
    protected string $sid;
    protected string $token;
    protected string $from;

    public function __construct($httpClient, array $config)
    {
        parent::__construct($httpClient, $config);
        $this->sid = $config['sid'] ?? '';
        $this->token = $config['token'] ?? '';
        $this->from = $config['from'] ?? '';
    }

    public function send(SmsMessage $message): SmsResult
    {
        try {
            if (!$this->sid || !$this->token) {
                throw new \YasserElgammal\LaraSms\Exceptions\InvalidConfigurationException("Twilio credentials not configured");
            }

            $response = $this->post(
                "https://api.twilio.com/2010-04-01/Accounts/{$this->sid}/Messages.json",
                [
                    'From' => $message->from ?? $this->from,
                    'To' => $message->to,
                    'Body' => $message->text,
                ],
                [
                    'Authorization' => 'Basic ' . base64_encode("{$this->sid}:{$this->token}"),
                ]
            );

            return new SmsResult(
                success: true,
                messageId: $response['sid'] ?? null,
                gateway: 'twilio'
            );
        } catch (\Throwable $e) {

            return new SmsResult(
                success: false,
                gateway: 'twilio',
                error: $e->getMessage(),
                retryable: $e instanceof \YasserElgammal\LaraSms\Exceptions\RetryableException ? true : ($e instanceof \YasserElgammal\LaraSms\Exceptions\NonRetryableException ? false : null)
            );
        }
    }

    public function getName(): string
    {
        return 'twilio';
    }
}
