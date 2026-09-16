<?php

namespace YasserElgammal\LaraSms\Gateways;

use YasserElgammal\LaraSms\Contracts\SmsGateway;
use YasserElgammal\LaraSms\Data\SmsMessage;
use YasserElgammal\LaraSms\Data\SmsResult;
use YasserElgammal\LaraSms\Network\AbstractHttpConnection;

class MobilySmsGateway extends AbstractHttpConnection implements SmsGateway
{
    protected string $username;
    protected string $password;
    protected string $sender;
    protected string $baseUrl;

    /** auto|u|e — auto picks u for Arabic text, e otherwise */
    protected string $unicodeMode = 'auto';

    /** full|simple — API supports return=full for verbose messages */
    protected string $returnMode = 'full';

    public function __construct($httpClient, array $config)
    {
        parent::__construct($httpClient, $config);

        $this->username    = $config['username']   ?? '';
        $this->password    = $config['password']   ?? '';
        $this->sender      = $config['sender']     ?? 'MobilySMS';
        $this->baseUrl     = rtrim($config['base_url'] ?? 'https://www.mobilysms.net', '/');
        $this->unicodeMode = $config['unicode_mode'] ?? 'auto'; // auto|u|e
        $this->returnMode  = $config['return_mode']  ?? 'full'; // full|simple
    }

    public function send(SmsMessage $message): SmsResult
    {
        try {
            if (!$this->username || !$this->password) {
                throw new \YasserElgammal\LaraSms\Exceptions\InvalidConfigurationException('Mobily credentials not configured');
            }

            $unicode = $this->resolveUnicode($message->text); // 'u' or 'e'

            $endpoint = $this->baseUrl . '/api/sendsms.php';
            $query = [
                'username' => $this->username,
                'password' => $this->password,
                'message'  => $message->text,
                'numbers'  => $message->to,
                'sender'   => $this->sender,
                'unicode'  => $unicode,
                'return'   => $this->returnMode, // 'full' is recommended
            ];

            // Expect AbstractHttpConnection to provide GET with query
            // If your base class uses a different signature, adapt accordingly.
            $headers = ['Accept' => 'text/plain'];
            $response = $this->get($endpoint, $query, $headers);

            // Response could be just a code "100" or verbose text.
            $raw     = is_string($response) ? $response : (string)json_encode($response);
            $code    = $this->extractCode($raw);
            $success = ($code === 100);

            if ($success) {

                // API doesn’t return a canonical message id in this endpoint;
                // use null or synthesize one if you need it.
                return new SmsResult(
                    success: true,
                    messageId: null,
                    gateway: 'mobilysms'
                );
            }

            $error = $this->humanReadableError($code) ?? 'Unknown error';

            return new SmsResult(
                success: false,
                gateway: 'mobilysms',
                error: $error
            );
        } catch (\Throwable $e) {

            return new SmsResult(
                success: false,
                gateway: 'mobilysms',
                error: $e->getMessage(),
                retryable: $e instanceof \YasserElgammal\LaraSms\Exceptions\RetryableException ? true : ($e instanceof \YasserElgammal\LaraSms\Exceptions\NonRetryableException ? false : null)
            );
        }
    }

    public function getName(): string
    {
        return 'mobilysms';
    }

    /**
     * Decide unicode flag based on config and content.
     * 'u' = Unicode (Arabic, etc.), 'e' = English/GSM.
     */
    protected function resolveUnicode(string $text): string
    {
        if ($this->unicodeMode === 'u' || $this->unicodeMode === 'e') {
            return $this->unicodeMode;
        }

        // auto: detect Arabic characters
        return preg_match('/\p{Arabic}/u', $text) ? 'u' : 'e';
    }

    /**
     * Extracts the first 3-digit code found in the raw response.
     * Example: "100", "Code: 100 - Submitted successfully", etc.
     */
    protected function extractCode(string $raw): ?int
    {
        if (preg_match('/\b(\d{3})\b/', $raw, $m)) {
            return (int)$m[1];
        }
        return null;
    }

    /**
     * Map common Mobily codes to human-friendly messages.
     */
    protected function humanReadableError(?int $code): ?string
    {
        return match ($code) {
            100 => 'Submitted successfully',
            102 => 'User name is incorrect',
            103 => 'Password is incorrect',
            106 => 'The sender name is not available',
            default => $code ? "Error code {$code}" : null,
        };
    }
}
