# AI Middleware System

## Overview

The JTD Laravel AI middleware system provides a powerful, Laravel-familiar way to intercept, transform, and enhance AI requests before they reach the AI providers. This enables sophisticated request routing, context injection, pre-processing, and cost optimization while maintaining clean separation of concerns.

## Architecture

### Request Flow with Middleware

```
AI Request → Middleware Stack → Driver → MCP Servers → AI Provider
     ↓              ↓              ↓         ↓           ↓
  Original    Pre-processing   Provider   Enhanced    Final
  Request     & Routing        Selection  Thinking    Response
```

### Middleware vs MCP Distinction

- **Middleware**: Operates on requests *before* they reach AI providers (routing, context, preprocessing)
- **MCP Servers**: Operates *during* AI processing to enhance thinking and capabilities
- **Complementary**: Both systems work together for maximum flexibility

## Core Middleware Interface

All middleware must implement the `AIMiddlewareInterface`:

```php
<?php

namespace JTD\LaravelAI\Contracts;

use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;

interface AIMiddlewareInterface
{
    /**
     * Handle the AI request
     */
    public function handle(AIMessage $message, \Closure $next): AIResponse;
}
```

## Built-in Middleware

### Smart Router Middleware

Automatically routes requests to optimal providers and models based on complexity, cost, and context.

```php
<?php

namespace JTD\LaravelAI\Middleware;

use JTD\LaravelAI\Contracts\AIMiddlewareInterface;
use JTD\LaravelAI\Models\AIMessage;

class SmartRouterMiddleware implements AIMiddlewareInterface
{
    public function handle(AIMessage $message, \Closure $next): AIResponse
    {
        $complexity = $this->analyzeComplexity($message->content);
        $userBudget = $this->getUserBudget($message->user_id);
        
        // Route based on complexity and budget
        if ($complexity < 0.3 && $userBudget->prefers_cost_optimization) {
            $message->provider = 'gemini';
            $message->model = 'gemini-pro';
        } elseif ($complexity > 0.8) {
            $message->provider = 'openai';
            $message->model = 'gpt-4';
        } elseif ($this->isCodeRelated($message->content)) {
            $message->provider = 'openai';
            $message->model = 'gpt-4';
        } else {
            $message->provider = 'openai';
            $message->model = 'gpt-3.5-turbo';
        }
        
        Log::info('Smart router selected', [
            'provider' => $message->provider,
            'model' => $message->model,
            'complexity' => $complexity,
        ]);
        
        return $next($message);
    }
    
    protected function analyzeComplexity(string $content): float
    {
        $indicators = [
            'analyze' => 0.4, 'design' => 0.5, 'architect' => 0.6,
            'calculate' => 0.3, 'solve' => 0.4, 'optimize' => 0.5,
            'explain' => 0.2, 'summarize' => 0.1, 'translate' => 0.1,
        ];
        
        $complexity = 0;
        $content = strtolower($content);
        
        foreach ($indicators as $keyword => $weight) {
            if (str_contains($content, $keyword)) {
                $complexity += $weight;
            }
        }
        
        // Length and question complexity factors
        $complexity += min(strlen($content) / 500, 0.3);
        $complexity += substr_count($content, '?') * 0.1;
        
        return min($complexity, 1.0);
    }
}
```

### Context Injection Middleware

Automatically injects relevant context from user history, project data, and memory systems.

```php
<?php

namespace JTD\LaravelAI\Middleware;

class ContextInjectionMiddleware implements AIMiddlewareInterface
{
    public function handle(AIMessage $message, \Closure $next): AIResponse
    {
        $context = $this->buildContext($message);
        
        if (!empty($context)) {
            $originalContent = $message->content;
            $message->content = $this->injectContext($originalContent, $context);
            
            Log::debug('Context injected', [
                'context_items' => count($context),
                'original_length' => strlen($originalContent),
                'enhanced_length' => strlen($message->content),
            ]);
        }
        
        return $next($message);
    }
    
    protected function buildContext(AIMessage $message): array
    {
        $context = [];
        
        // User context
        if ($message->user_id) {
            $userContext = $this->getUserContext($message->user_id);
            if ($userContext) {
                $context['user'] = $userContext;
            }
        }
        
        // Project context
        if ($projectId = $message->metadata['project_id'] ?? null) {
            $projectContext = $this->getProjectContext($projectId);
            if ($projectContext) {
                $context['project'] = $projectContext;
            }
        }
        
        // Conversation history context
        if ($message->conversation_id) {
            $relevantHistory = $this->getRelevantHistory($message);
            if ($relevantHistory) {
                $context['history'] = $relevantHistory;
            }
        }
        
        // Domain-specific context
        $domain = $this->detectDomain($message->content);
        if ($domain) {
            $domainContext = $this->getDomainContext($domain);
            if ($domainContext) {
                $context['domain'] = $domainContext;
            }
        }
        
        return $context;
    }
    
    protected function injectContext(string $originalContent, array $context): string
    {
        $contextString = "Context:\n";
        
        foreach ($context as $type => $data) {
            $contextString .= "- " . ucfirst($type) . ": " . $data . "\n";
        }
        
        return $contextString . "\nUser Question: " . $originalContent;
    }
}
```

### Budget Enforcement Middleware

Enforces spending limits and provides cost controls at the request level.

```php
<?php

namespace JTD\LaravelAI\Middleware;

use JTD\LaravelAI\Exceptions\BudgetExceededException;

class BudgetEnforcementMiddleware implements AIMiddlewareInterface
{
    public function handle(AIMessage $message, \Closure $next): AIResponse
    {
        $estimatedCost = $this->estimateRequestCost($message);
        
        // Check various budget limits
        $this->checkDailyBudget($message->user_id, $estimatedCost);
        $this->checkMonthlyBudget($message->user_id, $estimatedCost);
        $this->checkPerRequestBudget($message->user_id, $estimatedCost);
        
        // Check project budget if applicable
        if ($projectId = $message->metadata['project_id'] ?? null) {
            $this->checkProjectBudget($projectId, $estimatedCost);
        }
        
        $response = $next($message);
        
        // Track actual cost after response
        $this->trackActualCost($message->user_id, $response->cost);
        
        return $response;
    }
    
    protected function checkDailyBudget(int $userId, float $estimatedCost): void
    {
        $dailyBudget = $this->getUserDailyBudget($userId);
        $todaySpending = $this->getTodaySpending($userId);
        
        if (($todaySpending + $estimatedCost) > $dailyBudget->limit) {
            throw new BudgetExceededException(
                "Daily budget of \${$dailyBudget->limit} would be exceeded. " .
                "Current spending: \${$todaySpending}, Estimated cost: \${$estimatedCost}"
            );
        }
        
        // Send warning if approaching limit
        $percentage = (($todaySpending + $estimatedCost) / $dailyBudget->limit) * 100;
        if ($percentage > 80) {
            event(new BudgetWarningEvent($userId, 'daily', $percentage));
        }
    }
}
```

### Pre-processing Middleware

Analyzes and enhances requests before they reach AI providers.

```php
<?php

namespace JTD\LaravelAI\Middleware;

class PreProcessingMiddleware implements AIMiddlewareInterface
{
    public function handle(AIMessage $message, \Closure $next): AIResponse
    {
        $originalContent = $message->content;
        
        // Enhance the request based on analysis
        if ($this->needsClarification($originalContent)) {
            $message->content = $this->addClarifyingContext($originalContent);
        }
        
        if ($this->isVague($originalContent)) {
            $message->content = $this->addSpecificityPrompts($originalContent);
        }
        
        if ($this->containsCode($originalContent)) {
            $message->content = $this->enhanceCodeContext($originalContent);
        }
        
        return $next($message);
    }
    
    protected function needsClarification(string $content): bool
    {
        $vagueIndicators = ['it', 'this', 'that', 'the thing', 'stuff'];
        
        foreach ($vagueIndicators as $indicator) {
            if (str_contains(strtolower($content), $indicator)) {
                return true;
            }
        }
        
        return false;
    }
    
    protected function addClarifyingContext(string $content): string
    {
        return "Please provide a detailed and specific response. If the question is unclear, " .
               "ask for clarification or make reasonable assumptions and state them.\n\n" .
               "Question: " . $content;
    }
    
    protected function enhanceCodeContext(string $content): string
    {
        return "You are an expert programmer. When providing code examples, " .
               "include comments explaining the logic and consider edge cases.\n\n" .
               $content;
    }
}
```

## Usage Examples

### Global Middleware

Apply middleware to all AI requests:

```php
// In a service provider
use JTD\LaravelAI\Facades\AI;

public function boot(): void
{
    AI::addGlobalMiddleware([
        SmartRouterMiddleware::class,
        BudgetEnforcementMiddleware::class,
    ]);
}
```

### Per-Conversation Middleware

Apply middleware to specific conversations:

```php
$response = AI::conversation('Enhanced Chat')
    ->middleware([
        ContextInjectionMiddleware::class,
        PreProcessingMiddleware::class,
    ])
    ->message('How do I optimize this code?')
    ->send();
```

### Conditional Middleware

Apply middleware based on conditions:

```php
$conversation = AI::conversation('Smart Chat');

if (auth()->user()->isPremium()) {
    $conversation->middleware([ContextInjectionMiddleware::class]);
}

if (config('ai.cost_optimization')) {
    $conversation->middleware([SmartRouterMiddleware::class]);
}

$response = $conversation->message('Hello')->send();
```

### Middleware with Configuration

Pass configuration to middleware:

```php
$response = AI::conversation()
    ->middleware([
        SmartRouterMiddleware::class => [
            'prefer_cost_optimization' => true,
            'max_complexity_for_cheap_models' => 0.4,
        ],
        ContextInjectionMiddleware::class => [
            'max_context_length' => 2000,
            'include_user_preferences' => true,
        ],
    ])
    ->message('Complex question')
    ->send();
```

### Disabling Middleware

Disable middleware for specific requests:

```php
// Disable all middleware
$response = AI::conversation()
    ->withoutMiddleware()
    ->message('Direct request')
    ->send();

// Disable specific middleware
$response = AI::conversation()
    ->withoutMiddleware([BudgetEnforcementMiddleware::class])
    ->message('Expensive request')
    ->send();
```

## Custom Middleware

### Creating Custom Middleware

```php
<?php

namespace App\AI\Middleware;

use JTD\LaravelAI\Contracts\AIMiddlewareInterface;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;

class CustomTranslationMiddleware implements AIMiddlewareInterface
{
    public function handle(AIMessage $message, \Closure $next): AIResponse
    {
        // Detect if translation is needed
        if ($this->needsTranslation($message)) {
            $message->content = $this->translateToEnglish($message->content);
            $message->metadata['original_language'] = $this->detectLanguage($message->content);
        }
        
        $response = $next($message);
        
        // Translate response back if needed
        if (isset($message->metadata['original_language'])) {
            $response->content = $this->translateFromEnglish(
                $response->content,
                $message->metadata['original_language']
            );
        }
        
        return $response;
    }
}
```

### Registering Custom Middleware

```php
// In a service provider
AI::addMiddleware('translation', CustomTranslationMiddleware::class);

// Use the middleware
$response = AI::conversation()
    ->middleware(['translation'])
    ->message('Hola, ¿cómo estás?')
    ->send();
```

## Configuration

Configure middleware behavior in `config/ai.php`:

```php
'middleware' => [
    'enabled' => env('AI_MIDDLEWARE_ENABLED', true),
    
    'global' => [
        // Middleware applied to all requests
        'smart_router' => [
            'enabled' => env('AI_SMART_ROUTER_ENABLED', true),
            'prefer_cost_optimization' => env('AI_PREFER_COST_OPTIMIZATION', false),
        ],
        'budget_enforcement' => [
            'enabled' => env('AI_BUDGET_ENFORCEMENT_ENABLED', true),
            'strict_mode' => env('AI_BUDGET_STRICT_MODE', false),
        ],
    ],
    
    'available' => [
        'smart_router' => SmartRouterMiddleware::class,
        'context_injection' => ContextInjectionMiddleware::class,
        'budget_enforcement' => BudgetEnforcementMiddleware::class,
        'preprocessing' => PreProcessingMiddleware::class,
    ],
],
```

## Performance Considerations

### Middleware Optimization

- **Caching**: Cache expensive operations like context lookups
- **Async Processing**: Use queues for non-blocking operations
- **Selective Application**: Only apply middleware when needed
- **Performance Monitoring**: Track middleware execution times

### Example Optimized Middleware

```php
class OptimizedContextMiddleware implements AIMiddlewareInterface
{
    public function handle(AIMessage $message, \Closure $next): AIResponse
    {
        // Cache context for 5 minutes
        $cacheKey = "ai_context:{$message->user_id}:{$message->conversation_id}";
        
        $context = Cache::remember($cacheKey, 300, function () use ($message) {
            return $this->buildContext($message);
        });
        
        if (!empty($context)) {
            $message->content = $this->injectContext($message->content, $context);
        }
        
        return $next($message);
    }
}
```

## Testing Middleware

### Unit Testing

```php
<?php

namespace Tests\Unit\Middleware;

use Tests\TestCase;
use App\AI\Middleware\SmartRouterMiddleware;
use JTD\LaravelAI\Models\AIMessage;

class SmartRouterMiddlewareTest extends TestCase
{
    public function test_routes_simple_questions_to_cheap_models(): void
    {
        $middleware = new SmartRouterMiddleware();
        $message = new AIMessage('What is 2+2?');
        
        $middleware->handle($message, function ($msg) {
            $this->assertEquals('gemini', $msg->provider);
            $this->assertEquals('gemini-pro', $msg->model);
            return new AIResponse(['content' => '4']);
        });
    }
    
    public function test_routes_complex_questions_to_powerful_models(): void
    {
        $middleware = new SmartRouterMiddleware();
        $message = new AIMessage('Design a scalable microservices architecture for an e-commerce platform');
        
        $middleware->handle($message, function ($msg) {
            $this->assertEquals('openai', $msg->provider);
            $this->assertEquals('gpt-4', $msg->model);
            return new AIResponse(['content' => 'Architecture design...']);
        });
    }
}
```

## Best Practices

### Middleware Design Guidelines

1. **Single Responsibility**: Each middleware should have one clear purpose
2. **Fail Gracefully**: Handle errors without breaking the request flow
3. **Log Appropriately**: Log decisions and transformations for debugging
4. **Performance Aware**: Minimize latency and resource usage
5. **Configurable**: Allow customization through configuration
6. **Testable**: Write comprehensive unit tests

### Common Patterns

```php
// Pattern: Conditional processing
if ($this->shouldProcess($message)) {
    $message = $this->processMessage($message);
}

// Pattern: Graceful degradation
try {
    $enhancement = $this->enhanceMessage($message);
    $message->content = $enhancement;
} catch (\Exception $e) {
    Log::warning('Enhancement failed, continuing with original', ['error' => $e->getMessage()]);
}

// Pattern: Metadata tracking
$message->metadata['middleware_applied'][] = static::class;
$message->metadata['processing_time'] = microtime(true) - $startTime;
```
