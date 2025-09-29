<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for handling incoming webhooks from AI providers
    |
    */

    'signing_secret' => env('WEBHOOK_SIGNING_SECRET'),

    'timeout' => env('WEBHOOK_TIMEOUT', 30),

    'retry' => [
        'enabled' => env('WEBHOOK_RETRY_ENABLED', true),
        'max_attempts' => env('WEBHOOK_RETRY_MAX_ATTEMPTS', 3),
        'delay' => env('WEBHOOK_RETRY_DELAY', 60), // seconds
    ],

    'endpoints' => [
        'openai' => [
            'enabled' => env('WEBHOOK_OPENAI_ENABLED', true),
            'secret' => env('WEBHOOK_OPENAI_SECRET'),
            'verify_signature' => env('WEBHOOK_OPENAI_VERIFY', true),
        ],
        'anthropic' => [
            'enabled' => env('WEBHOOK_ANTHROPIC_ENABLED', true),
            'secret' => env('WEBHOOK_ANTHROPIC_SECRET'),
            'verify_signature' => env('WEBHOOK_ANTHROPIC_VERIFY', true),
        ],
        'gemini' => [
            'enabled' => env('WEBHOOK_GEMINI_ENABLED', true),
            'secret' => env('WEBHOOK_GEMINI_SECRET'),
            'verify_signature' => env('WEBHOOK_GEMINI_VERIFY', true),
        ],
        'custom' => [
            'enabled' => env('WEBHOOK_CUSTOM_ENABLED', true),
            'secret' => env('WEBHOOK_CUSTOM_SECRET'),
            'verify_signature' => env('WEBHOOK_CUSTOM_VERIFY', false),
        ],
    ],

    'allowed_ips' => env('WEBHOOK_ALLOWED_IPS') ? explode(',', env('WEBHOOK_ALLOWED_IPS')) : [],

    'rate_limit' => [
        'enabled' => env('WEBHOOK_RATE_LIMIT_ENABLED', true),
        'max_requests' => env('WEBHOOK_RATE_LIMIT_MAX', 100),
        'window' => env('WEBHOOK_RATE_LIMIT_WINDOW', 60), // seconds
    ],

    'logging' => [
        'enabled' => env('WEBHOOK_LOGGING_ENABLED', true),
        'log_payload' => env('WEBHOOK_LOG_PAYLOAD', false),
        'log_headers' => env('WEBHOOK_LOG_HEADERS', false),
    ],
];