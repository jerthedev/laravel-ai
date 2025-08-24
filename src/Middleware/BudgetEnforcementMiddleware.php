<?php

namespace JTD\LaravelAI\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Contracts\AIMiddlewareInterface;
use JTD\LaravelAI\Events\BudgetThresholdReached;
use JTD\LaravelAI\Exceptions\BudgetExceededException;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Services\BudgetService;
use JTD\LaravelAI\Services\EventPerformanceTracker;
use JTD\LaravelAI\Services\PricingService;

/**
 * Enhanced Budget Enforcement Middleware
 *
 * Enforces spending limits with <10ms processing overhead and real-time enforcement.
 * Supports monthly, daily, and per-request budgets with intelligent caching and
 * optimized database queries for high-performance budget checking.
 */
class BudgetEnforcementMiddleware implements AIMiddlewareInterface
{
    /**
     * Cache TTL for budget data (5 minutes for balance between accuracy and performance).
     */
    protected int $budgetCacheTtl = 300;

    /**
     * Cache TTL for spending data (1 minute for real-time accuracy).
     */
    protected int $spendingCacheTtl = 60;

    /**
     * Performance target in milliseconds.
     */
    protected int $performanceTargetMs = 10;

    /**
     * Create a new middleware instance.
     */
    public function __construct(
        protected BudgetService $budgetService,
        protected PricingService $pricingService,
        protected EventPerformanceTracker $performanceTracker
    ) {}

    /**
     * Handle the AI request through enhanced budget enforcement with <10ms overhead.
     *
     * @param  AIMessage  $message  The AI message to process
     * @param  Closure  $next  The next middleware in the pipeline
     * @return AIResponse The processed response
     * @throws BudgetExceededException When budget limits would be exceeded
     */
    public function handle(AIMessage $message, Closure $next): AIResponse
    {
        $startTime = microtime(true);

        try {
            // Fast cost estimation with caching
            $estimatedCost = $this->estimateRequestCostOptimized($message);

            // Enhanced budget checking with real-time enforcement
            $this->performEnhancedBudgetChecking($message, $estimatedCost);

            // Proceed with request
            $response = $next($message);

            // Track performance metrics
            $this->trackMiddlewarePerformance($startTime, $message);

            return $response;

        } catch (BudgetExceededException $e) {
            // Log budget enforcement action
            $this->logBudgetEnforcement($message, $estimatedCost ?? 0, $e);

            // Track performance even on failure
            $this->trackMiddlewarePerformance($startTime, $message, 'budget_exceeded');

            throw $e;
        } catch (\Exception $e) {
            // Log unexpected errors but don't block requests
            Log::error('Budget enforcement middleware error', [
                'user_id' => $message->user_id,
                'error' => $e->getMessage(),
                'processing_time_ms' => (microtime(true) - $startTime) * 1000,
            ]);

            // Continue with request if budget checking fails (fail-open approach)
            return $next($message);
        }
    }

    /**
     * Optimized cost estimation with caching for <10ms performance.
     *
     * @param  AIMessage  $message  The message to estimate cost for
     * @return float The estimated cost in USD
     */
    protected function estimateRequestCostOptimized(AIMessage $message): float
    {
        $provider = $message->provider ?? 'openai';
        $model = $message->model ?? $this->getDefaultModel($provider);
        $contentLength = strlen($message->content);

        // Cache key for cost estimation
        $cacheKey = "cost_estimate_{$provider}_{$model}_{$contentLength}";

        return Cache::remember($cacheKey, 300, function () use ($message, $provider, $model) {
            $estimatedTokens = $this->estimateTokens($message->content);

            // Use centralized pricing service for cost calculation
            $inputTokens = (int) ($estimatedTokens * 0.75); // Estimate 75% input
            $outputTokens = (int) ($estimatedTokens * 0.25); // Estimate 25% output

            try {
                $costData = $this->pricingService->calculateCost($provider, $model, $inputTokens, $outputTokens);
                return $costData['total_cost'] ?? 0.0;
            } catch (\Exception $e) {
                // Fallback to basic estimation if pricing service fails
                return $this->getFallbackCostEstimate($provider, $model, $estimatedTokens);
            }
        });
    }

    /**
     * Perform enhanced budget checking with real-time enforcement.
     *
     * @param  AIMessage  $message  The AI message
     * @param  float  $estimatedCost  Estimated cost
     * @throws BudgetExceededException When budget limits would be exceeded
     */
    protected function performEnhancedBudgetChecking(AIMessage $message, float $estimatedCost): void
    {
        $userId = $message->user_id;
        $projectId = $message->metadata['project_id'] ?? null;
        $organizationId = $message->metadata['organization_id'] ?? null;

        // Check per-request budget (fastest check first)
        $this->checkPerRequestBudget($userId, $estimatedCost, $message);

        // Check daily budget with optimized caching
        $this->checkDailyBudgetOptimized($userId, $estimatedCost, $message);

        // Check monthly budget with optimized caching
        $this->checkMonthlyBudgetOptimized($userId, $estimatedCost, $message);

        // Check project budget if applicable
        if ($projectId) {
            $this->checkProjectBudgetOptimized($projectId, $estimatedCost, $message);
        }

        // Check organization budget if applicable
        if ($organizationId) {
            $this->checkOrganizationBudgetOptimized($organizationId, $estimatedCost, $message);
        }
    }

    /**
     * Estimate token count from content.
     *
     * @param  string  $content  The content to estimate
     * @return int The estimated token count
     */
    protected function estimateTokens(string $content): int
    {
        // Rough estimation: 1 token ≈ 4 characters for English text
        return (int) ceil(strlen($content) / 4);
    }

    /**
     * Get cost estimate using the enhanced centralized PricingService.
     *
     * @param  string  $provider  The provider name
     * @param  int  $tokens  The estimated token count
     * @param  string  $model  The model name
     * @return float The estimated cost
     */
    protected function getProviderCostEstimate(string $provider, int $tokens, string $model): float
    {
        try {
            // Use the enhanced PricingService with database-first fallback
            $inputTokens = (int) ($tokens * 0.75); // Estimate 75% input
            $outputTokens = (int) ($tokens * 0.25); // Estimate 25% output

            $costData = $this->pricingService->calculateCost($provider, $model, $inputTokens, $outputTokens);

            return $costData['total_cost'] ?? $tokens * 0.00001;
        } catch (\Exception $e) {
            // Fallback on error
            return $tokens * 0.00001;
        }
    }

    /**
     * Check per-request budget limit.
     *
     * @param  int  $userId  User ID
     * @param  float  $estimatedCost  Estimated cost
     * @param  AIMessage  $message  The message
     * @throws BudgetExceededException When per-request limit exceeded
     */
    protected function checkPerRequestBudget(int $userId, float $estimatedCost, AIMessage $message): void
    {
        $perRequestLimit = $this->getCachedBudgetLimit($userId, 'per_request');

        if ($perRequestLimit && $estimatedCost > $perRequestLimit) {
            $this->fireBudgetThresholdEvent($userId, 'per_request', $estimatedCost, $perRequestLimit, $estimatedCost);

            throw new BudgetExceededException(
                "Per-request budget limit exceeded. Cost: $" . number_format($estimatedCost, 4) .
                ", Limit: $" . number_format($perRequestLimit, 4),
                'per_request',
                $estimatedCost,
                $perRequestLimit
            );
        }
    }

    /**
     * Check daily budget with optimized caching.
     *
     * @param  int  $userId  User ID
     * @param  float  $estimatedCost  Estimated cost
     * @param  AIMessage  $message  The message
     * @throws BudgetExceededException When daily limit would be exceeded
     */
    protected function checkDailyBudgetOptimized(int $userId, float $estimatedCost, AIMessage $message): void
    {
        $dailyLimit = $this->getCachedBudgetLimit($userId, 'daily');

        if (!$dailyLimit) {
            return;
        }

        $dailySpent = $this->getCachedDailySpending($userId);
        $projectedSpending = $dailySpent + $estimatedCost;

        if ($projectedSpending > $dailyLimit) {
            $this->fireBudgetThresholdEvent($userId, 'daily', $dailySpent, $dailyLimit, $estimatedCost);

            throw new BudgetExceededException(
                "Daily budget limit would be exceeded. Current: $" . number_format($dailySpent, 4) .
                ", Additional: $" . number_format($estimatedCost, 4) .
                ", Limit: $" . number_format($dailyLimit, 4),
                'daily',
                $projectedSpending,
                $dailyLimit
            );
        }
    }

    /**
     * Check monthly budget with optimized caching.
     *
     * @param  int  $userId  User ID
     * @param  float  $estimatedCost  Estimated cost
     * @param  AIMessage  $message  The message
     * @throws BudgetExceededException When monthly limit would be exceeded
     */
    protected function checkMonthlyBudgetOptimized(int $userId, float $estimatedCost, AIMessage $message): void
    {
        $monthlyLimit = $this->getCachedBudgetLimit($userId, 'monthly');

        if (!$monthlyLimit) {
            return;
        }

        $monthlySpent = $this->getCachedMonthlySpending($userId);
        $projectedSpending = $monthlySpent + $estimatedCost;

        if ($projectedSpending > $monthlyLimit) {
            $this->fireBudgetThresholdEvent($userId, 'monthly', $monthlySpent, $monthlyLimit, $estimatedCost);

            throw new BudgetExceededException(
                "Monthly budget limit would be exceeded. Current: $" . number_format($monthlySpent, 4) .
                ", Additional: $" . number_format($estimatedCost, 4) .
                ", Limit: $" . number_format($monthlyLimit, 4),
                'monthly',
                $projectedSpending,
                $monthlyLimit
            );
        }
    }

    /**
     * Get default model for a provider.
     *
     * @param  string  $provider  The provider name
     * @return string The default model
     */
    protected function getDefaultModel(string $provider): string
    {
        return match ($provider) {
            'openai' => 'gpt-4o-mini',
            'gemini' => 'gemini-2.0-flash',
            'xai' => 'grok-2-1212',
            default => 'gpt-4o-mini',
        };
    }

    // Optimized caching methods for <10ms performance

    /**
     * Get cached budget limit for user and type.
     *
     * @param  int  $userId  User ID
     * @param  string  $type  Budget type
     * @return float|null Budget limit or null if not set
     */
    protected function getCachedBudgetLimit(int $userId, string $type): ?float
    {
        $cacheKey = "budget_limit_{$userId}_{$type}";

        return Cache::remember($cacheKey, $this->budgetCacheTtl, function () use ($userId, $type) {
            return DB::table('ai_budgets')
                ->where('user_id', $userId)
                ->where('type', $type)
                ->where('is_active', true)
                ->value('limit_amount');
        });
    }

    /**
     * Get cached daily spending for user.
     *
     * @param  int  $userId  User ID
     * @return float Daily spending amount
     */
    protected function getCachedDailySpending(int $userId): float
    {
        $cacheKey = "daily_spending_{$userId}_" . now()->format('Y-m-d');

        return Cache::remember($cacheKey, $this->spendingCacheTtl, function () use ($userId) {
            return (float) DB::table('ai_usage_costs')
                ->where('user_id', $userId)
                ->whereDate('created_at', today())
                ->sum('total_cost');
        });
    }

    /**
     * Get cached monthly spending for user.
     *
     * @param  int  $userId  User ID
     * @return float Monthly spending amount
     */
    protected function getCachedMonthlySpending(int $userId): float
    {
        $cacheKey = "monthly_spending_{$userId}_" . now()->format('Y-m');

        return Cache::remember($cacheKey, $this->spendingCacheTtl, function () use ($userId) {
            return (float) DB::table('ai_usage_costs')
                ->where('user_id', $userId)
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->sum('total_cost');
        });
    }

    /**
     * Get cached project budget limit.
     *
     * @param  string  $projectId  Project ID
     * @return float|null Project budget limit
     */
    protected function getCachedProjectBudgetLimit(string $projectId): ?float
    {
        $cacheKey = "project_budget_limit_{$projectId}";

        return Cache::remember($cacheKey, $this->budgetCacheTtl, function () use ($projectId) {
            return DB::table('ai_budgets')
                ->where('project_id', $projectId)
                ->where('type', 'project')
                ->where('is_active', true)
                ->value('limit_amount');
        });
    }

    /**
     * Get cached project spending.
     *
     * @param  string  $projectId  Project ID
     * @return float Project spending amount
     */
    protected function getCachedProjectSpending(string $projectId): float
    {
        $cacheKey = "project_spending_{$projectId}_" . now()->format('Y-m-d');

        return Cache::remember($cacheKey, $this->spendingCacheTtl, function () use ($projectId) {
            return (float) DB::table('ai_usage_costs')
                ->whereJsonContains('metadata->context->project_id', $projectId)
                ->sum('total_cost');
        });
    }

    /**
     * Get cached organization budget limit.
     *
     * @param  string  $organizationId  Organization ID
     * @return float|null Organization budget limit
     */
    protected function getCachedOrganizationBudgetLimit(string $organizationId): ?float
    {
        $cacheKey = "org_budget_limit_{$organizationId}";

        return Cache::remember($cacheKey, $this->budgetCacheTtl, function () use ($organizationId) {
            return DB::table('ai_budgets')
                ->where('organization_id', $organizationId)
                ->where('type', 'organization')
                ->where('is_active', true)
                ->value('limit_amount');
        });
    }

    /**
     * Get cached organization spending.
     *
     * @param  string  $organizationId  Organization ID
     * @return float Organization spending amount
     */
    protected function getCachedOrganizationSpending(string $organizationId): float
    {
        $cacheKey = "org_spending_{$organizationId}_" . now()->format('Y-m-d');

        return Cache::remember($cacheKey, $this->spendingCacheTtl, function () use ($organizationId) {
            return (float) DB::table('ai_usage_costs')
                ->whereJsonContains('metadata->context->organization_id', $organizationId)
                ->sum('total_cost');
        });
    }

    /**
     * Fire budget threshold reached event.
     *
     * @param  int  $userId  User ID
     * @param  string  $budgetType  Budget type
     * @param  float  $currentSpending  Current spending
     * @param  float  $budgetLimit  Budget limit
     * @param  float  $additionalCost  Additional cost
     * @param  string|null  $projectId  Project ID
     * @param  string|null  $organizationId  Organization ID
     */
    protected function fireBudgetThresholdEvent(
        int $userId,
        string $budgetType,
        float $currentSpending,
        float $budgetLimit,
        float $additionalCost,
        ?string $projectId = null,
        ?string $organizationId = null
    ): void {
        $thresholdPercentage = (($currentSpending + $additionalCost) / $budgetLimit) * 100;

        event(new BudgetThresholdReached(
            userId: $userId,
            budgetType: $budgetType,
            currentSpending: $currentSpending,
            budgetLimit: $budgetLimit,
            additionalCost: $additionalCost,
            thresholdPercentage: $thresholdPercentage,
            projectId: $projectId,
            organizationId: $organizationId
        ));
    }

    /**
     * Get fallback cost estimate.
     *
     * @param  string  $provider  Provider name
     * @param  string  $model  Model name
     * @param  int  $tokens  Token count
     * @return float Fallback cost estimate
     */
    protected function getFallbackCostEstimate(string $provider, string $model, int $tokens): float
    {
        // Simple fallback pricing per 1k tokens
        $fallbackRates = [
            'openai' => 0.002,
            'anthropic' => 0.003,
            'google' => 0.001,
            'azure' => 0.002,
        ];

        $rate = $fallbackRates[$provider] ?? 0.002;
        return ($tokens / 1000) * $rate;
    }

    /**
     * Track middleware performance metrics.
     *
     * @param  float  $startTime  Start time
     * @param  AIMessage  $message  The message
     * @param  string|null  $outcome  Outcome type
     */
    protected function trackMiddlewarePerformance(float $startTime, AIMessage $message, ?string $outcome = 'success'): void
    {
        $durationMs = (microtime(true) - $startTime) * 1000;

        // Track with EventPerformanceTracker
        $this->performanceTracker->trackMiddlewarePerformance('BudgetEnforcementMiddleware', $durationMs, [
            'user_id' => $message->user_id,
            'provider' => $message->provider,
            'model' => $message->model,
            'outcome' => $outcome,
            'success' => $outcome === 'success',
            'memory_usage' => memory_get_usage(true),
            'project_id' => $message->metadata['project_id'] ?? null,
            'organization_id' => $message->metadata['organization_id'] ?? null,
        ]);

        // Log slow operations
        if ($durationMs > $this->performanceTargetMs) {
            Log::warning('Budget enforcement middleware exceeded performance target', [
                'duration_ms' => round($durationMs, 2),
                'target_ms' => $this->performanceTargetMs,
                'user_id' => $message->user_id,
                'provider' => $message->provider,
                'outcome' => $outcome,
            ]);
        }

        // Track legacy performance metrics for backward compatibility
        Cache::increment("budget_middleware_operations_total");
        Cache::increment("budget_middleware_duration_total", $durationMs);
        Cache::increment("budget_middleware_outcome_{$outcome}");
    }

    /**
     * Log budget enforcement action.
     *
     * @param  AIMessage  $message  The message
     * @param  float  $estimatedCost  Estimated cost
     * @param  BudgetExceededException  $exception  The exception
     */
    protected function logBudgetEnforcement(AIMessage $message, float $estimatedCost, BudgetExceededException $exception): void
    {
        Log::info('Budget enforcement action taken', [
            'user_id' => $message->user_id,
            'provider' => $message->provider,
            'model' => $message->model,
            'estimated_cost' => $estimatedCost,
            'budget_type' => $exception->getBudgetType(),
            'budget_limit' => $exception->getBudgetLimit(),
            'projected_spending' => $exception->getProjectedSpending(),
            'project_id' => $message->metadata['project_id'] ?? null,
            'organization_id' => $message->metadata['organization_id'] ?? null,
        ]);
    }
}
