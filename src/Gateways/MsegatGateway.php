<?php

namespace YasserElgammal\LaraSms\Gateways;

use Throwable;
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
                throw new \YasserElgammal\LaraSms\Exceptions\InvalidConfigurationException('Msegat credentials are missing.');
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

            $response = $this->postForm($url, $payload, [
                'Accept' => 'application/json',
            ]);

            return $this->handleResponse($response, $message);
        } catch (Throwable $e) {

            return new SmsResult(
                success: false,
                gateway: 'msegat',
                error: $e->getMessage(),
                retryable: $e instanceof \YasserElgammal\LaraSms\Exceptions\RetryableException ? true : ($e instanceof \YasserElgammal\LaraSms\Exceptions\NonRetryableException ? false : null),
            );
        }
    }

    protected function handleResponse(array|string|null $response, SmsMessage $message): SmsResult
    {
        $parsed = $this->tryParseResponse($response);

        if (is_array($parsed) && isset($parsed['code'])) {
            if ((int)$parsed['code'] === 1) {

                return new SmsResult(
                    success: true,
                    messageId: $parsed['messageId'] ?? null,
                    gateway: 'msegat'
                );
            }
            $err = $parsed['message'] ?? $parsed['error'] ?? 'Unknown error';

            return new SmsResult(
                success: false,
                gateway: 'msegat',
                error: $err
            );
        }

        // لو مش قادرين نفسّرها، رجّع النص كما هو

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
