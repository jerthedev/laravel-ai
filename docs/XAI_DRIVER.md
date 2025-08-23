# xAI Driver Documentation

## Overview

The xAI driver provides comprehensive integration with xAI's Grok API, including Chat Completions, streaming responses, model management, and cost tracking. It supports all Grok model variants including Grok 3, Grok 4, and vision models, following the established driver architecture patterns.

## Features

- ✅ **Chat Completions**: Full support for xAI's Chat Completions API
- ✅ **Streaming Responses**: Real-time streaming with chunk processing
- ✅ **Grok Models**: Support for all Grok variants (3, 4, mini, fast, vision)
- ✅ **Model Management**: Automatic model synchronization and capability detection
- ✅ **Cost Tracking**: Accurate cost calculation with real-time pricing
- ✅ **Error Handling**: Comprehensive error handling with retry logic
- ✅ **Event System**: Event-driven architecture for monitoring
- ✅ **Security**: Credential masking and validation
- ✅ **Performance**: Optimized for speed and reliability

## Installation & Setup

### 1. Configuration

Add your xAI configuration to `config/ai.php`:

```php
'providers' => [
    'xai' => [
        'driver' => 'xai',
        'api_key' => env('XAI_API_KEY'),
        'base_url' => env('XAI_BASE_URL', 'https://api.x.ai/v1'),
        'timeout' => (int) env('XAI_TIMEOUT', 30),
        'retry_attempts' => (int) env('XAI_RETRY_ATTEMPTS', 3),
        'retry_delay' => (int) env('XAI_RETRY_DELAY', 1000),
        'max_retry_delay' => (int) env('XAI_MAX_RETRY_DELAY', 30000),
        'default_model' => env('XAI_DEFAULT_MODEL', 'grok-3-mini'),
        'logging' => [
            'enabled' => (bool) env('AI_LOGGING_ENABLED', true),
            'channel' => env('AI_LOG_CHANNEL', 'default'),
            'level' => env('AI_LOG_LEVEL', 'info'),
            'include_content' => (bool) env('AI_LOG_INCLUDE_CONTENT', false),
        ],
        'rate_limiting' => [
            'enabled' => (bool) env('AI_RATE_LIMITING_ENABLED', true),
            'requests_per_minute' => (int) env('XAI_RPM_LIMIT', 1000),
            'tokens_per_minute' => (int) env('XAI_TPM_LIMIT', 50000),
        ],
    ],
],
```

### 2. Environment Variables

Set your xAI credentials in `.env`:

```env
XAI_API_KEY=xai-your-xai-api-key
XAI_DEFAULT_MODEL=grok-3-mini  # Optional
```

## Core Methods

### sendMessage()

Send a single message to xAI's Chat Completions API.

```php
public function sendMessage(AIMessage $message, array $options = []): AIResponse
```

**Parameters:**
- `$message` (AIMessage): The message to send
- `$options` (array): Optional parameters

**Options:**
- `model` (string): Model to use (default: 'grok-3-mini')
- `temperature` (float): Sampling temperature (0.0-2.0)
- `max_tokens` (int): Maximum tokens in response
- `top_p` (float): Nucleus sampling parameter
- `frequency_penalty` (float): Frequency penalty (-2.0 to 2.0)
- `presence_penalty` (float): Presence penalty (-2.0 to 2.0)
- `stop` (array|string): Stop sequences
- `stream` (bool): Enable streaming responses

**Example:**
```php
use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Models\AIMessage;

$response = AI::provider('xai')->sendMessage(
    AIMessage::user('Explain quantum computing in simple terms'),
    [
        'model' => 'grok-3',
        'temperature' => 0.7,
        'max_tokens' => 500,
    ]
);

echo $response->content;
```

### sendStreamingMessage()

Send a message with streaming response.

```php
public function sendStreamingMessage(AIMessage $message, array $options = []): \Generator
```

**Example:**
```php
foreach (AI::provider('xai')->sendStreamingMessage($message) as $chunk) {
    echo $chunk->content;
}
```

### calculateCost()

Calculate the cost for a message or token usage.

```php
public function calculateCost($input, string $model = null): array
```

**Parameters:**
- `$input` (string|AIMessage|TokenUsage): Input to calculate cost for
- `$model` (string): Model to use for pricing

**Example:**
```php
$cost = AI::provider('xai')->calculateCost('Hello world', 'grok-3-mini');
// Returns: ['total_cost' => 0.000001, 'input_cost' => 0.000001, ...]
```

## Available Models

The xAI driver supports all current Grok models:

### Grok 3 Series
- **grok-3**: Latest Grok 3 model with enhanced capabilities
- **grok-3-fast**: Optimized for speed with good performance
- **grok-3-mini**: Lightweight model for cost-effective usage
- **grok-3-mini-fast**: Fastest variant for real-time applications

### Grok 4 Series
- **grok-4-0709**: Advanced Grok 4 model (July 2024)
- **grok-4-0709-eu**: EU region variant of Grok 4

### Grok 2 Series (Legacy)
- **grok-2-1212**: Grok 2 December 2024 variant
- **grok-2-vision-1212**: Vision-enabled Grok 2 model
- **grok-2-image-1212**: Image processing variant

## Model Capabilities

```php
// Check model capabilities
$capabilities = AI::provider('xai')->getCapabilities();

// Available capabilities:
// - chat_completions: true
// - streaming: true
// - function_calling: false (not yet supported by xAI)
// - vision: true (for vision models)
// - image_generation: false
// - embeddings: false
```

## Pricing Information

Current xAI pricing (as of January 2025):

| Model | Input (per 1M tokens) | Output (per 1M tokens) |
|-------|----------------------|------------------------|
| grok-3 | $3.00 | $12.00 |
| grok-3-fast | $2.00 | $8.00 |
| grok-3-mini | $0.50 | $2.00 |
| grok-3-mini-fast | $0.30 | $1.00 |
| grok-4-0709 | $5.00 | $20.00 |
| grok-4-0709-eu | $5.00 | $20.00 |
| grok-2-vision-1212 | $3.00 | $15.00 |

## Error Handling

The xAI driver provides comprehensive error handling:

```php
use JTD\LaravelAI\Exceptions\XAI\XAIException;
use JTD\LaravelAI\Exceptions\XAI\XAIInvalidCredentialsException;
use JTD\LaravelAI\Exceptions\XAI\XAIQuotaExceededException;
use JTD\LaravelAI\Exceptions\XAI\XAIRateLimitException;

try {
    $response = AI::provider('xai')->sendMessage($message);
} catch (XAIInvalidCredentialsException $e) {
    // Handle invalid API key
} catch (XAIQuotaExceededException $e) {
    // Handle quota exceeded
} catch (XAIRateLimitException $e) {
    // Handle rate limiting
} catch (XAIException $e) {
    // Handle other xAI errors
}
```

## Health Checks

Monitor xAI API health and connectivity:

```php
$health = AI::provider('xai')->healthCheck();

// Returns:
// [
//     'status' => 'healthy',
//     'response_time_ms' => 1250.5,
//     'checks' => [
//         'credentials' => ['status' => 'pass'],
//         'models' => ['status' => 'pass'],
//         'completions' => ['status' => 'pass']
//     ]
// ]
```

## Model Synchronization

Sync available models from xAI API:

```php
// Sync models for xAI provider
php artisan ai:sync-models xai

// Sync all providers (including xAI)
php artisan ai:sync-models
```

## Advanced Usage

### Custom Configuration

```php
use JTD\LaravelAI\Drivers\XAI\XAIDriver;

$driver = new XAIDriver([
    'api_key' => 'your-api-key',
    'base_url' => 'https://api.x.ai/v1',
    'timeout' => 60,
    'retry_attempts' => 5,
    'default_model' => 'grok-3',
]);
```

### Conversation Context

```php
$messages = [
    AIMessage::system('You are a helpful AI assistant specialized in technology.'),
    AIMessage::user('What is machine learning?'),
    AIMessage::assistant('Machine learning is a subset of artificial intelligence...'),
    AIMessage::user('Can you give me a practical example?'),
];

$response = AI::provider('xai')->sendMessage($messages);
```

### Token Estimation

```php
// Estimate tokens for a message
$tokens = AI::provider('xai')->estimateTokens('Hello world');
// Returns: 2

// Estimate for conversation
$tokens = AI::provider('xai')->estimateTokens($messages);
// Returns: 45
```

## Best Practices

1. **Model Selection**: Use `grok-3-mini` for cost-effective general tasks, `grok-3` for complex reasoning
2. **Token Management**: Monitor token usage to control costs
3. **Error Handling**: Always implement proper exception handling
4. **Rate Limiting**: Respect API rate limits to avoid throttling
5. **Streaming**: Use streaming for long responses to improve user experience
6. **Health Monitoring**: Regular health checks for production systems

## Troubleshooting

### Common Issues

**Invalid API Key**
```
XAIInvalidCredentialsException: Invalid API key provided
```
- Verify your API key in `.env`
- Ensure the key starts with `xai-`

**Model Not Found**
```
XAIException: Model 'grok-beta' not found
```
- Use `php artisan ai:sync-models xai` to update available models
- Check available models with `AI::provider('xai')->getAvailableModels()`

**Rate Limiting**
```
XAIRateLimitException: Rate limit exceeded
```
- Implement exponential backoff
- Reduce request frequency
- Consider upgrading your xAI plan

## Testing

The xAI driver includes comprehensive test coverage:

```bash
# Run unit tests
vendor/bin/phpunit tests/Unit/Drivers/XAIDriverTest.php

# Run E2E tests (requires real API key)
vendor/bin/phpunit tests/E2E/Drivers/XAI/
```

## Support

For xAI-specific issues:
- [xAI API Documentation](https://docs.x.ai/)
- [xAI Support](https://x.ai/support)

For driver issues:
- Check the [Driver System Documentation](Driver%20System/README.md)
- Review [Troubleshooting Guide](Driver%20System/16-Troubleshooting.md)
