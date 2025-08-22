# OpenAI Driver Reference

## Overview

The OpenAI driver serves as the reference implementation for the JTD Laravel AI package. It demonstrates all features of the driver system including trait-based architecture, comprehensive error handling, streaming support, function calling, and model synchronization.

## Architecture

### Main Driver Class
```php
<?php

namespace JTD\LaravelAI\Drivers;

use JTD\LaravelAI\Drivers\AbstractAIProvider;
use JTD\LaravelAI\Drivers\OpenAI\Traits\{
    HandlesApiCommunication,
    HandlesErrors,
    HandlesStreaming,
    HandlesFunctionCalling,
    ValidatesHealth,
    ManagesModels,
    CalculatesCosts,
    IntegratesResponsesAPI
};

class OpenAIDriver extends AbstractAIProvider
{
    use HandlesApiCommunication,
        HandlesErrors,
        HandlesStreaming,
        HandlesFunctionCalling,
        ValidatesHealth,
        ManagesModels,
        CalculatesCosts,
        IntegratesResponsesAPI;

    protected string $name = 'openai';
    protected string $version = '1.0.0';
}
```

### Trait-Based Architecture

#### HandlesApiCommunication
- HTTP client configuration
- Request/response handling
- Authentication management
- Base URL and endpoint management

#### HandlesErrors
- OpenAI-specific error mapping
- Retry logic with exponential backoff
- Rate limit handling
- Quota management

#### ManagesModels
- Model discovery and caching
- **Model synchronization** (new in Phase 1)
- Capability detection
- Model validation

#### CalculatesCosts
- Token-based cost calculation
- Model-specific pricing
- Usage tracking
- Cost optimization

#### ValidatesHealth
- Credential validation
- Connection health checks
- API status monitoring
- Performance metrics

#### HandlesStreaming
- Real-time response streaming
- Chunk processing
- Connection management
- Error handling in streams

#### HandlesFunctionCalling
- Function definition management
- Function call execution
- Response parsing
- Error handling

#### IntegratesResponsesAPI
- Response storage and retrieval
- Conversation management
- Response analysis
- Historical data

## Configuration

### Required Configuration
```php
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
]
```

### Optional Configuration
```php
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
```

## Usage Examples

### Basic Message Sending
```php
use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Models\AIMessage;

// Simple message
$response = AI::provider('openai')->sendMessage(
    AIMessage::user('Explain quantum computing in simple terms')
);

echo $response->content;
```

### Conversation Context
```php
$messages = [
    AIMessage::system('You are a helpful coding assistant'),
    AIMessage::user('How do I create a Laravel controller?'),
    AIMessage::assistant('To create a Laravel controller, use: php artisan make:controller'),
    AIMessage::user('How do I add a method to it?'),
];

$response = AI::provider('openai')->sendMessages($messages);
```

### Streaming Responses
```php
$stream = AI::provider('openai')->sendStreamingMessage(
    AIMessage::user('Write a short story about AI')
);

foreach ($stream as $chunk) {
    echo $chunk->content;
    flush();
}
```

### Function Calling
```php
$functions = [
    [
        'name' => 'get_weather',
        'description' => 'Get current weather for a location',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'location' => [
                    'type' => 'string',
                    'description' => 'City name',
                ],
            ],
            'required' => ['location'],
        ],
    ],
];

$response = AI::provider('openai')->sendMessage(
    AIMessage::user('What\'s the weather in Paris?'),
    ['functions' => $functions]
);
```

### Model Management
```php
// Get available models
$models = AI::provider('openai')->getAvailableModels();

// Sync models from API
$result = AI::provider('openai')->syncModels();

// Check last sync time
$lastSync = AI::provider('openai')->getLastSyncTime();

// Get syncable models (dry run)
$syncable = AI::provider('openai')->getSyncableModels();
```

### Cost Calculation
```php
use JTD\LaravelAI\Models\TokenUsage;

$usage = new TokenUsage(
    promptTokens: 100,
    completionTokens: 50,
    totalTokens: 150
);

$cost = AI::provider('openai')->calculateCost($usage, 'gpt-4');
echo "Cost: $" . number_format($cost, 4);
```

## Supported Models

### GPT-4 Family
- `gpt-4`: Most capable model
- `gpt-4-turbo`: Faster, more efficient
- `gpt-4o`: Optimized for speed and cost
- `gpt-4-vision-preview`: Supports images

### GPT-3.5 Family
- `gpt-3.5-turbo`: Fast and efficient
- `gpt-3.5-turbo-16k`: Extended context

### Capabilities by Model
- **Function Calling**: All GPT-4 and GPT-3.5-turbo models
- **Vision**: GPT-4 vision models
- **Streaming**: All models
- **JSON Mode**: GPT-4 and GPT-3.5-turbo

## Error Handling

### OpenAI-Specific Exceptions
```php
use JTD\LaravelAI\Exceptions\OpenAI\{
    OpenAIException,
    OpenAIRateLimitException,
    OpenAIInvalidCredentialsException,
    OpenAIQuotaExceededException,
    OpenAIServerException
};

try {
    $response = AI::provider('openai')->sendMessage($message);
} catch (OpenAIRateLimitException $e) {
    // Handle rate limiting
    $retryAfter = $e->getRetryAfter();
    sleep($retryAfter);
} catch (OpenAIQuotaExceededException $e) {
    // Handle quota exceeded
    Log::error('OpenAI quota exceeded', ['error' => $e->getMessage()]);
} catch (OpenAIException $e) {
    // Handle other OpenAI errors
    Log::error('OpenAI error', ['error' => $e->getMessage()]);
}
```

### Automatic Retry Logic
The driver automatically retries failed requests with exponential backoff:
- Initial delay: 1 second
- Maximum delay: 30 seconds
- Maximum attempts: 3
- Retries on: Rate limits, server errors, network issues

## Performance Features

### Caching
- Model information cached for 24 hours
- Pricing data cached for 7 days
- Health check results cached for 5 minutes

### Connection Pooling
- Reuses HTTP connections for efficiency
- Configurable timeout and retry settings
- Automatic connection management

### Rate Limiting
- Built-in rate limit awareness
- Automatic backoff on rate limit errors
- Configurable rate limit thresholds

## Monitoring & Observability

### Logging
All operations are logged with appropriate detail levels:
```php
// Info level: Successful operations
Log::info('OpenAI message sent', [
    'model' => 'gpt-4',
    'tokens' => 150,
    'cost' => 0.0045,
]);

// Warning level: Retries and recoverable errors
Log::warning('OpenAI rate limit hit, retrying', [
    'retry_after' => 60,
    'attempt' => 2,
]);

// Error level: Failures
Log::error('OpenAI request failed', [
    'error' => $exception->getMessage(),
    'model' => 'gpt-4',
]);
```

### Events
The driver fires events for major operations:
- `MessageSent`: When messages are sent
- `ResponseGenerated`: When responses are received
- `CostCalculated`: When costs are calculated
- `ModelsSynced`: When models are synchronized

### Health Checks
```php
// Validate credentials
$validation = AI::provider('openai')->validateCredentials();

// Check capabilities
$capabilities = AI::provider('openai')->getCapabilities();

// Get configuration (masked)
$config = AI::provider('openai')->getConfig();
```

## Best Practices

### Configuration
- Always use environment variables for API keys
- Set appropriate timeouts for your use case
- Enable logging for production monitoring
- Configure rate limits based on your plan

### Error Handling
- Always wrap API calls in try-catch blocks
- Handle rate limits gracefully with retries
- Log errors for monitoring and debugging
- Implement fallback strategies for critical operations

### Performance
- Use streaming for long responses
- Cache expensive operations
- Monitor token usage and costs
- Use appropriate models for different tasks

### Security
- Never log API keys or sensitive content
- Validate all inputs before sending to API
- Use organization and project IDs for isolation
- Regularly rotate API keys

## Related Documentation

- **[Driver Interface](03-Interface.md)**: Complete interface specification
- **[Sync System Overview](04-Sync-System.md)**: Model synchronization features
- **[Configuration System](02-Configuration.md)**: Configuration and credentials
- **[Creating Custom Drivers](09-Custom-Drivers.md)**: Using OpenAI as a reference
