<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | This option controls the default AI provider that will be used by your
    | application. You may set this to any of the providers defined in the
    | "providers" array below.
    |
    */

    'default' => env('AI_DEFAULT_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    |
    | Here you may configure the AI providers for your application. Each
    | provider has its own configuration options. You may configure multiple
    | accounts for the same provider if needed.
    |
    */

    'providers' => [

        'openai' => [
            'driver' => 'openai',
            'api_key' => env('AI_OPENAI_API_KEY'),
            'organization' => env('AI_OPENAI_ORGANIZATION'),
            'project' => env('AI_OPENAI_PROJECT'),
            'base_url' => env('AI_OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'timeout' => env('AI_OPENAI_TIMEOUT', 30),
            'retry_attempts' => env('AI_OPENAI_RETRY_ATTEMPTS', 3),
            'retry_delay' => env('AI_OPENAI_RETRY_DELAY', 1000),
            'default_model' => env('AI_OPENAI_DEFAULT_MODEL', 'gpt-4'),
            'default_temperature' => env('AI_OPENAI_DEFAULT_TEMPERATURE', 0.7),
            'default_max_tokens' => env('AI_OPENAI_DEFAULT_MAX_TOKENS', 1000),
        ],

        'xai' => [
            'driver' => 'xai',
            'api_key' => env('AI_XAI_API_KEY'),
            'base_url' => env('AI_XAI_BASE_URL', 'https://api.x.ai/v1'),
            'timeout' => env('AI_XAI_TIMEOUT', 30),
            'retry_attempts' => env('AI_XAI_RETRY_ATTEMPTS', 3),
            'retry_delay' => env('AI_XAI_RETRY_DELAY', 1000),
            'default_model' => env('AI_XAI_DEFAULT_MODEL', 'grok-beta'),
            'default_temperature' => env('AI_XAI_DEFAULT_TEMPERATURE', 0.7),
        ],

        'gemini' => [
            'driver' => 'gemini',
            'api_key' => env('AI_GEMINI_API_KEY'),
            'base_url' => env('AI_GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1'),
            'timeout' => env('AI_GEMINI_TIMEOUT', 30),
            'retry_attempts' => env('AI_GEMINI_RETRY_ATTEMPTS', 3),
            'retry_delay' => env('AI_GEMINI_RETRY_DELAY', 1000),
            'default_model' => env('AI_GEMINI_DEFAULT_MODEL', 'gemini-pro'),
            'safety_settings' => [
                'HARM_CATEGORY_HARASSMENT' => 'BLOCK_MEDIUM_AND_ABOVE',
                'HARM_CATEGORY_HATE_SPEECH' => 'BLOCK_MEDIUM_AND_ABOVE',
                'HARM_CATEGORY_SEXUALLY_EXPLICIT' => 'BLOCK_MEDIUM_AND_ABOVE',
                'HARM_CATEGORY_DANGEROUS_CONTENT' => 'BLOCK_MEDIUM_AND_ABOVE',
            ],
        ],

        'ollama' => [
            'driver' => 'ollama',
            'base_url' => env('AI_OLLAMA_BASE_URL', 'http://localhost:11434'),
            'timeout' => env('AI_OLLAMA_TIMEOUT', 120),
            'retry_attempts' => env('AI_OLLAMA_RETRY_ATTEMPTS', 1),
            'default_model' => env('AI_OLLAMA_DEFAULT_MODEL', 'llama2'),
            'keep_alive' => env('AI_OLLAMA_KEEP_ALIVE', '5m'),
            'num_ctx' => env('AI_OLLAMA_NUM_CTX', 2048),
            'temperature' => env('AI_OLLAMA_TEMPERATURE', 0.7),
        ],

        'mock' => [
            'driver' => 'mock',
            'valid_credentials' => env('AI_MOCK_VALID_CREDENTIALS', true),
            'mock_responses' => [
                'Hello' => [
                    'content' => 'Hello! How can I help you today?',
                    'tokens_used' => 20,
                    'input_tokens' => 5,
                    'output_tokens' => 15,
                    'cost' => 0.002,
                ],
                'default' => [
                    'content' => 'This is a mock response from the AI provider.',
                    'tokens_used' => 25,
                    'input_tokens' => 10,
                    'output_tokens' => 15,
                    'cost' => 0.0025,
                ],
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cost Tracking
    |--------------------------------------------------------------------------
    |
    | These options control how the package tracks and calculates costs for
    | AI API usage. You can enable/disable cost tracking and configure
    | currency and precision settings.
    |
    */

    'cost_tracking' => [
        'enabled' => env('AI_COST_TRACKING_ENABLED', true),
        'currency' => env('AI_COST_CURRENCY', 'USD'),
        'precision' => env('AI_COST_PRECISION', 6),
        'batch_size' => env('AI_COST_BATCH_SIZE', 100),
        'auto_calculate' => env('AI_COST_AUTO_CALCULATE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Synchronization
    |--------------------------------------------------------------------------
    |
    | These settings control how the package synchronizes available models
    | from AI providers. Models are cached and updated periodically.
    |
    */

    'model_sync' => [
        'enabled' => env('AI_MODEL_SYNC_ENABLED', true),
        'frequency' => env('AI_MODEL_SYNC_FREQUENCY', 'hourly'),
        'auto_sync' => env('AI_MODEL_SYNC_AUTO', true),
        'batch_size' => env('AI_MODEL_SYNC_BATCH_SIZE', 50),
        'timeout' => env('AI_MODEL_SYNC_TIMEOUT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics Configuration
    |--------------------------------------------------------------------------
    |
    | Configure analytics and usage tracking for AI operations.
    |
    */

    'analytics' => [
        'enabled' => env('AI_ANALYTICS_ENABLED', true),
        'retention_days' => env('AI_ANALYTICS_RETENTION_DAYS', 90),
        'track_conversations' => env('AI_TRACK_CONVERSATIONS', true),
        'track_performance' => env('AI_TRACK_PERFORMANCE', true),
        'aggregate_daily' => env('AI_AGGREGATE_DAILY', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Configure caching for models, costs, and responses. This can significantly
    | improve performance by reducing API calls.
    |
    */

    'cache' => [
        'enabled' => env('AI_CACHE_ENABLED', true),
        'store' => env('AI_CACHE_STORE', 'redis'),
        'prefix' => env('AI_CACHE_PREFIX', 'ai:'),
        'ttl' => [
            'models' => env('AI_CACHE_TTL_MODELS', 3600), // 1 hour
            'costs' => env('AI_CACHE_TTL_COSTS', 86400), // 24 hours
            'responses' => env('AI_CACHE_TTL_RESPONSES', 300), // 5 minutes
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting to prevent exceeding provider API limits.
    | You can set global limits and per-provider limits.
    |
    */

    'rate_limiting' => [
        'enabled' => env('AI_RATE_LIMITING_ENABLED', true),
        'global' => [
            'requests_per_minute' => env('AI_RATE_LIMIT_GLOBAL_RPM', 60),
            'requests_per_hour' => env('AI_RATE_LIMIT_GLOBAL_RPH', 1000),
        ],
        'per_provider' => [
            'openai' => [
                'requests_per_minute' => env('AI_RATE_LIMIT_OPENAI_RPM', 50),
                'tokens_per_minute' => env('AI_RATE_LIMIT_OPENAI_TPM', 40000),
            ],
            'xai' => [
                'requests_per_minute' => env('AI_RATE_LIMIT_XAI_RPM', 30),
                'tokens_per_minute' => env('AI_RATE_LIMIT_XAI_TPM', 20000),
            ],
            'gemini' => [
                'requests_per_minute' => env('AI_RATE_LIMIT_GEMINI_RPM', 60),
                'tokens_per_minute' => env('AI_RATE_LIMIT_GEMINI_TPM', 32000),
            ],
        ],
        'per_user' => [
            'requests_per_minute' => env('AI_RATE_LIMIT_USER_RPM', 10),
            'requests_per_hour' => env('AI_RATE_LIMIT_USER_RPH', 100),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configure logging for AI operations. Be careful with logging responses
    | as they may contain sensitive information.
    |
    */

    'logging' => [
        'enabled' => env('AI_LOGGING_ENABLED', true),
        'channel' => env('AI_LOG_CHANNEL', 'ai'),
        'level' => env('AI_LOG_LEVEL', 'info'),
        'log_requests' => env('AI_LOG_REQUESTS', true),
        'log_responses' => env('AI_LOG_RESPONSES', false),
        'log_costs' => env('AI_LOG_COSTS', true),
        'log_errors' => env('AI_LOG_ERRORS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Context Protocol (MCP)
    |--------------------------------------------------------------------------
    |
    | Configure MCP servers for enhanced AI capabilities like Sequential
    | Thinking and custom tool integrations.
    |
    */

    'mcp' => [
        'enabled' => env('AI_MCP_ENABLED', true),
        'servers' => [
            'sequential-thinking' => [
                'enabled' => env('AI_MCP_SEQUENTIAL_THINKING_ENABLED', true),
                'max_thoughts' => env('AI_MCP_SEQUENTIAL_THINKING_MAX_THOUGHTS', 10),
                'timeout' => env('AI_MCP_SEQUENTIAL_THINKING_TIMEOUT', 30),
            ],
            'custom-server' => [
                'enabled' => env('AI_MCP_CUSTOM_ENABLED', false),
                'endpoint' => env('AI_MCP_CUSTOM_ENDPOINT'),
                'timeout' => env('AI_MCP_CUSTOM_TIMEOUT', 30),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global Settings
    |--------------------------------------------------------------------------
    |
    | Global settings that apply to all providers and operations.
    |
    */

    'timeout' => env('AI_TIMEOUT', 30),
    'retry_attempts' => env('AI_RETRY_ATTEMPTS', 3),
    'debug' => env('AI_DEBUG', false),

];
