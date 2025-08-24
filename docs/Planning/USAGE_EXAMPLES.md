# Usage Examples and Configuration Guide

## Overview

This guide provides practical examples of using the Laravel AI package's event and middleware systems in real-world scenarios. It covers common use cases, configuration patterns, and best practices for production deployments.

## Quick Start

### Basic Setup

```php
// config/ai.php
return [
    'events' => [
        'enabled' => true,
        'listeners' => [
            'cost_tracking' => ['enabled' => true],
            'analytics' => ['enabled' => true],
            'notifications' => ['enabled' => true],
        ],
    ],
    
    'middleware' => [
        'enabled' => true,
        'global' => [
            'budget_enforcement' => ['enabled' => true],
        ],
    ],
];
```

### Environment Configuration

```bash
# .env
AI_EVENTS_ENABLED=true
AI_MIDDLEWARE_ENABLED=true
AI_BUDGET_ENFORCEMENT_ENABLED=true
AI_DAILY_BUDGET_LIMIT=10.00
AI_MONTHLY_BUDGET_LIMIT=100.00
```

## Common Use Cases

### 1. Budget Management System

**Scenario**: Implement comprehensive budget controls with alerts and automatic cutoffs.

```php
// config/ai.php
'middleware' => [
    'global' => [
        'budget_enforcement' => [
            'enabled' => true,
            'daily_limit' => 25.00,
            'weekly_limit' => 150.00,
            'monthly_limit' => 500.00,
            'per_request_limit' => 2.00,
            'warning_thresholds' => [75, 90], // Percentages
        ],
    ],
],

'events' => [
    'listeners' => [
        'notifications' => [
            'enabled' => true,
            'channels' => ['email', 'slack', 'database'],
            'budget_alerts' => [
                'warning' => ['email'],
                'critical' => ['email', 'slack'],
                'exceeded' => ['email', 'slack', 'sms'],
            ],
        ],
    ],
],
```

**Custom Budget Alert Listener**:

```php
<?php

namespace App\Listeners;

use JTD\LaravelAI\Events\BudgetThresholdReached;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class BudgetAlertListener implements ShouldQueue
{
    public function handle(BudgetThresholdReached $event): void
    {
        $user = User::find($event->userId);
        
        match($event->severity) {
            'warning' => $this->sendWarningAlert($user, $event),
            'critical' => $this->sendCriticalAlert($user, $event),
            'exceeded' => $this->sendExceededAlert($user, $event),
        };
    }
    
    protected function sendWarningAlert($user, $event): void
    {
        Mail::to($user)->send(new BudgetWarningMail($event));
    }
    
    protected function sendCriticalAlert($user, $event): void
    {
        Mail::to($user)->send(new BudgetCriticalMail($event));
        // Send Slack notification to admin channel
        Notification::route('slack', config('services.slack.budget_webhook'))
            ->notify(new BudgetCriticalNotification($event));
    }
    
    protected function sendExceededAlert($user, $event): void
    {
        // Disable user's AI access temporarily
        $user->update(['ai_access_suspended' => true]);
        
        // Send all notifications
        Mail::to($user)->send(new BudgetExceededMail($event));
        Notification::route('slack', config('services.slack.admin_webhook'))
            ->notify(new BudgetExceededNotification($event));
    }
}
```

### 2. Analytics and Monitoring Dashboard

**Scenario**: Track usage patterns, performance metrics, and cost analytics.

```php
// Custom Analytics Listener
<?php

namespace App\Listeners;

use JTD\LaravelAI\Events\ResponseGenerated;
use JTD\LaravelAI\Events\CostCalculated;
use Illuminate\Contracts\Queue\ShouldQueue;

class AdvancedAnalyticsListener implements ShouldQueue
{
    public $queue = 'analytics';
    
    public function handle(ResponseGenerated $event): void
    {
        // Track usage metrics
        $this->recordUsageMetrics($event);
        
        // Track performance metrics
        $this->recordPerformanceMetrics($event);
        
        // Update user statistics
        $this->updateUserStats($event);
    }
    
    public function handleCostCalculated(CostCalculated $event): void
    {
        // Record cost analytics
        $this->recordCostAnalytics($event);
        
        // Update budget tracking
        $this->updateBudgetTracking($event);
    }
    
    protected function recordUsageMetrics($event): void
    {
        AnalyticsMetric::create([
            'user_id' => $event->message->user_id,
            'provider' => $event->response->provider,
            'model' => $event->response->model,
            'input_tokens' => $event->providerMetadata['input_tokens'] ?? 0,
            'output_tokens' => $event->providerMetadata['output_tokens'] ?? 0,
            'processing_time' => $event->totalProcessingTime,
            'timestamp' => now(),
        ]);
    }
    
    protected function recordPerformanceMetrics($event): void
    {
        // Track response times by provider/model
        Redis::zadd(
            "performance:{$event->response->provider}:{$event->response->model}",
            now()->timestamp,
            $event->totalProcessingTime
        );
        
        // Track token efficiency
        $efficiency = $this->calculateTokenEfficiency($event);
        Redis::lpush("efficiency:{$event->response->provider}", $efficiency);
        Redis::ltrim("efficiency:{$event->response->provider}", 0, 999); // Keep last 1000
    }
}
```

### 3. Content Moderation and Safety

**Scenario**: Implement content filtering and safety checks for AI interactions.

```php
// Content Safety Middleware
<?php

namespace App\AI\Middleware;

use JTD\LaravelAI\Contracts\AIMiddlewareInterface;
use JTD\LaravelAI\Exceptions\ContentViolationException;

class ContentSafetyMiddleware implements AIMiddlewareInterface
{
    public function handle(AIMessage $message, Closure $next): AIResponse
    {
        // Pre-process content safety
        $this->checkInputSafety($message);
        
        $response = $next($message);
        
        // Post-process content safety
        $this->checkOutputSafety($response);
        
        return $response;
    }
    
    protected function checkInputSafety(AIMessage $message): void
    {
        $safetyScore = $this->analyzeSafety($message->content);
        
        if ($safetyScore < 0.7) {
            throw new ContentViolationException(
                'Input content violates safety guidelines',
                ['safety_score' => $safetyScore]
            );
        }
        
        // Log safety check
        Log::info('Content safety check passed', [
            'user_id' => $message->user_id,
            'safety_score' => $safetyScore,
        ]);
    }
    
    protected function checkOutputSafety(AIResponse $response): void
    {
        $safetyScore = $this->analyzeSafety($response->content);
        
        if ($safetyScore < 0.8) {
            // Replace with safe response
            $response->content = "I apologize, but I cannot provide that response due to safety guidelines.";
            $response->metadata['safety_filtered'] = true;
            $response->metadata['original_safety_score'] = $safetyScore;
        }
    }
    
    protected function analyzeSafety(string $content): float
    {
        // Integrate with content moderation service
        // Return safety score between 0 and 1
        return app(ContentModerationService::class)->analyze($content);
    }
}
```

### 4. Multi-Tenant Usage Tracking

**Scenario**: Track usage and costs per organization in a multi-tenant application.

```php
// Organization Usage Tracker
<?php

namespace App\Listeners;

use JTD\LaravelAI\Events\CostCalculated;
use Illuminate\Contracts\Queue\ShouldQueue;

class OrganizationUsageTracker implements ShouldQueue
{
    public function handle(CostCalculated $event): void
    {
        $user = User::find($event->userId);
        $organization = $user->organization;
        
        // Update organization usage
        OrganizationUsage::updateOrCreate(
            [
                'organization_id' => $organization->id,
                'date' => now()->toDateString(),
                'provider' => $event->provider,
                'model' => $event->model,
            ],
            [
                'total_cost' => DB::raw("total_cost + {$event->cost}"),
                'total_tokens' => DB::raw("total_tokens + {$event->inputTokens} + {$event->outputTokens}"),
                'request_count' => DB::raw('request_count + 1'),
            ]
        );
        
        // Check organization limits
        $this->checkOrganizationLimits($organization, $event);
    }
    
    protected function checkOrganizationLimits($organization, $event): void
    {
        $monthlyUsage = OrganizationUsage::where('organization_id', $organization->id)
            ->whereMonth('date', now()->month)
            ->sum('total_cost');
            
        if ($monthlyUsage > $organization->monthly_ai_budget) {
            // Suspend organization AI access
            $organization->update(['ai_access_suspended' => true]);
            
            // Notify organization admins
            $organization->admins->each(function ($admin) use ($organization, $monthlyUsage) {
                Mail::to($admin)->send(new OrganizationBudgetExceededMail($organization, $monthlyUsage));
            });
        }
    }
}
```

### 5. A/B Testing and Experimentation

**Scenario**: Test different AI providers and models for performance optimization.

```php
// A/B Testing Middleware
<?php

namespace App\AI\Middleware;

class ABTestingMiddleware implements AIMiddlewareInterface
{
    public function handle(AIMessage $message, Closure $next): AIResponse
    {
        // Determine test group
        $testGroup = $this->getTestGroup($message->user_id);
        
        // Apply test configuration
        $this->applyTestConfiguration($message, $testGroup);
        
        $response = $next($message);
        
        // Record test results
        $this->recordTestResults($message, $response, $testGroup);
        
        return $response;
    }
    
    protected function getTestGroup(int $userId): string
    {
        // Consistent assignment based on user ID
        $hash = crc32($userId);
        return ($hash % 100) < 50 ? 'control' : 'variant';
    }
    
    protected function applyTestConfiguration(AIMessage $message, string $testGroup): void
    {
        $testConfig = config("ai.ab_tests.current_test.{$testGroup}");
        
        if ($testConfig) {
            $message->provider = $testConfig['provider'] ?? $message->provider;
            $message->model = $testConfig['model'] ?? $message->model;
            $message->metadata['ab_test_group'] = $testGroup;
        }
    }
    
    protected function recordTestResults(AIMessage $message, AIResponse $response, string $testGroup): void
    {
        ABTestResult::create([
            'user_id' => $message->user_id,
            'test_group' => $testGroup,
            'provider' => $response->provider,
            'model' => $response->model,
            'response_time' => $response->metadata['processing_time'] ?? 0,
            'token_count' => $response->tokenUsage?->totalTokens ?? 0,
            'cost' => $response->metadata['estimated_cost'] ?? 0,
            'timestamp' => now(),
        ]);
    }
}
```

## Production Configuration

### High-Performance Setup

```php
// config/ai.php - Production optimized
return [
    'events' => [
        'enabled' => true,
        'queue' => [
            'connection' => 'redis',
            'timeout' => 300,
            'retry_after' => 90,
            'max_exceptions' => 3,
        ],
        'listeners' => [
            'cost_tracking' => [
                'enabled' => true,
                'queue' => 'ai-analytics-high',
                'batch_size' => 100,
            ],
            'analytics' => [
                'enabled' => true,
                'queue' => 'ai-analytics-low',
                'batch_size' => 50,
            ],
        ],
    ],
    
    'middleware' => [
        'enabled' => true,
        'performance' => [
            'log_slow_middleware' => true,
            'slow_threshold_ms' => 50,
            'max_execution_time' => 10,
        ],
    ],
];
```

### Queue Worker Configuration

```bash
# Supervisor configuration for queue workers
[program:ai-analytics-high]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work redis --queue=ai-analytics-high --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
numprocs=4
redirect_stderr=true
stdout_logfile=/path/to/logs/ai-analytics-high.log

[program:ai-analytics-low]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work redis --queue=ai-analytics-low --sleep=5 --tries=3 --max-time=3600
autostart=true
autorestart=true
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/logs/ai-analytics-low.log
```

### Monitoring and Alerting

```php
// Custom Health Check
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class AISystemHealthCheck extends Command
{
    protected $signature = 'ai:health-check';
    
    public function handle(): void
    {
        $this->checkQueueHealth();
        $this->checkEventSystemHealth();
        $this->checkMiddlewareHealth();
        $this->checkBudgetSystemHealth();
    }
    
    protected function checkQueueHealth(): void
    {
        $queueSize = Redis::llen('queues:ai-analytics-high');
        
        if ($queueSize > 1000) {
            $this->error("High queue backlog: {$queueSize} jobs");
            // Send alert
            Notification::route('slack', config('services.slack.alerts'))
                ->notify(new QueueBacklogAlert($queueSize));
        }
    }
}
```

## Best Practices Summary

### Performance
- Use Redis for queues and caching
- Batch process analytics data
- Monitor queue sizes and processing times
- Set appropriate timeouts and retry limits

### Security
- Implement content filtering
- Validate all user inputs
- Use rate limiting
- Monitor for abuse patterns

### Reliability
- Handle failures gracefully
- Implement circuit breakers
- Use dead letter queues
- Monitor system health

### Cost Management
- Set appropriate budget limits
- Monitor usage patterns
- Implement automatic cutoffs
- Use cost-effective models when possible

This guide provides a foundation for implementing robust, production-ready AI systems with comprehensive monitoring, safety, and cost controls.
