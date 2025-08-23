<?php

namespace JTD\LaravelAI\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service for estimating and managing costs of AI-powered pricing discovery operations.
 *
 * This service provides cost estimation, budget tracking, and confirmation workflows
 * for pricing discovery operations to prevent unexpected charges.
 */
class DiscoveryCostEstimator
{
    protected array $costRates = [
        'brave_search' => 0.001, // $0.001 per search query
        'ai_processing' => 0.0001, // $0.0001 per result processed
        'extraction' => 0.00001, // $0.00001 per extraction attempt
    ];

    /**
     * Estimate the total cost of a pricing discovery operation.
     *
     * @param  string  $provider  AI provider name
     * @param  string  $model  Model name
     * @param  array  $options  Discovery options
     * @return array Cost estimation breakdown
     */
    public function estimateDiscoveryCost(string $provider, string $model, array $options = []): array
    {
        $searchQueries = $this->getSearchQueriesCount($provider, $model, $options);
        $expectedResults = $this->getExpectedResultsCount($searchQueries, $options);
        $extractionAttempts = $this->getExtractionAttemptsCount($expectedResults, $options);

        $costs = [
            'search_cost' => $searchQueries * $this->costRates['brave_search'],
            'processing_cost' => $expectedResults * $this->costRates['ai_processing'],
            'extraction_cost' => $extractionAttempts * $this->costRates['extraction'],
        ];

        $totalCost = array_sum($costs);

        return [
            'provider' => $provider,
            'model' => $model,
            'breakdown' => $costs,
            'total_cost' => $totalCost,
            'currency' => 'USD',
            'search_queries' => $searchQueries,
            'expected_results' => $expectedResults,
            'extraction_attempts' => $extractionAttempts,
            'estimated_at' => now()->toISOString(),
        ];
    }

    /**
     * Check if a discovery operation is within budget limits.
     *
     * @param  float  $estimatedCost  The estimated cost
     * @param  array  $options  Budget options
     * @return array Budget check result
     */
    public function checkBudget(float $estimatedCost, array $options = []): array
    {
        $maxCost = $options['max_cost'] ?? config('ai.model_sync.ai_discovery.max_cost_per_discovery', 0.01);
        $dailyBudget = $options['daily_budget'] ?? config('ai.model_sync.ai_discovery.daily_budget', 1.0);

        $withinMaxCost = $estimatedCost <= $maxCost;
        $dailySpent = $this->getDailySpent();
        $withinDailyBudget = ($dailySpent + $estimatedCost) <= $dailyBudget;

        return [
            'within_max_cost' => $withinMaxCost,
            'within_daily_budget' => $withinDailyBudget,
            'approved' => $withinMaxCost && $withinDailyBudget,
            'estimated_cost' => $estimatedCost,
            'max_cost' => $maxCost,
            'daily_spent' => $dailySpent,
            'daily_budget' => $dailyBudget,
            'daily_remaining' => max(0, $dailyBudget - $dailySpent),
            'reasons' => $this->getBudgetReasons($withinMaxCost, $withinDailyBudget, $estimatedCost, $maxCost, $dailySpent, $dailyBudget),
        ];
    }

    /**
     * Generate a confirmation prompt for the user.
     *
     * @param  string  $provider  AI provider name
     * @param  string  $model  Model name
     * @param  array  $costEstimate  Cost estimation data
     * @return array Confirmation prompt data
     */
    public function generateConfirmationPrompt(string $provider, string $model, array $costEstimate): array
    {
        $totalCost = $costEstimate['total_cost'];
        $searchQueries = $costEstimate['search_queries'];

        return [
            'title' => 'Confirm AI Pricing Discovery',
            'message' => "Discover pricing for {$provider} {$model}?",
            'details' => [
                'Provider' => $provider,
                'Model' => $model,
                'Estimated Cost' => '$' . number_format($totalCost, 4),
                'Search Queries' => $searchQueries,
                'Expected Results' => $costEstimate['expected_results'],
            ],
            'cost_breakdown' => [
                'Search Queries' => '$' . number_format($costEstimate['breakdown']['search_cost'], 4),
                'Result Processing' => '$' . number_format($costEstimate['breakdown']['processing_cost'], 4),
                'Price Extraction' => '$' . number_format($costEstimate['breakdown']['extraction_cost'], 4),
            ],
            'warnings' => $this->getConfirmationWarnings($costEstimate),
            'confirmation_required' => true,
            'timeout_seconds' => 300, // 5 minutes
        ];
    }

    /**
     * Record actual cost after discovery operation.
     *
     * @param  string  $provider  AI provider name
     * @param  string  $model  Model name
     * @param  float  $actualCost  The actual cost incurred
     * @param  array  $metadata  Additional metadata
     */
    public function recordActualCost(string $provider, string $model, float $actualCost, array $metadata = []): void
    {
        $today = now()->toDateString();
        $cacheKey = "discovery_costs:{$today}";

        $dailyCosts = Cache::get($cacheKey, []);
        $dailyCosts[] = [
            'provider' => $provider,
            'model' => $model,
            'cost' => $actualCost,
            'timestamp' => now()->toISOString(),
            'metadata' => $metadata,
        ];

        Cache::put($cacheKey, $dailyCosts, 86400); // Cache for 24 hours

        // Log for audit trail
        Log::info('AI discovery cost recorded', [
            'provider' => $provider,
            'model' => $model,
            'cost' => $actualCost,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Get daily spending statistics.
     *
     * @return array Daily spending data
     */
    public function getDailySpendingStats(): array
    {
        $today = now()->toDateString();
        $cacheKey = "discovery_costs:{$today}";

        $dailyCosts = Cache::get($cacheKey, []);
        $totalSpent = array_sum(array_column($dailyCosts, 'cost'));
        $operationCount = count($dailyCosts);

        $providerBreakdown = [];
        foreach ($dailyCosts as $cost) {
            $provider = $cost['provider'];
            if (! isset($providerBreakdown[$provider])) {
                $providerBreakdown[$provider] = ['cost' => 0, 'operations' => 0];
            }
            $providerBreakdown[$provider]['cost'] += $cost['cost'];
            $providerBreakdown[$provider]['operations']++;
        }

        return [
            'date' => $today,
            'total_spent' => $totalSpent,
            'operation_count' => $operationCount,
            'average_cost_per_operation' => $operationCount > 0 ? $totalSpent / $operationCount : 0,
            'provider_breakdown' => $providerBreakdown,
            'daily_budget' => config('ai.model_sync.ai_discovery.daily_budget', 1.0),
            'budget_remaining' => max(0, config('ai.model_sync.ai_discovery.daily_budget', 1.0) - $totalSpent),
            'budget_utilization' => config('ai.model_sync.ai_discovery.daily_budget', 1.0) > 0
                ? ($totalSpent / config('ai.model_sync.ai_discovery.daily_budget', 1.0)) * 100
                : 0,
        ];
    }

    /**
     * Get historical spending data.
     *
     * @param  int  $days  Number of days to look back
     * @return array Historical spending data
     */
    public function getHistoricalSpending(int $days = 7): array
    {
        $history = [];

        for ($i = 0; $i < $days; $i++) {
            $date = now()->subDays($i)->toDateString();
            $cacheKey = "discovery_costs:{$date}";
            $dailyCosts = Cache::get($cacheKey, []);

            $history[$date] = [
                'date' => $date,
                'total_spent' => array_sum(array_column($dailyCosts, 'cost')),
                'operation_count' => count($dailyCosts),
            ];
        }

        return [
            'period_days' => $days,
            'daily_data' => $history,
            'total_spent' => array_sum(array_column($history, 'total_spent')),
            'total_operations' => array_sum(array_column($history, 'operation_count')),
            'average_daily_spend' => count($history) > 0 ? array_sum(array_column($history, 'total_spent')) / count($history) : 0,
        ];
    }

    /**
     * Get the number of search queries for a discovery operation.
     */
    private function getSearchQueriesCount(string $provider, string $model, array $options): int
    {
        $baseQueries = 3; // Default number of search queries

        if (isset($options['thorough']) && $options['thorough']) {
            $baseQueries = 5;
        }

        if (isset($options['quick']) && $options['quick']) {
            $baseQueries = 2;
        }

        return $baseQueries;
    }

    /**
     * Get expected number of results from search queries.
     */
    private function getExpectedResultsCount(int $searchQueries, array $options): int
    {
        $resultsPerQuery = $options['results_per_query'] ?? 5;

        return $searchQueries * $resultsPerQuery;
    }

    /**
     * Get expected number of extraction attempts.
     */
    private function getExtractionAttemptsCount(int $expectedResults, array $options): int
    {
        // Assume we'll attempt extraction on all results
        return $expectedResults;
    }

    /**
     * Get current daily spending.
     */
    private function getDailySpent(): float
    {
        $today = now()->toDateString();
        $cacheKey = "discovery_costs:{$today}";
        $dailyCosts = Cache::get($cacheKey, []);

        return array_sum(array_column($dailyCosts, 'cost'));
    }

    /**
     * Get budget check reasons.
     */
    private function getBudgetReasons(bool $withinMaxCost, bool $withinDailyBudget, float $estimatedCost, float $maxCost, float $dailySpent, float $dailyBudget): array
    {
        $reasons = [];

        if (! $withinMaxCost) {
            $reasons[] = "Estimated cost (\${$estimatedCost}) exceeds maximum allowed (\${$maxCost})";
        }

        if (! $withinDailyBudget) {
            $remaining = $dailyBudget - $dailySpent;
            $reasons[] = "Would exceed daily budget. Remaining: \${$remaining}, needed: \${$estimatedCost}";
        }

        if (empty($reasons)) {
            $reasons[] = 'Within all budget limits';
        }

        return $reasons;
    }

    /**
     * Get confirmation warnings.
     */
    private function getConfirmationWarnings(array $costEstimate): array
    {
        $warnings = [];
        $totalCost = $costEstimate['total_cost'];

        if ($totalCost > 0.005) {
            $warnings[] = 'Cost is relatively high for a single discovery operation';
        }

        if ($costEstimate['search_queries'] > 5) {
            $warnings[] = 'Large number of search queries may take longer to complete';
        }

        $dailySpent = $this->getDailySpent();
        $dailyBudget = config('ai.model_sync.ai_discovery.daily_budget', 1.0);

        if (($dailySpent + $totalCost) > ($dailyBudget * 0.8)) {
            $warnings[] = 'This operation will use most of your daily discovery budget';
        }

        return $warnings;
    }
}
