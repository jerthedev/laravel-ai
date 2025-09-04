<?php

namespace JTD\LaravelAI\Drivers\Gemini\Traits;

use JTD\LaravelAI\Drivers\Gemini\Support\ModelPricing;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Models\TokenUsage;

/**
 * Calculates Costs for Google Gemini API Usage
 *
 * Handles cost calculation, pricing data, and
 * cost estimation for Gemini models.
 */
trait CalculatesCosts
{
    /**
     * Gemini model pricing per 1K tokens (input/output).
     * Prices are in USD per 1K tokens.
     */
    protected array $geminiPricing = [
        'gemini-pro' => ['input' => 0.0005, 'output' => 0.0015],
        'gemini-pro-vision' => ['input' => 0.00025, 'output' => 0.00025],
        'gemini-1.5-pro' => ['input' => 0.0035, 'output' => 0.0105],
        'gemini-1.5-flash' => ['input' => 0.00035, 'output' => 0.00105],
        'gemini-1.5-pro-exp-0801' => ['input' => 0.0035, 'output' => 0.0105],
        'gemini-1.5-flash-exp-0827' => ['input' => 0.00035, 'output' => 0.00105],
    ];

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
        return $this->estimateCost($tokens, $model);
    }

    /**
     * Calculate actual cost from token usage.
     */
    protected function calculateActualCost(TokenUsage $tokenUsage, string $modelId): array
    {
        return $this->calculateCostFromTokens(
            $tokenUsage->input_tokens,
            $tokenUsage->output_tokens,
            $modelId
        );
    }

    /**
     * Calculate cost for token usage.
     */
    protected function calculateCostFromTokens(int $inputTokens, int $outputTokens, string $modelId): array
    {
        return ModelPricing::calculateCost($inputTokens, $outputTokens, $modelId);
    }

    /**
     * Estimate cost for a given number of tokens.
     */
    protected function estimateCost(int $estimatedTokens, string $modelId): array
    {
        return ModelPricing::estimateCost($estimatedTokens, $modelId);
    }

    /**
     * Get cost rates for a model.
     */
    protected function getCostRates(string $model): array
    {
        return $this->getModelPricing($model);
    }

    /**
     * Get pricing information for a model.
     */
    protected function getModelPricing(string $modelId): array
    {
        return ModelPricing::getModelPricing($modelId);
    }

    /**
     * Normalize model name for pricing lookup.
     */
    protected function normalizeModelName(string $modelId): string
    {
        // Remove common suffixes that don't affect pricing
        $modelId = preg_replace('/-\d{4}-\d{2}-\d{2}$/', '', $modelId);
        $modelId = str_replace(['-preview', '-latest'], '', $modelId);

        return strtolower($modelId);
    }

    /**
     * Estimate cost for a conversation.
     */
    public function estimateConversationCost(array $messages, ?string $modelId = null): array
    {
        $model = $modelId ?? $this->getCurrentModel();
        $totalTokens = 0;

        foreach ($messages as $message) {
            $totalTokens += $this->estimateTokens($message, $model);
        }

        return $this->estimateCost($totalTokens, $model);
    }

    /**
     * Calculate cost for a completed response.
     */
    public function calculateResponseCost(AIResponse $response): array
    {
        return $this->calculateActualCost($response->tokenUsage, $response->model);
    }

    /**
     * Calculate batch cost for multiple requests.
     */
    public function calculateBatchCost(array $requests, ?string $modelId = null): array
    {
        $model = $modelId ?? $this->getCurrentModel();
        $requestCosts = [];
        $totalInputTokens = 0;
        $totalOutputTokens = 0;

        foreach ($requests as $index => $request) {
            if ($request instanceof AIResponse) {
                // Actual response with token usage
                $cost = $this->calculateResponseCost($request);
                $totalInputTokens += $request->tokenUsage->input_tokens;
                $totalOutputTokens += $request->tokenUsage->output_tokens;
            } else {
                // Estimate from message
                $estimatedTokens = $this->estimateTokens($request, $model);
                $cost = $this->estimateCost($estimatedTokens, $model);
                $totalInputTokens += $cost['input_tokens'];
                $totalOutputTokens += $cost['estimated_output_tokens'];
            }

            $requestCosts[] = $cost;
        }

        $totalCost = $this->calculateCostFromTokens($totalInputTokens, $totalOutputTokens, $model);

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
     * Get cost efficiency metrics for a model.
     */
    public function getCostEfficiencyMetrics(string $modelId): array
    {
        $pricing = $this->getModelPricing($modelId);
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
            'multimodal_support' => $this->isMultimodalModel($modelId),
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
    public function estimateMonthlyCost(array $usagePattern, ?string $modelId = null): array
    {
        $model = $modelId ?? $this->getCurrentModel();
        $pricing = $this->getModelPricing($model);

        $dailyInputTokens = $usagePattern['daily_input_tokens'] ?? 0;
        $dailyOutputTokens = $usagePattern['daily_output_tokens'] ?? 0;
        $workingDaysPerMonth = $usagePattern['working_days_per_month'] ?? 22;

        $monthlyInputTokens = $dailyInputTokens * $workingDaysPerMonth;
        $monthlyOutputTokens = $dailyOutputTokens * $workingDaysPerMonth;

        $monthlyCost = $this->calculateCostFromTokens($monthlyInputTokens, $monthlyOutputTokens, $model);

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
     * Get all available models with pricing.
     */
    public function getAllModelPricing(): array
    {
        return $this->geminiPricing;
    }

    /**
     * Check if a model has pricing information.
     */
    public function hasPricing(string $modelId): bool
    {
        $normalizedModel = $this->normalizeModelName($modelId);

        return isset($this->geminiPricing[$normalizedModel]);
    }

    /**
     * Compare costs between models for the same input.
     */
    public function compareModelCosts($input, ?array $modelIds = null): array
    {
        $models = $modelIds ?? array_keys($this->geminiPricing);
        $comparisons = [];

        foreach ($models as $modelId) {
            $cost = $this->calculateCost($input, $modelId);
            $comparisons[$modelId] = $cost;
        }

        // Sort by total cost
        uasort($comparisons, function ($a, $b) {
            return $a['estimated_total_cost'] <=> $b['estimated_total_cost'];
        });

        return $comparisons;
    }
}
