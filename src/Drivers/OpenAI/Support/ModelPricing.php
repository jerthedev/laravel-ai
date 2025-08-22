<?php

namespace JTD\LaravelAI\Drivers\OpenAI\Support;

/**
 * OpenAI Model Pricing Data
 *
 * Centralized pricing information for OpenAI models.
 * Prices are per 1K tokens (input/output).
 */
class ModelPricing
{
    /**
     * OpenAI model pricing per 1K tokens (input/output).
     */
    public static array $pricing = [
        'gpt-3.5-turbo' => ['input' => 0.0015, 'output' => 0.002],
        'gpt-3.5-turbo-16k' => ['input' => 0.003, 'output' => 0.004],
        'gpt-3.5-turbo-0125' => ['input' => 0.0005, 'output' => 0.0015],
        'gpt-3.5-turbo-1106' => ['input' => 0.001, 'output' => 0.002],
        'gpt-4' => ['input' => 0.03, 'output' => 0.06],
        'gpt-4-32k' => ['input' => 0.06, 'output' => 0.12],
        'gpt-4-turbo' => ['input' => 0.01, 'output' => 0.03],
        'gpt-4-turbo-preview' => ['input' => 0.01, 'output' => 0.03],
        'gpt-4-1106-preview' => ['input' => 0.01, 'output' => 0.03],
        'gpt-4-0125-preview' => ['input' => 0.01, 'output' => 0.03],
        'gpt-4o' => ['input' => 0.005, 'output' => 0.015],
        'gpt-4o-2024-05-13' => ['input' => 0.005, 'output' => 0.015],
        'gpt-4o-2024-08-06' => ['input' => 0.0025, 'output' => 0.01],
        'gpt-4o-mini' => ['input' => 0.00015, 'output' => 0.0006],
        'gpt-4o-mini-2024-07-18' => ['input' => 0.00015, 'output' => 0.0006],
        'gpt-5' => ['input' => 0.01, 'output' => 0.03], // Estimated pricing
        'gpt-5-2025-08-07' => ['input' => 0.01, 'output' => 0.03], // Estimated pricing
        'text-embedding-ada-002' => ['input' => 0.0001, 'output' => 0.0001],
        'text-embedding-3-small' => ['input' => 0.00002, 'output' => 0.00002],
        'text-embedding-3-large' => ['input' => 0.00013, 'output' => 0.00013],
        'whisper-1' => ['input' => 0.006, 'output' => 0.006], // Per minute
        'tts-1' => ['input' => 0.015, 'output' => 0.015], // Per 1K characters
        'tts-1-hd' => ['input' => 0.030, 'output' => 0.030], // Per 1K characters
        'dall-e-2' => ['input' => 0.020, 'output' => 0.020], // Per image (1024x1024)
        'dall-e-3' => ['input' => 0.040, 'output' => 0.040], // Per image (1024x1024)
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

        // Default fallback pricing (GPT-3.5 Turbo rates)
        return ['input' => 0.0015, 'output' => 0.002];
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

        $inputCost = ($estimatedInputTokens / 1000) * $pricing['input'];
        $outputCost = ($estimatedOutputTokens / 1000) * $pricing['output'];
        $totalCost = $inputCost + $outputCost;

        return [
            'model' => $modelId,
            'input_tokens' => $estimatedInputTokens,
            'estimated_output_tokens' => $estimatedOutputTokens,
            'input_cost' => $inputCost,
            'estimated_output_cost' => $outputCost,
            'estimated_total_cost' => $totalCost,
            'pricing_per_1k' => $pricing,
            'currency' => 'USD',
        ];
    }

    /**
     * Get all available models with pricing.
     */
    public static function getAllModelPricing(): array
    {
        return static::$pricing;
    }

    /**
     * Check if a model has pricing information.
     */
    public static function hasPricing(string $modelId): bool
    {
        $normalizedModel = static::normalizeModelName($modelId);
        return isset(static::$pricing[$normalizedModel]);
    }
}
