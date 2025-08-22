# Configuration Guide

## Overview

The JTD Laravel AI package uses a comprehensive configuration system that supports multiple AI providers, environment-specific settings, and advanced features like logging, rate limiting, and cost tracking. The OpenAI driver is fully implemented and production-ready.

## Configuration Files

### Main Configuration File

The primary configuration is located at `config/ai.php`. Publish it using:

```bash
php artisan vendor:publish --provider="JTD\LaravelAI\LaravelAIServiceProvider"
```

### Environment Variables

All sensitive configuration should be stored in your `.env` file for security and environment-specific customization.

## OpenAI Configuration (Production Ready)

### Quick Setup

Add these essential environment variables to your `.env` file:

```env
# OpenAI Configuration (Required)
OPENAI_API_KEY=sk-your-openai-api-key-here
OPENAI_ORGANIZATION=org-your-organization-id    # Optional
OPENAI_PROJECT=proj_your-project-id             # Optional

# Default Provider
AI_DEFAULT_PROVIDER=openai
```

### Complete OpenAI Configuration

```php
// config/ai.php
'providers' => [
    'openai' => [
        // Driver Configuration
        'driver' => 'openai',
        'api_key' => env('OPENAI_API_KEY'),
        'organization' => env('OPENAI_ORGANIZATION'),
        'project' => env('OPENAI_PROJECT'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),

        // Timeout Configuration
        'timeout' => (int) env('OPENAI_TIMEOUT', 30),
        'connect_timeout' => (int) env('OPENAI_CONNECT_TIMEOUT', 10),
        'read_timeout' => (int) env('OPENAI_READ_TIMEOUT', 30),

        // Retry Configuration
        'retry_attempts' => (int) env('OPENAI_RETRY_ATTEMPTS', 3),
        'retry_delay' => (int) env('OPENAI_RETRY_DELAY', 1000),
        'max_retry_delay' => (int) env('OPENAI_MAX_RETRY_DELAY', 30000),
        'retry_jitter' => (bool) env('OPENAI_RETRY_JITTER', true),

        // Logging Configuration
        'logging' => [
            'enabled' => (bool) env('AI_LOGGING_ENABLED', true),
            'channel' => env('AI_LOG_CHANNEL', 'default'),
            'level' => env('AI_LOG_LEVEL', 'info'),
            'include_content' => (bool) env('AI_LOG_INCLUDE_CONTENT', false),
            'include_responses' => (bool) env('AI_LOG_INCLUDE_RESPONSES', false),
            'include_costs' => (bool) env('AI_LOG_INCLUDE_COSTS', true),
            'include_performance' => (bool) env('AI_LOG_INCLUDE_PERFORMANCE', true),
        ],

        // Rate Limiting Configuration
        'rate_limiting' => [
            'enabled' => (bool) env('AI_RATE_LIMITING_ENABLED', true),
            'requests_per_minute' => (int) env('OPENAI_RPM_LIMIT', 3500),
            'tokens_per_minute' => (int) env('OPENAI_TPM_LIMIT', 90000),
            'burst_allowance' => (int) env('OPENAI_BURST_ALLOWANCE', 100),
            'backoff_strategy' => env('OPENAI_BACKOFF_STRATEGY', 'exponential'),
        ],

        // Cost Configuration
        'cost_tracking' => [
            'enabled' => (bool) env('AI_COST_TRACKING_ENABLED', true),
            'currency' => env('AI_COST_CURRENCY', 'USD'),
            'precision' => (int) env('AI_COST_PRECISION', 6),
            'daily_limit' => (float) env('AI_DAILY_COST_LIMIT', 100.00),
            'monthly_limit' => (float) env('AI_MONTHLY_COST_LIMIT', 1000.00),
        ],
    ],

    // Future providers (planned)
    'xai' => [
        'driver' => 'xai',
        'api_key' => env('XAI_API_KEY'),
        'base_url' => env('XAI_BASE_URL', 'https://api.x.ai/v1'),
        'timeout' => (int) env('XAI_TIMEOUT', 30),
        'retry_attempts' => (int) env('XAI_RETRY_ATTEMPTS', 3),
    ],

    'gemini' => [
        'driver' => 'gemini',
        'api_key' => env('GEMINI_API_KEY'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 30),
        'retry_attempts' => (int) env('GEMINI_RETRY_ATTEMPTS', 3),
    ],

    'ollama' => [
        'driver' => 'ollama',
        'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
        'timeout' => (int) env('OLLAMA_TIMEOUT', 120), // Longer timeout for local processing
        'retry_attempts' => (int) env('OLLAMA_RETRY_ATTEMPTS', 1),
    ],
],
```

## Environment Variables Reference

### Required Variables

```env
# OpenAI API Key (Required for OpenAI provider)
OPENAI_API_KEY=sk-your-api-key-here
```

### Optional OpenAI Variables

```env
# Organization and Project (Optional)
OPENAI_ORGANIZATION=org-your-organization-id
OPENAI_PROJECT=proj_your-project-id

# API Configuration
OPENAI_BASE_URL=https://api.openai.com/v1
OPENAI_TIMEOUT=30
OPENAI_CONNECT_TIMEOUT=10
OPENAI_READ_TIMEOUT=30

# Retry Configuration
OPENAI_RETRY_ATTEMPTS=3
OPENAI_RETRY_DELAY=1000
OPENAI_MAX_RETRY_DELAY=30000
OPENAI_RETRY_JITTER=true

# Rate Limiting (Adjust based on your OpenAI tier)
OPENAI_RPM_LIMIT=3500
OPENAI_TPM_LIMIT=90000
OPENAI_BURST_ALLOWANCE=100
OPENAI_BACKOFF_STRATEGY=exponential

# Model Configuration
OPENAI_DEFAULT_MODEL=gpt-3.5-turbo
OPENAI_MODEL_SYNC_ENABLED=true
OPENAI_MODEL_SYNC_INTERVAL=3600
OPENAI_MODEL_CACHE_TTL=3600
OPENAI_ALLOWED_MODELS=gpt-3.5-turbo,gpt-4,gpt-4-turbo
```

### Global AI Configuration

```env
# Default Provider
AI_DEFAULT_PROVIDER=openai

# Logging Configuration
AI_LOGGING_ENABLED=true
AI_LOG_CHANNEL=default
AI_LOG_LEVEL=info
AI_LOG_INCLUDE_CONTENT=false      # Security: Don't log message content
AI_LOG_INCLUDE_RESPONSES=false    # Security: Don't log AI responses
AI_LOG_INCLUDE_COSTS=true
AI_LOG_INCLUDE_PERFORMANCE=true

# Rate Limiting
AI_RATE_LIMITING_ENABLED=true

# Cost Tracking
AI_COST_TRACKING_ENABLED=true
AI_COST_CURRENCY=USD
AI_COST_PRECISION=6
AI_DAILY_COST_LIMIT=100.00
AI_MONTHLY_COST_LIMIT=1000.00
```

### Future Provider Configuration (Planned)

```env
# xAI Configuration
XAI_API_KEY=xai-your-api-key
XAI_BASE_URL=https://api.x.ai/v1
XAI_TIMEOUT=30

# Google Gemini Configuration
GEMINI_API_KEY=your-gemini-api-key
GEMINI_BASE_URL=https://generativelanguage.googleapis.com/v1
GEMINI_TIMEOUT=30

# Ollama Configuration (Local AI)
OLLAMA_BASE_URL=http://localhost:11434
OLLAMA_TIMEOUT=120
```

## Configuration by Environment

### Development Environment

```env
# Development-specific settings
AI_LOGGING_ENABLED=true
AI_LOG_LEVEL=debug
AI_LOG_INCLUDE_CONTENT=true       # OK in development
AI_LOG_INCLUDE_RESPONSES=true     # OK in development

# Lower limits for development
AI_DAILY_COST_LIMIT=10.00
OPENAI_RPM_LIMIT=500
OPENAI_TPM_LIMIT=10000

# Shorter timeouts for faster feedback
OPENAI_TIMEOUT=15
OPENAI_RETRY_ATTEMPTS=2
```

### Production Environment

```env
# Production-specific settings
AI_LOGGING_ENABLED=true
AI_LOG_LEVEL=info
AI_LOG_INCLUDE_CONTENT=false      # Security: Never log content in production
AI_LOG_INCLUDE_RESPONSES=false    # Security: Never log responses in production

# Production limits
AI_DAILY_COST_LIMIT=1000.00
OPENAI_RPM_LIMIT=3500
OPENAI_TPM_LIMIT=90000

# Production timeouts
OPENAI_TIMEOUT=30
OPENAI_RETRY_ATTEMPTS=3
OPENAI_MAX_RETRY_DELAY=30000
```

### Testing Environment

```env
# Testing-specific settings
AI_DEFAULT_PROVIDER=mock          # Use mock provider for tests
AI_LOGGING_ENABLED=false          # Disable logging in tests
AI_COST_TRACKING_ENABLED=false    # Disable cost tracking in tests

# Fast timeouts for tests
OPENAI_TIMEOUT=5
OPENAI_RETRY_ATTEMPTS=1
OPENAI_RETRY_DELAY=1
```

## Advanced Configuration

### Custom Log Channel

Create a dedicated log channel for AI operations:

```php
// config/logging.php
'channels' => [
    'ai' => [
        'driver' => 'daily',
        'path' => storage_path('logs/ai.log'),
        'level' => env('AI_LOG_LEVEL', 'info'),
        'days' => 14,
        'replace_placeholders' => true,
    ],
],
```

Then set in your `.env`:
```env
AI_LOG_CHANNEL=ai
```

### Multiple OpenAI Configurations

```php
// config/ai.php
'providers' => [
    'openai' => [
        'driver' => 'openai',
        'api_key' => env('OPENAI_API_KEY'),
        // Standard configuration
    ],

    'openai-premium' => [
        'driver' => 'openai',
        'api_key' => env('OPENAI_PREMIUM_API_KEY'),
        'organization' => env('OPENAI_PREMIUM_ORGANIZATION'),
        'rate_limiting' => [
            'requests_per_minute' => 10000, // Higher limits for premium
            'tokens_per_minute' => 300000,
        ],
    ],

    'openai-development' => [
        'driver' => 'openai',
        'api_key' => env('OPENAI_DEV_API_KEY'),
        'cost_tracking' => [
            'daily_limit' => 10.00, // Lower limits for development
        ],
    ],
],
```

## Security Configuration

### Credential Security

```php
'security' => [
    'mask_credentials' => (bool) env('AI_MASK_CREDENTIALS', true),
    'validate_ssl' => (bool) env('AI_VALIDATE_SSL', true),
    'allowed_models' => env('OPENAI_ALLOWED_MODELS', null), // Comma-separated list
    'blocked_content_types' => env('AI_BLOCKED_CONTENT_TYPES', ''),
    'max_request_size' => (int) env('AI_MAX_REQUEST_SIZE', 1048576), // 1MB
    'content_filtering' => (bool) env('AI_CONTENT_FILTERING', false),
],
```

Environment variables:
```env
AI_MASK_CREDENTIALS=true
AI_VALIDATE_SSL=true
OPENAI_ALLOWED_MODELS=gpt-3.5-turbo,gpt-4,gpt-4-turbo
AI_BLOCKED_CONTENT_TYPES=""
AI_MAX_REQUEST_SIZE=1048576
AI_CONTENT_FILTERING=false
```

### HTTP Client Security

```php
'http_client' => [
    'verify_ssl' => (bool) env('OPENAI_VERIFY_SSL', true),
    'user_agent' => env('OPENAI_USER_AGENT', 'JTD-Laravel-AI/1.0'),
    'max_redirects' => (int) env('OPENAI_MAX_REDIRECTS', 3),
    'headers' => [
        'X-Request-ID' => function () {
            return Str::uuid();
        },
    ],
    'proxy' => env('HTTP_PROXY'), // Proxy configuration
    'no_proxy' => env('NO_PROXY'),
],
```

## Configuration Validation

### Validate Configuration

Use the built-in validation command to check your configuration:

```bash
# Validate all providers
php artisan ai:validate-config

# Validate specific provider
php artisan ai:validate-config openai

# Validate with detailed output
php artisan ai:validate-config --verbose
```

### Validate Credentials

Test your API credentials:

```bash
# Test OpenAI credentials
php artisan ai:validate-credentials openai

# Test all configured providers
php artisan ai:validate-credentials --all
```

### Configuration Testing

```php
// Test configuration in code
$validation = AI::provider('openai')->validateCredentials();

if ($validation['valid']) {
    echo "Configuration is valid!";
    echo "Organization: {$validation['organization']}";
    echo "Available models: {$validation['available_models']}";
} else {
    echo "Configuration errors:";
    foreach ($validation['errors'] as $error) {
        echo "- {$error}";
    }
}
```

## Troubleshooting

### Common Configuration Issues

1. **Invalid API Key Format**:
```bash
# Check API key format
echo $OPENAI_API_KEY | grep -E '^sk-[a-zA-Z0-9]{48}$'
```

2. **Rate Limiting Issues**:
```env
# Adjust rate limits based on your OpenAI tier
OPENAI_RPM_LIMIT=3500  # Tier 1: 3,500 RPM
OPENAI_TPM_LIMIT=90000 # Tier 1: 90,000 TPM
```

3. **Timeout Issues**:
```env
# Increase timeouts for slow responses
OPENAI_TIMEOUT=60
OPENAI_READ_TIMEOUT=60
```

4. **SSL Certificate Issues**:
```env
# Disable SSL verification (not recommended for production)
OPENAI_VERIFY_SSL=false
```

### Debug Configuration

Enable debug logging to troubleshoot issues:

```env
AI_LOGGING_ENABLED=true
AI_LOG_LEVEL=debug
AI_LOG_INCLUDE_CONTENT=true  # Only in development
AI_LOG_INCLUDE_RESPONSES=true # Only in development
```

### Configuration Cache

Clear configuration cache after changes:

```bash
php artisan config:clear
php artisan config:cache
```

## Best Practices

### Security Best Practices

1. **Never commit API keys to version control**:
```bash
# Add to .gitignore
echo ".env" >> .gitignore
echo "tests/credentials/e2e-credentials.json" >> .gitignore
```

2. **Use different API keys for different environments**:
```env
# Production
OPENAI_API_KEY=sk-prod-key...

# Development
OPENAI_API_KEY=sk-dev-key...

# Testing
OPENAI_API_KEY=sk-test-key...
```

3. **Implement cost limits**:
```env
AI_DAILY_COST_LIMIT=100.00
AI_MONTHLY_COST_LIMIT=1000.00
```

4. **Disable content logging in production**:
```env
AI_LOG_INCLUDE_CONTENT=false
AI_LOG_INCLUDE_RESPONSES=false
```

### Performance Best Practices

1. **Configure appropriate timeouts**:
```env
OPENAI_TIMEOUT=30          # Standard requests
OPENAI_CONNECT_TIMEOUT=10  # Connection timeout
OPENAI_READ_TIMEOUT=30     # Read timeout
```

2. **Use connection pooling**:
```env
OPENAI_POOL_SIZE=10
OPENAI_KEEP_ALIVE=true
OPENAI_KEEP_ALIVE_TIMEOUT=30
```

3. **Configure retry logic**:
```env
OPENAI_RETRY_ATTEMPTS=3
OPENAI_RETRY_DELAY=1000
OPENAI_MAX_RETRY_DELAY=30000
OPENAI_RETRY_JITTER=true
```

### Cost Management Best Practices

1. **Monitor costs in real-time**:
```env
AI_COST_TRACKING_ENABLED=true
AI_LOG_INCLUDE_COSTS=true
```

2. **Set appropriate rate limits**:
```env
OPENAI_RPM_LIMIT=3500  # Based on your OpenAI tier
OPENAI_TPM_LIMIT=90000 # Based on your OpenAI tier
```

3. **Use cost estimation**:
```php
// Estimate costs before sending expensive requests
$estimated = AI::provider('openai')->estimateTokens($messages, 'gpt-4');
$cost = AI::provider('openai')->calculateCost($estimated, 'gpt-4');

if ($cost > 1.00) {
    // Ask for confirmation or use cheaper model
}
```

## Configuration Examples

### Minimal Production Configuration

```env
# Required
OPENAI_API_KEY=sk-your-production-key

# Recommended
AI_DEFAULT_PROVIDER=openai
AI_LOGGING_ENABLED=true
AI_LOG_INCLUDE_CONTENT=false
AI_LOG_INCLUDE_RESPONSES=false
AI_COST_TRACKING_ENABLED=true
AI_DAILY_COST_LIMIT=1000.00
```

### Development Configuration

```env
# Required
OPENAI_API_KEY=sk-your-development-key

# Development settings
AI_DEFAULT_PROVIDER=openai
AI_LOGGING_ENABLED=true
AI_LOG_LEVEL=debug
AI_LOG_INCLUDE_CONTENT=true
AI_LOG_INCLUDE_RESPONSES=true
AI_DAILY_COST_LIMIT=10.00
OPENAI_TIMEOUT=15
```

### Testing Configuration

```env
# Use mock provider for tests
AI_DEFAULT_PROVIDER=mock
AI_LOGGING_ENABLED=false
AI_COST_TRACKING_ENABLED=false

# Fast timeouts for tests
OPENAI_TIMEOUT=5
OPENAI_RETRY_ATTEMPTS=1
```

For more detailed information about specific features, see:
- [OpenAI Driver Documentation](OPENAI_DRIVER.md)
- [E2E Testing Setup](E2E_TESTING.md)
- [Function Calling Examples](FUNCTION_CALLING_EXAMPLES.md)
