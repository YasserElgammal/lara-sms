<?php

namespace YasserElgammal\LaraSms\Gateways;

use Throwable;
use Illuminate\Support\Facades\Log;
use YasserElgammal\LaraSms\Contracts\SmsGateway;
use YasserElgammal\LaraSms\Data\SmsMessage;
use YasserElgammal\LaraSms\Data\SmsResult;
use YasserElgammal\LaraSms\Network\AbstractHttpConnection;

class TaqnyatGateway extends AbstractHttpConnection implements SmsGateway
{
    protected string $token;
    protected ?string $senderId = null;
    protected string $base = 'https://api.taqnyat.sa';

    public function __construct($httpClient, array $config)
    {
        parent::__construct($httpClient, $config);

        $this->token    = $config['token'] ?? '';
        $this->senderId = $config['sender_id'] ?? null;
    }

    /**
     * Send SMS via Taqnyat
     *
     * Required by Taqnyat:
     *  - recipients (array of E.164 numbers or local as per your account)
     *  - sender
     *  - body
     */
    public function send(SmsMessage $message): SmsResult
    {
        try {
            if (!$this->token) {
                throw new \Exception('Taqnyat token not configured');
            }

            $sender = $message->from ?? $this->senderId;
            if (!$sender) {
                throw new \Exception('Taqnyat sender_id not configured or provided in message');
            }

            // Taqnyat expects an array of recipients
            // We'll support single or comma-separated string transparently.
            $recipients = is_array($message->to)
                ? $message->to
                : (strpos((string)$message->to, ',') !== false
                    ? array_map('trim', explode(',', (string)$message->to))
                    : [(string)$message->to]
                );

            $payload = [
                'recipients'         => $recipients,
                'sender'             => $sender,
                'body'               => (string) $message->text,
            ];

            $headers = [
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ];

            // AbstractHttpConnection::post(url, array $data, array $headers = [])
            $response = $this->post($this->base . '/v1/messages', $payload, $headers);

            /**
             * Taqnyat responses vary by plan/endpoints; typical fields:
             * - messageId, deleteKey, status, message, invalid, etc.
             * We'll consider it success if we get a messageId or a success/queued status.
             */
            $messageId = $response['messageId'] ?? $response['messageID'] ?? $response['id'] ?? null;
            $status    = strtolower($response['status'] ?? '');

            $isOk = $messageId || in_array($status, ['success', 'queued', 'sent', 'accepted'], true);

            if ($isOk) {
                Log::info('Taqnyat SMS sent successfully', [
                    'to'         => $recipients,
                    'message_id' => $messageId,
                    'status'     => $status ?: 'success',
                ]);

                return new SmsResult(
                    success: true,
                    messageId: $messageId,
                    gateway: 'taqnyat'
                );
            }

            $errorText = $response['message'] ?? $response['error'] ?? 'Unknown error';

            Log::error('Taqnyat SMS failed', [
                'to'     => $recipients,
                'status' => $status ?: 'unknown',
                'error'  => $errorText,
            ]);

            return new SmsResult(
                success: false,
                gateway: 'taqnyat',
                error: $errorText
            );
        } catch (Throwable $e) {
            Log::error('Taqnyat SMS error', [
                'to'    => $message->to,
                'error' => $e->getMessage(),
            ]);

            return new SmsResult(
                success: false,
                gateway: 'taqnyat',
                error: $e->getMessage()
            );
        }
    }

    /* ===================== Optional Convenience APIs ===================== */

    /**
     * Get account balance
     * GET /account/balance
     */
    public function balance(): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept'        => 'application/json',
        ];

        return $this->get($this->base . '/account/balance', [], $headers);
    }

    public function getName(): string
    {
        return 'taqnyat';
    }
}
