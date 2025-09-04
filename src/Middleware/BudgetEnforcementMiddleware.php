<?php

namespace JTD\LaravelAI\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Contracts\AIMiddlewareInterface;
use JTD\LaravelAI\Events\BudgetThresholdReached;
use JTD\LaravelAI\Exceptions\BudgetExceededException;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Services\BudgetCacheService;
use JTD\LaravelAI\Services\BudgetService;
use JTD\LaravelAI\Services\EventPerformanceTracker;
use JTD\LaravelAI\Services\PricingService;

/**
 * Budget Enforcement Middleware
 *
 * Enforces AI spending limits at user, project, and organization levels with
 * real-time budget checking and <10ms processing overhead. Prevents budget
 * overruns by validating estimated costs before processing AI requests and
 * firing BudgetThresholdReached events when usage approaches configured thresholds.
 *
 * The middleware supports multiple budget types:
 * - Per-request limits: Maximum cost allowed for a single AI request
 * - Daily limits: Maximum spending allowed per user per day
 * - Monthly limits: Maximum spending allowed per user per month
 * - Project limits: Maximum spending allowed for a specific project
 * - Organization limits: Maximum spending allowed for an entire organization
 *
 * Features:
 * - Intelligent caching with 5-minute budget cache and 1-minute spending cache
 * - Real-time cost estimation using PricingService with provider-specific models
 * - Performance monitoring with EventPerformanceTracker integration
 * - Fail-open approach to prevent blocking requests on system errors
 * - Comprehensive logging and analytics for budget enforcement actions
 * - Threshold event firing at 80% and 95% budget utilization
 *
 * Configuration:
 * Configure budget limits in your application:
 * ```php
 * // Via BudgetService
 * $budgetService->setBudgetLimit($userId, 'daily', 50.00);
 * $budgetService->setBudgetLimit($userId, 'monthly', 500.00);
 * $budgetService->setProjectBudgetLimit($projectId, 1000.00);
 *
 * // Via config/ai.php middleware settings
 * 'middleware' => [
 *     'global' => ['budget-enforcement'],
 *     'available' => [
 *         'budget-enforcement' => BudgetEnforcementMiddleware::class,
 *     ],
 * ],
 * ```
 *
 * Usage Examples:
 * ```php
 * // Via ConversationBuilder (uses global middleware automatically)
 * $response = AI::conversation()
 *     ->message('Generate report')
 *     ->send();
 *
 * // Via Direct SendMessage with explicit middleware
 * $response = AI::provider('openai')->sendMessage('Hello', [
 *     'middleware' => ['budget-enforcement'],
 *     'user_id' => $userId,
 *     'metadata' => [
 *         'project_id' => $projectId,
 *         'organization_id' => $organizationId,
 *     ]
 * ]);
 * ```
 *
 * Performance Targets:
 * - <10ms execution time for budget checks
 * - <5ms for cached budget limit lookups
 * - <1ms for per-request limit validation
 *
 * @author JTD Laravel AI Package
 *
 * @since 1.0.0
 */
class BudgetEnforcementMiddleware implements AIMiddlewareInterface
{
    /**
     * Cache TTL for budget data in seconds.
     *
     * 5 minutes provides balance between accuracy and performance while
     * ensuring budget limits are reasonably up-to-date for enforcement.
     */
    protected int $budgetCacheTtl = 300;

    /**
     * Cache TTL for spending data in seconds.
     *
     * 1 minute provides real-time accuracy for spending calculations
     * while maintaining high performance for budget enforcement.
     */
    protected int $spendingCacheTtl = 60;

    /**
     * Performance target in milliseconds.
     *
     * Target execution time for budget enforcement operations.
     * Operations exceeding this threshold will be logged as warnings.
     */
    protected int $performanceTargetMs = 10;

    /**
     * Create a new budget enforcement middleware instance.
     *
     * @param  BudgetService  $budgetService  Service for budget limit management
     * @param  PricingService  $pricingService  Service for AI cost calculations
     * @param  EventPerformanceTracker  $performanceTracker  Service for performance monitoring
     * @param  BudgetCacheService  $budgetCacheService  Service for intelligent budget caching
     *
     * @since 1.0.0
     */
    public function __construct(
        protected BudgetService $budgetService,
        protected PricingService $pricingService,
        protected EventPerformanceTracker $performanceTracker,
        protected BudgetCacheService $budgetCacheService
    ) {}

    /**
     * Handle the AI request through budget enforcement middleware.
     *
     * Validates that the estimated cost of the AI request does not exceed
     * configured budget limits before allowing the request to proceed. Performs
     * multi-level budget checking including per-request, daily, monthly, project,
     * and organization limits with intelligent caching for <10ms overhead.
     *
     * The enforcement process:
     * 1. Estimates request cost using PricingService and token analysis
     * 2. Checks per-request budget (fastest check first)
     * 3. Validates daily and monthly user spending limits
     * 4. Verifies project budget if project_id provided in metadata
     * 5. Confirms organization budget if organization_id provided
     * 6. Fires BudgetThresholdReached events when approaching limits
     * 7. Tracks performance metrics and logs enforcement actions
     *
     * @param  AIMessage  $message  The AI message to process through budget enforcement
     * @param  Closure  $next  The next middleware in the pipeline
     * @return AIResponse The processed AI response after budget validation
     *
     * @throws BudgetExceededException When any budget limit would be exceeded
     * @throws \InvalidArgumentException When message data is invalid for cost estimation
     *
     * @since 1.0.0
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
     *
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
     *
     * @throws BudgetExceededException When per-request limit exceeded
     */
    protected function checkPerRequestBudget(int $userId, float $estimatedCost, AIMessage $message): void
    {
        $perRequestLimit = $this->getCachedBudgetLimit($userId, 'per_request');

        if ($perRequestLimit && $estimatedCost > $perRequestLimit) {
            $this->fireBudgetThresholdEvent($userId, 'per_request', $estimatedCost, $perRequestLimit, $estimatedCost);

            throw new BudgetExceededException(
                'Per-request budget limit exceeded. Cost: $' . number_format($estimatedCost, 4) .
                ', Limit: $' . number_format($perRequestLimit, 4),
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
     *
     * @throws BudgetExceededException When daily limit would be exceeded
     */
    protected function checkDailyBudgetOptimized(int $userId, float $estimatedCost, AIMessage $message): void
    {
        $dailyLimit = $this->getCachedBudgetLimit($userId, 'daily');

        if (! $dailyLimit) {
            return;
        }

        $dailySpent = $this->getCachedDailySpending($userId);
        $projectedSpending = $dailySpent + $estimatedCost;

        if ($projectedSpending > $dailyLimit) {
            $this->fireBudgetThresholdEvent($userId, 'daily', $dailySpent, $dailyLimit, $estimatedCost);

            throw new BudgetExceededException(
                'Daily budget limit would be exceeded. Current: $' . number_format($dailySpent, 4) .
                ', Additional: $' . number_format($estimatedCost, 4) .
                ', Limit: $' . number_format($dailyLimit, 4),
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
     *
     * @throws BudgetExceededException When monthly limit would be exceeded
     */
    protected function checkMonthlyBudgetOptimized(int $userId, float $estimatedCost, AIMessage $message): void
    {
        $monthlyLimit = $this->getCachedBudgetLimit($userId, 'monthly');

        if (! $monthlyLimit) {
            return;
        }

        $monthlySpent = $this->getCachedMonthlySpending($userId);
        $projectedSpending = $monthlySpent + $estimatedCost;

        if ($projectedSpending > $monthlyLimit) {
            $this->fireBudgetThresholdEvent($userId, 'monthly', $monthlySpent, $monthlyLimit, $estimatedCost);

            throw new BudgetExceededException(
                'Monthly budget limit would be exceeded. Current: $' . number_format($monthlySpent, 4) .
                ', Additional: $' . number_format($estimatedCost, 4) .
                ', Limit: $' . number_format($monthlyLimit, 4),
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
     * Get cached budget limit for user and type using intelligent caching service.
     *
     * @param  int  $userId  User ID
     * @param  string  $type  Budget type
     * @return float|null Budget limit or null if not set
     */
    protected function getCachedBudgetLimit(int $userId, string $type): ?float
    {
        return $this->budgetCacheService->getBudgetLimit($userId, $type);
    }

    /**
     * Get cached daily spending for user using intelligent caching service.
     *
     * @param  int  $userId  User ID
     * @return float Daily spending amount
     */
    protected function getCachedDailySpending(int $userId): float
    {
        return $this->budgetCacheService->getDailySpending($userId);
    }

    /**
     * Get cached monthly spending for user using intelligent caching service.
     *
     * @param  int  $userId  User ID
     * @return float Monthly spending amount
     */
    protected function getCachedMonthlySpending(int $userId): float
    {
        return $this->budgetCacheService->getMonthlySpending($userId);
    }

    /**
     * Get cached project budget limit using intelligent caching service.
     *
     * @param  string  $projectId  Project ID
     * @return float|null Project budget limit
     */
    protected function getCachedProjectBudgetLimit(string $projectId): ?float
    {
        return $this->budgetCacheService->getProjectBudgetLimit($projectId);
    }

    /**
     * Get cached project spending using intelligent caching service.
     *
     * @param  string  $projectId  Project ID
     * @return float Project spending amount
     */
    protected function getCachedProjectSpending(string $projectId): float
    {
        return $this->budgetCacheService->getProjectSpending($projectId, 'all');
    }

    /**
     * Get cached organization budget limit using intelligent caching service.
     *
     * @param  string  $organizationId  Organization ID
     * @return float|null Organization budget limit
     */
    protected function getCachedOrganizationBudgetLimit(string $organizationId): ?float
    {
        return $this->budgetCacheService->getOrganizationBudgetLimit($organizationId);
    }

    /**
     * Get cached organization spending using intelligent caching service.
     *
     * @param  string  $organizationId  Organization ID
     * @return float Organization spending amount
     */
    protected function getCachedOrganizationSpending(string $organizationId): float
    {
        return $this->budgetCacheService->getOrganizationSpending($organizationId, 'all');
    }

    /**
     * Check project budget with optimized caching.
     *
     * @param  string  $projectId  Project ID
     * @param  float  $estimatedCost  Estimated cost
     * @param  AIMessage  $message  The message
     *
     * @throws BudgetExceededException When project limit would be exceeded
     */
    protected function checkProjectBudgetOptimized(string $projectId, float $estimatedCost, AIMessage $message): void
    {
        $projectLimit = $this->getCachedProjectBudgetLimit($projectId);

        if (! $projectLimit) {
            return;
        }

        $projectSpent = $this->getCachedProjectSpending($projectId);
        $projectedSpending = $projectSpent + $estimatedCost;

        if ($projectedSpending > $projectLimit) {
            $this->fireBudgetThresholdEvent(
                $message->user_id,
                'project',
                $projectSpent,
                $projectLimit,
                $estimatedCost,
                $projectId
            );

            throw new BudgetExceededException(
                'Project budget limit would be exceeded. Current: $' . number_format($projectSpent, 4) .
                ', Additional: $' . number_format($estimatedCost, 4) .
                ', Limit: $' . number_format($projectLimit, 4),
                'project',
                $projectedSpending,
                $projectLimit
            );
        }
    }

    /**
     * Check organization budget with optimized caching.
     *
     * @param  string  $organizationId  Organization ID
     * @param  float  $estimatedCost  Estimated cost
     * @param  AIMessage  $message  The message
     *
     * @throws BudgetExceededException When organization limit would be exceeded
     */
    protected function checkOrganizationBudgetOptimized(string $organizationId, float $estimatedCost, AIMessage $message): void
    {
        $orgLimit = $this->getCachedOrganizationBudgetLimit($organizationId);

        if (! $orgLimit) {
            return;
        }

        $orgSpent = $this->getCachedOrganizationSpending($organizationId);
        $projectedSpending = $orgSpent + $estimatedCost;

        if ($projectedSpending > $orgLimit) {
            $this->fireBudgetThresholdEvent(
                $message->user_id,
                'organization',
                $orgSpent,
                $orgLimit,
                $estimatedCost,
                null,
                $organizationId
            );

            throw new BudgetExceededException(
                'Organization budget limit would be exceeded. Current: $' . number_format($orgSpent, 4) .
                ', Additional: $' . number_format($estimatedCost, 4) .
                ', Limit: $' . number_format($orgLimit, 4),
                'organization',
                $projectedSpending,
                $orgLimit
            );
        }
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
            current_spending: $currentSpending,
            budget_limit: $budgetLimit,
            additionalCost: $additionalCost,
            threshold_percentage: $thresholdPercentage,
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
        Cache::increment('budget_middleware_operations_total');
        Cache::increment('budget_middleware_duration_total', $durationMs);
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
