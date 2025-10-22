<?php

namespace YasserElgammal\LaraSms;

use Illuminate\Support\ServiceProvider;
use YasserElgammal\LaraSms\Services\SmsManager;

class LaraSmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/sms.php', 'sms');

        $this->app->singleton(SmsManager::class, function ($app) {
            return new SmsManager($app['config']['sms']);
        });

        $this->app->alias(SmsManager::class, 'lara-sms');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/sms.php' => config_path('sms.php'),
        ], 'sms-config');
    }
}
