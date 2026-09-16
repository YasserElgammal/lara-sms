<?php
namespace YasserElgammal\LaraSms\Tests;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Bus;
use Orchestra\Testbench\TestCase;
use YasserElgammal\LaraSms\Data\SmsMessage;
use YasserElgammal\LaraSms\Enums\FallbackStrategy;
use YasserElgammal\LaraSms\Exceptions\InvalidConfigurationException;
use YasserElgammal\LaraSms\Exceptions\SmsException;
use YasserElgammal\LaraSms\Gateways\TwilioGateway;
use YasserElgammal\LaraSms\Jobs\SendSms;
use YasserElgammal\LaraSms\Services\SmsManager;

class ReliabilityTest extends TestCase
{
    private function manager(array|callable $responses, array $http = [], array $credentials = ['sid' => 'test', 'token' => 'test']): array
    {
        $client = new Factory;
        $sequence = $client->sequence();
        foreach (is_array($responses) ? $responses : [] as $status) {
            $sequence->push(['sid' => 'message-1'], $status);
        }
        $client->fake(is_callable($responses) ? $responses : ['*' => $sequence]);
        $client->preventStrayRequests();
        $this->app->instance(Factory::class, $client);
        $gateway = ['class' => TwilioGateway::class, 'config' => $credentials];
        return [new SmsManager([
            'default_fallback_strategy' => 'fail_fast',
            'gateways' => ['first' => $gateway, 'second' => $gateway],
            'http' => $http + ['retry_attempts' => 2, 'retry_delay' => 0],
        ]), $client];
    }

    public function test_success_stops_fallback(): void
    {
        [$manager, $client] = $this->manager([200]);
        $result = $manager->send(new SmsMessage('123', 'hello'));
        self::assertTrue($result->success);
        self::assertSame('first', $result->gateway);
        $client->assertSentCount(1);
    }

    public function test_rate_limit_and_server_errors_retry(): void
    {
        foreach ([429, 500, 503] as $status) {
            [$manager, $client] = $this->manager([$status, 200]);
            self::assertTrue($manager->send(new SmsMessage('123', 'hello'))->success);
            $client->assertSentCount(2);
        }
    }

    public function test_exhausted_retryable_gateway_falls_back(): void
    {
        [$manager, $client] = $this->manager([503, 503, 200]);
        $result = $manager->send(new SmsMessage('123', 'hello'));
        self::assertSame('second', $result->gateway);
        self::assertTrue($result->attempts[0]['retryable']);
        $client->assertSentCount(3);
    }

    public function test_fail_fast_stops_on_client_error(): void
    {
        [$manager, $client] = $this->manager([401]);
        $result = $manager->send(new SmsMessage('123', 'hello'));
        self::assertFalse($result->success);
        self::assertFalse($result->retryable);
        $client->assertSentCount(1);
    }

    public function test_try_all_continues_on_client_error(): void
    {
        [$manager, $client] = $this->manager([400, 200]);
        self::assertTrue($manager->send(new SmsMessage('123', 'hello'), FallbackStrategy::TRY_ALL)->success);
        $client->assertSentCount(2);
    }

    public function test_missing_credentials_are_non_retryable(): void
    {
        [$manager, $client] = $this->manager([], [], []);
        self::assertFalse($manager->send(new SmsMessage('123', 'hello'))->retryable);
        $client->assertNothingSent();
    }

    public function test_invalid_configuration(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        new SmsManager([]);
    }

    public function test_invalid_retry_count(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->manager([], ['retry_attempts' => 0]);
    }

    public function test_unknown_gateway(): void
    {
        [$manager] = $this->manager([]);
        $this->expectException(InvalidConfigurationException::class);
        $manager->send(new SmsMessage('123', 'hello'), gatewayOrder: ['missing']);
    }

    public function test_builder_queues_serializable_job_without_sending(): void
    {
        Bus::fake();
        [$manager, $client] = $this->manager([]);
        $manager->builder()->to('123')->text('hello')->gateway('first')->queue('redis', 'sms');
        Bus::assertDispatched(SendSms::class, function ($job) {
            $copy = unserialize(serialize($job));
            return $copy->message->text === 'hello' && $copy->gatewayOrder === ['first'] &&
                $copy->connection === 'redis' && $copy->queue === 'sms' && $copy->tries === 1;
        });
        $client->assertNothingSent();
    }

    public function test_connection_failure_stops_fail_fast_without_retry(): void
    {
        $calls = 0;
        [$manager, $client] = $this->manager(function () use (&$calls) {
            $calls++;
            throw new \Illuminate\Http\Client\ConnectionException('Ambiguous timeout');
        });
        $result = $manager->send(new SmsMessage('123', 'hello'));
        self::assertFalse($result->success);
        self::assertNull($result->retryable);
        self::assertCount(1, $result->attempts);
        self::assertSame(1, $calls);
    }

    public function test_builder_requires_message_before_queueing(): void
    {
        [$manager] = $this->manager([]);
        $this->expectException(\InvalidArgumentException::class);
        $manager->builder()->queue();
    }

    public function test_successful_job_sends_once(): void
    {
        [$manager, $client] = $this->manager([200]);
        (new SendSms(new SmsMessage('123', 'hello')))->handle($manager);
        $client->assertSentCount(1);
    }

    public function test_logs_correlate_retries_and_fallback_without_sensitive_data(): void
    {
        $records = [];
        $logger = new class($records) extends \Psr\Log\AbstractLogger {
            public function __construct(public array &$records) {}
            public function log($level, $message, array $context = []): void
            {
                $this->records[] = ['event' => $message, 'context' => $context];
            }
        };
        \Illuminate\Support\Facades\Log::swap($logger);
        $calls = 0;
        [$manager] = $this->manager(function () use (&$calls) {
            return Factory::response(['sid' => 'secret-response', 'error' => 'secret-token secret-body +201012345678'], ++$calls <= 2 ? 503 : 200);
        }, [], ['sid' => 'secret-account', 'token' => 'secret-token']);
        $manager->send(new SmsMessage('+201012345678', 'secret-body'));
        $encoded = json_encode($records);
        foreach (['+201012345678', 'secret-body', 'secret-token', 'secret-account', 'secret-response'] as $secret) {
            self::assertStringNotContainsString($secret, $encoded);
        }
        self::assertStringContainsString('***5678', $encoded);
        self::assertCount(1, array_unique(array_column(array_column($records, 'context'), 'correlation_id')));
        $http = array_values(array_filter($records, fn ($record) => $record['event'] === '[LaraSms] http.completed'));
        self::assertCount(3, $http);
        self::assertSame([1, 2, 1], array_column(array_column($http, 'context'), 'http_attempt'));
        self::assertSame([503, 503, 200], array_column(array_column($http, 'context'), 'status'));
        foreach ($http as $record) {
            self::assertGreaterThanOrEqual(0, $record['context']['duration_ms']);
        }
        $firstId = $records[0]['context']['correlation_id'];
        $records = [];
        $manager->send(new SmsMessage('+201012345678', 'secret-body'));
        self::assertNotSame($firstId, $records[0]['context']['correlation_id']);
        self::assertSame('first', $records[1]['context']['gateway']);
    }

    public function test_connection_exception_text_is_not_logged(): void
    {
        $records = [];
        $logger = new class($records) extends \Psr\Log\AbstractLogger {
            public function __construct(public array &$records) {}
            public function log($level, $message, array $context = []): void
            {
                $this->records[] = [$message, $context];
            }
        };
        \Illuminate\Support\Facades\Log::swap($logger);
        [$manager] = $this->manager(function () {
            throw new \Illuminate\Http\Client\ConnectionException('https://secret-token@example.test/secret-body');
        });
        $manager->send(new SmsMessage('123', 'secret-body'));
        self::assertStringNotContainsString('secret-', json_encode($records));
        self::assertStringContainsString('unknown', json_encode($records));
    }

    public function test_failed_job_throws_for_worker(): void
    {
        [$manager] = $this->manager([401]);
        $this->expectException(SmsException::class);
        (new SendSms(new SmsMessage('123', 'hello')))->handle($manager);
    }
}
