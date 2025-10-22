<?php

namespace YasserElgammal\LaraSms\Gateways;

use Throwable;
use Illuminate\Support\Facades\Log;
use YasserElgammal\LaraSms\Contracts\SmsGateway;
use YasserElgammal\LaraSms\Data\SmsMessage;
use YasserElgammal\LaraSms\Data\SmsResult;
use YasserElgammal\LaraSms\Network\AbstractHttpConnection;

class DreamsGateway extends AbstractHttpConnection implements SmsGateway
{
    protected string $username;
    protected string $secretKey;
    protected ?string $defaultSender = null;

    protected string $endpoint = 'https://www.dreams.sa/index.php/api/sendsms/';

    public function __construct($httpClient, array $config)
    {
        parent::__construct($httpClient, $config);

        $this->username      = $config['username']    ?? '';
        $this->secretKey     = $config['secret_key']  ?? '';
        $this->defaultSender = $config['sender']      ?? null;
    }

    public function getName(): string
    {
        return 'dreams';
    }

    /**
     * Send a single SMS message via Dreams SMS Gateway.
     */
    public function send(SmsMessage $message): SmsResult
    {
        try {
            if (!$this->username || !$this->secretKey) {
                throw new \Exception('Dreams credentials not configured (username/secret_key)');
            }

            $sender = $message->from ?? $this->defaultSender;
            if (!$sender) {
                throw new \Exception('Dreams sender is required (configure default sender or pass $message->from)');
            }

            // Required fields
            $payload = [
                'user'        => $this->username,
                'secret_key'  => $this->secretKey,
                'to'          => is_array($message->to) ? implode(',', $message->to) : (string)$message->to,
                'message'     => (string)$message->text,
                'sender'      => $sender,
            ];

            $headers = [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept'       => 'text/plain, application/json',
            ];

            $response = $this->post($this->endpoint, $payload, $headers);

            // Normalize response (plain text or array)
            $raw = is_array($response)
                ? ($response['Result'] ?? $response['result'] ?? reset($response))
                : (string)$response;

            $normalized = trim((string)$raw);

            $errorMap = [
                '-100' => 'Missing parameters',
                '-110' => 'Wrong username or secret_key',
                '-111' => 'Account not activated',
                '-112' => 'Blocked account',
                '-113' => 'Not enough balance',
                '-114' => 'Service not available',
                '-115' => 'Sender not available',
                '-116' => 'Invalid sender name',
                '-117' => 'Check your number. There is a problem',
                '-118' => 'Unwanted error',
                '-119' => 'Later date time is not correct',
                '-122' => 'Number not allowed',
                '-123' => "Sender's name exceeds daily sending limit",
                '-124' => 'IP not allowed',
            ];

            $isSuccess = strcasecmp($normalized, 'Result') === 0 || strcasecmp($normalized, 'Success') === 0;

            if ($isSuccess) {
                Log::info('Dreams SMS sent successfully', [
                    'to'     => $payload['to'],
                    'sender' => $sender,
                    'response' => $normalized,
                ]);

                return new SmsResult(
                    success: true,
                    messageId: null,
                    gateway: 'dreams'
                );
            }

            if (isset($errorMap[$normalized])) {
                $err = $errorMap[$normalized];
                Log::error('Dreams SMS failed', [
                    'to'    => $payload['to'],
                    'code'  => $normalized,
                    'error' => $err,
                ]);

                return new SmsResult(
                    success: false,
                    gateway: 'dreams',
                    error: "{$normalized}: {$err}"
                );
            }

            // Unknown / unexpected response
            Log::error('Dreams SMS unknown response', [
                'to'       => $payload['to'],
                'response' => $normalized,
            ]);

            return new SmsResult(
                success: false,
                gateway: 'dreams',
                error: 'Unknown response from Dreams API'
            );
        } catch (Throwable $e) {
            Log::error('Dreams SMS error', [
                'to'    => is_array($message->to) ? implode(',', $message->to) : (string)$message->to,
                'error' => $e->getMessage(),
            ]);

            return new SmsResult(
                success: false,
                gateway: 'dreams',
                error: $e->getMessage()
            );
        }
    }
}
