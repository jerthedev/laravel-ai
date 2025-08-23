<?php

namespace JTD\LaravelAI\Drivers\Gemini\Support;

/**
 * Gemini Model Pricing Data
 *
 * Centralized pricing information for Gemini models.
 * Prices are per 1K tokens (input/output).
 */
class ModelPricing
{
    /**
     * Gemini model pricing per 1K tokens (input/output).
     * Prices are in USD per 1K tokens (updated 2025).
     */
    public static array $pricing = [
        // Current generation models (2025 pricing)
        'gemini-2.5-pro' => ['input' => 1.25, 'output' => 10.00],
        'gemini-2.5-flash' => ['input' => 0.30, 'output' => 2.50],
        'gemini-2.5-flash-lite' => ['input' => 0.10, 'output' => 0.40],
        'gemini-2.0-flash' => ['input' => 0.075, 'output' => 0.30], // Updated pricing
        'gemini-2.0-flash-lite' => ['input' => 0.075, 'output' => 0.30],
        'gemini-2.0-pro' => ['input' => 1.25, 'output' => 10.00], // New model

        // Previous generation models (updated pricing)
        'gemini-1.5-pro' => ['input' => 1.25, 'output' => 5.00],
        'gemini-1.5-flash' => ['input' => 0.075, 'output' => 0.30],
        'gemini-1.5-pro-exp-0801' => ['input' => 1.25, 'output' => 5.00],
        'gemini-1.5-flash-exp-0827' => ['input' => 0.075, 'output' => 0.30],

        // Legacy models (legacy pricing)
        'gemini-pro' => ['input' => 0.0005, 'output' => 0.0015],
        'gemini-pro-vision' => ['input' => 0.00025, 'output' => 0.00025],
        'gemini-1.0-pro' => ['input' => 0.0005, 'output' => 0.0015],
        'gemini-1.0-pro-vision' => ['input' => 0.00025, 'output' => 0.00025],
        'gemini-1.0-pro-001' => ['input' => 0.0005, 'output' => 0.0015],
        'gemini-1.0-pro-latest' => ['input' => 0.0005, 'output' => 0.0015],
        'gemini-1.0-pro-vision-latest' => ['input' => 0.00025, 'output' => 0.00025],
    ];

    /**
     * Free tier limits (requests per minute).
     */
    public static array $freeTierLimits = [
        // Current generation models
        'gemini-2.5-pro' => 2,
        'gemini-2.5-flash' => 15,
        'gemini-2.5-flash-lite' => 15,
        'gemini-2.0-flash' => 15,
        'gemini-2.0-flash-lite' => 15,

        // Previous generation models
        'gemini-1.5-pro' => 2,
        'gemini-1.5-flash' => 15,

        // Legacy models
        'gemini-pro' => 60,
        'gemini-pro-vision' => 60,
        'gemini-1.0-pro' => 60,
        'gemini-1.0-pro-vision' => 60,
    ];

    /**
     * Paid tier limits (requests per minute).
     */
    public static array $paidTierLimits = [
        // Current generation models
        'gemini-2.5-pro' => 1000,
        'gemini-2.5-flash' => 4000,
        'gemini-2.5-flash-lite' => 4000,
        'gemini-2.0-flash' => 4000,
        'gemini-2.0-flash-lite' => 4000,

        // Previous generation models
        'gemini-1.5-pro' => 1000,
        'gemini-1.5-flash' => 4000,

        // Legacy models
        'gemini-pro' => 360,
        'gemini-pro-vision' => 360,
        'gemini-1.0-pro' => 360,
        'gemini-1.0-pro-vision' => 360,
    ];

    /**
     * Get pricing for a specific model.
     */
    public static function getModelPricing(string $modelId): array
    {
        // Handle model variations and fallbacks
        $normalizedModel = static::normalizeModelName($modelId);

        if (isset(static::$pricing[$normalizedModel])) {
            return static::$pricing[$normalizedModel];
        }

        // Try to find a base model match
        foreach (static::$pricing as $model => $pricing) {
            if (str_starts_with($normalizedModel, $model)) {
                return $pricing;
            }
        }

        // Default fallback pricing (Gemini Pro rates)
        return ['input' => 0.0005, 'output' => 0.0015];
    }

    /**
     * Normalize model name for pricing lookup.
     */
    protected static function normalizeModelName(string $modelId): string
    {
        // Remove common suffixes that don't affect pricing
        $modelId = preg_replace('/-\d{4}-\d{2}-\d{2}$/', '', $modelId);
        $modelId = str_replace(['-preview', '-latest'], '', $modelId);

        return strtolower($modelId);
    }

    /**
     * Calculate cost for token usage.
     */
    public static function calculateCost(int $inputTokens, int $outputTokens, string $modelId): array
    {
        $pricing = static::getModelPricing($modelId);

        $inputCost = ($inputTokens / 1000) * $pricing['input'];
        $outputCost = ($outputTokens / 1000) * $pricing['output'];
        $totalCost = $inputCost + $outputCost;

        return [
            'model' => $modelId,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'input_cost' => $inputCost,
            'output_cost' => $outputCost,
            'total_cost' => $totalCost,
            'pricing_per_1k' => $pricing,
            'currency' => 'USD',
        ];
    }

    /**
     * Estimate cost for a given number of tokens.
     */
    public static function estimateCost(int $estimatedTokens, string $modelId): array
    {
        $pricing = static::getModelPricing($modelId);

        // Estimate 75% input, 25% output for cost estimation
        $estimatedInputTokens = (int) ($estimatedTokens * 0.75);
        $estimatedOutputTokens = (int) ($estimatedTokens * 0.25);

        return static::calculateCost($estimatedInputTokens, $estimatedOutputTokens, $modelId);
    }

    /**
     * Get cost efficiency score for a model.
     */
    public static function getCostEfficiency(string $modelId): float
    {
        $pricing = static::getModelPricing($modelId);
        $avgCost = ($pricing['input'] + $pricing['output']) / 2;

        // Lower cost = higher efficiency score
        return 1 / max($avgCost * 1000, 0.001);
    }

    /**
     * Compare costs between models for the same input.
     */
    public static function compareModelCosts(int $inputTokens, int $outputTokens, ?array $modelIds = null): array
    {
        $models = $modelIds ?? array_keys(static::$pricing);
        $comparisons = [];

        foreach ($models as $modelId) {
            $cost = static::calculateCost($inputTokens, $outputTokens, $modelId);
            $comparisons[$modelId] = $cost;
        }

        // Sort by total cost
        uasort($comparisons, function ($a, $b) {
            return $a['total_cost'] <=> $b['total_cost'];
        });

        return $comparisons;
    }

    /**
     * Get the most cost-effective model for a task.
     */
    public static function getMostCostEffectiveModel(array $requirements = []): string
    {
        $candidates = array_keys(static::$pricing);

        // Filter by requirements
        if (isset($requirements['vision']) && $requirements['vision']) {
            $candidates = array_filter($candidates, function ($model) {
                return str_contains($model, 'vision') || str_contains($model, '1.5');
            });
        }

        if (isset($requirements['large_context']) && $requirements['large_context']) {
            $candidates = array_filter($candidates, function ($model) {
                return str_contains($model, '1.5');
            });
        }

        // Find the cheapest among candidates
        $cheapest = null;
        $lowestCost = PHP_FLOAT_MAX;

        foreach ($candidates as $model) {
            $pricing = static::getModelPricing($model);
            $avgCost = ($pricing['input'] + $pricing['output']) / 2;

            if ($avgCost < $lowestCost) {
                $lowestCost = $avgCost;
                $cheapest = $model;
            }
        }

        return $cheapest ?? 'gemini-pro';
    }

    /**
     * Get rate limits for a model.
     */
    public static function getRateLimits(string $modelId, bool $isPaidTier = false): array
    {
        $limits = $isPaidTier ? static::$paidTierLimits : static::$freeTierLimits;

        $requestsPerMinute = $limits[$modelId] ?? ($isPaidTier ? 360 : 60);

        return [
            'requests_per_minute' => $requestsPerMinute,
            'requests_per_day' => $requestsPerMinute * 60 * 24,
            'tokens_per_minute' => $requestsPerMinute * 1000, // Rough estimate
            'concurrent_requests' => $isPaidTier ? 5 : 1,
            'tier' => $isPaidTier ? 'paid' : 'free',
        ];
    }

    /**
     * Calculate monthly cost estimate based on usage patterns.
     */
    public static function estimateMonthlyCost(array $usagePattern, string $modelId): array
    {
        $pricing = static::getModelPricing($modelId);

        $dailyInputTokens = $usagePattern['daily_input_tokens'] ?? 0;
        $dailyOutputTokens = $usagePattern['daily_output_tokens'] ?? 0;
        $workingDaysPerMonth = $usagePattern['working_days_per_month'] ?? 22;

        $monthlyInputTokens = $dailyInputTokens * $workingDaysPerMonth;
        $monthlyOutputTokens = $dailyOutputTokens * $workingDaysPerMonth;

        $monthlyCost = static::calculateCost($monthlyInputTokens, $monthlyOutputTokens, $modelId);

        return [
            'model' => $modelId,
            'monthly_usage' => [
                'input_tokens' => $monthlyInputTokens,
                'output_tokens' => $monthlyOutputTokens,
                'total_tokens' => $monthlyInputTokens + $monthlyOutputTokens,
            ],
            'monthly_cost' => $monthlyCost,
            'daily_average' => [
                'input_tokens' => $dailyInputTokens,
                'output_tokens' => $dailyOutputTokens,
                'cost' => $monthlyCost['total_cost'] / $workingDaysPerMonth,
            ],
            'assumptions' => [
                'working_days_per_month' => $workingDaysPerMonth,
                'pricing_per_1k' => $pricing,
            ],
        ];
    }

    /**
     * Get pricing tier information.
     */
    public static function getPricingTiers(): array
    {
        return [
            'free' => [
                'name' => 'Free Tier',
                'description' => 'Limited requests per minute, no billing required',
                'features' => [
                    'Rate limited requests',
                    'All model access',
                    'Safety settings',
                    'Basic support',
                ],
                'limitations' => [
                    'Lower rate limits',
                    'No SLA guarantee',
                    'Community support only',
                ],
            ],
            'paid' => [
                'name' => 'Pay-as-you-go',
                'description' => 'Higher rate limits, pay only for what you use',
                'features' => [
                    'Higher rate limits',
                    'All model access',
                    'Priority support',
                    'Usage analytics',
                ],
                'benefits' => [
                    'Scalable usage',
                    'Production ready',
                    'SLA guarantee',
                ],
            ],
        ];
    }

    /**
     * Check if a model has free tier access.
     */
    public static function hasFreeTierAccess(string $modelId): bool
    {
        return isset(static::$freeTierLimits[$modelId]);
    }

    /**
     * Get all available models with pricing.
     */
    public static function getAllModelPricing(): array
    {
        return static::$pricing;
    }

    /**
     * Get pricing history (placeholder for future implementation).
     */
    public static function getPricingHistory(string $modelId): array
    {
        // This would contain historical pricing data
        return [
            'model' => $modelId,
            'current_pricing' => static::getModelPricing($modelId),
            'price_changes' => [],
            'last_updated' => '2024-01-01',
        ];
    }

    /**
     * Calculate break-even point between models.
     */
    public static function calculateBreakEvenPoint(string $model1, string $model2, int $tokensPerDay): array
    {
        $pricing1 = static::getModelPricing($model1);
        $pricing2 = static::getModelPricing($model2);

        $dailyCost1 = (($tokensPerDay * 0.75) / 1000) * $pricing1['input'] +
                      (($tokensPerDay * 0.25) / 1000) * $pricing1['output'];

        $dailyCost2 = (($tokensPerDay * 0.75) / 1000) * $pricing2['input'] +
                      (($tokensPerDay * 0.25) / 1000) * $pricing2['output'];

        $costDifference = abs($dailyCost1 - $dailyCost2);
        $cheaperModel = $dailyCost1 < $dailyCost2 ? $model1 : $model2;
        $expensiveModel = $dailyCost1 < $dailyCost2 ? $model2 : $model1;

        return [
            'cheaper_model' => $cheaperModel,
            'expensive_model' => $expensiveModel,
            'daily_cost_difference' => $costDifference,
            'monthly_savings' => $costDifference * 30,
            'yearly_savings' => $costDifference * 365,
            'tokens_per_day' => $tokensPerDay,
        ];
    }
}
