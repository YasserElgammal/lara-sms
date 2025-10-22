<?php

namespace YasserElgammal\LaraSms\Gateways;

use Throwable;
use Illuminate\Support\Facades\Log;
use YasserElgammal\LaraSms\Contracts\SmsGateway;
use YasserElgammal\LaraSms\Data\SmsMessage;
use YasserElgammal\LaraSms\Data\SmsResult;
use YasserElgammal\LaraSms\Network\AbstractHttpConnection;

class MsegatGateway extends AbstractHttpConnection implements SmsGateway
{
    protected string $userName;
    protected string $apiKey;
    protected string $userSender;

    public function __construct($httpClient, array $config)
    {
        parent::__construct($httpClient, $config);

        $this->userName   = $config['username']    ?? '';
        $this->apiKey     = $config['api_key']     ?? '';
        $this->userSender = $config['user_sender'] ?? 'auth-mseg'; // demo sender
    }

    public function send(SmsMessage $message): SmsResult
    {
        try {
            if (!$this->userName || !$this->apiKey) {
                throw new \Exception('Msegat credentials are missing.');
            }

            // ملاحظة: numbers ممكن تقبل قائمة بأرقام مفصولة بفواصل, هنا بنفترض رقماً واحداً
            $payload = [
                'userName'   => $this->userName,
                'numbers'    => $message->to,
                'userSender' => $this->userSender,
                'apiKey'     => $this->apiKey,
                'msg'        => $message->text,
            ];

            $url = 'https://www.msegat.com/gw/sendsms.php';

            Log::debug('[Msegat] Request payload', [
                'url'     => $url,
                'payload' => array_merge($payload, ['apiKey' => '***']), // اخفاء المفتاح
            ]);

            $response = $this->postForm($url, $payload, [
                'Accept' => 'application/json',
            ]);

            return $this->handleResponse($response, $message);
        } catch (Throwable $e) {
            Log::error('[Msegat] Exception', [
                'to'    => $message->to,
                'error' => $e->getMessage(),
            ]);

            return new SmsResult(
                success: false,
                gateway: 'msegat',
                error: $e->getMessage(),
            );
        }
    }

    protected function handleResponse(array|string|null $response, SmsMessage $message): SmsResult
    {
        $parsed = $this->tryParseResponse($response);

        if (is_array($parsed) && isset($parsed['code'])) {
            if ((int)$parsed['code'] === 1) {
                Log::info('[Msegat] SMS sent successfully', [
                    'to'       => $message->to,
                    'response' => $parsed,
                ]);

                return new SmsResult(
                    success: true,
                    messageId: $parsed['messageId'] ?? null,
                    gateway: 'msegat'
                );
            }
            $err = $parsed['message'] ?? $parsed['error'] ?? 'Unknown error';
            Log::warning('[Msegat] SMS failed', [
                'to'       => $message->to,
                'response' => $parsed,
            ]);

            return new SmsResult(
                success: false,
                gateway: 'msegat',
                error: $err
            );
        }

        // لو مش قادرين نفسّرها، رجّع النص كما هو
        Log::warning('[Msegat] Unrecognized response', [
            'to'       => $message->to,
            'response' => $response,
        ]);

        return new SmsResult(
            success: false,
            gateway: 'msegat',
            error: $this->parseError($response)
        );
    }

    protected function tryParseResponse(array|string|null $response): array|string|null
    {
        if (is_array($response)) {
            return $response;
        }

        if (is_string($response)) {
            // حاول JSON
            $json = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                return $json;
            }

            parse_str($response, $arr);
            if (!empty($arr)) {
                return $arr;
            }

            return $response;
        }

        return $response;
    }

    protected function parseError(array|string|null $response): string
    {
        if (is_array($response)) {
            return $response['message'] ?? $response['error'] ?? 'Unknown error occurred.';
        }

        if (is_string($response)) {
            return trim($response) === '' ? 'Unknown error occurred.' : $response;
        }

        return 'Unknown error occurred.';
    }

    public function getName(): string
    {
        return 'msegat';
    }
}
