<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Service Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for AI integration service including provider settings,
    | processing options, and feature flags.
    |
    */

    'default_provider' => env('AI_DEFAULT_PROVIDER', 'openai'),

    'providers' => [
        'openai' => [
            'enabled' => env('OPENAI_ENABLED', false),
            'api_key' => env('OPENAI_API_KEY'),
            'organization' => env('OPENAI_ORGANIZATION'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'models' => [
                'categorization' => env('OPENAI_MODEL_CATEGORIZATION', 'gpt-3.5-turbo'),
                'prioritization' => env('OPENAI_MODEL_PRIORITIZATION', 'gpt-3.5-turbo'),
                'response_suggestion' => env('OPENAI_MODEL_RESPONSE', 'gpt-4'),
                'sentiment' => env('OPENAI_MODEL_SENTIMENT', 'gpt-3.5-turbo'),
                'summarization' => env('OPENAI_MODEL_SUMMARIZATION', 'gpt-3.5-turbo'),
            ],
            'timeout' => env('OPENAI_TIMEOUT', 30),
            'retry_attempts' => env('OPENAI_RETRY_ATTEMPTS', 3),
        ],

        'anthropic' => [
            'enabled' => env('ANTHROPIC_ENABLED', false),
            'api_key' => env('ANTHROPIC_API_KEY'),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
            'models' => [
                'default' => env('ANTHROPIC_MODEL', 'claude-2'),
            ],
            'timeout' => env('ANTHROPIC_TIMEOUT', 30),
        ],

        'gemini' => [
            'enabled' => env('GEMINI_ENABLED', false),
            'api_key' => env('GEMINI_API_KEY'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com'),
            'models' => [
                'default' => env('GEMINI_MODEL', 'gemini-pro'),
            ],
            'timeout' => env('GEMINI_TIMEOUT', 30),
        ],

        'custom' => [
            'enabled' => env('CUSTOM_AI_ENABLED', false),
            'endpoint' => env('CUSTOM_AI_ENDPOINT'),
            'api_key' => env('CUSTOM_AI_API_KEY'),
            'headers' => env('CUSTOM_AI_HEADERS'),
            'timeout' => env('CUSTOM_AI_TIMEOUT', 30),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Processing Configuration
    |--------------------------------------------------------------------------
    */

    'processing' => [
        'max_retries' => env('AI_MAX_RETRIES', 3),
        'retry_delay' => env('AI_RETRY_DELAY', 60), // seconds
        'batch_size' => env('AI_BATCH_SIZE', 10),
        'async_enabled' => env('AI_ASYNC_ENABLED', true),
        'cache_ttl' => env('AI_CACHE_TTL', 3600), // seconds
        'rate_limit' => env('AI_RATE_LIMIT', 100), // requests per minute
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    */

    'features' => [
        'auto_categorization' => env('AI_FEATURE_AUTO_CATEGORIZATION', false),
        'auto_prioritization' => env('AI_FEATURE_AUTO_PRIORITIZATION', false),
        'response_suggestions' => env('AI_FEATURE_RESPONSE_SUGGESTIONS', false),
        'sentiment_analysis' => env('AI_FEATURE_SENTIMENT_ANALYSIS', false),
        'entity_extraction' => env('AI_FEATURE_ENTITY_EXTRACTION', false),
        'summarization' => env('AI_FEATURE_SUMMARIZATION', false),
        'kb_generation' => env('AI_FEATURE_KB_GENERATION', false),
        'smart_routing' => env('AI_FEATURE_SMART_ROUTING', false),
        'predictive_analytics' => env('AI_FEATURE_PREDICTIVE_ANALYTICS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Prompts Configuration
    |--------------------------------------------------------------------------
    */

    'prompts' => [
        'categorization' => [
            'system' => 'You are a customer support categorization assistant. Analyze tickets and assign the most appropriate category.',
            'temperature' => 0.3,
            'max_tokens' => 100,
        ],
        'prioritization' => [
            'system' => 'You are a customer support prioritization assistant. Analyze tickets and determine their priority level (low, medium, high, urgent).',
            'temperature' => 0.2,
            'max_tokens' => 50,
        ],
        'response_suggestion' => [
            'system' => 'You are a helpful customer support assistant. Provide professional, empathetic responses to customer inquiries.',
            'temperature' => 0.7,
            'max_tokens' => 500,
        ],
        'sentiment_analysis' => [
            'system' => 'Analyze the sentiment of the customer message. Classify as: positive, neutral, negative, or critical.',
            'temperature' => 0.1,
            'max_tokens' => 50,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring Configuration
    |--------------------------------------------------------------------------
    */

    'monitoring' => [
        'enabled' => env('AI_MONITORING_ENABLED', true),
        'log_requests' => env('AI_LOG_REQUESTS', true),
        'log_responses' => env('AI_LOG_RESPONSES', false),
        'metrics_enabled' => env('AI_METRICS_ENABLED', true),
        'alert_on_failure' => env('AI_ALERT_ON_FAILURE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    */

    'security' => [
        'encrypt_api_keys' => env('AI_ENCRYPT_API_KEYS', true),
        'mask_sensitive_data' => env('AI_MASK_SENSITIVE_DATA', true),
        'allowed_ips' => env('AI_ALLOWED_IPS'),
        'webhook_secret' => env('AI_WEBHOOK_SECRET'),
    ],
];