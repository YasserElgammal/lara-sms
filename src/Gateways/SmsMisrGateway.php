<?php

namespace YasserElgammal\LaraSms\Gateways;

use YasserElgammal\LaraSms\Contracts\SmsGateway;
use YasserElgammal\LaraSms\Data\SmsMessage;
use YasserElgammal\LaraSms\Data\SmsResult;
use YasserElgammal\LaraSms\Network\AbstractHttpConnection;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SmsMisrGateway extends AbstractHttpConnection implements SmsGateway
{
    protected string $username;
    protected string $password;
    protected string $sender;

    public function __construct($httpClient, array $config)
    {
        parent::__construct($httpClient, $config);
        $this->username = $config['username'] ?? '';
        $this->password = $config['password'] ?? '';
        $this->sender = $config['sender'] ?? '';
    }

    public function send(SmsMessage $message): SmsResult
    {
        try {
            if (!$this->username || !$this->password) {
                throw new \Exception("SMS Misr credentials not configured");
            }

            $payload = [
                'username' => $this->username,
                'password' => $this->password,
                'sender' => $message->from ?? $this->sender,
                'mobile' => $message->to,
                'message' => $message->text,
                'language' => $message->metadata['language'] ?? '2',
                'environment' => $message->metadata['environment'] ?? '1'
            ];

            if (isset($message->metadata['delay_until'])) {
                $payload['DelayUntil'] = $this->formatDelayUntil($message->metadata['delay_until']);
            }

            $response = $this->post('https://smsmisr.com/api/SMS/', $payload);

            if (isset($response['code']) && $response['code'] === '4901') {
                Log::info('SMS Misr sent successfully', [
                    'to' => $message->to,
                    'smsid' => $response['smsid'] ?? null,
                ]);

                return new SmsResult(
                    success: true,
                    messageId: $response['smsid'] ?? null,
                    gateway: 'smsmisr'
                );
            }

            Log::error('SMS Misr failed', [
                'to' => $message->to,
                'error' => $response['message'] ?? 'Unknown error',
                'code' => $response['code'] ?? null,
            ]);

            return new SmsResult(
                success: false,
                gateway: 'smsmisr',
                error: $response['message'] ?? 'Unknown error'
            );
        } catch (\Throwable $e) {
            Log::error('SMS Misr error', [
                'to' => $message->to,
                'error' => $e->getMessage(),
            ]);

            return new SmsResult(
                success: false,
                gateway: 'smsmisr',
                error: $e->getMessage()
            );
        }
    }

    public function getName(): string
    {
        return 'smsmisr';
    }

    protected function formatDelayUntil($delayUntil)
    {
        // If already in correct format (12 digits)
        if (is_string($delayUntil) && preg_match('/^\d{12}$/', $delayUntil)) {
            return $delayUntil;
        }

        // Parse to Carbon
        if ($delayUntil instanceof Carbon) {
            $carbon = $delayUntil;
        } elseif (is_string($delayUntil)) {
            try {
                $carbon = Carbon::parse($delayUntil);
            } catch (\Exception $e) {
                Log::warning('[Msegat] Invalid delay_until format', [
                    'value' => $delayUntil,
                    'error' => $e->getMessage(),
                ]);
                throw new \Exception("Invalid delay_until format: {$delayUntil}");
            }
        } else {
            throw new \Exception("Invalid delay_until type");
        }
    }
}
