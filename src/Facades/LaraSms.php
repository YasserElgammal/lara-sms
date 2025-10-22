<?php

namespace YasserElgammal\LaraSms\Facades;

use Illuminate\Support\Facades\Facade;
use YasserElgammal\LaraSms\Services\SmsManager;

class LaraSms extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SmsManager::class;
    }
}
