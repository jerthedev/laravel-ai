<?php

namespace JTD\LaravelAI\Drivers\XAI\Support;

/**
 * xAI Model Pricing Data
 *
 * Centralized pricing information for xAI Grok models.
 * Prices are per 1M tokens (input/output).
 */
class ModelPricing
{
    /**
     * xAI model pricing per 1M tokens (input/output).
     * Updated January 2025 with latest pricing from xAI API.
     */
    public static $pricing = [
        'grok-beta' => [
            'input' => 5.00,   // $5 per 1M input tokens
            'output' => 15.00, // $15 per 1M output tokens
        ],
        'grok-2' => [
            'input' => 2.00,   // $2 per 1M input tokens
            'output' => 10.00, // $10 per 1M output tokens
        ],
        'grok-2-1212' => [
            'input' => 2.00,   // $2 per 1M input tokens (latest pricing)
            'output' => 10.00, // $10 per 1M output tokens
        ],
        'grok-2-vision-1212' => [
            'input' => 2.00,   // $2 per 1M input tokens (updated pricing)
            'output' => 10.00, // $10 per 1M output tokens
        ],
        'grok-4' => [
            'input' => 3.00,   // $3 per 1M input tokens (flagship model)
            'output' => 15.00, // $15 per 1M output tokens
        ],
        'grok-4-0709' => [
            'input' => 3.00,   // $3 per 1M input tokens
            'output' => 15.00, // $15 per 1M output tokens
        ],
        'grok-2-mini' => [
            'input' => 1.00,   // $1 per 1M input tokens
            'output' => 5.00,  // $5 per 1M output tokens
        ],
    ];

    /**
     * Get pricing for a specific model.
     */
    public static function getModelPricing(string $modelId): array
    {
        $normalizedId = self::normalizeModelName($modelId);

        if (isset(self::$pricing[$normalizedId])) {
            return [
                'input' => self::$pricing[$normalizedId]['input'] / 1000000,  // Convert to per-token
                'output' => self::$pricing[$normalizedId]['output'] / 1000000, // Convert to per-token
                'input_per_1m' => self::$pricing[$normalizedId]['input'],
                'output_per_1m' => self::$pricing[$normalizedId]['output'],
                'currency' => 'USD',
                'model' => $normalizedId,
            ];
        }

        // Default fallback pricing
        return [
            'input' => 0.000005,  // $5 per 1M tokens
            'output' => 0.000015, // $15 per 1M tokens
            'input_per_1m' => 5.00,
            'output_per_1m' => 15.00,
            'currency' => 'USD',
            'model' => $modelId,
        ];
    }

    /**
     * Normalize model name for pricing lookup.
     */
    protected static function normalizeModelName(string $modelId): string
    {
        // Remove any version suffixes or prefixes that don't affect pricing
        $normalized = strtolower(trim($modelId));

        // Handle common variations
        $normalized = str_replace(['_', ' '], '-', $normalized);

        return $normalized;
    }

    /**
     * Calculate cost for token usage.
     */
    public static function calculateCost(int $inputTokens, int $outputTokens, string $modelId): array
    {
        $pricing = self::getModelPricing($modelId);

        $inputCost = $inputTokens * $pricing['input'];
        $outputCost = $outputTokens * $pricing['output'];
        $totalCost = $inputCost + $outputCost;

        return [
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'total_tokens' => $inputTokens + $outputTokens,
            'input_cost' => round($inputCost, 6),
            'output_cost' => round($outputCost, 6),
            'total_cost' => round($totalCost, 6),
            'model' => $modelId,
            'currency' => 'USD',
            'pricing' => [
                'input_per_token' => $pricing['input'],
                'output_per_token' => $pricing['output'],
                'input_per_1m' => $pricing['input_per_1m'],
                'output_per_1m' => $pricing['output_per_1m'],
            ],
        ];
    }

    /**
     * Estimate cost for a given number of tokens.
     */
    public static function estimateCost(int $estimatedTokens, string $modelId): array
    {
        // Assume 70% input, 30% output for estimation
        $inputTokens = (int) ($estimatedTokens * 0.7);
        $outputTokens = (int) ($estimatedTokens * 0.3);

        $result = self::calculateCost($inputTokens, $outputTokens, $modelId);
        $result['estimated'] = true;
        $result['assumption'] = '70% input, 30% output tokens';

        return $result;
    }

    /**
     * Get all available models with pricing.
     */
    public static function getAllModelPricing(): array
    {
        $allPricing = [];

        foreach (self::$pricing as $modelId => $pricing) {
            $allPricing[$modelId] = self::getModelPricing($modelId);
        }

        return $allPricing;
    }

    /**
     * Check if a model has pricing information.
     */
    public static function hasPricing(string $modelId): bool
    {
        $normalizedId = self::normalizeModelName($modelId);

        return isset(self::$pricing[$normalizedId]);
    }

    /**
     * Get cost comparison between models.
     */
    public static function compareCosts(int $inputTokens, int $outputTokens): array
    {
        $comparison = [];

        foreach (array_keys(self::$pricing) as $modelId) {
            $cost = self::calculateCost($inputTokens, $outputTokens, $modelId);
            $comparison[$modelId] = [
                'model' => $modelId,
                'total_cost' => $cost['total_cost'],
                'cost_per_1k_tokens' => round($cost['total_cost'] * 1000 / ($inputTokens + $outputTokens), 6),
                'tier' => self::getModelTier($modelId),
            ];
        }

        // Sort by total cost
        uasort($comparison, fn ($a, $b) => $a['total_cost'] <=> $b['total_cost']);

        return $comparison;
    }

    /**
     * Get model tier for pricing context.
     */
    protected static function getModelTier(string $modelId): string
    {
        return match ($modelId) {
            'grok-2-mini' => 'economy',
            'grok-2', 'grok-2-1212' => 'standard',
            'grok-beta', 'grok-2-vision-1212' => 'premium',
            default => 'standard',
        };
    }

    /**
     * Get pricing recommendations based on usage.
     */
    public static function getPricingRecommendations(int $monthlyTokens): array
    {
        $recommendations = [];

        foreach (array_keys(self::$pricing) as $modelId) {
            $monthlyCost = self::estimateCost($monthlyTokens, $modelId);

            $recommendations[] = [
                'model' => $modelId,
                'monthly_cost' => $monthlyCost['total_cost'],
                'cost_per_1k_tokens' => round($monthlyCost['total_cost'] * 1000 / $monthlyTokens, 6),
                'tier' => self::getModelTier($modelId),
                'recommended_for' => self::getRecommendedUsage($modelId, $monthlyTokens),
            ];
        }

        // Sort by monthly cost
        usort($recommendations, fn ($a, $b) => $a['monthly_cost'] <=> $b['monthly_cost']);

        return $recommendations;
    }

    /**
     * Get recommended usage for model based on volume.
     */
    protected static function getRecommendedUsage(string $modelId, int $monthlyTokens): string
    {
        $tier = self::getModelTier($modelId);

        if ($monthlyTokens < 100000) { // < 100K tokens/month
            return match ($tier) {
                'economy' => 'Excellent for low-volume applications',
                'standard' => 'Good balance of cost and performance',
                'premium' => 'Consider for specialized tasks only',
            };
        } elseif ($monthlyTokens < 1000000) { // < 1M tokens/month
            return match ($tier) {
                'economy' => 'Most cost-effective for medium volume',
                'standard' => 'Good for balanced performance needs',
                'premium' => 'Use for high-quality requirements',
            };
        } else { // > 1M tokens/month
            return match ($tier) {
                'economy' => 'Best cost efficiency for high volume',
                'standard' => 'Balanced option for enterprise use',
                'premium' => 'Premium quality for critical applications',
            };
        }
    }

    /**
     * Calculate break-even point between two models.
     */
    public static function calculateBreakEven(string $model1, string $model2, int $inputTokens, int $outputTokens): array
    {
        $cost1 = self::calculateCost($inputTokens, $outputTokens, $model1);
        $cost2 = self::calculateCost($inputTokens, $outputTokens, $model2);

        $difference = abs($cost1['total_cost'] - $cost2['total_cost']);
        $cheaperModel = $cost1['total_cost'] < $cost2['total_cost'] ? $model1 : $model2;
        $expensiveModel = $cheaperModel === $model1 ? $model2 : $model1;

        return [
            'cheaper_model' => $cheaperModel,
            'expensive_model' => $expensiveModel,
            'cost_difference' => round($difference, 6),
            'percentage_difference' => round(($difference / min($cost1['total_cost'], $cost2['total_cost'])) * 100, 2),
            'savings_per_1k_tokens' => round($difference * 1000 / ($inputTokens + $outputTokens), 6),
        ];
    }

    /**
     * Get pricing history (placeholder for future implementation).
     */
    public static function getPricingHistory(string $modelId): array
    {
        // Placeholder for future pricing history tracking
        return [
            'model' => $modelId,
            'current_pricing' => self::getModelPricing($modelId),
            'history' => [],
            'last_updated' => '2024-12-01',
        ];
    }
}
