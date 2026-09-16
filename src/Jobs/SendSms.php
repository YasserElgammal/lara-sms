<?php
namespace YasserElgammal\LaraSms\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use YasserElgammal\LaraSms\Data\SmsMessage;
use YasserElgammal\LaraSms\Enums\FallbackStrategy;
use YasserElgammal\LaraSms\Exceptions\SmsException;
use YasserElgammal\LaraSms\Services\SmsManager;

class SendSms implements ShouldQueue
{
    use Queueable, InteractsWithQueue;

    public int $tries = 1;
    public bool $failOnTimeout = true;

    public function __construct(
        public SmsMessage $message,
        public ?FallbackStrategy $strategy = null,
        public ?array $gatewayOrder = null,
    ) {}

    public function handle(SmsManager $manager): void
    {
        $result = $manager->send($this->message, $this->strategy, $this->gatewayOrder);
        if (!$result->success) {
            throw new SmsException($result->error ?? 'SMS sending failed');
        }
    }
}
