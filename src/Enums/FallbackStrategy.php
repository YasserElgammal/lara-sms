<?php

namespace YasserElgammal\LaraSms\Enums;

enum FallbackStrategy: string
{
    case FAIL_FAST = 'fail_fast';
    case TRY_ALL = 'try_all';
}
