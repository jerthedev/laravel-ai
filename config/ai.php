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
            'connect_timeout' => env('AI_OPENAI_CONNECT_TIMEOUT', 10),
            'retry_attempts' => env('AI_OPENAI_RETRY_ATTEMPTS', 3),
            'retry_delay' => env('AI_OPENAI_RETRY_DELAY', 1000),
            'default_model' => env('AI_OPENAI_DEFAULT_MODEL', 'gpt-4o-mini'),
            'default_temperature' => env('AI_OPENAI_DEFAULT_TEMPERATURE', 0.7),
            'default_max_tokens' => env('AI_OPENAI_DEFAULT_MAX_TOKENS', 1000),
            'default_top_p' => env('AI_OPENAI_DEFAULT_TOP_P', 1.0),
            'default_frequency_penalty' => env('AI_OPENAI_DEFAULT_FREQUENCY_PENALTY', 0.0),
            'default_presence_penalty' => env('AI_OPENAI_DEFAULT_PRESENCE_PENALTY', 0.0),
            'streaming_enabled' => env('AI_OPENAI_STREAMING_ENABLED', true),
            'function_calling_enabled' => env('AI_OPENAI_FUNCTION_CALLING_ENABLED', true),
        ],

        'xai' => [
            'driver' => 'xai',
            'api_key' => env('AI_XAI_API_KEY'),
            'base_url' => env('AI_XAI_BASE_URL', 'https://api.x.ai/v1'),
            'timeout' => env('AI_XAI_TIMEOUT', 30),
            'connect_timeout' => env('AI_XAI_CONNECT_TIMEOUT', 10),
            'retry_attempts' => env('AI_XAI_RETRY_ATTEMPTS', 3),
            'retry_delay' => env('AI_XAI_RETRY_DELAY', 1000),
            'max_retry_delay' => env('AI_XAI_MAX_RETRY_DELAY', 30000),
            'default_model' => env('AI_XAI_DEFAULT_MODEL', 'grok-2-mini'),
            'default_temperature' => env('AI_XAI_DEFAULT_TEMPERATURE', 0.7),
            'default_max_tokens' => env('AI_XAI_DEFAULT_MAX_TOKENS', 1000),
            'default_top_p' => env('AI_XAI_DEFAULT_TOP_P', 1.0),
            'default_frequency_penalty' => env('AI_XAI_DEFAULT_FREQUENCY_PENALTY', 0.0),
            'default_presence_penalty' => env('AI_XAI_DEFAULT_PRESENCE_PENALTY', 0.0),
            'streaming_enabled' => env('AI_XAI_STREAMING_ENABLED', true),
            'function_calling_enabled' => env('AI_XAI_FUNCTION_CALLING_ENABLED', true),
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
    | Conversation Management
    |--------------------------------------------------------------------------
    |
    | Configuration for conversation persistence, templates, and management.
    | These settings control how conversations are stored, searched, and
    | managed within your application.
    |
    */

    'conversations' => [
        'enabled' => env('AI_CONVERSATIONS_ENABLED', true),

        'defaults' => [
            'auto_title' => env('AI_CONVERSATIONS_AUTO_TITLE', true),
            'max_messages' => env('AI_CONVERSATIONS_MAX_MESSAGES', null),
            'language' => env('AI_CONVERSATIONS_DEFAULT_LANGUAGE', 'en'),
            'conversation_type' => env('AI_CONVERSATIONS_DEFAULT_TYPE', 'chat'),
        ],

        'persistence' => [
            'store_messages' => env('AI_CONVERSATIONS_STORE_MESSAGES', true),
            'store_metadata' => env('AI_CONVERSATIONS_STORE_METADATA', true),
            'store_costs' => env('AI_CONVERSATIONS_STORE_COSTS', true),
            'store_performance' => env('AI_CONVERSATIONS_STORE_PERFORMANCE', true),
        ],

        'search' => [
            'enabled' => env('AI_CONVERSATIONS_SEARCH_ENABLED', true),
            'index_content' => env('AI_CONVERSATIONS_INDEX_CONTENT', true),
            'results_per_page' => env('AI_CONVERSATIONS_RESULTS_PER_PAGE', 15),
            'max_search_results' => env('AI_CONVERSATIONS_MAX_SEARCH_RESULTS', 100),
        ],

        'statistics' => [
            'enabled' => env('AI_CONVERSATIONS_STATISTICS_ENABLED', true),
            'cache_duration' => env('AI_CONVERSATIONS_STATS_CACHE_DURATION', 60), // minutes
            'real_time_updates' => env('AI_CONVERSATIONS_REAL_TIME_STATS', false),
        ],

        'templates' => [
            'enabled' => env('AI_CONVERSATION_TEMPLATES_ENABLED', true),
            'public_templates' => env('AI_CONVERSATION_TEMPLATES_PUBLIC', true),
            'template_sharing' => env('AI_CONVERSATION_TEMPLATES_SHARING', true),
            'max_parameters' => env('AI_CONVERSATION_TEMPLATES_MAX_PARAMS', 20),
        ],

        'context_management' => [
            'enabled' => env('AI_CONVERSATIONS_CONTEXT_MANAGEMENT', true),
            'default_window_size' => env('AI_CONVERSATIONS_CONTEXT_WINDOW', 10),
            'max_window_size' => env('AI_CONVERSATIONS_MAX_CONTEXT_WINDOW', 50),
            'smart_truncation' => env('AI_CONVERSATIONS_SMART_TRUNCATION', true),
            'preserve_system_messages' => env('AI_CONVERSATIONS_PRESERVE_SYSTEM', true),

            // Enhanced context management settings (Story 5)
            'default_context_window' => env('AI_CONVERSATION_DEFAULT_CONTEXT_WINDOW', 4096),
            'default_preservation_strategy' => env('AI_CONVERSATION_DEFAULT_PRESERVATION_STRATEGY', 'intelligent_truncation'),
            'default_context_ratio' => env('AI_CONVERSATION_DEFAULT_CONTEXT_RATIO', 0.8),
            'search_enhanced_context' => env('AI_CONVERSATION_SEARCH_ENHANCED_CONTEXT', true),
            'context_cache_ttl' => env('AI_CONVERSATION_CONTEXT_CACHE_TTL', 300),
            'max_search_results' => env('AI_CONVERSATION_MAX_SEARCH_RESULTS', 10),
            'relevance_threshold' => env('AI_CONVERSATION_RELEVANCE_THRESHOLD', 0.7),
        ],

        'provider_switching' => [
            'enabled' => env('AI_CONVERSATIONS_PROVIDER_SWITCHING', true),
            'preserve_context' => env('AI_CONVERSATIONS_PRESERVE_CONTEXT', true),
            'fallback_enabled' => env('AI_CONVERSATIONS_FALLBACK_ENABLED', true),
            'track_provider_history' => env('AI_CONVERSATIONS_TRACK_PROVIDERS', true),
        ],

        'cleanup' => [
            'auto_archive_days' => env('AI_CONVERSATIONS_AUTO_ARCHIVE_DAYS', null),
            'auto_delete_days' => env('AI_CONVERSATIONS_AUTO_DELETE_DAYS', null),
            'cleanup_empty_conversations' => env('AI_CONVERSATIONS_CLEANUP_EMPTY', false),
        ],

        'events' => [
            'dispatch_events' => env('AI_CONVERSATIONS_DISPATCH_EVENTS', true),
            'log_events' => env('AI_CONVERSATIONS_LOG_EVENTS', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Middleware System
    |--------------------------------------------------------------------------
    |
    | The middleware system provides Laravel-familiar request interception
    | and transformation capabilities for AI requests. Enables smart routing,
    | context injection, budget enforcement, and performance optimization.
    |
    */

    'middleware' => [
        'enabled' => env('AI_MIDDLEWARE_ENABLED', true),

        'global' => [
            'budget_enforcement' => [
                'enabled' => env('AI_BUDGET_ENFORCEMENT_ENABLED', true),
                'strict_mode' => env('AI_BUDGET_STRICT_MODE', false),
            ],
        ],

        'performance' => [
            'track_execution_time' => true,
            'log_slow_middleware' => true,
            'slow_threshold_ms' => 100,
        ],

        'available' => [
            'budget_enforcement' => \JTD\LaravelAI\Middleware\BudgetEnforcementMiddleware::class,
            // Additional middleware will be added in future phases
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Event System Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the AI event system that enables 85% performance
    | improvements through background processing of cost tracking, analytics,
    | and other non-critical operations.
    |
    */

    'events' => [
        'enabled' => env('AI_EVENTS_ENABLED', true),

        'queues' => [
            'analytics' => env('AI_ANALYTICS_QUEUE', 'ai-analytics'),
            'notifications' => env('AI_NOTIFICATIONS_QUEUE', 'ai-notifications'),
            'integrations' => env('AI_INTEGRATIONS_QUEUE', 'ai-integrations'),
        ],

        'listeners' => [
            'cost_tracking' => [
                'enabled' => env('AI_COST_TRACKING_EVENTS', true),
                'queue' => 'ai-analytics',
                'max_tries' => 3,
                'retry_delay' => 60, // seconds
            ],
            'analytics' => [
                'enabled' => env('AI_ANALYTICS_EVENTS', true),
                'queue' => 'ai-analytics',
                'max_tries' => 3,
                'retry_delay' => 60,
            ],
            'notifications' => [
                'enabled' => env('AI_NOTIFICATIONS_EVENTS', true),
                'queue' => 'ai-notifications',
                'max_tries' => 5, // More retries for critical notifications
                'retry_delay' => 30,
            ],
        ],

        'error_handling' => [
            'log_failures' => true,
            'dead_letter_queue' => env('AI_DEAD_LETTER_QUEUE', 'ai-failed'),
            'max_retry_attempts' => 3,
            'retry_backoff' => 'exponential', // 'linear', 'exponential'
        ],

        'function_calling' => [
            'enabled' => env('AI_FUNCTION_CALLING_ENABLED', true),
            'queue' => env('AI_FUNCTION_QUEUE', 'ai-functions'),
            'auto_register' => true, // Automatically register functions with providers
            'timeout' => 300, // Function execution timeout in seconds
            'max_retries' => 3,
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
