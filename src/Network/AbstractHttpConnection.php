<?php

namespace YasserElgammal\LaraSms\Network;

use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use YasserElgammal\LaraSms\Contracts\HttpConnection;
use YasserElgammal\LaraSms\Exceptions\NonRetryableException;
use YasserElgammal\LaraSms\Exceptions\RetryableException;

abstract class AbstractHttpConnection implements HttpConnection
{
    protected int $timeout;
    protected int $retryAttempts;
    protected int $retryDelay;

    public function __construct(
        protected HttpClient $httpClient,
        array $config = []
    ) {
        $this->timeout = $config['timeout'] ?? 30;
        $this->retryAttempts = $config['retry_attempts'] ?? 3;
        $this->retryDelay = $config['retry_delay'] ?? 1000;
    }

    public function get(string $url, array $headers = []): array
    {
        return $this->makeRequest('GET', $url, [], $headers);
    }

    public function post(string $url, array $data = [], array $headers = []): array
    {
        return $this->makeRequest('POST', $url, $data, $headers);
    }

    public function postJson(string $url, array $data = [], array $headers = []): array
    {
        $headers = array_merge(['Content-Type' => 'application/json'], $headers);
        return $this->makeRequest('POST_JSON', $url, $data, $headers);
    }

    public function postForm(string $url, array $data = [], array $headers = []): array
    {
        return $this->makeRequest('POST_FORM', $url, $data, $headers);
    }

    protected function makeRequest(string $method, string $url, array $data = [], array $headers = []): array
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->retryAttempts) {
            try {
                Log::debug("SMS HTTP Request", [
                    'method' => $method,
                    'url' => $url,
                    'attempt' => $attempt + 1,
                    'max_attempts' => $this->retryAttempts,
                ]);

                $response = $this->httpClient
                    ->timeout($this->timeout)
                    ->withHeaders($headers);

                if ($method === 'POST_FORM') {
                    $response = $response->asForm()->post($url, $data);
                } elseif ($method === 'POST') {
                    $response = $response->post($url, $data); // دي بتبعت JSON افتراضيًا
                } elseif ($method === 'POST_JSON') {
                    $response = $response->asJson()->post($url, $data);
                } else {
                    $response = $response->get($url, $data);
                }

                if ($response->successful()) {
                    Log::debug("SMS HTTP Response successful", [
                        'status' => $response->status(),
                    ]);
                    return $response->json() ?? [];
                }

                // Handle HTTP errors
                if ($response->status() >= 500) {
                    throw new RetryableException("Server error: {$response->status()}");
                }

                throw new NonRetryableException("Client error: {$response->status()} - {$response->body()}");
            } catch (RequestException $e) {
                $lastException = $e;

                if ($this->isRetryable($e)) {
                    $attempt++;
                    if ($attempt < $this->retryAttempts) {
                        usleep($this->retryDelay * 1000);
                        continue;
                    }
                }

                throw $lastException;
            } catch (RetryableException $e) {
                $lastException = $e;
                $attempt++;

                if ($attempt < $this->retryAttempts) {
                    usleep($this->retryDelay * 1000);
                    continue;
                }

                throw $e;
            }
        }

        throw $lastException ?? new RetryableException("Max retry attempts reached");
    }

    protected function isRetryable(\Throwable $e): bool
    {
        return $e instanceof RequestException &&
            ($e->getCode() >= 500 || $e->getCode() === 429);
    }
}
