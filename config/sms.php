<?php

use YasserElgammal\LaraSms\Gateways\{
    DreamsGateway,
    InfobipGateway,
    TaqnyatGateway,
    TwilioGateway,
    SmsMisrGateway,
    MsegatGateway,
    JawalyGateway,
    MobilySmsGateway,
    VonageGateway
};


return [
    'default_gateway' => env('SMS_DEFAULT_GATEWAY', 'infobip'),
    'default_fallback_strategy' => env('SMS_FALLBACK_STRATEGY', 'fail_fast'),

    'gateways' => [
        'infobip' => [
            'class' => InfobipGateway::class,
            'config' => [
                'api_key' => env('INFOBIP_API_KEY'),
                'sender' => env('INFOBIP_SENDER'),
                'base_url' => env('INFOBIP_BASE_URL'),
            ],
        ],
        'vonage' => [
            'class' => VonageGateway::class,
            'config' => [
                'api_key' => env('VONAGE_API_KEY'),
                'api_secret' => env('VONAGE_API_SECRET'),
                'sender' => env('VONAGE_SENDER'),
            ],
        ],
        'jawaly' => [
            'class' => JawalyGateway::class,
            'config' => [
                'api_key' => env('JAWALY_API_KEY'),
                'api_secret' => env('JAWALY_API_SECRET'),
                'sender' => env('JAWALY_SENDER'),
            ],
        ],
        'smsmisr' => [
            'class' => SmsMisrGateway::class,
            'config' => [
                'username' => env('SMSMISR_USERNAME'),
                'password' => env('SMSMISR_PASSWORD'),
                'sender' => env('SMSMISR_SENDER'),
            ],
        ],
        'msegat' => [
            'class' => MsegatGateway::class,
            'config' => [
                'username' => env('MSEGAT_USERNAME'),
                'api_key' => env('MSEGAT_API_KEY'),
                'user_sender' => env('MSEGAT_USER_SENDER', 'auth-mseg'),
                'msg_encoding' => env('MSEGAT_MSG_ENCODING', 'UTF8'),
            ],
        ],
        'mobilysms' => [
            'class' => MobilySmsGateway::class,
            'config' => [
                'username' => env('MOBILY_USERNAME'),
                'password' => env('MOBILY_PASSWORD'),
                'sender' => env('MOBILY_SENDER'),
                'base_url' => env('MOBILY_BASE_URL', 'https://www.mobilysms.net'),
            ],
        ],
        'dreams' => [
            'class' => DreamsGateway::class,
            'config' => [
                'username' => env('DREAMS_USERNAME'),
                'secret_key' => env('DREAMS_SECRET_KEY'),
                'sender' => env('DREAMS_SENDER'),
            ],
        ],
        'taqnyat' => [
            'class' => TaqnyatGateway::class,
            'config' => [
                'token' => env('TAQNYAT_TOKEN'),
                'sender_id' => env('TAQNYAT_SENDER_ID'),
            ],
        ],
        'twilio' => [
            'class' => TwilioGateway::class,
            'config' => [
                'sid' => env('TWILIO_SID'),
                'token' => env('TWILIO_TOKEN'),
                'from' => env('TWILIO_FROM'),
            ],
        ]
    ],

    'http' => [
        'timeout' => env('SMS_TIMEOUT', 30),
        'retry_attempts' => env('SMS_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('SMS_RETRY_DELAY', 1000),
    ],
];
