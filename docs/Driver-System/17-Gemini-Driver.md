# Gemini Driver Implementation

The Gemini driver provides comprehensive integration with Google's Gemini AI models, including advanced safety settings, multimodal support, and cost calculation.

## Overview

The Gemini driver (`JTD\LaravelAI\Drivers\GeminiDriver`) implements the complete AIProviderInterface with Google-specific optimizations and features.

### Key Features

- **Safety Settings**: Configurable content filtering and safety controls
- **Multimodal Support**: Text and image processing capabilities
- **Cost Calculation**: Accurate token-based pricing
- **Model Synchronization**: Auto-discovery of available models
- **Streaming Support**: Real-time response streaming
- **Function Calling**: Tool integration capabilities

## Configuration

### Basic Configuration

```php
// config/ai.php
'providers' => [
    'gemini' => [
        'driver' => 'gemini',
        'api_key' => env('AI_GEMINI_API_KEY'),
        'base_url' => env('AI_GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1'),
        'default_model' => env('AI_GEMINI_DEFAULT_MODEL', 'gemini-pro'),
        'timeout' => env('AI_GEMINI_TIMEOUT', 30),
        'max_retries' => env('AI_GEMINI_MAX_RETRIES', 3),
        'safety_settings' => [
            'HARM_CATEGORY_HARASSMENT' => 'BLOCK_MEDIUM_AND_ABOVE',
            'HARM_CATEGORY_HATE_SPEECH' => 'BLOCK_MEDIUM_AND_ABOVE',
            'HARM_CATEGORY_SEXUALLY_EXPLICIT' => 'BLOCK_MEDIUM_AND_ABOVE',
            'HARM_CATEGORY_DANGEROUS_CONTENT' => 'BLOCK_MEDIUM_AND_ABOVE',
        ],
    ],
],
```

### Environment Variables

```bash
# Required
AI_GEMINI_API_KEY=your_gemini_api_key_here

# Optional
AI_GEMINI_BASE_URL=https://generativelanguage.googleapis.com/v1
AI_GEMINI_DEFAULT_MODEL=gemini-pro
AI_GEMINI_TIMEOUT=30
AI_GEMINI_MAX_RETRIES=3
```

## Safety Settings

### Available Categories

- `HARM_CATEGORY_HARASSMENT` - Harassment content
- `HARM_CATEGORY_HATE_SPEECH` - Hate speech content
- `HARM_CATEGORY_SEXUALLY_EXPLICIT` - Sexually explicit content
- `HARM_CATEGORY_DANGEROUS_CONTENT` - Dangerous content

### Safety Thresholds

- `BLOCK_NONE` - No blocking
- `BLOCK_ONLY_HIGH` - Block only high-risk content
- `BLOCK_MEDIUM_AND_ABOVE` - Block medium and high-risk content (recommended)
- `BLOCK_LOW_AND_ABOVE` - Block low, medium, and high-risk content

### Custom Safety Configuration

```php
use JTD\LaravelAI\Facades\AI;

$response = AI::provider('gemini')
    ->model('gemini-pro')
    ->safetySettings([
        'HARM_CATEGORY_HARASSMENT' => 'BLOCK_ONLY_HIGH',
        'HARM_CATEGORY_DANGEROUS_CONTENT' => 'BLOCK_NONE',
    ])
    ->message('Your message here')
    ->send();
```

## Multimodal Support

### Image Processing

```php
use JTD\LaravelAI\Facades\AI;

$response = AI::provider('gemini')
    ->model('gemini-pro-vision')
    ->message('What do you see in this image?')
    ->attachment('path/to/image.jpg', 'image')
    ->send();
```

### Supported Image Formats

- JPEG
- PNG
- WebP
- HEIC
- HEIF

### Image Size Limits

- Maximum file size: 20MB
- Maximum dimensions: 16,384 x 16,384 pixels

## Model Support

### Available Models

- **gemini-pro** - Text-only model for general use
- **gemini-pro-vision** - Multimodal model with image support
- **gemini-1.5-pro** - Latest generation with enhanced capabilities
- **gemini-1.5-flash** - Faster model for quick responses

### Model Selection

```php
// Automatic model selection based on content
$response = AI::provider('gemini')
    ->message('Hello world')
    ->send();

// Explicit model selection
$response = AI::provider('gemini')
    ->model('gemini-1.5-pro')
    ->message('Complex reasoning task')
    ->send();
```

## Cost Calculation

### Token Counting

The Gemini driver accurately calculates costs based on:

- **Input tokens**: Text and image content
- **Output tokens**: Generated response
- **Model-specific pricing**: Different rates per model

### Cost Tracking

```php
$response = AI::provider('gemini')
    ->message('Hello world')
    ->send();

echo "Input tokens: " . $response->usage['input_tokens'];
echo "Output tokens: " . $response->usage['output_tokens'];
echo "Total cost: $" . $response->cost;
```

## Function Calling

### Defining Functions

```php
use JTD\LaravelAI\Facades\AI;

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

$response = AI::provider('gemini')
    ->functions($functions)
    ->message('What\'s the weather in New York?')
    ->send();
```

## Streaming Support

### Real-time Responses

```php
use JTD\LaravelAI\Facades\AI;

AI::provider('gemini')
    ->stream()
    ->message('Tell me a story')
    ->onChunk(function ($chunk) {
        echo $chunk->content;
    })
    ->send();
```

## Error Handling

### Common Errors

- **Invalid API Key**: Check your API key configuration
- **Safety Filter Triggered**: Content blocked by safety settings
- **Rate Limit Exceeded**: Too many requests per minute
- **Model Not Found**: Invalid model name specified

### Error Response Format

```php
try {
    $response = AI::provider('gemini')->message('Hello')->send();
} catch (\JTD\LaravelAI\Exceptions\SafetyFilterException $e) {
    // Handle safety filter blocking
    echo "Content blocked: " . $e->getMessage();
} catch (\JTD\LaravelAI\Exceptions\RateLimitException $e) {
    // Handle rate limiting
    echo "Rate limited. Retry after: " . $e->getRetryAfter();
}
```

## Model Synchronization

### Automatic Sync

```bash
# Sync all Gemini models
php artisan ai:sync-models gemini

# Sync with cost updates
php artisan ai:sync-models gemini --with-costs
```

### Manual Model Management

```php
use JTD\LaravelAI\Services\ModelSyncService;

$syncService = app(ModelSyncService::class);
$result = $syncService->syncProvider('gemini');

echo "Synced {$result['models_synced']} models";
```

## Performance Optimization

### Connection Pooling

The driver uses HTTP connection pooling for better performance:

```php
// config/ai.php
'providers' => [
    'gemini' => [
        // ... other config
        'http_options' => [
            'pool_size' => 10,
            'keep_alive' => true,
            'timeout' => 30,
        ],
    ],
],
```

### Caching

Enable response caching for repeated requests:

```php
$response = AI::provider('gemini')
    ->cache(300) // Cache for 5 minutes
    ->message('What is AI?')
    ->send();
```

## Testing

### Unit Tests

```php
use JTD\LaravelAI\Tests\TestCase;
use JTD\LaravelAI\Drivers\GeminiDriver;

class GeminiDriverTest extends TestCase
{
    #[Test]
    public function it_sends_messages_successfully(): void
    {
        $driver = new GeminiDriver($this->getTestConfig());
        
        $message = new AIMessage([
            'role' => 'user',
            'content' => 'Hello world',
        ]);
        
        $response = $driver->sendMessage($message);
        
        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertNotEmpty($response->content);
    }
}
```

### E2E Testing

Create `tests/credentials/e2e-credentials.json`:

```json
{
    "gemini": {
        "api_key": "your_test_api_key",
        "base_url": "https://generativelanguage.googleapis.com/v1"
    }
}
```

## Best Practices

### Safety Configuration

1. **Always configure safety settings** for production use
2. **Use BLOCK_MEDIUM_AND_ABOVE** as the default threshold
3. **Test safety settings** with your specific use case
4. **Handle safety exceptions** gracefully in your application

### Model Selection

1. **Use gemini-pro** for text-only tasks
2. **Use gemini-pro-vision** for image analysis
3. **Use gemini-1.5-flash** for quick responses
4. **Use gemini-1.5-pro** for complex reasoning

### Performance

1. **Enable connection pooling** for high-traffic applications
2. **Use streaming** for long responses
3. **Cache responses** when appropriate
4. **Monitor token usage** to optimize costs

### Error Handling

1. **Implement retry logic** for transient errors
2. **Handle safety filter exceptions** appropriately
3. **Monitor rate limits** and implement backoff
4. **Log errors** for debugging and monitoring

## Troubleshooting

### Common Issues

**API Key Issues**
- Verify your API key is correct
- Check that the key has proper permissions
- Ensure the key is not expired

**Safety Filter Blocks**
- Review your safety settings configuration
- Test with different safety thresholds
- Consider content modifications

**Rate Limiting**
- Implement exponential backoff
- Monitor your request rate
- Consider upgrading your quota

**Model Errors**
- Verify model names are correct
- Check model availability in your region
- Ensure proper model permissions

For more troubleshooting help, see [Troubleshooting Guide](16-Troubleshooting.md).
