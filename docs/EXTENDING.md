# Extending JTD Laravel AI

## Overview

JTD Laravel AI is designed to be highly extensible. You can create custom drivers, middleware, MCP servers, cost calculators, and more. This document covers all the extension points and how to implement custom functionality.

## Custom AI Providers

### Creating a Custom Driver

```php
<?php

namespace App\AI\Drivers;

use JTD\LaravelAI\Contracts\AIProviderInterface;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Models\TokenUsage;
use Illuminate\Support\Collection;
use Illuminate\Http\Client\Factory as HttpClient;

class CustomAIDriver implements AIProviderInterface
{
    protected HttpClient $http;
    protected array $config;
    
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->http = app(HttpClient::class);
    }
    
    public function getName(): string
    {
        return 'custom-ai';
    }
    
    public function sendMessage(AIMessage $message, array $options = []): AIResponse
    {
        $response = $this->http
            ->withHeaders($this->getHeaders())
            ->timeout($this->config['timeout'] ?? 30)
            ->post($this->getEndpoint('/chat'), [
                'model' => $options['model'] ?? $this->config['default_model'],
                'messages' => $this->formatMessages($message),
                'temperature' => $options['temperature'] ?? 0.7,
            ]);
        
        return $this->parseResponse($response->json());
    }
    
    public function getAvailableModels(): Collection
    {
        $response = $this->http
            ->withHeaders($this->getHeaders())
            ->get($this->getEndpoint('/models'));
        
        return collect($response->json('models'))
            ->map(fn($model) => [
                'id' => $model['id'],
                'name' => $model['name'],
                'type' => $model['type'] ?? 'chat',
                'context_length' => $model['max_tokens'] ?? 4096,
                'capabilities' => $this->parseCapabilities($model),
            ]);
    }
    
    // Implement other required methods...
    
    protected function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->config['api_key'],
            'Content-Type' => 'application/json',
            'User-Agent' => 'JTD-Laravel-AI/1.0',
        ];
    }
    
    protected function getEndpoint(string $path): string
    {
        return rtrim($this->config['base_url'], '/') . $path;
    }
}
```

### Registering Custom Drivers

```php
// In a service provider
use JTD\LaravelAI\Facades\AI;

public function boot(): void
{
    AI::extend('custom-ai', function ($config) {
        return new CustomAIDriver($config);
    });
}
```

### Advanced Driver Features

```php
<?php

namespace App\AI\Drivers;

use JTD\LaravelAI\Drivers\BaseDriver;
use JTD\LaravelAI\Contracts\StreamableInterface;
use JTD\LaravelAI\Contracts\BatchableInterface;

class AdvancedCustomDriver extends BaseDriver implements StreamableInterface, BatchableInterface
{
    public function streamMessage(AIMessage $message, callable $callback, array $options = []): AIResponse
    {
        $stream = $this->http
            ->withHeaders($this->getHeaders())
            ->timeout($this->config['stream_timeout'] ?? 60)
            ->post($this->getEndpoint('/stream'), [
                'model' => $options['model'] ?? $this->config['default_model'],
                'messages' => $this->formatMessages($message),
                'stream' => true,
            ]);
        
        $fullContent = '';
        
        $stream->onChunk(function ($chunk) use ($callback, &$fullContent) {
            $data = json_decode($chunk, true);
            
            if (isset($data['content'])) {
                $fullContent .= $data['content'];
                
                $callback(new AIResponse([
                    'content' => $data['content'],
                    'is_chunk' => true,
                    'full_content' => $fullContent,
                ]));
            }
        });
        
        return new AIResponse([
            'content' => $fullContent,
            'is_streamed' => true,
        ]);
    }
    
    public function sendBatch(array $messages, array $options = []): Collection
    {
        $batchRequest = [
            'requests' => collect($messages)->map(function ($message, $index) use ($options) {
                return [
                    'id' => $index,
                    'model' => $options['model'] ?? $this->config['default_model'],
                    'messages' => $this->formatMessages($message),
                ];
            })->toArray(),
        ];
        
        $response = $this->http
            ->withHeaders($this->getHeaders())
            ->timeout($this->config['batch_timeout'] ?? 120)
            ->post($this->getEndpoint('/batch'), $batchRequest);
        
        return collect($response->json('responses'))
            ->map(fn($response) => $this->parseResponse($response));
    }
}
```

## Custom Middleware

### Creating Middleware

```php
<?php

namespace App\AI\Middleware;

use JTD\LaravelAI\Contracts\AIMiddlewareInterface;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;

class LoggingMiddleware implements AIMiddlewareInterface
{
    public function handle(AIMessage $message, \Closure $next): AIResponse
    {
        $startTime = microtime(true);
        
        Log::info('AI request started', [
            'message_preview' => Str::limit($message->content, 100),
            'provider' => $message->provider,
            'model' => $message->model,
        ]);
        
        $response = $next($message);
        
        $duration = microtime(true) - $startTime;
        
        Log::info('AI request completed', [
            'duration' => $duration,
            'tokens_used' => $response->tokens_used,
            'cost' => $response->cost,
            'provider' => $response->provider,
        ]);
        
        return $response;
    }
}
```

### Rate Limiting Middleware

```php
<?php

namespace App\AI\Middleware;

use JTD\LaravelAI\Contracts\AIMiddlewareInterface;
use JTD\LaravelAI\Exceptions\RateLimitExceededException;
use Illuminate\Support\Facades\Cache;

class RateLimitingMiddleware implements AIMiddlewareInterface
{
    public function __construct(
        protected int $maxRequests = 60,
        protected int $perMinutes = 1
    ) {}
    
    public function handle(AIMessage $message, \Closure $next): AIResponse
    {
        $key = $this->getRateLimitKey($message);
        $requests = Cache::get($key, 0);
        
        if ($requests >= $this->maxRequests) {
            throw new RateLimitExceededException(
                "Rate limit exceeded: {$this->maxRequests} requests per {$this->perMinutes} minute(s)"
            );
        }
        
        Cache::put($key, $requests + 1, now()->addMinutes($this->perMinutes));
        
        return $next($message);
    }
    
    protected function getRateLimitKey(AIMessage $message): string
    {
        $userId = auth()->id() ?? 'anonymous';
        $minute = now()->format('Y-m-d-H-i');
        
        return "ai_rate_limit:{$userId}:{$minute}";
    }
}
```

### Registering Middleware

```php
// In a service provider
use JTD\LaravelAI\Facades\AI;

public function boot(): void
{
    AI::addMiddleware('logging', LoggingMiddleware::class);
    AI::addMiddleware('rate-limiting', RateLimitingMiddleware::class);
    
    // Global middleware
    AI::addGlobalMiddleware([
        'logging',
        'rate-limiting',
    ]);
}
```

## Custom MCP Servers

### Creating an MCP Server

```php
<?php

namespace App\AI\MCP;

use JTD\LaravelAI\Contracts\MCPServerInterface;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;

class CodeAnalysisMCPServer implements MCPServerInterface
{
    public function getName(): string
    {
        return 'code-analysis';
    }
    
    public function getDescription(): string
    {
        return 'Analyzes code and provides structured feedback';
    }
    
    public function processMessage(AIMessage $message, array $config = []): AIMessage
    {
        if ($this->containsCode($message->content)) {
            $analysis = $this->analyzeCode($message->content);
            
            $enhancedContent = $message->content . "\n\n" . 
                "Code Analysis Context:\n" . 
                "- Language: {$analysis['language']}\n" . 
                "- Complexity: {$analysis['complexity']}\n" . 
                "- Issues: " . implode(', ', $analysis['issues']);
            
            $message->content = $enhancedContent;
            $message->context['code_analysis'] = $analysis;
        }
        
        return $message;
    }
    
    public function processResponse(AIResponse $response, array $config = []): AIResponse
    {
        // Post-process the response if needed
        return $response;
    }
    
    public function getCapabilities(): array
    {
        return [
            'code_detection',
            'syntax_analysis',
            'complexity_analysis',
            'issue_detection',
        ];
    }
    
    protected function containsCode(string $content): bool
    {
        // Simple heuristic to detect code
        return preg_match('/```|function\s+\w+|class\s+\w+|def\s+\w+/', $content);
    }
    
    protected function analyzeCode(string $content): array
    {
        // Implement code analysis logic
        return [
            'language' => $this->detectLanguage($content),
            'complexity' => $this->calculateComplexity($content),
            'issues' => $this->findIssues($content),
        ];
    }
}
```

### MCP Server with Tools

```php
<?php

namespace App\AI\MCP;

use JTD\LaravelAI\Contracts\MCPServerInterface;
use JTD\LaravelAI\Contracts\MCPToolInterface;

class ResearchMCPServer implements MCPServerInterface, MCPToolInterface
{
    public function getTools(): array
    {
        return [
            [
                'name' => 'web_search',
                'description' => 'Search the web for information',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string'],
                        'num_results' => ['type' => 'integer', 'default' => 5],
                    ],
                ],
            ],
            [
                'name' => 'fetch_url',
                'description' => 'Fetch content from a URL',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'url' => ['type' => 'string'],
                    ],
                ],
            ],
        ];
    }
    
    public function executeTool(string $toolName, array $parameters): array
    {
        return match ($toolName) {
            'web_search' => $this->webSearch($parameters),
            'fetch_url' => $this->fetchUrl($parameters),
            default => throw new \InvalidArgumentException("Unknown tool: {$toolName}"),
        };
    }
    
    protected function webSearch(array $params): array
    {
        // Implement web search logic
        return [
            'results' => [
                ['title' => 'Result 1', 'url' => 'https://example.com/1'],
                ['title' => 'Result 2', 'url' => 'https://example.com/2'],
            ],
        ];
    }
}
```

## Custom Cost Calculators

### Creating a Cost Calculator

```php
<?php

namespace App\AI\CostCalculators;

use JTD\LaravelAI\Contracts\CostCalculatorInterface;
use JTD\LaravelAI\Models\TokenUsage;

class CustomCostCalculator implements CostCalculatorInterface
{
    public function calculateCost(TokenUsage $usage, string $provider, string $model): float
    {
        $pricing = $this->getPricing($provider, $model);
        
        $inputCost = $usage->input_tokens * $pricing['input_cost_per_token'];
        $outputCost = $usage->output_tokens * $pricing['output_cost_per_token'];
        
        // Apply volume discounts
        $totalCost = $inputCost + $outputCost;
        $discount = $this->calculateVolumeDiscount($usage->total_tokens);
        
        return $totalCost * (1 - $discount);
    }
    
    public function estimateCost(int $inputTokens, int $outputTokens, string $provider, string $model): float
    {
        $usage = new TokenUsage($inputTokens, $outputTokens);
        return $this->calculateCost($usage, $provider, $model);
    }
    
    protected function calculateVolumeDiscount(int $totalTokens): float
    {
        if ($totalTokens > 100000) {
            return 0.1; // 10% discount for high volume
        }
        
        if ($totalTokens > 50000) {
            return 0.05; // 5% discount for medium volume
        }
        
        return 0; // No discount
    }
    
    protected function getPricing(string $provider, string $model): array
    {
        // Get pricing from database or configuration
        return [
            'input_cost_per_token' => 0.0001,
            'output_cost_per_token' => 0.0002,
        ];
    }
}
```

### Registering Cost Calculators

```php
// In a service provider
use JTD\LaravelAI\Facades\AI;

public function boot(): void
{
    AI::setCostCalculator('custom', CustomCostCalculator::class);
    
    // Set as default
    AI::setDefaultCostCalculator('custom');
}
```

## Custom Analytics Providers

### Creating an Analytics Provider

```php
<?php

namespace App\AI\Analytics;

use JTD\LaravelAI\Contracts\AnalyticsProviderInterface;

class CustomAnalyticsProvider implements AnalyticsProviderInterface
{
    public function trackUsage(array $data): void
    {
        // Send to custom analytics service
        Http::post('https://analytics.company.com/ai-usage', $data);
    }
    
    public function trackCost(array $data): void
    {
        // Send to cost tracking service
        Http::post('https://analytics.company.com/ai-costs', $data);
    }
    
    public function generateReport(array $criteria): array
    {
        // Generate custom report
        return [
            'total_usage' => $this->getTotalUsage($criteria),
            'cost_breakdown' => $this->getCostBreakdown($criteria),
            'trends' => $this->getTrends($criteria),
        ];
    }
}
```

## Custom Event Listeners

### Creating Event Listeners

```php
<?php

namespace App\Listeners;

use JTD\LaravelAI\Events\MessageSent;
use JTD\LaravelAI\Events\ResponseReceived;
use JTD\LaravelAI\Events\CostCalculated;

class AIEventListener
{
    public function handleMessageSent(MessageSent $event): void
    {
        // Custom logic when message is sent
        $this->logUserActivity($event->message);
        $this->updateUserStats($event->message->user_id);
    }
    
    public function handleResponseReceived(ResponseReceived $event): void
    {
        // Custom logic when response is received
        $this->analyzeResponseQuality($event->response);
        $this->updateModelPerformance($event->response);
    }
    
    public function handleCostCalculated(CostCalculated $event): void
    {
        // Custom logic when cost is calculated
        $this->checkBudgetLimits($event->cost, $event->user_id);
        $this->updateCostAnalytics($event->cost, $event->provider);
    }
}
```

### Registering Event Listeners

```php
// In EventServiceProvider
protected $listen = [
    MessageSent::class => [
        AIEventListener::class . '@handleMessageSent',
    ],
    ResponseReceived::class => [
        AIEventListener::class . '@handleResponseReceived',
    ],
    CostCalculated::class => [
        AIEventListener::class . '@handleCostCalculated',
    ],
];
```

## Custom Macros

### Adding Macros to the AI Facade

```php
// In a service provider
use JTD\LaravelAI\Facades\AI;

public function boot(): void
{
    AI::macro('summarize', function (string $text, int $maxWords = 100) {
        return AI::conversation()
            ->message("Summarize this text in {$maxWords} words or less: {$text}")
            ->send();
    });
    
    AI::macro('translate', function (string $text, string $targetLanguage) {
        return AI::conversation()
            ->message("Translate this text to {$targetLanguage}: {$text}")
            ->send();
    });
    
    AI::macro('codeReview', function (string $code) {
        return AI::conversation()
            ->mcp('code-analysis')
            ->message("Please review this code and provide feedback:\n\n```\n{$code}\n```")
            ->send();
    });
}
```

### Using Custom Macros

```php
// Summarize text
$summary = AI::summarize($longText, 50);

// Translate text
$translation = AI::translate('Hello world', 'Spanish');

// Code review
$review = AI::codeReview($phpCode);
```

## Custom Commands

### Creating Artisan Commands

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use JTD\LaravelAI\Facades\AI;

class CustomAICommand extends Command
{
    protected $signature = 'ai:custom-analysis {--provider=openai}';
    protected $description = 'Run custom AI analysis';
    
    public function handle(): void
    {
        $provider = $this->option('provider');
        
        $this->info("Running custom analysis with {$provider}...");
        
        $results = AI::conversation()
            ->provider($provider)
            ->message('Perform custom analysis task')
            ->send();
        
        $this->info('Analysis completed:');
        $this->line($results->content);
        
        $this->table(['Metric', 'Value'], [
            ['Tokens Used', $results->tokens_used],
            ['Cost', '$' . number_format($results->cost, 4)],
            ['Response Time', $results->response_time . 'ms'],
        ]);
    }
}
```

## Package Extensions

### Creating Extension Packages

```php
<?php

namespace YourCompany\AIExtension;

use Illuminate\Support\ServiceProvider;
use JTD\LaravelAI\Facades\AI;

class AIExtensionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Register custom driver
        AI::extend('your-ai-provider', function ($config) {
            return new YourAIDriver($config);
        });
        
        // Register custom MCP server
        AI::registerMCPServer('your-mcp-server', YourMCPServer::class);
        
        // Add custom middleware
        AI::addMiddleware('your-middleware', YourMiddleware::class);
        
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../config/ai-extension.php' => config_path('ai-extension.php'),
        ], 'config');
        
        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'migrations');
    }
    
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/ai-extension.php',
            'ai-extension'
        );
    }
}
```

## Best Practices for Extensions

### Development Guidelines

1. **Follow Laravel Conventions**: Use Laravel's patterns and conventions
2. **Implement Interfaces**: Always implement the appropriate interfaces
3. **Handle Errors Gracefully**: Provide meaningful error messages
4. **Add Tests**: Include comprehensive tests for your extensions
5. **Document Everything**: Provide clear documentation and examples
6. **Version Compatibility**: Ensure compatibility with package versions
7. **Performance Considerations**: Optimize for performance and memory usage
8. **Security First**: Validate inputs and handle credentials securely
9. **Configuration**: Make extensions configurable through config files
10. **Backward Compatibility**: Maintain backward compatibility when possible

### Extension Checklist

- [ ] Implements required interfaces
- [ ] Includes comprehensive tests
- [ ] Has proper error handling
- [ ] Follows PSR standards
- [ ] Includes documentation
- [ ] Has configuration options
- [ ] Handles edge cases
- [ ] Is performance optimized
- [ ] Includes examples
- [ ] Has proper versioning
