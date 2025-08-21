# Facades and Method Chaining

## Overview

JTD Laravel AI provides a fluent, Laravel-style interface through facades and method chaining. This familiar pattern makes it easy for Laravel developers to work with AI providers while maintaining clean, readable code.

## AI Facade

The primary entry point is the `AI` facade, which provides access to all package functionality:

```php
use JTD\LaravelAI\Facades\AI;

// Basic usage
$response = AI::conversation('My Chat')
    ->message('Hello world')
    ->send();
```

## Conversation Builder

### Basic Chaining

```php
// Simple conversation
$response = AI::conversation()
    ->message('What is Laravel?')
    ->send();

// With provider and model
$response = AI::conversation()
    ->provider('openai')
    ->model('gpt-4')
    ->message('Explain quantum computing')
    ->send();

// With parameters
$response = AI::conversation()
    ->provider('openai')
    ->model('gpt-4')
    ->temperature(0.8)
    ->maxTokens(1000)
    ->topP(0.9)
    ->message('Write a creative story')
    ->send();
```

### Advanced Chaining

```php
// Complex conversation setup
$response = AI::conversation('Technical Discussion')
    ->user(auth()->user())
    ->provider('openai')
    ->model('gpt-4')
    ->systemMessage('You are a senior software engineer.')
    ->temperature(0.7)
    ->maxTokens(2000)
    ->contextWindow(10)
    ->metadata(['project_id' => 123])
    ->message('How should I architect this system?')
    ->onSuccess(fn($response) => $this->logSuccess($response))
    ->onError(fn($error) => $this->handleError($error))
    ->send();
```

## Method Categories

### Provider Configuration

```php
$builder = AI::conversation()
    ->provider('openai')           // Set AI provider
    ->account('premium')           // Use specific account
    ->model('gpt-4')              // Set model
    ->fallbackProvider('gemini')   // Fallback if primary fails
    ->providers(['openai', 'gemini']); // Try providers in order
```

### Message Configuration

```php
$builder = AI::conversation()
    ->message('User message')                    // Add user message
    ->systemMessage('System context')           // Add system message
    ->assistantMessage('Previous response')     // Add assistant message
    ->messages([                                // Add multiple messages
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi there!'],
    ]);
```

### Model Parameters

```php
$builder = AI::conversation()
    ->temperature(0.8)        // Creativity (0.0-2.0)
    ->maxTokens(1000)        // Maximum output tokens
    ->topP(0.9)              // Nucleus sampling
    ->frequencyPenalty(0.1)  // Reduce repetition
    ->presencePenalty(0.1)   // Encourage new topics
    ->stop(['END', 'STOP'])  // Stop sequences
    ->seed(12345);           // Deterministic output
```

### Context Management

```php
$builder = AI::conversation()
    ->contextWindow(20)              // Keep last 20 messages
    ->systemMessage('You are...')    // Set system context
    ->addContext($previousConv)      // Add context from another conversation
    ->clearContext()                 // Clear existing context
    ->preserveContext(true);         // Maintain context across requests
```

### Attachments and Media

```php
$builder = AI::conversation()
    ->attachImage('path/to/image.jpg')           // Attach image
    ->attachFile('path/to/document.pdf')         // Attach file
    ->attachFiles(['file1.pdf', 'file2.docx'])  // Multiple files
    ->attachUrl('https://example.com/image.jpg') // Attach from URL
    ->attachBase64($base64Image, 'image/jpeg');  // Base64 attachment
```

### Response Handling

```php
$builder = AI::conversation()
    ->onSuccess(function ($response) {
        Log::info('AI response received', ['tokens' => $response->tokens_used]);
        return $response;
    })
    ->onError(function ($error) {
        Log::error('AI request failed', ['error' => $error->getMessage()]);
        throw $error;
    })
    ->onProgress(function ($progress) {
        echo "Progress: {$progress}%\n";
    });
```

### Cost and Budget Controls

```php
$builder = AI::conversation()
    ->maxCost(0.50)              // Maximum cost per request
    ->budget(10.00)              // Conversation budget
    ->costOptimized()            // Use cheapest suitable provider
    ->trackCosts(true)           // Enable cost tracking
    ->warnOnHighCost(1.00);      // Warn if cost exceeds $1
```

## Batch Operations

### Batch Processing

```php
// Process multiple messages
$responses = AI::batch()
    ->provider('openai')
    ->model('gpt-3.5-turbo')
    ->temperature(0.7)
    ->messages([
        'Summarize this article...',
        'Translate this text...',
        'Generate a title for...',
    ])
    ->onEach(function ($response, $index) {
        echo "Response {$index}: {$response->content}\n";
    })
    ->process();

// Batch with different configurations
$responses = AI::batch()
    ->add('openai', 'gpt-4', 'Complex analysis task')
    ->add('gemini', 'gemini-pro', 'Simple question')
    ->add('xai', 'grok-beta', 'Creative writing')
    ->process();
```

### Parallel Processing

```php
// Process requests in parallel
$responses = AI::parallel()
    ->add(AI::conversation()->provider('openai')->message('Task 1'))
    ->add(AI::conversation()->provider('gemini')->message('Task 2'))
    ->add(AI::conversation()->provider('xai')->message('Task 3'))
    ->timeout(30)
    ->process();

// With callback for each completion
$responses = AI::parallel()
    ->add(AI::conversation()->message('Task 1'))
    ->add(AI::conversation()->message('Task 2'))
    ->onComplete(function ($response, $index) {
        echo "Task {$index} completed\n";
    })
    ->process();
```

## Streaming Responses

### Real-time Streaming

```php
// Stream response in real-time
AI::conversation()
    ->provider('openai')
    ->model('gpt-4')
    ->stream(true)
    ->message('Write a long story')
    ->onChunk(function ($chunk) {
        echo $chunk->content;
        flush();
    })
    ->onComplete(function ($response) {
        echo "\nStreaming complete. Total tokens: {$response->tokens_used}\n";
    })
    ->send();
```

### Server-Sent Events

```php
// Stream to browser via SSE
Route::get('/ai-stream', function () {
    return response()->stream(function () {
        AI::conversation()
            ->stream(true)
            ->message(request('message'))
            ->onChunk(function ($chunk) {
                echo "data: " . json_encode(['content' => $chunk->content]) . "\n\n";
                flush();
            })
            ->send();
    }, 200, [
        'Content-Type' => 'text/event-stream',
        'Cache-Control' => 'no-cache',
    ]);
});
```

## Function Calling

### OpenAI Function Calling

```php
$response = AI::conversation()
    ->provider('openai')
    ->model('gpt-4')
    ->functions([
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
                    ],
                ],
                'required' => ['location'],
            ],
        ],
    ])
    ->functionCall('auto') // auto, none, or specific function
    ->onFunctionCall(function ($functionCall) {
        // Handle function call
        if ($functionCall->name === 'get_weather') {
            return $this->getWeather($functionCall->arguments);
        }
    })
    ->message('What\'s the weather in New York?')
    ->send();
```

### Tool Integration

```php
$response = AI::conversation()
    ->tools([
        'calculator' => Calculator::class,
        'database' => DatabaseTool::class,
        'web_search' => WebSearchTool::class,
    ])
    ->message('Calculate 15% of 250 and search for Laravel tutorials')
    ->send();
```

## Conditional Chaining

### Conditional Logic

```php
$builder = AI::conversation()
    ->when(auth()->user()->isPremium(), function ($builder) {
        return $builder->provider('openai')->model('gpt-4');
    })
    ->when(app()->environment('production'), function ($builder) {
        return $builder->trackCosts(true)->maxCost(1.00);
    })
    ->unless(app()->environment('testing'), function ($builder) {
        return $builder->timeout(30);
    });
```

### Dynamic Configuration

```php
$response = AI::conversation()
    ->provider(function () {
        return auth()->user()->preferred_provider ?? 'openai';
    })
    ->model(function ($provider) {
        return match ($provider) {
            'openai' => 'gpt-4',
            'gemini' => 'gemini-pro',
            'xai' => 'grok-beta',
            default => 'gpt-3.5-turbo',
        };
    })
    ->temperature(function () {
        return request()->has('creative') ? 0.9 : 0.7;
    })
    ->message('Hello')
    ->send();
```

## Response Processing

### Response Transformation

```php
$result = AI::conversation()
    ->message('List 5 programming languages')
    ->send()
    ->transform(function ($response) {
        // Extract list items
        preg_match_all('/\d+\.\s*(.+)/', $response->content, $matches);
        return $matches[1];
    });

// Chain transformations
$result = AI::conversation()
    ->message('Generate JSON data')
    ->send()
    ->transform(fn($r) => json_decode($r->content, true))
    ->transform(fn($data) => collect($data))
    ->transform(fn($collection) => $collection->pluck('name'));
```

### Response Validation

```php
$response = AI::conversation()
    ->message('Generate a valid email address')
    ->send()
    ->validate(function ($response) {
        return filter_var($response->content, FILTER_VALIDATE_EMAIL);
    })
    ->retry(3) // Retry up to 3 times if validation fails
    ->onValidationFailed(function ($response, $attempt) {
        Log::warning("Validation failed on attempt {$attempt}");
    });
```

## Middleware and Hooks

### Request Middleware

```php
AI::addMiddleware('logging', function ($request, $next) {
    Log::info('AI request started', ['message' => $request->message]);
    
    $response = $next($request);
    
    Log::info('AI request completed', [
        'tokens' => $response->tokens_used,
        'cost' => $response->cost,
    ]);
    
    return $response;
});

// Use middleware
$response = AI::conversation()
    ->middleware(['logging', 'rate-limiting'])
    ->message('Hello')
    ->send();
```

### Global Hooks

```php
// Global before hook
AI::before(function ($request) {
    if (!auth()->check()) {
        throw new UnauthorizedException();
    }
});

// Global after hook
AI::after(function ($response) {
    Cache::put("last_ai_response:{auth()->id()}", $response->content, 3600);
});
```

## Macros and Extensions

### Custom Macros

```php
// Register macro
AI::macro('summarize', function ($text, $maxLength = 100) {
    return $this->message("Summarize this text in {$maxLength} words or less: {$text}");
});

// Use macro
$response = AI::conversation()
    ->provider('openai')
    ->summarize($longText, 50)
    ->send();
```

### Builder Extensions

```php
// Extend conversation builder
ConversationBuilder::macro('forUser', function ($user) {
    return $this->user($user)
        ->provider($user->preferred_ai_provider)
        ->maxCost($user->ai_budget_per_request);
});

// Use extension
$response = AI::conversation()
    ->forUser(auth()->user())
    ->message('Hello')
    ->send();
```

## Error Handling

### Graceful Error Handling

```php
$response = AI::conversation()
    ->message('Hello')
    ->catch(function ($exception) {
        if ($exception instanceof RateLimitException) {
            return $this->retry()->after(60); // Retry after 60 seconds
        }
        
        if ($exception instanceof BudgetExceededException) {
            return new AIResponse('Budget exceeded. Please try again later.');
        }
        
        throw $exception; // Re-throw unhandled exceptions
    })
    ->send();
```

### Retry Logic

```php
$response = AI::conversation()
    ->message('Hello')
    ->retry(3)                    // Retry up to 3 times
    ->retryDelay(1000)           // 1 second delay between retries
    ->retryOn([                  // Retry on specific exceptions
        RateLimitException::class,
        TimeoutException::class,
    ])
    ->onRetry(function ($attempt, $exception) {
        Log::warning("Retry attempt {$attempt}: {$exception->getMessage()}");
    })
    ->send();
```

## Performance Optimization

### Caching

```php
$response = AI::conversation()
    ->cache(3600)                // Cache response for 1 hour
    ->cacheKey('weather-nyc')    // Custom cache key
    ->message('What\'s the weather in NYC?')
    ->send();

// Cache with tags
$response = AI::conversation()
    ->cache(3600, ['weather', 'nyc'])
    ->message('Weather in NYC')
    ->send();
```

### Lazy Loading

```php
// Lazy conversation loading
$conversation = AI::conversation()->lazy();

// Only loads when actually used
$response = $conversation
    ->message('Hello')
    ->send();
```

### Connection Pooling

```php
// Use connection pooling for better performance
AI::useConnectionPool(true)
    ->poolSize(10)
    ->conversation()
    ->message('Hello')
    ->send();
```
