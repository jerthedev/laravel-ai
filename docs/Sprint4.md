# Sprint 4: Cost Tracking and MCP Integration

**Duration**: 2 weeks  
**Epic**: Cost Tracking and Analytics + MCP Integration  
**Goal**: Implement comprehensive cost tracking, analytics, and Model Context Protocol integration with Sequential Thinking

## Sprint Objectives

1. Build real-time cost tracking and calculation system
2. Implement usage analytics and reporting
3. Create budget management with alerts and limits
4. Develop Sequential Thinking MCP server
5. Add MCP server architecture and extensibility
6. Implement background jobs for analytics and maintenance

## User Stories

### Story 1: Real-time Cost Tracking
**As a finance manager, I want real-time cost tracking so I can monitor AI spending**

**Acceptance Criteria:**
- Costs are calculated automatically for every request
- Real-time cost updates in database
- Cost breakdown by provider, model, and user
- Historical cost data is preserved
- Cost calculations are accurate to provider billing

**Tasks:**
- [ ] Create cost calculation engine
- [ ] Implement real-time cost tracking
- [ ] Add cost breakdown analytics
- [ ] Create cost history storage
- [ ] Validate cost accuracy
- [ ] Add cost tracking tests

**Estimated Effort:** 3 days

### Story 2: Budget Management
**As a user, I want budget limits so I don't exceed my spending allowance**

**Acceptance Criteria:**
- Can set monthly, daily, and per-request budgets
- Budget enforcement prevents overspending
- Alerts are sent when approaching limits
- Budget status is easily accessible
- Supports different budget types (user, project, organization)

**Tasks:**
- [ ] Create budget management system
- [ ] Implement budget enforcement
- [ ] Add budget alert system
- [ ] Create budget status dashboard
- [ ] Support multiple budget types
- [ ] Write budget management tests

**Estimated Effort:** 2 days

### Story 3: Usage Analytics
**As an administrator, I want usage analytics so I can optimize our AI usage**

**Acceptance Criteria:**
- Comprehensive usage reports and dashboards
- Trend analysis and forecasting
- Provider and model performance comparison
- Cost optimization recommendations
- Exportable reports in multiple formats

**Tasks:**
- [ ] Create analytics data collection
- [ ] Implement usage reporting
- [ ] Add trend analysis
- [ ] Create optimization recommendations
- [ ] Build report export functionality
- [ ] Add analytics tests

**Estimated Effort:** 3 days

### Story 4: Sequential Thinking MCP Server
**As a developer, I want Sequential Thinking so AI can break down complex problems**

**Acceptance Criteria:**
- AI breaks down complex problems into steps
- Thinking process is visible and trackable
- Configurable thinking parameters
- Integration with existing conversation system
- Performance impact is minimal

**Tasks:**
- [ ] Design Sequential Thinking architecture
- [ ] Implement thinking step processing
- [ ] Create thinking visualization
- [ ] Add configuration options
- [ ] Integrate with conversation system
- [ ] Write Sequential Thinking tests

**Estimated Effort:** 3 days

### Story 5: MCP Server Framework
**As a developer, I want MCP server framework so I can create custom enhancements**

**Acceptance Criteria:**
- Extensible MCP server architecture
- Easy registration of custom MCP servers
- MCP server chaining and composition
- Performance monitoring for MCP servers
- Documentation for creating custom servers

**Tasks:**
- [ ] Create MCP server interface
- [ ] Implement MCP server registry
- [ ] Add MCP server chaining
- [ ] Create performance monitoring
- [ ] Write MCP framework documentation
- [ ] Add MCP framework tests

**Estimated Effort:** 2 days

### Story 6: Background Processing
**As a system administrator, I want background jobs so the system runs efficiently**

**Acceptance Criteria:**
- Analytics are generated in background
- Model syncing happens automatically
- Cost calculations are processed efficiently
- System maintenance runs automatically
- Job monitoring and error handling

**Tasks:**
- [ ] Create analytics generation jobs
- [ ] Implement model sync jobs
- [ ] Add cost calculation jobs
- [ ] Create maintenance jobs
- [ ] Add job monitoring
- [ ] Write background job tests

**Estimated Effort:** 1 day

## Technical Implementation

### Cost Tracking System

```php
<?php

namespace JTD\LaravelAI\Services;

use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AICostTracking;

class CostTrackingService
{
    public function trackMessageCost(AIMessage $message): float
    {
        $provider = AI::provider($message->provider);
        
        $usage = new TokenUsage(
            $message->input_tokens,
            $message->output_tokens
        );
        
        $cost = $provider->calculateCost($usage, $message->model);
        
        // Update message cost
        $message->update(['cost' => $cost]);
        
        // Track in analytics
        $this->recordCostAnalytics($message, $cost);
        
        // Update conversation total
        $this->updateConversationCost($message->conversation, $cost);
        
        return $cost;
    }
    
    protected function recordCostAnalytics(AIMessage $message, float $cost): void
    {
        AICostTracking::create([
            'user_id' => $message->conversation->user_id,
            'provider_id' => $message->provider,
            'model_id' => $message->model,
            'conversation_id' => $message->conversation_id,
            'message_id' => $message->id,
            'cost' => $cost,
            'tokens_used' => $message->tokens_used,
            'input_tokens' => $message->input_tokens,
            'output_tokens' => $message->output_tokens,
            'date' => now()->toDateString(),
        ]);
    }
    
    public function getUserCosts(int $userId, string $startDate, string $endDate): array
    {
        return AICostTracking::where('user_id', $userId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                SUM(cost) as total_cost,
                COUNT(*) as total_requests,
                SUM(tokens_used) as total_tokens,
                AVG(cost) as avg_cost_per_request,
                provider_id,
                model_id
            ')
            ->groupBy(['provider_id', 'model_id'])
            ->get()
            ->toArray();
    }
}
```

### Budget Management

```php
<?php

namespace JTD\LaravelAI\Services;

use JTD\LaravelAI\Models\AIBudget;
use JTD\LaravelAI\Exceptions\BudgetExceededException;

class BudgetService
{
    public function checkBudget(int $userId, float $estimatedCost): bool
    {
        $budgets = AIBudget::where('user_id', $userId)
            ->where('is_active', true)
            ->get();
        
        foreach ($budgets as $budget) {
            if (!$this->canAfford($budget, $estimatedCost)) {
                throw new BudgetExceededException(
                    "Budget limit exceeded: {$budget->type} budget of {$budget->limit}"
                );
            }
        }
        
        return true;
    }
    
    protected function canAfford(AIBudget $budget, float $cost): bool
    {
        $currentSpending = $this->getCurrentSpending($budget);
        
        return ($currentSpending + $cost) <= $budget->limit;
    }
    
    protected function getCurrentSpending(AIBudget $budget): float
    {
        $query = AICostTracking::where('user_id', $budget->user_id);
        
        switch ($budget->type) {
            case 'daily':
                $query->whereDate('created_at', today());
                break;
            case 'monthly':
                $query->whereMonth('created_at', now()->month)
                      ->whereYear('created_at', now()->year);
                break;
            case 'per_request':
                return 0; // Per-request budgets are checked before the request
        }
        
        return $query->sum('cost');
    }
    
    public function sendBudgetAlert(AIBudget $budget, float $currentSpending): void
    {
        $percentage = ($currentSpending / $budget->limit) * 100;
        
        if ($percentage >= 90) {
            event(new BudgetAlertEvent($budget, $currentSpending, 'critical'));
        } elseif ($percentage >= 80) {
            event(new BudgetAlertEvent($budget, $currentSpending, 'warning'));
        }
    }
}
```

### Sequential Thinking MCP Server

```php
<?php

namespace JTD\LaravelAI\MCP;

use JTD\LaravelAI\Contracts\MCPServerInterface;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;

class SequentialThinkingServer implements MCPServerInterface
{
    public function getName(): string
    {
        return 'sequential-thinking';
    }
    
    public function getDescription(): string
    {
        return 'Enhances AI responses with structured step-by-step thinking';
    }
    
    public function processMessage(AIMessage $message, array $config = []): AIMessage
    {
        if ($this->shouldUseSequentialThinking($message, $config)) {
            $enhancedPrompt = $this->addThinkingPrompt($message->content, $config);
            $message->content = $enhancedPrompt;
            $message->metadata['mcp_sequential_thinking'] = true;
        }
        
        return $message;
    }
    
    public function processResponse(AIResponse $response, array $config = []): AIResponse
    {
        if (isset($response->metadata['mcp_sequential_thinking'])) {
            $thinkingSteps = $this->extractThinkingSteps($response->content);
            $finalAnswer = $this->extractFinalAnswer($response->content);
            
            $response->content = $finalAnswer;
            $response->metadata['thinking_steps'] = $thinkingSteps;
            $response->metadata['show_thinking'] = $config['show_thinking'] ?? false;
        }
        
        return $response;
    }
    
    protected function shouldUseSequentialThinking(AIMessage $message, array $config): bool
    {
        $complexity = $this->assessComplexity($message->content);
        $minComplexity = $config['min_complexity'] ?? 0.5;
        
        return $complexity >= $minComplexity;
    }
    
    protected function addThinkingPrompt(string $content, array $config): string
    {
        $maxThoughts = $config['max_thoughts'] ?? 5;
        $minThoughts = $config['min_thoughts'] ?? 2;
        
        return "Please think through this step by step before providing your final answer. " .
               "Use between {$minThoughts} and {$maxThoughts} thinking steps. " .
               "Format your response as:\n\n" .
               "<thinking>\n" .
               "Step 1: [your first thought]\n" .
               "Step 2: [your second thought]\n" .
               "...\n" .
               "</thinking>\n\n" .
               "[Your final answer]\n\n" .
               "Question: {$content}";
    }
    
    protected function extractThinkingSteps(string $content): array
    {
        preg_match('/<thinking>(.*?)<\/thinking>/s', $content, $matches);
        
        if (!isset($matches[1])) {
            return [];
        }
        
        $thinkingContent = trim($matches[1]);
        $steps = [];
        
        preg_match_all('/Step \d+: (.+?)(?=Step \d+:|$)/s', $thinkingContent, $stepMatches);
        
        foreach ($stepMatches[1] as $step) {
            $steps[] = trim($step);
        }
        
        return $steps;
    }
    
    protected function extractFinalAnswer(string $content): string
    {
        // Remove thinking section and return the rest
        return preg_replace('/<thinking>.*?<\/thinking>\s*/s', '', $content);
    }
    
    protected function assessComplexity(string $content): float
    {
        $indicators = [
            'calculate' => 0.3,
            'analyze' => 0.4,
            'compare' => 0.3,
            'explain' => 0.2,
            'solve' => 0.4,
            'design' => 0.5,
            'plan' => 0.4,
            'why' => 0.3,
            'how' => 0.3,
            'what if' => 0.4,
        ];
        
        $complexity = 0;
        $content = strtolower($content);
        
        foreach ($indicators as $keyword => $weight) {
            if (str_contains($content, $keyword)) {
                $complexity += $weight;
            }
        }
        
        // Length factor
        $lengthFactor = min(strlen($content) / 200, 0.3);
        $complexity += $lengthFactor;
        
        return min($complexity, 1.0);
    }
    
    public function getCapabilities(): array
    {
        return [
            'step_by_step_thinking',
            'complexity_assessment',
            'structured_reasoning',
            'thought_extraction',
        ];
    }
}
```

### MCP Server Framework

```php
<?php

namespace JTD\LaravelAI\Services;

use JTD\LaravelAI\Contracts\MCPServerInterface;

class MCPManager
{
    protected array $servers = [];
    
    public function register(string $name, MCPServerInterface $server): void
    {
        $this->servers[$name] = $server;
    }
    
    public function processMessage(AIMessage $message, array $mcpServers = []): AIMessage
    {
        foreach ($mcpServers as $serverName => $config) {
            if (isset($this->servers[$serverName])) {
                $server = $this->servers[$serverName];
                $message = $server->processMessage($message, $config);
            }
        }
        
        return $message;
    }
    
    public function processResponse(AIResponse $response, array $mcpServers = []): AIResponse
    {
        foreach ($mcpServers as $serverName => $config) {
            if (isset($this->servers[$serverName])) {
                $server = $this->servers[$serverName];
                $response = $server->processResponse($response, $config);
            }
        }
        
        return $response;
    }
    
    public function getAvailableServers(): array
    {
        return collect($this->servers)
            ->map(fn($server) => [
                'name' => $server->getName(),
                'description' => $server->getDescription(),
                'capabilities' => $server->getCapabilities(),
            ])
            ->toArray();
    }
}
```

### Background Jobs

```php
<?php

namespace JTD\LaravelAI\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateUsageAnalyticsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public function __construct(
        public string $period = 'daily',
        public ?string $date = null
    ) {}
    
    public function handle(): void
    {
        $date = $this->date ?? now()->format('Y-m-d');
        
        $analytics = AICostTracking::whereDate('created_at', $date)
            ->selectRaw('
                DATE(created_at) as date,
                provider_id,
                model_id,
                COUNT(*) as total_requests,
                SUM(cost) as total_cost,
                SUM(tokens_used) as total_tokens,
                AVG(cost) as avg_cost_per_request,
                COUNT(DISTINCT user_id) as unique_users
            ')
            ->groupBy(['date', 'provider_id', 'model_id'])
            ->get();
        
        foreach ($analytics as $data) {
            AIUsageAnalytics::updateOrCreate([
                'date' => $data->date,
                'provider_id' => $data->provider_id,
                'model_id' => $data->model_id,
                'period' => $this->period,
            ], [
                'total_requests' => $data->total_requests,
                'total_cost' => $data->total_cost,
                'total_tokens' => $data->total_tokens,
                'avg_cost_per_request' => $data->avg_cost_per_request,
                'unique_users' => $data->unique_users,
            ]);
        }
    }
}
```

## Testing Strategy

### Cost Tracking Tests

```php
<?php

namespace Tests\Feature;

class CostTrackingTest extends TestCase
{
    public function test_cost_is_calculated_automatically(): void
    {
        $conversation = AI::conversation('Cost Test');
        
        $response = $conversation
            ->provider('mock')
            ->message('Hello')
            ->send();
        
        $this->assertGreaterThan(0, $response->cost);
        
        $message = $conversation->messages()->latest()->first();
        $this->assertEquals($response->cost, $message->cost);
    }
    
    public function test_budget_enforcement(): void
    {
        $user = User::factory()->create();
        
        AIBudget::create([
            'user_id' => $user->id,
            'type' => 'daily',
            'limit' => 0.01, // Very low limit
            'is_active' => true,
        ]);
        
        $this->actingAs($user);
        
        $this->expectException(BudgetExceededException::class);
        
        AI::conversation()
            ->message('This should exceed the budget')
            ->send();
    }
}
```

### Sequential Thinking Tests

```php
<?php

namespace Tests\Unit\MCP;

class SequentialThinkingTest extends TestCase
{
    protected SequentialThinkingServer $server;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->server = new SequentialThinkingServer();
    }
    
    public function test_adds_thinking_prompt_for_complex_questions(): void
    {
        $message = new AIMessage('How do I design a scalable microservices architecture?');
        
        $processed = $this->server->processMessage($message, []);
        
        $this->assertStringContainsString('think through this step by step', $processed->content);
        $this->assertTrue($processed->metadata['mcp_sequential_thinking']);
    }
    
    public function test_extracts_thinking_steps_from_response(): void
    {
        $responseContent = "<thinking>\nStep 1: First thought\nStep 2: Second thought\n</thinking>\n\nFinal answer here";
        
        $response = new AIResponse(['content' => $responseContent]);
        $response->metadata['mcp_sequential_thinking'] = true;
        
        $processed = $this->server->processResponse($response, []);
        
        $this->assertEquals('Final answer here', $processed->content);
        $this->assertCount(2, $processed->metadata['thinking_steps']);
        $this->assertEquals('First thought', $processed->metadata['thinking_steps'][0]);
    }
}
```

## Configuration Updates

```php
'cost_tracking' => [
    'enabled' => env('AI_COST_TRACKING_ENABLED', true),
    'currency' => env('AI_COST_CURRENCY', 'USD'),
    'precision' => 6,
    'real_time' => true,
    'batch_processing' => false,
],

'budgets' => [
    'enforcement_enabled' => env('AI_BUDGET_ENFORCEMENT', true),
    'alert_thresholds' => [80, 90, 100],
    'default_limits' => [
        'daily' => 10.00,
        'monthly' => 100.00,
    ],
],

'mcp' => [
    'enabled' => env('AI_MCP_ENABLED', true),
    'servers' => [
        'sequential-thinking' => [
            'enabled' => true,
            'max_thoughts' => 10,
            'min_thoughts' => 2,
            'show_thinking' => false,
        ],
    ],
],

'analytics' => [
    'enabled' => env('AI_ANALYTICS_ENABLED', true),
    'retention_days' => 365,
    'aggregation_frequency' => 'daily',
],
```

## Definition of Done

- [ ] Real-time cost tracking works for all providers
- [ ] Budget management enforces limits correctly
- [ ] Usage analytics provide meaningful insights
- [ ] Sequential Thinking MCP server enhances responses
- [ ] MCP framework supports custom servers
- [ ] Background jobs process analytics efficiently
- [ ] All tests pass with 85%+ coverage
- [ ] Documentation covers cost management and MCP usage

## Next Sprint Preview

Sprint 5 will focus on:
- Advanced features and optimization
- Batch processing and streaming
- Caching and performance improvements
- Multi-tenant support
- Enterprise-level features
