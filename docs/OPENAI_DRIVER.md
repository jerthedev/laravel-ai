# OpenAI Driver Documentation

## Overview

The OpenAI driver provides comprehensive integration with OpenAI's API, including Chat Completions, streaming responses, function calling, model management, and cost tracking. It serves as the reference implementation for the JTD Laravel AI package driver system.

## Features

- ✅ **Chat Completions**: Full support for OpenAI's Chat Completions API
- ✅ **Streaming Responses**: Real-time streaming with chunk processing
- ✅ **Function Calling**: Complete function calling with parallel execution
- ✅ **Model Management**: Automatic model synchronization and capability detection
- ✅ **Cost Tracking**: Accurate cost calculation with real-time pricing
- ✅ **Error Handling**: Comprehensive error handling with retry logic
- ✅ **Event System**: Event-driven architecture for monitoring
- ✅ **Security**: Credential masking and validation
- ✅ **Performance**: Optimized for speed and reliability

## Installation & Setup

### 1. Install Dependencies

The OpenAI driver requires the OpenAI PHP client:

```bash
composer require openai-php/client
```

### 2. Configuration

Add your OpenAI configuration to `config/ai.php`:

```php
'providers' => [
    'openai' => [
        'driver' => 'openai',
        'api_key' => env('OPENAI_API_KEY'),
        'organization' => env('OPENAI_ORGANIZATION'), // Optional
        'project' => env('OPENAI_PROJECT'),           // Optional
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
],
```

### 3. Environment Variables

Set your OpenAI credentials in `.env`:

```env
OPENAI_API_KEY=sk-your-openai-api-key
OPENAI_ORGANIZATION=org-your-organization-id  # Optional
OPENAI_PROJECT=proj_your-project-id           # Optional
```

## Core Methods

### sendMessage()

Send a single message to OpenAI's Chat Completions API.

```php
public function sendMessage(AIMessage $message, array $options = []): AIResponse
```

**Parameters:**
- `$message` (AIMessage): The message to send
- `$options` (array): Optional parameters

**Options:**
- `model` (string): Model to use (default: 'gpt-3.5-turbo')
- `temperature` (float): Sampling temperature (0.0-2.0)
- `max_tokens` (int): Maximum tokens in response
- `top_p` (float): Nucleus sampling parameter
- `frequency_penalty` (float): Frequency penalty (-2.0 to 2.0)
- `presence_penalty` (float): Presence penalty (-2.0 to 2.0)
- `stop` (array|string): Stop sequences
- `tools` (array): Function definitions for function calling
- `tool_choice` (string|object): Tool choice strategy

**Example:**
```php
use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Models\AIMessage;

$response = AI::provider('openai')->sendMessage(
    AIMessage::user('Explain Laravel in one sentence'),
    [
        'model' => 'gpt-4',
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

$response = AI::provider('openai')->sendMessages($messages, [
    'model' => 'gpt-4-turbo',
    'temperature' => 0.3,
]);
```

### sendStreamingMessage()

Send a message with streaming response for real-time output.

```php
public function sendStreamingMessage(AIMessage $message, array $options = []): \Generator
```

**Parameters:**
- `$message` (AIMessage): The message to send
- `$options` (array): Optional parameters (same as sendMessage)

**Returns:** Generator yielding AIResponse chunks

**Example:**
```php
$stream = AI::provider('openai')->sendStreamingMessage(
    AIMessage::user('Write a short story about Laravel'),
    ['model' => 'gpt-4']
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

### sendStreamingMessages()

Send multiple messages with streaming response.

```php
public function sendStreamingMessages(array $messages, array $options = []): \Generator
```

**Example:**
```php
$messages = [
    AIMessage::system('You are a creative writer.'),
    AIMessage::user('Continue this story: "It was a dark and stormy night..."'),
];

$stream = AI::provider('openai')->sendStreamingMessages($messages, [
    'model' => 'gpt-4',
    'temperature' => 0.8,
]);

foreach ($stream as $chunk) {
    echo $chunk->content;
    flush(); // Ensure immediate output
}
```

## Model Management

### getAvailableModels()

Retrieve available models from OpenAI with capabilities and pricing information.

```php
public function getAvailableModels(): Collection
```

**Returns:** Collection of model information

**Example:**
```php
$models = AI::provider('openai')->getAvailableModels();

foreach ($models as $model) {
    echo "Model: {$model['id']}\n";
    echo "Context Length: {$model['context_length']}\n";
    echo "Capabilities: " . implode(', ', $model['capabilities']) . "\n";
    echo "Input Cost: $" . number_format($model['pricing']['input'], 6) . " per token\n";
    echo "Output Cost: $" . number_format($model['pricing']['output'], 6) . " per token\n\n";
}
```

### syncModels()

Synchronize models from OpenAI API to local database.

```php
public function syncModels(): array
```

**Returns:** Array with sync results

**Example:**
```php
$result = AI::provider('openai')->syncModels();

echo "Synced: {$result['synced']} models\n";
echo "Updated: {$result['updated']} models\n";
echo "Errors: {$result['errors']} models\n";

foreach ($result['model_details'] as $model) {
    echo "- {$model['id']}: {$model['status']}\n";
}
```

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
$cost = AI::provider('openai')->calculateCost($usage, 'gpt-4');

echo "Cost: $" . number_format($cost, 4);
```

### estimateTokens()

Estimate token count for messages before sending.

```php
public function estimateTokens(array $messages, string $model = 'gpt-3.5-turbo'): TokenUsage
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

$estimated = AI::provider('openai')->estimateTokens($messages, 'gpt-4');
$estimatedCost = AI::provider('openai')->calculateCost($estimated, 'gpt-4');

echo "Estimated tokens: {$estimated->totalTokens}\n";
echo "Estimated cost: $" . number_format($estimatedCost, 4) . "\n";
```

## Function Calling

The OpenAI driver supports comprehensive function calling with parallel execution and automatic result handling.

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
    [
        'name' => 'calculate_tip',
        'description' => 'Calculate tip amount',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'bill_amount' => [
                    'type' => 'number',
                    'description' => 'Total bill amount',
                ],
                'tip_percentage' => [
                    'type' => 'number',
                    'description' => 'Tip percentage (default: 15)',
                    'default' => 15,
                ],
            ],
            'required' => ['bill_amount'],
        ],
    ],
];

// Send message with function definitions
$response = AI::provider('openai')->sendMessage(
    AIMessage::user('What\'s the weather in Paris and calculate a 20% tip on $50?'),
    [
        'model' => 'gpt-4',
        'tools' => array_map(fn($func) => ['type' => 'function', 'function' => $func], $functions),
        'tool_choice' => 'auto',
    ]
);

// Handle function calls
if ($response->hasToolCalls()) {
    $messages = [
        AIMessage::user('What\'s the weather in Paris and calculate a 20% tip on $50?'),
        AIMessage::assistant('', null, null, null, null, null, $response->toolCalls),
    ];

    foreach ($response->toolCalls as $toolCall) {
        $functionName = $toolCall->function->name;
        $arguments = json_decode($toolCall->function->arguments, true);

        // Execute function based on name
        $result = match ($functionName) {
            'get_weather' => getWeatherData($arguments['location'], $arguments['unit'] ?? 'celsius'),
            'calculate_tip' => calculateTip($arguments['bill_amount'], $arguments['tip_percentage'] ?? 15),
            default => ['error' => 'Unknown function'],
        };

        // Add function result to conversation
        $messages[] = AIMessage::tool($toolCall->id, json_encode($result));
    }

    // Get final response with function results
    $finalResponse = AI::provider('openai')->sendMessages($messages);
    echo $finalResponse->content;
}
```

### Parallel Function Calling

OpenAI can call multiple functions in parallel:

```php
$response = AI::provider('openai')->sendMessage(
    AIMessage::user('Get weather for London, Paris, and Tokyo'),
    [
        'model' => 'gpt-4',
        'tools' => [['type' => 'function', 'function' => $weatherFunction]],
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

    $finalResponse = AI::provider('openai')->sendMessages($messages);
    echo $finalResponse->content; // AI will summarize all weather data
}
```

### Function Call Validation

```php
// Validate function definitions before sending
$isValid = AI::provider('openai')->validateFunctionDefinitions($functions);

if (!$isValid['valid']) {
    foreach ($isValid['errors'] as $error) {
        echo "Validation error: {$error}\n";
    }
}
```

## Health Check & Validation

### validateCredentials()

Validate OpenAI API credentials and configuration.

```php
public function validateCredentials(): array
```

**Returns:** Array with validation results

**Example:**
```php
$validation = AI::provider('openai')->validateCredentials();

if ($validation['valid']) {
    echo "Credentials are valid!\n";
    echo "Organization: {$validation['organization']}\n";
    echo "Available models: {$validation['available_models']}\n";

    if (isset($validation['account_info'])) {
        echo "Account type: {$validation['account_info']['type']}\n";
        echo "Usage limits: " . json_encode($validation['account_info']['limits']) . "\n";
    }
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
$config = AI::provider('openai')->getConfig();

echo "API Key: {$config['api_key']}\n";        // sk-***1234 (masked)
echo "Organization: {$config['organization']}\n";
echo "Timeout: {$config['timeout']}s\n";
echo "Retry attempts: {$config['retry_attempts']}\n";
```

### getCapabilities()

Get provider capabilities and features.

```php
public function getCapabilities(): array
```

**Returns:** Array of supported capabilities

**Example:**
```php
$capabilities = AI::provider('openai')->getCapabilities();

echo "Chat: " . ($capabilities['chat'] ? 'Yes' : 'No') . "\n";
echo "Streaming: " . ($capabilities['streaming'] ? 'Yes' : 'No') . "\n";
echo "Function calling: " . ($capabilities['function_calling'] ? 'Yes' : 'No') . "\n";
echo "Vision: " . ($capabilities['vision'] ? 'Yes' : 'No') . "\n";
echo "Max context: {$capabilities['max_context_length']} tokens\n";
```

## Error Handling

The OpenAI driver provides comprehensive error handling with specific exception types and retry logic.

### Exception Types

```php
use JTD\LaravelAI\Exceptions\OpenAI\OpenAIException;
use JTD\LaravelAI\Exceptions\OpenAI\OpenAIRateLimitException;
use JTD\LaravelAI\Exceptions\OpenAI\OpenAIInvalidCredentialsException;
use JTD\LaravelAI\Exceptions\OpenAI\OpenAIQuotaExceededException;
use JTD\LaravelAI\Exceptions\OpenAI\OpenAIServerException;

try {
    $response = AI::provider('openai')->sendMessage(
        AIMessage::user('Hello, world!')
    );
} catch (OpenAIRateLimitException $e) {
    // Rate limit exceeded - includes retry information
    $retryAfter = $e->getRetryAfter(); // Seconds to wait
    $limitType = $e->getLimitType();   // 'requests' or 'tokens'
    $resetTime = $e->getResetTime();   // When limit resets

    Log::warning("Rate limited", [
        'retry_after' => $retryAfter,
        'limit_type' => $limitType,
        'reset_time' => $resetTime,
    ]);

} catch (OpenAIQuotaExceededException $e) {
    // Quota/billing issues
    $quotaType = $e->getQuotaType();     // 'monthly', 'daily', etc.
    $currentUsage = $e->getCurrentUsage();
    $usageLimit = $e->getUsageLimit();

    Log::error("Quota exceeded", [
        'quota_type' => $quotaType,
        'current_usage' => $currentUsage,
        'usage_limit' => $usageLimit,
    ]);

} catch (OpenAIInvalidCredentialsException $e) {
    // Authentication/authorization errors
    $account = $e->getAccount();
    $organizationId = $e->getOrganizationId();

    Log::error("Invalid credentials", [
        'account' => $account,
        'organization' => $organizationId,
    ]);

} catch (OpenAIServerException $e) {
    // Server errors (500, 503, etc.) - automatically retried
    $isRetryable = $e->isRetryable();
    $suggestedDelay = $e->getSuggestedRetryDelay();

    Log::error("Server error", [
        'retryable' => $isRetryable,
        'suggested_delay' => $suggestedDelay,
    ]);

} catch (OpenAIException $e) {
    // Generic OpenAI errors
    $errorType = $e->getOpenAIErrorType();
    $details = $e->getDetails();

    Log::error("OpenAI error", [
        'type' => $errorType,
        'details' => $details,
    ]);
}
```

### Retry Logic

The driver automatically retries failed requests with exponential backoff:

```php
// Configure retry behavior
$response = AI::provider('openai')->sendMessage(
    AIMessage::user('Hello'),
    [
        'model' => 'gpt-4',
        // Retry configuration (optional - uses driver defaults)
        'retry_attempts' => 5,
        'retry_delay' => 2000,      // Initial delay in ms
        'max_retry_delay' => 60000, // Maximum delay in ms
    ]
);
```

### Custom Error Handling

```php
// Disable automatic retries for specific request
$response = AI::provider('openai')->withoutRetry()->sendMessage(
    AIMessage::user('Hello')
);

// Custom retry logic
$maxAttempts = 3;
$attempt = 1;

while ($attempt <= $maxAttempts) {
    try {
        $response = AI::provider('openai')->sendMessage(AIMessage::user('Hello'));
        break; // Success
    } catch (OpenAIRateLimitException $e) {
        if ($attempt === $maxAttempts) {
            throw $e; // Final attempt failed
        }

        sleep($e->getRetryAfter());
        $attempt++;
    }
}
```

## Event System

The OpenAI driver fires events for monitoring and observability:

### Available Events

```php
use JTD\LaravelAI\Events\MessageSent;
use JTD\LaravelAI\Events\ResponseGenerated;
use JTD\LaravelAI\Events\CostCalculated;
use JTD\LaravelAI\Events\ConversationUpdated;
use JTD\LaravelAI\Events\ModelSynced;
use JTD\LaravelAI\Events\ErrorOccurred;

// Listen for events
Event::listen(MessageSent::class, function (MessageSent $event) {
    Log::info('AI message sent', [
        'provider' => $event->provider,
        'model' => $event->options['model'] ?? 'default',
        'message_count' => count($event->messages),
        'user_id' => $event->userId,
        'conversation_id' => $event->conversationId,
    ]);
});

Event::listen(ResponseGenerated::class, function (ResponseGenerated $event) {
    Log::info('AI response generated', [
        'provider' => $event->provider,
        'response_time_ms' => $event->response->responseTimeMs,
        'tokens_used' => $event->response->tokenUsage->totalTokens,
        'finish_reason' => $event->response->finishReason,
    ]);
});

Event::listen(CostCalculated::class, function (CostCalculated $event) {
    // Track costs in analytics
    Analytics::track('ai_cost', [
        'provider' => $event->provider,
        'model' => $event->model,
        'cost' => $event->cost,
        'input_tokens' => $event->tokenUsage->inputTokens,
        'output_tokens' => $event->tokenUsage->outputTokens,
        'user_id' => $event->userId,
    ]);

    // Alert on high costs
    if ($event->cost > 1.00) {
        Notification::route('slack', config('slack.webhook'))
            ->notify(new HighCostAlert($event));
    }
});

Event::listen(ErrorOccurred::class, function (ErrorOccurred $event) {
    Log::error('AI error occurred', [
        'provider' => $event->provider,
        'error_type' => $event->errorType,
        'error_message' => $event->errorMessage,
        'retryable' => $event->isRetryable,
        'attempt' => $event->attempt,
    ]);
});
```

### Event-Driven Cost Monitoring

```php
// Create a cost monitoring listener
class CostMonitoringListener
{
    public function handle(CostCalculated $event): void
    {
        // Store cost data
        AIUsageLog::create([
            'provider' => $event->provider,
            'model' => $event->model,
            'cost' => $event->cost,
            'input_tokens' => $event->tokenUsage->inputTokens,
            'output_tokens' => $event->tokenUsage->outputTokens,
            'user_id' => $event->userId,
            'conversation_id' => $event->conversationId,
            'created_at' => now(),
        ]);

        // Check daily limits
        $dailyCost = AIUsageLog::where('user_id', $event->userId)
            ->whereDate('created_at', today())
            ->sum('cost');

        if ($dailyCost > config('ai.daily_cost_limit', 10.00)) {
            // Notify user of limit reached
            $user = User::find($event->userId);
            $user->notify(new DailyCostLimitReached($dailyCost));
        }
    }
}

// Register the listener
Event::listen(CostCalculated::class, CostMonitoringListener::class);
```

## Advanced Configuration

### Logging Configuration

```php
'logging' => [
    'enabled' => true,
    'channel' => 'ai',              // Custom log channel
    'level' => 'info',              // Log level
    'include_content' => false,     // Log message content (security consideration)
    'include_responses' => false,   // Log AI responses (security consideration)
    'include_costs' => true,        // Log cost information
    'include_performance' => true,  // Log performance metrics
],
```

### Rate Limiting Configuration

```php
'rate_limiting' => [
    'enabled' => true,
    'requests_per_minute' => 3500,  // OpenAI tier limits
    'tokens_per_minute' => 90000,   // OpenAI tier limits
    'burst_allowance' => 100,       // Allow bursts up to this many requests
    'backoff_strategy' => 'exponential', // 'linear' or 'exponential'
],
```

### HTTP Client Configuration

```php
'http_client' => [
    'timeout' => 30,
    'connect_timeout' => 10,
    'read_timeout' => 30,
    'write_timeout' => 30,
    'max_redirects' => 3,
    'verify_ssl' => true,
    'user_agent' => 'JTD-Laravel-AI/1.0',
    'headers' => [
        'X-Custom-Header' => 'value',
    ],
],
```

## Performance Optimization

### Connection Pooling

```php
// The driver automatically reuses HTTP connections
// Configure connection pool size
'http_client' => [
    'pool_size' => 10,              // Maximum concurrent connections
    'keep_alive' => true,           // Keep connections alive
    'keep_alive_timeout' => 30,     // Keep-alive timeout in seconds
],
```

### Caching

```php
// Cache model information
$models = Cache::remember('openai_models', 3600, function () {
    return AI::provider('openai')->getAvailableModels();
});

// Cache cost calculations
$cost = Cache::remember("cost_{$model}_{$tokens}", 300, function () use ($usage, $model) {
    return AI::provider('openai')->calculateCost($usage, $model);
});
```

### Batch Processing

```php
// Process multiple requests efficiently
$requests = [
    ['message' => AIMessage::user('Question 1'), 'options' => ['model' => 'gpt-4']],
    ['message' => AIMessage::user('Question 2'), 'options' => ['model' => 'gpt-4']],
    ['message' => AIMessage::user('Question 3'), 'options' => ['model' => 'gpt-4']],
];

$responses = [];
foreach ($requests as $request) {
    $responses[] = AI::provider('openai')->sendMessage(
        $request['message'],
        $request['options']
    );
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
'api_key' => env('OPENAI_API_KEY'), // ✅ Good
'api_key' => 'sk-hardcoded-key',    // ❌ Bad
```

3. **Implement rate limiting**:
```php
// Respect OpenAI's rate limits
'rate_limiting' => [
    'enabled' => true,
    'requests_per_minute' => 3500, // Adjust based on your tier
],
```

### Cost Management

1. **Monitor costs in real-time**:
```php
Event::listen(CostCalculated::class, function (CostCalculated $event) {
    if ($event->cost > 0.10) { // Alert on expensive requests
        Log::warning('High cost AI request', ['cost' => $event->cost]);
    }
});
```

2. **Use appropriate models**:
```php
// Use cheaper models for simple tasks
$simpleResponse = AI::provider('openai')->sendMessage(
    AIMessage::user('Summarize this in one word: ' . $text),
    ['model' => 'gpt-3.5-turbo'] // Cheaper than GPT-4
);

// Use expensive models only when needed
$complexResponse = AI::provider('openai')->sendMessage(
    AIMessage::user('Analyze this complex data: ' . $data),
    ['model' => 'gpt-4'] // More capable but expensive
);
```

3. **Estimate costs before sending**:
```php
$estimated = AI::provider('openai')->estimateTokens($messages, 'gpt-4');
$estimatedCost = AI::provider('openai')->calculateCost($estimated, 'gpt-4');

if ($estimatedCost > 1.00) {
    // Ask for user confirmation or use cheaper model
    throw new CostTooHighException("Estimated cost: $" . number_format($estimatedCost, 2));
}
```

### Performance

1. **Use streaming for long responses**:
```php
// For long-form content, use streaming
$stream = AI::provider('openai')->sendStreamingMessage(
    AIMessage::user('Write a detailed essay about Laravel'),
    ['model' => 'gpt-4']
);
```

2. **Implement proper error handling**:
```php
// Always handle errors gracefully
try {
    $response = AI::provider('openai')->sendMessage($message);
} catch (OpenAIRateLimitException $e) {
    // Implement backoff strategy
    sleep($e->getRetryAfter());
    $response = AI::provider('openai')->sendMessage($message);
} catch (OpenAIException $e) {
    // Log error and provide fallback
    Log::error('AI request failed', ['error' => $e->getMessage()]);
    $response = $this->getFallbackResponse();
}
```

3. **Cache expensive operations**:
```php
// Cache model lists and pricing information
$models = Cache::remember('openai_models', 3600, function () {
    return AI::provider('openai')->getAvailableModels();
});
```

## Troubleshooting

### Common Issues

1. **Authentication Errors**:
```bash
# Check API key format
echo $OPENAI_API_KEY | grep -E '^sk-[a-zA-Z0-9]{48}$'

# Validate credentials
php artisan ai:validate-credentials openai
```

2. **Rate Limiting**:
```php
// Check rate limit status
$validation = AI::provider('openai')->validateCredentials();
echo "Rate limits: " . json_encode($validation['rate_limits']);
```

3. **High Costs**:
```php
// Monitor token usage
$response = AI::provider('openai')->sendMessage($message);
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

The OpenAI driver provides a comprehensive, production-ready integration with OpenAI's API. It includes all the features needed for enterprise applications: robust error handling, cost tracking, performance optimization, and comprehensive monitoring.

For additional examples and advanced usage patterns, see the [Function Calling Examples](FUNCTION_CALLING_EXAMPLES.md) and [Streaming Examples](STREAMING_EXAMPLES.md) documentation.
