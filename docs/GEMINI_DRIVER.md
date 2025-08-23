# Gemini Driver Documentation

## Overview

The Gemini driver provides comprehensive integration with Google's Gemini API, including Chat Completions, streaming responses, function calling, multimodal support, safety settings, model management, and cost tracking. It follows the same architecture patterns as the OpenAI driver for consistency and reliability.

## Features

- ✅ **Chat Completions**: Full support for Gemini's generateContent API
- ✅ **Streaming Responses**: Real-time streaming with Server-Sent Events (SSE)
- ✅ **Function Calling**: Complete function calling with parallel execution
- ✅ **Multimodal Support**: Text + image processing capabilities
- ✅ **Safety Settings**: Configurable content filtering and safety controls
- ✅ **Model Management**: Automatic model synchronization and capability detection
- ✅ **Cost Tracking**: Accurate cost calculation with real-time pricing
- ✅ **Error Handling**: Comprehensive error handling with retry logic
- ✅ **Event System**: Event-driven architecture for monitoring
- ✅ **Security**: Credential masking and validation
- ✅ **Performance**: Optimized for speed and reliability

## Installation & Setup

### 1. Install Dependencies

The Gemini driver uses Laravel's HTTP client and doesn't require additional packages:

```bash
# No additional packages required - uses Laravel HTTP client
```

### 2. Configuration

Add your Gemini configuration to `config/ai.php`:

```php
'providers' => [
    'gemini' => [
        'driver' => 'gemini',
        'api_key' => env('GEMINI_API_KEY'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1'),
        'default_model' => env('GEMINI_DEFAULT_MODEL', 'gemini-2.5-flash'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 30),
        'retry_attempts' => (int) env('GEMINI_RETRY_ATTEMPTS', 3),
        'retry_delay' => (int) env('GEMINI_RETRY_DELAY', 1000),
        'max_retry_delay' => (int) env('GEMINI_MAX_RETRY_DELAY', 30000),
        'logging' => [
            'enabled' => (bool) env('AI_LOGGING_ENABLED', true),
            'channel' => env('AI_LOG_CHANNEL', 'default'),
            'level' => env('AI_LOG_LEVEL', 'info'),
            'include_content' => (bool) env('AI_LOG_INCLUDE_CONTENT', false),
        ],
        'rate_limiting' => [
            'enabled' => (bool) env('AI_RATE_LIMITING_ENABLED', true),
            'requests_per_minute' => (int) env('GEMINI_RPM_LIMIT', 60),
            'tokens_per_minute' => (int) env('GEMINI_TPM_LIMIT', 32000),
        ],
        'safety_settings' => [
            'HARM_CATEGORY_HARASSMENT' => env('GEMINI_SAFETY_HARASSMENT', 'BLOCK_MEDIUM_AND_ABOVE'),
            'HARM_CATEGORY_HATE_SPEECH' => env('GEMINI_SAFETY_HATE_SPEECH', 'BLOCK_MEDIUM_AND_ABOVE'),
            'HARM_CATEGORY_SEXUALLY_EXPLICIT' => env('GEMINI_SAFETY_SEXUAL', 'BLOCK_MEDIUM_AND_ABOVE'),
            'HARM_CATEGORY_DANGEROUS_CONTENT' => env('GEMINI_SAFETY_DANGEROUS', 'BLOCK_MEDIUM_AND_ABOVE'),
        ],
    ],
],
```

### 3. Environment Variables

Set your Gemini credentials in `.env`:

```env
GEMINI_API_KEY=your-gemini-api-key
GEMINI_DEFAULT_MODEL=gemini-2.5-flash
```

## Core Methods

### sendMessage()

Send a single message to Gemini's generateContent API.

```php
public function sendMessage(AIMessage $message, array $options = []): AIResponse
```

**Parameters:**
- `$message` (AIMessage): The message to send
- `$options` (array): Optional parameters

**Options:**
- `model` (string): Model to use (default: 'gemini-2.5-flash')
- `temperature` (float): Sampling temperature (0.0-2.0)
- `max_tokens` (int): Maximum tokens in response (maxOutputTokens)
- `top_p` (float): Nucleus sampling parameter
- `top_k` (int): Top-k sampling parameter
- `safety_settings` (array): Content filtering settings
- `functions` (array): Function definitions for function calling

**Example:**
```php
use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Models\AIMessage;

$response = AI::provider('gemini')->sendMessage(
    AIMessage::user('Explain Laravel in one sentence'),
    [
        'model' => 'gemini-2.5-flash',
        'temperature' => 0.7,
        'max_tokens' => 100,
    ]
);

echo $response->content;
echo $response->tokenUsage->totalTokens;
echo $response->cost;
```

### sendMessages()

Send multiple messages in a conversation context.

```php
public function sendMessages(array $messages, array $options = []): AIResponse
```

**Parameters:**
- `$messages` (array): Array of AIMessage objects
- `$options` (array): Optional parameters (same as sendMessage)

**Example:**
```php
$messages = [
    AIMessage::system('You are a helpful Laravel expert.'),
    AIMessage::user('How do I create a middleware?'),
    AIMessage::assistant('To create middleware in Laravel, use: php artisan make:middleware'),
    AIMessage::user('Can you show me an example?'),
];

$response = AI::provider('gemini')->sendMessages($messages, [
    'model' => 'gemini-2.5-pro',
    'temperature' => 0.3,
]);
```

### sendStreamingMessage()

Send a message with streaming response for real-time output.

```php
public function sendStreamingMessage($message, array $options = []): \Generator
```

**Parameters:**
- `$message` (AIMessage): The message to send
- `$options` (array): Optional parameters (same as sendMessage)

**Returns:** Generator yielding AIResponse chunks

**Example:**
```php
$stream = AI::provider('gemini')->sendStreamingMessage(
    AIMessage::user('Write a short story about Laravel'),
    ['model' => 'gemini-2.5-flash']
);

foreach ($stream as $chunk) {
    echo $chunk->content; // Print each chunk as it arrives
    
    if ($chunk->finishReason === 'stop') {
        echo "\n\nStream complete!";
        echo "\nTotal cost: $" . number_format($chunk->cost, 4);
        break;
    }
}
```

## Multimodal Support

The Gemini driver supports text + image processing for multimodal AI interactions.

### Image Processing

```php
use JTD\LaravelAI\Models\AIMessage;

// Process image with text
$message = AIMessage::user('What do you see in this image?')
    ->withImage('/path/to/image.jpg');

$response = AI::provider('gemini')->sendMessage($message, [
    'model' => 'gemini-2.5-flash', // Supports vision
    'max_tokens' => 200,
]);

echo $response->content; // Description of the image
```

### Multiple Images

```php
// Process multiple images
$message = AIMessage::user('Compare these two images')
    ->withImage('/path/to/image1.jpg')
    ->withImage('/path/to/image2.jpg');

$response = AI::provider('gemini')->sendMessage($message, [
    'model' => 'gemini-2.5-flash',
    'max_tokens' => 300,
]);
```

### Image Formats

Supported image formats:
- JPEG (.jpg, .jpeg)
- PNG (.png)
- WebP (.webp)
- HEIC (.heic)
- HEIF (.heif)

```php
// Different image sources
$message = AIMessage::user('Analyze this image')
    ->withImage('https://example.com/image.jpg')     // URL
    ->withImage('/storage/uploads/image.png')        // Local file
    ->withImage('data:image/jpeg;base64,/9j/4AA...'); // Base64
```

## Safety Settings

Gemini provides comprehensive content filtering and safety controls.

### Default Safety Settings

```php
// Default safety configuration (in config/ai.php)
'safety_settings' => [
    'HARM_CATEGORY_HARASSMENT' => 'BLOCK_MEDIUM_AND_ABOVE',
    'HARM_CATEGORY_HATE_SPEECH' => 'BLOCK_MEDIUM_AND_ABOVE',
    'HARM_CATEGORY_SEXUALLY_EXPLICIT' => 'BLOCK_MEDIUM_AND_ABOVE',
    'HARM_CATEGORY_DANGEROUS_CONTENT' => 'BLOCK_MEDIUM_AND_ABOVE',
],
```

### Custom Safety Settings

```php
// Override safety settings per request
$response = AI::provider('gemini')->sendMessage(
    AIMessage::user('Tell me about online safety'),
    [
        'model' => 'gemini-2.5-flash',
        'safety_settings' => [
            'HARM_CATEGORY_HARASSMENT' => 'BLOCK_ONLY_HIGH',
            'HARM_CATEGORY_HATE_SPEECH' => 'BLOCK_ONLY_HIGH',
            'HARM_CATEGORY_SEXUALLY_EXPLICIT' => 'BLOCK_MEDIUM_AND_ABOVE',
            'HARM_CATEGORY_DANGEROUS_CONTENT' => 'BLOCK_MEDIUM_AND_ABOVE',
        ],
    ]
);
```

### Safety Levels

Available safety levels:
- `BLOCK_NONE`: No blocking
- `BLOCK_ONLY_HIGH`: Block only high-probability harmful content
- `BLOCK_MEDIUM_AND_ABOVE`: Block medium and high-probability harmful content
- `BLOCK_LOW_AND_ABOVE`: Block low, medium, and high-probability harmful content

### Handling Safety Blocks

```php
try {
    $response = AI::provider('gemini')->sendMessage($message);
} catch (\JTD\LaravelAI\Exceptions\Gemini\GeminiSafetyException $e) {
    $safetyRatings = $e->getSafetyRatings();
    $blockedCategory = $e->getBlockedCategory();

    Log::warning('Content blocked by safety filters', [
        'category' => $blockedCategory,
        'ratings' => $safetyRatings,
    ]);

    // Provide fallback response
    $response = $this->getFallbackResponse();
}
```

## Model Management

### getAvailableModels()

Retrieve available models from Gemini with capabilities and pricing information.

```php
public function getAvailableModels(): Collection
```

**Returns:** Collection of model information

**Example:**
```php
$models = AI::provider('gemini')->getAvailableModels();

foreach ($models as $model) {
    echo "Model: {$model['id']}\n";
    echo "Context Length: {$model['context_length']}\n";
    echo "Capabilities: " . implode(', ', $model['capabilities']) . "\n";
    echo "Input Cost: $" . number_format($model['pricing']['input'], 6) . " per token\n";
    echo "Output Cost: $" . number_format($model['pricing']['output'], 6) . " per token\n\n";
}
```

### syncModels()

Synchronize models from Gemini API to local database.

```php
public function syncModels(): array
```

**Returns:** Array with sync results

**Example:**
```php
$result = AI::provider('gemini')->syncModels();

echo "Synced: {$result['synced']} models\n";
echo "Updated: {$result['updated']} models\n";
echo "Errors: {$result['errors']} models\n";

foreach ($result['model_details'] as $model) {
    echo "- {$model['id']}: {$model['status']}\n";
}
```

### Current Models

Available Gemini models:
- `gemini-2.5-pro`: Most capable model for complex tasks
- `gemini-2.5-flash`: Fast, efficient model for most tasks
- `gemini-2.0-flash`: Previous generation fast model
- `gemini-2.5-flash-lite`: Lightweight model for simple tasks

## Cost Calculation

### calculateCost()

Calculate cost for token usage with specific model.

```php
public function calculateCost(TokenUsage $usage, string $modelId): float
```

**Parameters:**
- `$usage` (TokenUsage): Token usage information
- `$modelId` (string): Model identifier

**Returns:** Cost in USD

**Example:**
```php
use JTD\LaravelAI\Models\TokenUsage;

$usage = new TokenUsage(100, 50); // 100 input, 50 output tokens
$cost = AI::provider('gemini')->calculateCost($usage, 'gemini-2.5-flash');

echo "Cost: $" . number_format($cost, 4);
```

### estimateTokens()

Estimate token count for messages before sending.

```php
public function estimateTokens(array $messages, string $model = 'gemini-2.5-flash'): TokenUsage
```

**Parameters:**
- `$messages` (array): Array of AIMessage objects
- `$model` (string): Model to estimate for

**Returns:** TokenUsage with estimated counts

**Example:**
```php
$messages = [
    AIMessage::user('This is a test message for token estimation'),
];

$estimated = AI::provider('gemini')->estimateTokens($messages, 'gemini-2.5-flash');
$estimatedCost = AI::provider('gemini')->calculateCost($estimated, 'gemini-2.5-flash');

echo "Estimated tokens: {$estimated->totalTokens}\n";
echo "Estimated cost: $" . number_format($estimatedCost, 4) . "\n";
```

## Function Calling

The Gemini driver supports comprehensive function calling with parallel execution and automatic result handling.

### Basic Function Calling

```php
// Define functions
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
                'unit' => [
                    'type' => 'string',
                    'enum' => ['celsius', 'fahrenheit'],
                    'description' => 'Temperature unit',
                ],
            ],
            'required' => ['location'],
        ],
    ],
];

// Send message with function definitions
$response = AI::provider('gemini')->sendMessage(
    AIMessage::user('What\'s the weather in Paris?'),
    [
        'model' => 'gemini-2.5-flash',
        'functions' => $functions,
        'max_tokens' => 100,
    ]
);

// Handle function calls
if ($response->hasToolCalls()) {
    $messages = [
        AIMessage::user('What\'s the weather in Paris?'),
        AIMessage::assistant('', null, null, null, null, null, $response->toolCalls),
    ];

    foreach ($response->toolCalls as $toolCall) {
        $functionName = $toolCall->function->name;
        $arguments = json_decode($toolCall->function->arguments, true);

        // Execute function
        $result = match ($functionName) {
            'get_weather' => getWeatherData($arguments['location'], $arguments['unit'] ?? 'celsius'),
            default => ['error' => 'Unknown function'],
        };

        // Add function result to conversation
        $messages[] = AIMessage::tool($toolCall->id, json_encode($result));
    }

    // Get final response with function results
    $finalResponse = AI::provider('gemini')->sendMessages($messages);
    echo $finalResponse->content;
}
```

### Parallel Function Calling

```php
$response = AI::provider('gemini')->sendMessage(
    AIMessage::user('Get weather for London, Paris, and Tokyo'),
    [
        'model' => 'gemini-2.5-flash',
        'functions' => [$weatherFunction],
        'parallel_tool_calls' => true, // Enable parallel calls
    ]
);

if ($response->hasToolCalls()) {
    $messages = [
        AIMessage::user('Get weather for London, Paris, and Tokyo'),
        AIMessage::assistant('', null, null, null, null, null, $response->toolCalls),
    ];

    // Process all function calls
    foreach ($response->toolCalls as $toolCall) {
        $args = json_decode($toolCall->function->arguments, true);
        $weather = getWeatherData($args['location']);
        $messages[] = AIMessage::tool($toolCall->id, json_encode($weather));
    }

    $finalResponse = AI::provider('gemini')->sendMessages($messages);
    echo $finalResponse->content; // AI will summarize all weather data
}
```

## Health Check & Validation

### validateCredentials()

Validate Gemini API credentials and configuration.

```php
public function validateCredentials(): array
```

**Returns:** Array with validation results

**Example:**
```php
$validation = AI::provider('gemini')->validateCredentials();

if ($validation['valid']) {
    echo "Credentials are valid!\n";
    echo "Available models: {$validation['available_models']}\n";
    echo "API version: {$validation['api_version']}\n";
} else {
    echo "Credential validation failed:\n";
    foreach ($validation['errors'] as $error) {
        echo "- {$error}\n";
    }
}
```

### getConfig()

Get driver configuration with sensitive data masked.

```php
public function getConfig(): array
```

**Returns:** Configuration array with masked credentials

**Example:**
```php
$config = AI::provider('gemini')->getConfig();

echo "API Key: {$config['api_key']}\n";        // ***key123 (masked)
echo "Base URL: {$config['base_url']}\n";
echo "Default Model: {$config['default_model']}\n";
echo "Timeout: {$config['timeout']}s\n";
```

### getCapabilities()

Get provider capabilities and features.

```php
public function getCapabilities(): array
```

**Returns:** Array of supported capabilities

**Example:**
```php
$capabilities = AI::provider('gemini')->getCapabilities();

echo "Chat: " . ($capabilities['chat'] ? 'Yes' : 'No') . "\n";
echo "Streaming: " . ($capabilities['streaming'] ? 'Yes' : 'No') . "\n";
echo "Function calling: " . ($capabilities['function_calling'] ? 'Yes' : 'No') . "\n";
echo "Vision: " . ($capabilities['vision'] ? 'Yes' : 'No') . "\n";
echo "Safety settings: " . ($capabilities['safety_settings'] ? 'Yes' : 'No') . "\n";
echo "Max context: {$capabilities['max_context_length']} tokens\n";
```

## Error Handling

The Gemini driver provides comprehensive error handling with specific exception types and retry logic.

### Exception Types

```php
use JTD\LaravelAI\Exceptions\Gemini\GeminiException;
use JTD\LaravelAI\Exceptions\Gemini\GeminiRateLimitException;
use JTD\LaravelAI\Exceptions\Gemini\GeminiInvalidCredentialsException;
use JTD\LaravelAI\Exceptions\Gemini\GeminiQuotaExceededException;
use JTD\LaravelAI\Exceptions\Gemini\GeminiSafetyException;
use JTD\LaravelAI\Exceptions\Gemini\GeminiServerException;

try {
    $response = AI::provider('gemini')->sendMessage(
        AIMessage::user('Hello, world!')
    );
} catch (GeminiRateLimitException $e) {
    // Rate limit exceeded
    $retryAfter = $e->getRetryAfter(); // Seconds to wait
    $limitType = $e->getLimitType();   // 'requests' or 'tokens'

    Log::warning("Rate limited", [
        'retry_after' => $retryAfter,
        'limit_type' => $limitType,
    ]);

} catch (GeminiSafetyException $e) {
    // Content blocked by safety filters
    $safetyRatings = $e->getSafetyRatings();
    $blockedCategory = $e->getBlockedCategory();

    Log::warning("Content blocked", [
        'category' => $blockedCategory,
        'ratings' => $safetyRatings,
    ]);

} catch (GeminiQuotaExceededException $e) {
    // Quota/billing issues
    $quotaType = $e->getQuotaType();
    $currentUsage = $e->getCurrentUsage();

    Log::error("Quota exceeded", [
        'quota_type' => $quotaType,
        'current_usage' => $currentUsage,
    ]);

} catch (GeminiInvalidCredentialsException $e) {
    // Authentication/authorization errors
    Log::error("Invalid credentials", [
        'message' => $e->getMessage(),
    ]);

} catch (GeminiServerException $e) {
    // Server errors (500, 503, etc.) - automatically retried
    $isRetryable = $e->isRetryable();

    Log::error("Server error", [
        'retryable' => $isRetryable,
        'message' => $e->getMessage(),
    ]);

} catch (GeminiException $e) {
    // Generic Gemini errors
    $errorType = $e->getGeminiErrorType();
    $details = $e->getDetails();

    Log::error("Gemini error", [
        'type' => $errorType,
        'details' => $details,
    ]);
}
```

## Best Practices

### Security

1. **Never log sensitive content**:
```php
'logging' => [
    'include_content' => false,     // Never log user messages
    'include_responses' => false,   // Never log AI responses
],
```

2. **Use environment variables for credentials**:
```php
// Never hardcode API keys
'api_key' => env('GEMINI_API_KEY'), // ✅ Good
'api_key' => 'hardcoded-key',       // ❌ Bad
```

3. **Configure appropriate safety settings**:
```php
// Use appropriate safety levels for your use case
'safety_settings' => [
    'HARM_CATEGORY_HARASSMENT' => 'BLOCK_MEDIUM_AND_ABOVE',
    'HARM_CATEGORY_HATE_SPEECH' => 'BLOCK_MEDIUM_AND_ABOVE',
    'HARM_CATEGORY_SEXUALLY_EXPLICIT' => 'BLOCK_MEDIUM_AND_ABOVE',
    'HARM_CATEGORY_DANGEROUS_CONTENT' => 'BLOCK_MEDIUM_AND_ABOVE',
],
```

### Cost Management

1. **Monitor costs in real-time**:
```php
Event::listen(CostCalculated::class, function (CostCalculated $event) {
    if ($event->cost > 0.05) { // Alert on expensive requests
        Log::warning('High cost AI request', ['cost' => $event->cost]);
    }
});
```

2. **Use appropriate models**:
```php
// Use efficient models for simple tasks
$simpleResponse = AI::provider('gemini')->sendMessage(
    AIMessage::user('Summarize this in one word: ' . $text),
    ['model' => 'gemini-2.5-flash'] // Fast and cost-effective
);

// Use powerful models only when needed
$complexResponse = AI::provider('gemini')->sendMessage(
    AIMessage::user('Analyze this complex data: ' . $data),
    ['model' => 'gemini-2.5-pro'] // More capable but expensive
);
```

3. **Estimate costs before sending**:
```php
$estimated = AI::provider('gemini')->estimateTokens($messages, 'gemini-2.5-flash');
$estimatedCost = AI::provider('gemini')->calculateCost($estimated, 'gemini-2.5-flash');

if ($estimatedCost > 0.50) {
    // Ask for user confirmation or use cheaper model
    throw new CostTooHighException("Estimated cost: $" . number_format($estimatedCost, 2));
}
```

### Performance

1. **Use streaming for long responses**:
```php
// For long-form content, use streaming
$stream = AI::provider('gemini')->sendStreamingMessage(
    AIMessage::user('Write a detailed essay about Laravel'),
    ['model' => 'gemini-2.5-flash']
);
```

2. **Implement proper error handling**:
```php
// Always handle errors gracefully
try {
    $response = AI::provider('gemini')->sendMessage($message);
} catch (GeminiRateLimitException $e) {
    // Implement backoff strategy
    sleep($e->getRetryAfter());
    $response = AI::provider('gemini')->sendMessage($message);
} catch (GeminiSafetyException $e) {
    // Handle safety blocks gracefully
    Log::warning('Content blocked by safety filters');
    $response = $this->getFallbackResponse();
} catch (GeminiException $e) {
    // Log error and provide fallback
    Log::error('AI request failed', ['error' => $e->getMessage()]);
    $response = $this->getFallbackResponse();
}
```

3. **Optimize multimodal requests**:
```php
// Resize images before sending to reduce costs
$optimizedImage = $this->resizeImage('/path/to/large-image.jpg', 1024, 1024);
$message = AIMessage::user('Analyze this image')->withImage($optimizedImage);
```

## Troubleshooting

### Common Issues

1. **Authentication Errors**:
```bash
# Check API key format
echo $GEMINI_API_KEY

# Validate credentials
php artisan ai:validate-credentials gemini
```

2. **Safety Blocks**:
```php
// Check safety ratings in response
if ($response->metadata['safety_ratings'] ?? false) {
    foreach ($response->metadata['safety_ratings'] as $rating) {
        echo "Category: {$rating['category']}, Probability: {$rating['probability']}\n";
    }
}
```

3. **High Costs**:
```php
// Monitor token usage
$response = AI::provider('gemini')->sendMessage($message);
Log::info('Token usage', [
    'input_tokens' => $response->tokenUsage->inputTokens,
    'output_tokens' => $response->tokenUsage->outputTokens,
    'cost' => $response->cost,
]);
```

### Debug Mode

Enable debug logging for troubleshooting:

```php
'logging' => [
    'enabled' => true,
    'level' => 'debug',
    'include_content' => true,  // Only in development
    'include_responses' => true, // Only in development
],
```

## Conclusion

The Gemini driver provides a comprehensive, production-ready integration with Google's Gemini API. It includes all the features needed for enterprise applications: multimodal support, safety settings, robust error handling, cost tracking, performance optimization, and comprehensive monitoring.

The driver follows the same patterns as the OpenAI driver, ensuring consistency across your AI integrations while providing Gemini-specific features like advanced safety controls and multimodal capabilities.

For additional examples and advanced usage patterns, see the [Driver System Documentation](Driver%20System/README.md) and [Testing Strategy](TESTING_STRATEGY.md).
