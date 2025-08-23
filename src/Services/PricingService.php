<?php

namespace JTD\LaravelAI\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Centralized Pricing Service
 *
 * Provides unified access to pricing data across all AI providers.
 * Integrates with the model sync system to keep pricing up-to-date.
 */
class PricingService
{
    /**
     * Provider pricing class mappings.
     */
    protected array $pricingClasses = [
        'openai' => \JTD\LaravelAI\Drivers\OpenAI\Support\ModelPricing::class,
        'gemini' => \JTD\LaravelAI\Drivers\Gemini\Support\ModelPricing::class,
        'xai' => \JTD\LaravelAI\Drivers\XAI\Support\ModelPricing::class,
    ];

    /**
     * Get pricing for a specific model and provider.
     *
     * @param  string  $provider  The provider name
     * @param  string  $model  The model name
     * @return array The pricing data
     */
    public function getModelPricing(string $provider, string $model): array
    {
        $pricingClass = $this->pricingClasses[strtolower($provider)] ?? null;

        if (! $pricingClass || ! class_exists($pricingClass)) {
            return $this->getDefaultPricing();
        }

        try {
            return $pricingClass::getModelPricing($model);
        } catch (\Exception $e) {
            return $this->getDefaultPricing();
        }
    }

    /**
     * Calculate cost for token usage.
     *
     * @param  string  $provider  The provider name
     * @param  string  $model  The model name
     * @param  int  $inputTokens  The input token count
     * @param  int  $outputTokens  The output token count
     * @return array The cost calculation
     */
    public function calculateCost(string $provider, string $model, int $inputTokens, int $outputTokens): array
    {
        $pricingClass = $this->pricingClasses[strtolower($provider)] ?? null;

        if (! $pricingClass || ! class_exists($pricingClass)) {
            return $this->calculateDefaultCost($inputTokens, $outputTokens, $model);
        }

        try {
            return $pricingClass::calculateCost($inputTokens, $outputTokens, $model);
        } catch (\Exception $e) {
            return $this->calculateDefaultCost($inputTokens, $outputTokens, $model);
        }
    }

    /**
     * Estimate cost for a given number of tokens.
     *
     * @param  string  $provider  The provider name
     * @param  string  $model  The model name
     * @param  int  $estimatedTokens  The estimated token count
     * @return array The cost estimation
     */
    public function estimateCost(string $provider, string $model, int $estimatedTokens): array
    {
        $pricingClass = $this->pricingClasses[strtolower($provider)] ?? null;

        if (! $pricingClass || ! class_exists($pricingClass)) {
            return $this->estimateDefaultCost($estimatedTokens, $model);
        }

        try {
            return $pricingClass::estimateCost($estimatedTokens, $model);
        } catch (\Exception $e) {
            return $this->estimateDefaultCost($estimatedTokens, $model);
        }
    }

    /**
     * Get all pricing data for a provider.
     *
     * @param  string  $provider  The provider name
     * @return array All pricing data
     */
    public function getAllProviderPricing(string $provider): array
    {
        $pricingClass = $this->pricingClasses[strtolower($provider)] ?? null;

        if (! $pricingClass || ! class_exists($pricingClass)) {
            return [];
        }

        try {
            return $pricingClass::getAllModelPricing();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Compare costs across providers for the same input.
     *
     * @param  array  $providers  The providers to compare
     * @param  string  $model  The model name (or null for default)
     * @param  int  $inputTokens  The input token count
     * @param  int  $outputTokens  The output token count
     * @return array Cost comparison
     */
    public function compareProviderCosts(array $providers, ?string $model, int $inputTokens, int $outputTokens): array
    {
        $comparisons = [];

        foreach ($providers as $provider) {
            $modelToUse = $model ?? $this->getDefaultModel($provider);
            $cost = $this->calculateCost($provider, $modelToUse, $inputTokens, $outputTokens);
            $comparisons[$provider] = $cost;
        }

        // Sort by total cost
        uasort($comparisons, function ($a, $b) {
            return ($a['total_cost'] ?? 0) <=> ($b['total_cost'] ?? 0);
        });

        return $comparisons;
    }

    /**
     * Find the most cost-effective provider for a task.
     *
     * @param  array  $providers  The providers to consider
     * @param  int  $inputTokens  The input token count
     * @param  int  $outputTokens  The output token count
     * @param  array  $requirements  Additional requirements
     * @return string The most cost-effective provider
     */
    public function findCheapestProvider(array $providers, int $inputTokens, int $outputTokens, array $requirements = []): string
    {
        $comparisons = $this->compareProviderCosts($providers, null, $inputTokens, $outputTokens);

        // Return the first (cheapest) provider
        return array_key_first($comparisons) ?? 'openai';
    }

    /**
     * Get default model for a provider.
     *
     * @param  string  $provider  The provider name
     * @return string The default model
     */
    protected function getDefaultModel(string $provider): string
    {
        return match (strtolower($provider)) {
            'openai' => 'gpt-4o-mini',
            'gemini' => 'gemini-2.0-flash',
            'xai' => 'grok-2-1212',
            default => 'gpt-4o-mini',
        };
    }

    /**
     * Get default pricing fallback.
     *
     * @return array Default pricing
     */
    protected function getDefaultPricing(): array
    {
        return [
            'input' => 0.00001,  // $0.01 per 1K tokens
            'output' => 0.00002, // $0.02 per 1K tokens
        ];
    }

    /**
     * Calculate cost using default pricing.
     *
     * @param  int  $inputTokens  The input token count
     * @param  int  $outputTokens  The output token count
     * @param  string  $model  The model name
     * @return array The cost calculation
     */
    protected function calculateDefaultCost(int $inputTokens, int $outputTokens, string $model): array
    {
        $pricing = $this->getDefaultPricing();

        $inputCost = ($inputTokens / 1000) * $pricing['input'];
        $outputCost = ($outputTokens / 1000) * $pricing['output'];
        $totalCost = $inputCost + $outputCost;

        return [
            'model' => $model,
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
     * Estimate cost using default pricing.
     *
     * @param  int  $estimatedTokens  The estimated token count
     * @param  string  $model  The model name
     * @return array The cost estimation
     */
    protected function estimateDefaultCost(int $estimatedTokens, string $model): array
    {
        // Estimate 75% input, 25% output
        $estimatedInputTokens = (int) ($estimatedTokens * 0.75);
        $estimatedOutputTokens = (int) ($estimatedTokens * 0.25);

        return $this->calculateDefaultCost($estimatedInputTokens, $estimatedOutputTokens, $model);
    }

    /**
     * Get cached pricing data with TTL.
     *
     * @param  string  $provider  The provider name
     * @param  string  $model  The model name
     * @return array Cached pricing data
     */
    public function getCachedPricing(string $provider, string $model): array
    {
        $cacheKey = "ai_pricing:{$provider}:{$model}";

        return Cache::remember($cacheKey, 3600, function () use ($provider, $model) {
            return $this->getModelPricing($provider, $model);
        });
    }

    /**
     * Clear pricing cache for a provider or model.
     *
     * @param  string|null  $provider  The provider name (null for all)
     * @param  string|null  $model  The model name (null for all models)
     */
    public function clearPricingCache(?string $provider = null, ?string $model = null): void
    {
        if ($provider && $model) {
            Cache::forget("ai_pricing:{$provider}:{$model}");
        } elseif ($provider) {
            // Clear all models for provider
            $pattern = "ai_pricing:{$provider}:*";
            // Note: This would need a more sophisticated cache clearing mechanism
            // For now, we'll just clear the common models
            foreach (['gpt-4o', 'gpt-4o-mini', 'gemini-2.0-flash', 'grok-2-1212'] as $commonModel) {
                Cache::forget("ai_pricing:{$provider}:{$commonModel}");
            }
        } else {
            // Clear all pricing cache
            Cache::flush(); // This is aggressive, but ensures all pricing is cleared
        }
    }
}
