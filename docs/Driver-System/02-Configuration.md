# Configuration System

## Overview

The JTD Laravel AI package uses Laravel's standard configuration system for all driver settings, ensuring proper environment variable management, configuration caching, and security best practices.

## Production Configuration

### Configuration File Structure

All driver configuration is managed through `config/ai.php`:

```php
<?php

return [
    'default' => env('AI_DEFAULT_PROVIDER', 'openai'),
    
    'providers' => [
        'openai' => [
            'driver' => 'openai',
            'api_key' => env('OPENAI_API_KEY'),
            'organization' => env('OPENAI_ORGANIZATION'),
            'project' => env('OPENAI_PROJECT'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'timeout' => (int) env('OPENAI_TIMEOUT', 30),
            'retry_attempts' => (int) env('OPENAI_RETRY_ATTEMPTS', 3),
            'retry_delay' => (int) env('OPENAI_RETRY_DELAY', 1000),
            'max_retry_delay' => (int) env('OPENAI_MAX_RETRY_DELAY', 30000),
            'logging' => [
                'enabled' => (bool) env('AI_LOGGING_ENABLED', true),
                'channel' => env('AI_LOG_CHANNEL', 'default'),
                'level' => env('AI_LOG_LEVEL', 'info'),
                'include_content' => (bool) env('AI_LOG_INCLUDE_CONTENT', false),
            ],
            'rate_limiting' => [
                'enabled' => (bool) env('AI_RATE_LIMITING_ENABLED', true),
                'requests_per_minute' => (int) env('OPENAI_RPM_LIMIT', 3500),
                'tokens_per_minute' => (int) env('OPENAI_TPM_LIMIT', 90000),
            ],
        ],
        
        'gemini' => [
            'driver' => 'gemini',
            'api_key' => env('GEMINI_API_KEY'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1'),
            'timeout' => (int) env('GEMINI_TIMEOUT', 30),
            'retry_attempts' => (int) env('GEMINI_RETRY_ATTEMPTS', 3),
            'safety_settings' => [
                'harassment' => env('GEMINI_SAFETY_HARASSMENT', 'BLOCK_MEDIUM_AND_ABOVE'),
                'hate_speech' => env('GEMINI_SAFETY_HATE_SPEECH', 'BLOCK_MEDIUM_AND_ABOVE'),
                'sexually_explicit' => env('GEMINI_SAFETY_SEXUALLY_EXPLICIT', 'BLOCK_MEDIUM_AND_ABOVE'),
                'dangerous_content' => env('GEMINI_SAFETY_DANGEROUS_CONTENT', 'BLOCK_MEDIUM_AND_ABOVE'),
            ],
        ],
        
        'xai' => [
            'driver' => 'xai',
            'api_key' => env('XAI_API_KEY'),
            'base_url' => env('XAI_BASE_URL', 'https://api.x.ai/v1'),
            'timeout' => (int) env('XAI_TIMEOUT', 30),
            'retry_attempts' => (int) env('XAI_RETRY_ATTEMPTS', 3),
        ],
    ],
    
    'sync' => [
        'auto_sync' => (bool) env('AI_AUTO_SYNC_MODELS', true),
        'sync_interval' => (int) env('AI_SYNC_INTERVAL_HOURS', 12),
        'cache_ttl' => (int) env('AI_MODEL_CACHE_TTL_HOURS', 24),
    ],
];
```

### Environment Variables

Add these to your `.env` file:

```env
# Default provider
AI_DEFAULT_PROVIDER=openai

# OpenAI Configuration
OPENAI_API_KEY=sk-your-openai-key-here
OPENAI_ORGANIZATION=org-your-org-id
OPENAI_PROJECT=proj-your-project-id

# Gemini Configuration
GEMINI_API_KEY=your-gemini-key-here

# xAI Configuration
XAI_API_KEY=your-xai-key-here

# Global AI Settings
AI_LOGGING_ENABLED=true
AI_LOG_LEVEL=info
AI_AUTO_SYNC_MODELS=true
AI_SYNC_INTERVAL_HOURS=12
```

## Development & Testing Configuration

### Package Development Environment

In the package development environment, we maintain separation between configuration and credentials while ensuring all code uses the configuration system.

### E2E Testing Credentials

For End-to-End (E2E) testing with real AI provider APIs, credentials are stored in:

```
tests/credentials/e2e-credentials.json
```

**Important**: This file is git-excluded and contains actual API keys for testing purposes.

#### E2E Credentials File Structure

```json
{
  "openai": {
    "api_key": "sk-real-openai-key-here",
    "organization": "org-123456789",
    "project": "proj-123456789"
  },
  "gemini": {
    "api_key": "real-gemini-key-here"
  },
  "xai": {
    "api_key": "real-xai-key-here"
  }
}
```

### E2E Test Configuration Pattern

E2E tests should:
1. **Use the configuration system**: Always read from `config/ai.php` like production code
2. **Override with real credentials**: Load credentials from `tests/credentials/e2e-credentials.json` and override config values
3. **Skip when missing**: Skip E2E tests when the credentials file doesn't exist
4. **Test real functionality**: Use actual API endpoints to validate driver behavior

#### E2E Test Implementation Example

```php
<?php

namespace Tests\E2E\Drivers;

use JTD\LaravelAI\Drivers\OpenAI\OpenAIDriver;use JTD\LaravelAI\Models\AIMessage;use Tests\TestCase;

class OpenAIDriverE2ETest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        if (!$this->hasE2ECredentials('openai')) {
            $this->markTestSkipped('E2E credentials not available for OpenAI');
        }
        
        // Override config with real credentials
        $this->overrideConfigWithE2ECredentials('openai');
    }
    
    protected function hasE2ECredentials(string $provider): bool
    {
        $credentialsFile = base_path('tests/credentials/e2e-credentials.json');
        
        if (!file_exists($credentialsFile)) {
            return false;
        }
        
        $credentials = json_decode(file_get_contents($credentialsFile), true);
        return isset($credentials[$provider]['api_key']);
    }
    
    protected function overrideConfigWithE2ECredentials(string $provider): void
    {
        $credentialsFile = base_path('tests/credentials/e2e-credentials.json');
        $credentials = json_decode(file_get_contents($credentialsFile), true);
        
        // Override the config with real credentials
        config([
            "ai.providers.{$provider}" => array_merge(
                config("ai.providers.{$provider}", []),
                $credentials[$provider]
            )
        ]);
    }
    
    public function test_real_api_message_sending(): void
    {
        $driver = new OpenAIDriver(config('ai.providers.openai'));
        
        $response = $driver->sendMessage(
            AIMessage::user('Say "Hello, E2E test!" and nothing else.')
        );
        
        $this->assertNotEmpty($response->content);
        $this->assertStringContainsString('Hello, E2E test!', $response->content);
    }
    
    public function test_real_api_model_sync(): void
    {
        $driver = new OpenAIDriver(config('ai.providers.openai'));
        
        $result = $driver->syncModels(true);
        
        $this->assertEquals('success', $result['status']);
        $this->assertGreaterThan(0, $result['models_synced']);
    }
}
```

## Configuration Best Practices

### Security
- Never commit API keys to version control
- Use environment variables for all sensitive data
- Mask credentials in logs and error messages
- Validate configuration before using

### Performance
- Use configuration caching in production
- Set appropriate timeouts for your use case
- Configure retry attempts based on provider reliability
- Enable rate limiting to avoid API limits

### Monitoring
- Enable logging for debugging and monitoring
- Set appropriate log levels for different environments
- Include relevant context in logs without exposing credentials
- Monitor configuration changes

### Testing
- Use mock providers for unit tests
- Use E2E credentials for integration tests
- Test configuration validation
- Test error scenarios with invalid configurations

## Configuration Validation

Each driver validates its configuration on initialization:

```php
protected function validateConfiguration(array $config): void
{
    $required = ['api_key'];
    
    foreach ($required as $key) {
        if (empty($config[$key])) {
            throw new \InvalidArgumentException("Missing required config: {$key}");
        }
    }
    
    // Validate API key format
    if (!$this->isValidApiKeyFormat($config['api_key'])) {
        throw new InvalidCredentialsException('Invalid API key format');
    }
    
    // Validate timeout values
    if (isset($config['timeout']) && $config['timeout'] < 1) {
        throw new \InvalidArgumentException('Timeout must be at least 1 second');
    }
}
```

## Benefits of This Approach

### Production Benefits
- **Standard Laravel patterns**: Uses familiar configuration system
- **Environment-specific**: Different settings per environment
- **Secure**: Credentials managed through environment variables
- **Cacheable**: Configuration can be cached for performance

### Development Benefits
- **Consistent behavior**: Same configuration system as production
- **Real testing**: E2E tests use actual APIs
- **Flexible**: Easy to add new providers and settings
- **Secure**: Real credentials never committed to version control

### Testing Benefits
- **Skippable**: Tests skip when credentials unavailable
- **Realistic**: Tests use real API behavior
- **Isolated**: Each test can override specific settings
- **Maintainable**: Clear separation of concerns

## Related Documentation

- **[Driver Interface](03-Interface.md)**: Understanding the complete interface
- **[E2E Testing Setup](13-E2E-Setup.md)**: Detailed E2E testing configuration
- **[OpenAI Driver](07-OpenAI-Driver.md)**: Example of configuration usage
- **[Creating Custom Drivers](09-Custom-Drivers.md)**: Implementing configuration validation
