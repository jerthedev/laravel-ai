<?php

namespace JTD\LaravelAI\Drivers\OpenAI\Traits;

use JTD\LaravelAI\Drivers\OpenAI\Support\ModelPricing;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Models\TokenUsage;

/**
 * Calculates Costs for OpenAI API Usage
 *
 * Handles cost calculation, pricing data, and
 * cost estimation for OpenAI models.
 */
trait CalculatesCosts
{
    /**
     * Calculate cost for a message or token usage.
     */
    public function calculateCost($message, ?string $modelId = null): array
    {
        $model = $modelId ?? $this->getCurrentModel();

        if ($message instanceof TokenUsage) {
            return $this->calculateActualCost($message, $model);
        }

        // Estimate tokens for the message
        $estimatedTokens = $this->estimateTokens($message, $model);

        return $this->doCalculateCost($estimatedTokens, $model);
    }

    /**
     * Calculate cost based on estimated tokens.
     */
    protected function doCalculateCost(int $tokens, string $model): array
    {
        return ModelPricing::estimateCost($tokens, $model);
    }

    /**
     * Calculate actual cost from token usage.
     */
    protected function calculateActualCost(TokenUsage $tokenUsage, string $modelId): array
    {
        return ModelPricing::calculateCost(
            $tokenUsage->inputTokens,
            $tokenUsage->outputTokens,
            $modelId
        );
    }

    /**
     * Get cost rates for a model.
     */
    protected function getCostRates(string $model): array
    {
        return ModelPricing::getModelPricing($model);
    }

    /**
     * Get pricing information for a model.
     */
    protected function getModelPricing(string $modelId): array
    {
        return ModelPricing::getModelPricing($modelId);
    }

    /**
     * Estimate cost for a conversation.
     */
    public function estimateConversationCost(array $messages, string $modelId = null): array
    {
        $model = $modelId ?? $this->getCurrentModel();
        $totalTokens = 0;

        foreach ($messages as $message) {
            $totalTokens += $this->estimateTokens($message, $model);
        }

        return ModelPricing::estimateCost($totalTokens, $model);
    }

    /**
     * Calculate cost for a completed response.
     */
    public function calculateResponseCost(AIResponse $response): array
    {
        return $this->calculateActualCost($response->tokenUsage, $response->model);
    }

    /**
     * Get cost breakdown for multiple requests.
     */
    public function calculateBatchCost(array $requests, string $modelId = null): array
    {
        $model = $modelId ?? $this->getCurrentModel();
        $totalInputTokens = 0;
        $totalOutputTokens = 0;
        $requestCosts = [];

        foreach ($requests as $index => $request) {
            if ($request instanceof AIResponse) {
                // Actual response with token usage
                $cost = $this->calculateResponseCost($request);
                $totalInputTokens += $request->tokenUsage->inputTokens;
                $totalOutputTokens += $request->tokenUsage->outputTokens;
            } else {
                // Estimate from message
                $estimatedTokens = $this->estimateTokens($request, $model);
                $cost = ModelPricing::estimateCost($estimatedTokens, $model);
                $totalInputTokens += $cost['input_tokens'];
                $totalOutputTokens += $cost['output_tokens'];
            }

            $requestCosts[] = $cost;
        }

        $totalCost = ModelPricing::calculateCost($totalInputTokens, $totalOutputTokens, $model);

        return [
            'total_cost' => $totalCost,
            'request_costs' => $requestCosts,
            'summary' => [
                'total_requests' => count($requests),
                'total_input_tokens' => $totalInputTokens,
                'total_output_tokens' => $totalOutputTokens,
                'total_tokens' => $totalInputTokens + $totalOutputTokens,
                'average_cost_per_request' => $totalCost['total_cost'] / count($requests),
            ],
        ];
    }

    /**
     * Compare costs across different models.
     */
    public function compareCostsAcrossModels($message, array $modelIds): array
    {
        $comparisons = [];

        foreach ($modelIds as $modelId) {
            $cost = $this->calculateCost($message, $modelId);
            $comparisons[$modelId] = [
                'model' => $modelId,
                'estimated_cost' => $cost['total_cost'],
                'cost_breakdown' => $cost,
                'model_info' => $this->getModelInfo($modelId),
            ];
        }

        // Sort by cost (lowest first)
        uasort($comparisons, function ($a, $b) {
            return $a['estimated_cost'] <=> $b['estimated_cost'];
        });

        return $comparisons;
    }

    /**
     * Get cost efficiency metrics for a model.
     */
    public function getCostEfficiencyMetrics(string $modelId): array
    {
        $pricing = ModelPricing::getModelPricing($modelId);
        $capabilities = $this->getModelCapabilities($modelId);
        $contextLength = $this->getModelContextLength($modelId);

        return [
            'model' => $modelId,
            'input_cost_per_1k' => $pricing['input'],
            'output_cost_per_1k' => $pricing['output'],
            'context_length' => $contextLength,
            'cost_per_context_token' => $pricing['input'] / 1000,
            'capabilities_count' => count($capabilities),
            'efficiency_score' => $this->calculateEfficiencyScore($pricing, $capabilities, $contextLength),
        ];
    }

    /**
     * Calculate efficiency score for a model.
     */
    protected function calculateEfficiencyScore(array $pricing, array $capabilities, int $contextLength): float
    {
        // Simple efficiency score based on capabilities per cost
        $avgCost = ($pricing['input'] + $pricing['output']) / 2;
        $capabilityScore = count($capabilities) * 10;
        $contextScore = min($contextLength / 1000, 100); // Cap at 100

        return ($capabilityScore + $contextScore) / max($avgCost * 1000, 1);
    }

    /**
     * Estimate monthly cost based on usage patterns.
     */
    public function estimateMonthlyCost(array $usagePattern, string $modelId = null): array
    {
        $model = $modelId ?? $this->getCurrentModel();
        $pricing = ModelPricing::getModelPricing($model);

        $dailyInputTokens = $usagePattern['daily_input_tokens'] ?? 0;
        $dailyOutputTokens = $usagePattern['daily_output_tokens'] ?? 0;
        $workingDaysPerMonth = $usagePattern['working_days_per_month'] ?? 22;

        $monthlyInputTokens = $dailyInputTokens * $workingDaysPerMonth;
        $monthlyOutputTokens = $dailyOutputTokens * $workingDaysPerMonth;

        $monthlyCost = ModelPricing::calculateCost($monthlyInputTokens, $monthlyOutputTokens, $model);

        return [
            'model' => $model,
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
     * Get cost optimization recommendations.
     */
    public function getCostOptimizationRecommendations($message, string $currentModel = null): array
    {
        $current = $currentModel ?? $this->getCurrentModel();
        $availableModels = ['gpt-3.5-turbo', 'gpt-4', 'gpt-4-turbo', 'gpt-4o', 'gpt-4o-mini'];

        $comparisons = $this->compareCostsAcrossModels($message, $availableModels);
        $currentCost = $comparisons[$current]['estimated_cost'] ?? null;

        $recommendations = [];

        foreach ($comparisons as $modelId => $comparison) {
            if ($modelId === $current) {
                continue;
            }

            $savings = $currentCost ? ($currentCost - $comparison['estimated_cost']) : 0;
            $savingsPercent = $currentCost ? (($savings / $currentCost) * 100) : 0;

            if ($savings > 0) {
                $recommendations[] = [
                    'model' => $modelId,
                    'estimated_cost' => $comparison['estimated_cost'],
                    'savings' => $savings,
                    'savings_percent' => $savingsPercent,
                    'trade_offs' => $this->getModelTradeOffs($current, $modelId),
                ];
            }
        }

        // Sort by savings (highest first)
        usort($recommendations, function ($a, $b) {
            return $b['savings'] <=> $a['savings'];
        });

        return [
            'current_model' => $current,
            'current_cost' => $currentCost,
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Get trade-offs between two models.
     */
    protected function getModelTradeOffs(string $currentModel, string $alternativeModel): array
    {
        $currentInfo = $this->getModelInfo($currentModel);
        $alternativeInfo = $this->getModelInfo($alternativeModel);

        return [
            'context_length_change' => $alternativeInfo['context_length'] - $currentInfo['context_length'],
            'capability_changes' => array_diff($currentInfo['capabilities'], $alternativeInfo['capabilities']),
            'new_capabilities' => array_diff($alternativeInfo['capabilities'], $currentInfo['capabilities']),
        ];
    }
}
