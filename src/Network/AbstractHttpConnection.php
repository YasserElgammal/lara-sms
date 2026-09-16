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
    private array $telemetry = [];

    public function withTelemetry(array $context, callable $callback): mixed
    {
        $previous = $this->telemetry;
        $this->telemetry = $context;
        try {
            return $callback();
        } finally {
            $this->telemetry = $previous;
        }
    }

    protected int $timeout;
    protected int $retryAttempts;
    protected int $retryDelay;

    public function __construct(
        protected HttpClient $httpClient,
        array $config = []
    ) {
        foreach (['timeout' => 1, 'retry_attempts' => 1, 'retry_delay' => 0] as $key => $minimum) {
            if (isset($config[$key]) && (filter_var($config[$key], FILTER_VALIDATE_INT) === false || $config[$key] < $minimum)) {
                throw new \YasserElgammal\LaraSms\Exceptions\InvalidConfigurationException("Invalid HTTP option: {$key}");
            }
        }
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

        $context = $this->telemetry ?: ['correlation_id' => bin2hex(random_bytes(16)), 'gateway' => static::class];
        while ($attempt < $this->retryAttempts) {
            $httpStarted = hrtime(true);
            $httpAttempt = $attempt + 1;
            $status = null;
            $httpFinished = null;
            try {

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

                $httpFinished = hrtime(true);
                $status = $response->status();
                if ($response->successful()) {
                    return $response->json() ?? [];
                }

                // Handle HTTP errors
                if ($response->status() === 429 || $response->status() >= 500) {
                    throw new RetryableException("Retryable HTTP error: {$response->status()}");
                }

                throw new NonRetryableException("Client error: {$response->status()} - {$response->body()}");
            } catch (RequestException $e) {
                $httpFinished ??= hrtime(true);
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
                $httpFinished ??= hrtime(true);
                $lastException = $e;
                $attempt++;

                if ($attempt < $this->retryAttempts) {
                    usleep($this->retryDelay * 1000);
                    continue;
                }

                throw $e;
            } finally {
                Log::debug('[LaraSms] http.completed', $context + [
                    'http_attempt' => $httpAttempt,
                    'max_attempts' => $this->retryAttempts,
                    'status' => $status,
                    'duration_ms' => round((($httpFinished ?? hrtime(true)) - $httpStarted) / 1e6, 3),
                    'error_classification' => $status === null ? 'unknown' : ($status >= 200 && $status < 300 ? null : ($status === 429 || $status >= 500 ? 'retryable' : 'permanent')),
                ]);
            }
        }

        throw $lastException ?? new RetryableException("Max retry attempts reached");
    }

    protected function isRetryable(\Throwable $e): bool
    {
        return $e instanceof RequestException &&
            ($e->response->status() >= 500 || $e->response->status() === 429);
    }
}
