# Sprint 5: Middleware and Event System Foundation

**Duration**: 2 weeks
**Epic**: Advanced Features and Optimization
**Goal**: Implement the core middleware system architecture, event system, and basic middleware components

## Sprint Objectives

1. Design and implement core middleware architecture
2. Create comprehensive event system for async processing
3. Create middleware interface and manager
4. Implement Smart Router middleware for cost optimization
5. Build Budget Enforcement middleware with event integration
6. Add middleware registration and configuration system
7. Integrate middleware and events with existing conversation system

## User Stories

### Story 1: Event System Architecture
**As a developer, I want an event system so I can handle post-response actions asynchronously**

**Acceptance Criteria:**
- Event system provides 85% faster response times
- Core events are defined (MessageSent, ResponseGenerated, CostCalculated)
- Event listeners handle background processing
- Queue integration works correctly
- Error handling and retry logic is implemented

**Tasks:**
- [ ] Create core event classes
- [ ] Implement event listeners for cost tracking and analytics
- [ ] Add queue integration for background processing
- [ ] Create event registration system
- [ ] Add error handling and retry logic
- [ ] Write event system tests

**Estimated Effort:** 2 days

### Story 2: Core Middleware Architecture
**As a developer, I want a middleware system so I can intercept and transform AI requests**

**Acceptance Criteria:**
- Middleware interface is well-defined and extensible
- Middleware manager handles registration and execution
- Middleware stack executes in correct order
- Error handling prevents middleware failures from breaking requests
- Performance impact is minimal
- Integrates with event system

**Tasks:**
- [ ] Create AIMiddlewareInterface contract
- [ ] Implement MiddlewareManager service
- [ ] Add middleware stack execution logic
- [ ] Create middleware registration system
- [ ] Add error handling and logging
- [ ] Integrate with event system
- [ ] Write core middleware tests

**Estimated Effort:** 2 days

### Story 3: Smart Router Middleware
**As a user, I want automatic model selection so I can optimize costs without manual configuration**

**Acceptance Criteria:**
- Analyzes request complexity automatically
- Routes simple requests to cheaper models
- Routes complex requests to more capable models
- Considers user budget preferences
- Logs routing decisions for transparency
- Achieves 30%+ cost savings on typical workloads
- Fires events for routing decisions

**Tasks:**
- [ ] Implement complexity analysis algorithm
- [ ] Create model selection logic
- [ ] Add user preference integration
- [ ] Implement cost-aware routing
- [ ] Add comprehensive logging
- [ ] Fire routing events for analytics
- [ ] Write Smart Router tests
- [ ] Create performance benchmarks

**Estimated Effort:** 2 days

### Story 4: Budget Enforcement Middleware with Events
**As a finance manager, I want automatic budget enforcement so users can't exceed spending limits**

**Acceptance Criteria:**
- Enforces daily, monthly, and per-request budgets
- Provides clear error messages when budgets are exceeded
- Sends warnings when approaching limits via events
- Tracks actual costs after requests complete via events
- Supports project-level budgets
- Fires BudgetThresholdReached events for notifications

**Tasks:**
- [ ] Implement budget checking logic
- [ ] Add cost estimation before requests
- [ ] Create budget warning system with events
- [ ] Implement event-driven cost tracking
- [ ] Add project budget support
- [ ] Fire budget threshold events
- [ ] Write budget enforcement tests
- [ ] Create budget alert event listeners

**Estimated Effort:** 2 days

### Story 4: Middleware Integration
**As a developer, I want middleware to integrate seamlessly with the existing API**

**Acceptance Criteria:**
- Middleware can be applied globally or per-conversation
- Fluent API supports middleware chaining
- Middleware can be disabled for debugging
- Configuration system supports middleware settings
- Integration doesn't break existing functionality
- Performance impact is documented and acceptable

**Tasks:**
- [ ] Integrate middleware with ConversationBuilder
- [ ] Add fluent API methods for middleware
- [ ] Implement global middleware registration
- [ ] Create middleware configuration system
- [ ] Add middleware disable functionality
- [ ] Update existing tests for middleware compatibility
- [ ] Write integration tests

**Estimated Effort:** 2 days

### Story 5: Middleware Configuration and Management
**As a system administrator, I want to configure middleware behavior through configuration files**

**Acceptance Criteria:**
- Middleware can be configured in config/ai.php
- Global middleware can be enabled/disabled
- Middleware-specific settings are supported
- Environment variables control middleware behavior
- Configuration validation prevents invalid settings
- Documentation covers all configuration options

**Tasks:**
- [ ] Add middleware configuration to config/ai.php
- [ ] Implement configuration validation
- [ ] Add environment variable support
- [ ] Create middleware management commands
- [ ] Write configuration documentation
- [ ] Add configuration tests

**Estimated Effort:** 1 day

### Story 6: Performance Optimization and Monitoring
**As a developer, I want middleware performance monitoring so I can optimize request processing**

**Acceptance Criteria:**
- Middleware execution times are tracked
- Performance metrics are logged
- Slow middleware is identified automatically
- Caching is implemented where appropriate
- Memory usage is optimized
- Performance benchmarks are established

**Tasks:**
- [ ] Add middleware performance tracking
- [ ] Implement caching for expensive operations
- [ ] Create performance monitoring dashboard
- [ ] Add memory usage optimization
- [ ] Write performance tests
- [ ] Create performance documentation

**Estimated Effort:** 1 day

## Technical Implementation

### Core Middleware Architecture

```php
<?php

namespace JTD\LaravelAI\Services;

use JTD\LaravelAI\Contracts\AIMiddlewareInterface;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;

class MiddlewareManager
{
    protected array $globalMiddleware = [];
    protected array $registeredMiddleware = [];
    
    public function registerGlobal(string $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }
    
    public function register(string $name, string $middleware): void
    {
        $this->registeredMiddleware[$name] = $middleware;
    }
    
    public function process(AIMessage $message, array $middleware = []): AIResponse
    {
        $stack = $this->buildStack(array_merge($this->globalMiddleware, $middleware));
        
        return $stack($message);
    }
    
    protected function buildStack(array $middleware): \Closure
    {
        return array_reduce(
            array_reverse($middleware),
            function ($next, $middleware) {
                return function (AIMessage $message) use ($next, $middleware) {
                    $instance = $this->resolveMiddleware($middleware);
                    
                    $startTime = microtime(true);
                    
                    try {
                        $response = $instance->handle($message, $next);
                        
                        $this->logPerformance($middleware, microtime(true) - $startTime);
                        
                        return $response;
                    } catch (\Exception $e) {
                        Log::error('Middleware failed', [
                            'middleware' => $middleware,
                            'error' => $e->getMessage(),
                            'message_id' => $message->id,
                        ]);
                        
                        // Continue with next middleware or final handler
                        return $next($message);
                    }
                };
            },
            function (AIMessage $message) {
                // Final handler - send to AI provider
                return app(AIManager::class)->sendMessage($message);
            }
        );
    }
}
```

### ConversationBuilder Integration

```php
<?php

namespace JTD\LaravelAI\Services;

class ConversationBuilder
{
    protected array $middleware = [];
    protected bool $middlewareDisabled = false;
    
    public function middleware(array $middleware): self
    {
        $this->middleware = array_merge($this->middleware, $middleware);
        return $this;
    }
    
    public function withoutMiddleware(array $except = []): self
    {
        if (empty($except)) {
            $this->middlewareDisabled = true;
        } else {
            $this->middleware = array_diff($this->middleware, $except);
        }
        
        return $this;
    }
    
    public function send(): AIResponse
    {
        $message = $this->buildMessage();
        
        if ($this->middlewareDisabled) {
            return app(AIManager::class)->sendMessage($message);
        }
        
        return app(MiddlewareManager::class)->process($message, $this->middleware);
    }
}
```

### Smart Router Implementation

```php
<?php

namespace JTD\LaravelAI\Middleware;

class SmartRouterMiddleware implements AIMiddlewareInterface
{
    protected array $config;
    
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'cost_optimization_threshold' => 0.3,
            'complexity_threshold_gpt4' => 0.8,
            'prefer_cost_optimization' => false,
        ], $config);
    }
    
    public function handle(AIMessage $message, \Closure $next): AIResponse
    {
        $routing = $this->determineRouting($message);
        
        $message->provider = $routing['provider'];
        $message->model = $routing['model'];
        $message->metadata['routing_reason'] = $routing['reason'];
        $message->metadata['complexity_score'] = $routing['complexity'];
        
        Log::info('Smart router decision', [
            'provider' => $routing['provider'],
            'model' => $routing['model'],
            'reason' => $routing['reason'],
            'complexity' => $routing['complexity'],
        ]);
        
        return $next($message);
    }
    
    protected function determineRouting(AIMessage $message): array
    {
        $complexity = $this->analyzeComplexity($message->content);
        $userPrefs = $this->getUserPreferences($message->user_id);
        
        // Cost optimization mode
        if ($userPrefs['prefer_cost_optimization'] || $this->config['prefer_cost_optimization']) {
            if ($complexity < $this->config['cost_optimization_threshold']) {
                return [
                    'provider' => 'gemini',
                    'model' => 'gemini-pro',
                    'reason' => 'cost_optimization',
                    'complexity' => $complexity,
                ];
            }
        }
        
        // High complexity - use most capable model
        if ($complexity > $this->config['complexity_threshold_gpt4']) {
            return [
                'provider' => 'openai',
                'model' => 'gpt-4',
                'reason' => 'high_complexity',
                'complexity' => $complexity,
            ];
        }
        
        // Code-related queries
        if ($this->isCodeRelated($message->content)) {
            return [
                'provider' => 'openai',
                'model' => 'gpt-4',
                'reason' => 'code_specialization',
                'complexity' => $complexity,
            ];
        }
        
        // Default to balanced option
        return [
            'provider' => 'openai',
            'model' => 'gpt-3.5-turbo',
            'reason' => 'default_balanced',
            'complexity' => $complexity,
        ];
    }
}
```

## Testing Strategy

### Middleware Unit Tests

```php
<?php

namespace Tests\Unit\Middleware;

use Tests\TestCase;
use JTD\LaravelAI\Middleware\SmartRouterMiddleware;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;

class SmartRouterMiddlewareTest extends TestCase
{
    public function test_routes_simple_questions_to_cheap_models(): void
    {
        $middleware = new SmartRouterMiddleware(['prefer_cost_optimization' => true]);
        $message = new AIMessage('What is the capital of France?');
        
        $response = $middleware->handle($message, function ($msg) {
            $this->assertEquals('gemini', $msg->provider);
            $this->assertEquals('gemini-pro', $msg->model);
            return new AIResponse(['content' => 'Paris']);
        });
        
        $this->assertEquals('Paris', $response->content);
    }
    
    public function test_routes_complex_questions_to_powerful_models(): void
    {
        $middleware = new SmartRouterMiddleware();
        $message = new AIMessage('Design a distributed system architecture for handling 1M concurrent users');
        
        $middleware->handle($message, function ($msg) {
            $this->assertEquals('openai', $msg->provider);
            $this->assertEquals('gpt-4', $msg->model);
            $this->assertEquals('high_complexity', $msg->metadata['routing_reason']);
            return new AIResponse(['content' => 'Architecture design...']);
        });
    }
    
    public function test_handles_middleware_errors_gracefully(): void
    {
        $middleware = new SmartRouterMiddleware();
        $message = new AIMessage('Test message');
        
        // Simulate middleware error
        $response = $middleware->handle($message, function ($msg) {
            throw new \Exception('Simulated error');
        });
        
        // Should not throw exception, should be handled gracefully
        $this->assertInstanceOf(AIResponse::class, $response);
    }
}
```

### Integration Tests

```php
<?php

namespace Tests\Feature\Middleware;

use Tests\TestCase;
use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Middleware\SmartRouterMiddleware;

class MiddlewareIntegrationTest extends TestCase
{
    public function test_middleware_integrates_with_conversation_builder(): void
    {
        $response = AI::conversation()
            ->middleware([SmartRouterMiddleware::class])
            ->message('Simple question')
            ->send();
        
        $this->assertNotNull($response->content);
        $this->assertNotNull($response->metadata['routing_reason']);
    }
    
    public function test_can_disable_middleware(): void
    {
        $response = AI::conversation()
            ->middleware([SmartRouterMiddleware::class])
            ->withoutMiddleware()
            ->message('Test message')
            ->send();
        
        $this->assertArrayNotHasKey('routing_reason', $response->metadata);
    }
}
```

## Configuration

### config/ai.php Updates

```php
'middleware' => [
    'enabled' => env('AI_MIDDLEWARE_ENABLED', true),
    
    'global' => [
        // Applied to all requests
        'smart_router' => [
            'enabled' => env('AI_SMART_ROUTER_ENABLED', false),
            'prefer_cost_optimization' => env('AI_PREFER_COST_OPTIMIZATION', false),
            'cost_optimization_threshold' => 0.3,
            'complexity_threshold_gpt4' => 0.8,
        ],
        'budget_enforcement' => [
            'enabled' => env('AI_BUDGET_ENFORCEMENT_ENABLED', true),
            'strict_mode' => env('AI_BUDGET_STRICT_MODE', false),
        ],
    ],
    
    'performance' => [
        'track_execution_time' => true,
        'log_slow_middleware' => true,
        'slow_threshold_ms' => 1000,
        'cache_expensive_operations' => true,
    ],
],
```

## Definition of Done

- [ ] Core middleware architecture is implemented and tested
- [ ] Smart Router middleware reduces costs by 30%+ on test workloads
- [ ] Budget Enforcement middleware prevents overspending
- [ ] Middleware integrates seamlessly with existing API
- [ ] Configuration system supports all middleware settings
- [ ] Performance impact is under 100ms per middleware
- [ ] All tests pass with 90%+ coverage
- [ ] Documentation covers middleware usage and configuration

## Risks and Mitigation

### Risk 1: Performance Impact
**Mitigation**: Implement caching, async processing, and performance monitoring

### Risk 2: Complexity for Simple Use Cases
**Mitigation**: Make middleware optional and provide sensible defaults

### Risk 3: Middleware Failures Breaking Requests
**Mitigation**: Implement graceful error handling and fallback mechanisms

## Sprint Review Criteria

1. Middleware system processes requests correctly
2. Smart Router achieves cost savings targets
3. Budget Enforcement prevents overspending
4. Integration doesn't break existing functionality
5. Performance benchmarks meet requirements
6. Configuration system works as designed
7. All tests pass and coverage targets are met

## Next Sprint Preview

Sprint 6 will focus on:
- Context Injection middleware for enhanced responses
- Pre-processing middleware for request optimization
- Advanced middleware features and customization
- Performance optimization and caching improvements
