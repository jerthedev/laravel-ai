# xAI Driver Implementation

The xAI driver provides integration with xAI's Grok models, offering advanced reasoning capabilities and real-time information access.

## Overview

The xAI driver (`JTD\LaravelAI\Drivers\XAIDriver`) implements the complete AIProviderInterface with xAI-specific optimizations and features.

### Key Features

- **Grok Model Support**: Access to xAI's advanced Grok models
- **Real-time Information**: Up-to-date information access
- **Cost Calculation**: Accurate token-based pricing
- **Model Synchronization**: Auto-discovery of available models
- **Streaming Support**: Real-time response streaming
- **Function Calling**: Tool integration capabilities

## Configuration

### Basic Configuration

```php
// config/ai.php
'providers' => [
    'xai' => [
        'driver' => 'xai',
        'api_key' => env('AI_XAI_API_KEY'),
        'base_url' => env('AI_XAI_BASE_URL', 'https://api.x.ai/v1'),
        'default_model' => env('AI_XAI_DEFAULT_MODEL', 'grok-beta'),
        'timeout' => env('AI_XAI_TIMEOUT', 30),
        'max_retries' => env('AI_XAI_MAX_RETRIES', 3),
    ],
],
```

### Environment Variables

```bash
# Required
AI_XAI_API_KEY=your_xai_api_key_here

# Optional
AI_XAI_BASE_URL=https://api.x.ai/v1
AI_XAI_DEFAULT_MODEL=grok-beta
AI_XAI_TIMEOUT=30
AI_XAI_MAX_RETRIES=3
```

## Model Support

### Available Models

- **grok-beta** - Latest Grok model with enhanced capabilities
- **grok-vision-beta** - Multimodal model with image understanding
- **grok-2** - Previous generation Grok model
- **grok-2-mini** - Lightweight version for faster responses

### Model Selection

```php
use JTD\LaravelAI\Facades\AI;

// Use default model
$response = AI::provider('xai')
    ->message('What are the latest developments in AI?')
    ->send();

// Explicit model selection
$response = AI::provider('xai')
    ->model('grok-vision-beta')
    ->message('Analyze this image')
    ->attachment('path/to/image.jpg', 'image')
    ->send();
```

## Real-time Information Access

### Current Events and Information

Grok models have access to real-time information, making them excellent for:

- Current events and news
- Recent developments in technology
- Market information
- Social media trends
- Weather updates

```php
$response = AI::provider('xai')
    ->message('What are the latest news about artificial intelligence today?')
    ->send();
```

## Multimodal Support

### Image Analysis

```php
use JTD\LaravelAI\Facades\AI;

$response = AI::provider('xai')
    ->model('grok-vision-beta')
    ->message('What do you see in this image? Provide detailed analysis.')
    ->attachment('path/to/image.jpg', 'image')
    ->send();
```

### Supported Image Formats

- JPEG
- PNG
- WebP
- GIF (static analysis)

### Image Processing Capabilities

- Object detection and recognition
- Scene understanding
- Text extraction (OCR)
- Visual reasoning
- Chart and graph analysis

## Cost Calculation

### Token-based Pricing

The xAI driver calculates costs based on:

- **Input tokens**: Text and image content
- **Output tokens**: Generated response
- **Model-specific rates**: Different pricing per model

### Cost Tracking

```php
$response = AI::provider('xai')
    ->message('Explain quantum computing')
    ->send();

echo "Input tokens: " . $response->usage['input_tokens'];
echo "Output tokens: " . $response->usage['output_tokens'];
echo "Total cost: $" . $response->cost;
```

## Function Calling

### Tool Integration

```php
use JTD\LaravelAI\Facades\AI;

$functions = [
    [
        'name' => 'search_web',
        'description' => 'Search the web for current information',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Search query',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Number of results',
                    'default' => 5,
                ],
            ],
            'required' => ['query'],
        ],
    ],
];

$response = AI::provider('xai')
    ->functions($functions)
    ->message('What are the latest AI research papers?')
    ->send();
```

## Streaming Support

### Real-time Response Generation

```php
use JTD\LaravelAI\Facades\AI;

AI::provider('xai')
    ->stream()
    ->message('Write a detailed analysis of current market trends')
    ->onChunk(function ($chunk) {
        echo $chunk->content;
        flush();
    })
    ->send();
```

## Advanced Configuration

### Custom Parameters

```php
$response = AI::provider('xai')
    ->model('grok-beta')
    ->temperature(0.7)
    ->maxTokens(2000)
    ->topP(0.9)
    ->message('Your message here')
    ->send();
```

### System Messages

```php
$response = AI::provider('xai')
    ->systemMessage('You are an expert financial analyst with access to real-time market data.')
    ->message('What should I know about today\'s market movements?')
    ->send();
```

## Error Handling

### Common Errors

- **Invalid API Key**: Check your API key configuration
- **Rate Limit Exceeded**: Too many requests per time period
- **Model Not Available**: Requested model is not accessible
- **Content Policy Violation**: Content violates xAI policies

### Error Response Handling

```php
try {
    $response = AI::provider('xai')->message('Hello')->send();
} catch (\JTD\LaravelAI\Exceptions\RateLimitException $e) {
    // Handle rate limiting
    echo "Rate limited. Retry after: " . $e->getRetryAfter();
} catch (\JTD\LaravelAI\Exceptions\ContentPolicyException $e) {
    // Handle content policy violations
    echo "Content policy violation: " . $e->getMessage();
} catch (\JTD\LaravelAI\Exceptions\ModelNotAvailableException $e) {
    // Handle model availability issues
    echo "Model not available: " . $e->getMessage();
}
```

## Model Synchronization

### Automatic Model Discovery

```bash
# Sync all xAI models
php artisan ai:sync-models xai

# Sync with cost information
php artisan ai:sync-models xai --with-costs

# Force refresh all models
php artisan ai:sync-models xai --force
```

### Programmatic Sync

```php
use JTD\LaravelAI\Services\ModelSyncService;

$syncService = app(ModelSyncService::class);
$result = $syncService->syncProvider('xai');

echo "Synced models: " . $result['models_synced'];
echo "Updated costs: " . $result['costs_updated'];
```

## Performance Optimization

### Connection Configuration

```php
// config/ai.php
'providers' => [
    'xai' => [
        // ... other config
        'http_options' => [
            'timeout' => 60, // Longer timeout for complex queries
            'connect_timeout' => 10,
            'pool_size' => 5,
        ],
    ],
],
```

### Response Caching

```php
$response = AI::provider('xai')
    ->cache(600) // Cache for 10 minutes
    ->message('What is the current state of renewable energy?')
    ->send();
```

## Testing

### Unit Testing

```php
use JTD\LaravelAI\Tests\TestCase;
use JTD\LaravelAI\Drivers\XAIDriver;

class XAIDriverTest extends TestCase
{
    #[Test]
    public function it_handles_real_time_queries(): void
    {
        $driver = new XAIDriver($this->getTestConfig());
        
        $message = new AIMessage([
            'role' => 'user',
            'content' => 'What time is it now?',
        ]);
        
        $response = $driver->sendMessage($message);
        
        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertNotEmpty($response->content);
    }
}
```

### E2E Testing Setup

Create `tests/credentials/e2e-credentials.json`:

```json
{
    "xai": {
        "api_key": "your_test_api_key",
        "base_url": "https://api.x.ai/v1"
    }
}
```

Run E2E tests:

```bash
php artisan test tests/E2E/Drivers/XAI --group=e2e
```

## Best Practices

### Model Selection

1. **Use grok-beta** for general reasoning and current information
2. **Use grok-vision-beta** for image analysis tasks
3. **Use grok-2-mini** for quick responses and simple queries
4. **Use grok-2** for complex reasoning without real-time data needs

### Real-time Information

1. **Leverage real-time capabilities** for current events
2. **Be specific** in queries about recent information
3. **Verify information** from multiple sources when critical
4. **Consider data freshness** in your application logic

### Performance

1. **Use appropriate timeouts** for complex queries
2. **Implement caching** for repeated information requests
3. **Monitor token usage** to optimize costs
4. **Use streaming** for long-form content generation

### Error Handling

1. **Implement retry logic** with exponential backoff
2. **Handle rate limits** gracefully
3. **Validate content** before sending to avoid policy violations
4. **Monitor API status** and model availability

## Use Cases

### Ideal Applications

- **News and Current Events**: Real-time information retrieval
- **Market Analysis**: Financial and business intelligence
- **Research Assistance**: Academic and technical research
- **Content Creation**: Articles, reports, and analysis
- **Image Analysis**: Visual content understanding
- **Conversational AI**: Knowledgeable chat applications

### Example Implementations

```php
// News summarization
$news = AI::provider('xai')
    ->message('Summarize today\'s top technology news')
    ->send();

// Market analysis
$analysis = AI::provider('xai')
    ->systemMessage('You are a financial analyst')
    ->message('Analyze current cryptocurrency market trends')
    ->send();

// Image analysis
$imageAnalysis = AI::provider('xai')
    ->model('grok-vision-beta')
    ->message('Analyze this chart and explain the trends')
    ->attachment('chart.png', 'image')
    ->send();
```

## Troubleshooting

### Common Issues

**API Authentication**
- Verify your xAI API key is valid
- Check key permissions and quotas
- Ensure proper environment configuration

**Model Access**
- Confirm model availability in your region
- Check your account's model access permissions
- Verify model names are correct

**Rate Limiting**
- Monitor your request frequency
- Implement proper backoff strategies
- Consider upgrading your plan if needed

**Content Issues**
- Review xAI content policies
- Modify queries that trigger policy violations
- Use appropriate content filtering

For additional help, see [Troubleshooting Guide](16-Troubleshooting.md) or contact xAI support.
