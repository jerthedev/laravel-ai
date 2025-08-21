# Configuration

## Overview

JTD Laravel AI uses a driver-based configuration system similar to Laravel's database connections. This allows you to configure multiple AI providers and seamlessly switch between them.

## Configuration File

The main configuration file is `config/ai.php`. Publish it using:

```bash
php artisan vendor:publish --provider="JTD\LaravelAI\LaravelAIServiceProvider" --tag="config"
```

## Basic Configuration

### Default Provider

Set your default AI provider in `.env`:

```env
AI_DEFAULT_PROVIDER=openai
```

### Provider Configuration

Each provider is configured in the `providers` array:

```php
'providers' => [
    'openai' => [
        'driver' => 'openai',
        'api_key' => env('AI_OPENAI_API_KEY'),
        'organization' => env('AI_OPENAI_ORGANIZATION'),
        'base_url' => 'https://api.openai.com/v1',
        'timeout' => 30,
        'retry_attempts' => 3,
        'retry_delay' => 1000, // milliseconds
    ],
    
    'xai' => [
        'driver' => 'xai',
        'api_key' => env('AI_XAI_API_KEY'),
        'base_url' => 'https://api.x.ai/v1',
        'timeout' => 30,
        'retry_attempts' => 3,
    ],
    
    'gemini' => [
        'driver' => 'gemini',
        'api_key' => env('AI_GEMINI_API_KEY'),
        'base_url' => 'https://generativelanguage.googleapis.com/v1',
        'timeout' => 30,
        'retry_attempts' => 3,
    ],
    
    'ollama' => [
        'driver' => 'ollama',
        'base_url' => env('AI_OLLAMA_BASE_URL', 'http://localhost:11434'),
        'timeout' => 120, // Longer timeout for local processing
        'retry_attempts' => 1,
    ],
],
```

## Environment Variables

### Core Settings

```env
# Default provider
AI_DEFAULT_PROVIDER=openai

# Global settings
AI_TIMEOUT=30
AI_RETRY_ATTEMPTS=3
AI_DEBUG=false
```

### OpenAI Configuration

```env
AI_OPENAI_API_KEY=sk-your-openai-api-key
AI_OPENAI_ORGANIZATION=org-your-organization-id
AI_OPENAI_BASE_URL=https://api.openai.com/v1
AI_OPENAI_TIMEOUT=30
```

### xAI Configuration

```env
AI_XAI_API_KEY=xai-your-api-key
AI_XAI_BASE_URL=https://api.x.ai/v1
AI_XAI_TIMEOUT=30
```

### Google Gemini Configuration

```env
AI_GEMINI_API_KEY=your-gemini-api-key
AI_GEMINI_BASE_URL=https://generativelanguage.googleapis.com/v1
AI_GEMINI_TIMEOUT=30
```

### Ollama Configuration

```env
AI_OLLAMA_BASE_URL=http://localhost:11434
AI_OLLAMA_TIMEOUT=120
```

## Advanced Configuration

### Multiple Accounts per Provider

You can configure multiple accounts for the same provider:

```php
'providers' => [
    'openai' => [
        'driver' => 'openai',
        'accounts' => [
            'default' => [
                'api_key' => env('AI_OPENAI_API_KEY'),
                'organization' => env('AI_OPENAI_ORGANIZATION'),
                'name' => 'Default OpenAI Account',
            ],
            'premium' => [
                'api_key' => env('AI_OPENAI_PREMIUM_API_KEY'),
                'organization' => env('AI_OPENAI_PREMIUM_ORG'),
                'name' => 'Premium OpenAI Account',
            ],
            'development' => [
                'api_key' => env('AI_OPENAI_DEV_API_KEY'),
                'organization' => env('AI_OPENAI_DEV_ORG'),
                'name' => 'Development Account',
            ],
        ],
        'default_account' => 'default',
    ],
],
```

Usage with multiple accounts:

```php
// Use specific account
$response = AI::conversation()
    ->provider('openai')
    ->account('premium')
    ->message('Hello')
    ->send();
```

### Cost Tracking Configuration

```php
'cost_tracking' => [
    'enabled' => env('AI_COST_TRACKING_ENABLED', true),
    'currency' => env('AI_COST_CURRENCY', 'USD'),
    'precision' => 6, // Decimal places for cost calculations
    'batch_size' => 100, // Batch size for cost calculations
    'auto_calculate' => true, // Automatically calculate costs
],
```

Environment variables:

```env
AI_COST_TRACKING_ENABLED=true
AI_COST_CURRENCY=USD
AI_COST_PRECISION=6
```

### Model Synchronization

```php
'model_sync' => [
    'enabled' => env('AI_MODEL_SYNC_ENABLED', true),
    'frequency' => env('AI_MODEL_SYNC_FREQUENCY', 'hourly'), // hourly, daily, weekly
    'auto_sync' => true, // Sync on provider registration
    'batch_size' => 50, // Models to sync per batch
],
```

Environment variables:

```env
AI_MODEL_SYNC_ENABLED=true
AI_MODEL_SYNC_FREQUENCY=hourly
```

### Caching Configuration

```php
'cache' => [
    'enabled' => env('AI_CACHE_ENABLED', true),
    'store' => env('AI_CACHE_STORE', 'redis'), // redis, database, file
    'ttl' => [
        'models' => 3600, // 1 hour
        'costs' => 86400, // 24 hours
        'responses' => 300, // 5 minutes (optional response caching)
    ],
    'prefix' => 'ai:',
],
```

Environment variables:

```env
AI_CACHE_ENABLED=true
AI_CACHE_STORE=redis
AI_CACHE_TTL=3600
```

### Rate Limiting

```php
'rate_limiting' => [
    'enabled' => env('AI_RATE_LIMITING_ENABLED', true),
    'global' => [
        'requests_per_minute' => 60,
        'requests_per_hour' => 1000,
    ],
    'per_provider' => [
        'openai' => [
            'requests_per_minute' => 50,
            'tokens_per_minute' => 40000,
        ],
        'xai' => [
            'requests_per_minute' => 30,
            'tokens_per_minute' => 20000,
        ],
    ],
    'per_user' => [
        'requests_per_minute' => 10,
        'requests_per_hour' => 100,
    ],
],
```

### Logging Configuration

```php
'logging' => [
    'enabled' => env('AI_LOGGING_ENABLED', true),
    'channel' => env('AI_LOG_CHANNEL', 'ai'), // Custom log channel
    'level' => env('AI_LOG_LEVEL', 'info'),
    'log_requests' => true,
    'log_responses' => false, // Be careful with sensitive data
    'log_costs' => true,
    'log_errors' => true,
],
```

Create a custom log channel in `config/logging.php`:

```php
'channels' => [
    'ai' => [
        'driver' => 'daily',
        'path' => storage_path('logs/ai.log'),
        'level' => env('AI_LOG_LEVEL', 'info'),
        'days' => 14,
    ],
],
```

### MCP (Model Context Protocol) Configuration

```php
'mcp' => [
    'enabled' => env('AI_MCP_ENABLED', true),
    'servers' => [
        'sequential-thinking' => [
            'enabled' => true,
            'max_thoughts' => 10,
            'timeout' => 30,
        ],
        'custom-server' => [
            'enabled' => false,
            'endpoint' => env('AI_MCP_CUSTOM_ENDPOINT'),
            'timeout' => 30,
        ],
    ],
],
```

## Provider-Specific Configuration

### OpenAI Specific Options

```php
'openai' => [
    'driver' => 'openai',
    'api_key' => env('AI_OPENAI_API_KEY'),
    'organization' => env('AI_OPENAI_ORGANIZATION'),
    'project' => env('AI_OPENAI_PROJECT'), // Optional project ID
    'base_url' => 'https://api.openai.com/v1',
    'timeout' => 30,
    'retry_attempts' => 3,
    'default_model' => 'gpt-4',
    'default_temperature' => 0.7,
    'default_max_tokens' => 1000,
    'stream_support' => true,
    'function_calling' => true,
    'vision_support' => true,
],
```

### Gemini Specific Options

```php
'gemini' => [
    'driver' => 'gemini',
    'api_key' => env('AI_GEMINI_API_KEY'),
    'base_url' => 'https://generativelanguage.googleapis.com/v1',
    'timeout' => 30,
    'default_model' => 'gemini-pro',
    'safety_settings' => [
        'HARM_CATEGORY_HARASSMENT' => 'BLOCK_MEDIUM_AND_ABOVE',
        'HARM_CATEGORY_HATE_SPEECH' => 'BLOCK_MEDIUM_AND_ABOVE',
    ],
],
```

### Ollama Specific Options

```php
'ollama' => [
    'driver' => 'ollama',
    'base_url' => env('AI_OLLAMA_BASE_URL', 'http://localhost:11434'),
    'timeout' => 120,
    'default_model' => 'llama2',
    'keep_alive' => '5m', // Keep model loaded for 5 minutes
    'num_ctx' => 2048, // Context window size
    'temperature' => 0.7,
],
```

## Configuration Validation

Validate your configuration:

```bash
# Check configuration
php artisan ai:config:validate

# Test provider connections
php artisan ai:providers:test

# Verify credentials
php artisan ai:credentials:verify
```

## Dynamic Configuration

You can also configure providers dynamically at runtime:

```php
use JTD\LaravelAI\Facades\AI;

// Add a provider at runtime
AI::addProvider('custom-openai', [
    'driver' => 'openai',
    'api_key' => 'custom-key',
    'organization' => 'custom-org',
]);

// Use the custom provider
$response = AI::conversation()
    ->provider('custom-openai')
    ->message('Hello')
    ->send();
```

## Security Considerations

1. **Never commit API keys** to version control
2. **Use environment variables** for all sensitive data
3. **Rotate API keys** regularly
4. **Monitor usage** to detect unauthorized access
5. **Use separate keys** for different environments
6. **Enable logging** for audit trails

## Configuration Examples

### Development Environment

```env
AI_DEFAULT_PROVIDER=openai
AI_OPENAI_API_KEY=sk-dev-key
AI_COST_TRACKING_ENABLED=false
AI_CACHE_ENABLED=false
AI_DEBUG=true
AI_LOG_LEVEL=debug
```

### Production Environment

```env
AI_DEFAULT_PROVIDER=openai
AI_OPENAI_API_KEY=sk-prod-key
AI_COST_TRACKING_ENABLED=true
AI_CACHE_ENABLED=true
AI_RATE_LIMITING_ENABLED=true
AI_DEBUG=false
AI_LOG_LEVEL=warning
```
