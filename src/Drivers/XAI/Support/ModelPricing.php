<?php

namespace JTD\LaravelAI\Drivers\XAI\Support;

use JTD\LaravelAI\Contracts\PricingInterface;
use JTD\LaravelAI\Enums\BillingModel;
use JTD\LaravelAI\Enums\PricingUnit;

/**
 * xAI Model Pricing Data
 *
 * Centralized pricing information for xAI Grok models implementing the standardized
 * pricing interface with proper enums and validation.
 */
class ModelPricing implements PricingInterface
{
    /**
     * xAI model pricing with standardized format using enums.
     * Updated January 2025 with latest pricing from xAI API.
     */
    public static array $pricing = [
        'grok-beta' => [
            'input' => 5.00,
            'output' => 15.00,
            'unit' => PricingUnit::PER_1M_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2024-12-01',
        ],
        'grok-2' => [
            'input' => 2.00,
            'output' => 10.00,
            'unit' => PricingUnit::PER_1M_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2024-12-01',
        ],
        'grok-2-1212' => [
            'input' => 2.00,
            'output' => 10.00,
            'unit' => PricingUnit::PER_1M_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2024-12-12',
        ],
        'grok-2-vision-1212' => [
            'input' => 2.00,
            'output' => 10.00,
            'unit' => PricingUnit::PER_1M_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2024-12-12',
        ],
        'grok-4' => [
            'input' => 3.00,
            'output' => 15.00,
            'unit' => PricingUnit::PER_1M_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2025-01-01',
        ],
        'grok-4-0709' => [
            'input' => 3.00,
            'output' => 15.00,
            'unit' => PricingUnit::PER_1M_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2024-07-09',
        ],
        'grok-2-mini' => [
            'input' => 1.00,
            'output' => 5.00,
            'unit' => PricingUnit::PER_1M_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2024-12-01',
        ],
    ];

    /**
     * Get pricing for a specific model.
     */
    public function getModelPricing(string $model): array
    {
        $normalizedId = $this->normalizeModelName($model);

        if (isset(static::$pricing[$normalizedId])) {
            return static::$pricing[$normalizedId];
        }

        // Default fallback pricing
        return [
            'input' => 5.00,
            'output' => 15.00,
            'unit' => PricingUnit::PER_1M_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2024-12-01',
            'source' => 'fallback',
        ];
    }

    /**
     * Calculate cost based on usage metrics.
     */
    public function calculateCost(string $model, array $usage): float
    {
        $pricing = $this->getModelPricing($model);

        if (empty($pricing)) {
            return 0.0;
        }

        $unit = $pricing['unit'];

        return match ($unit) {
            PricingUnit::PER_1M_TOKENS => $this->calculateTokenCost($pricing, $usage),
            default => 0.0,
        };
    }

    /**
     * Calculate cost for token-based models.
     */
    private function calculateTokenCost(array $pricing, array $usage): float
    {
        $inputTokens = $usage['input_tokens'] ?? 0;
        $outputTokens = $usage['output_tokens'] ?? 0;

        $inputCost = ($inputTokens / 1000000) * ($pricing['input'] ?? 0);
        $outputCost = ($outputTokens / 1000000) * ($pricing['output'] ?? 0);

        return $inputCost + $outputCost;
    }

    /**
     * Get all available models with pricing.
     */
    public function getAllModelPricing(): array
    {
        return static::$pricing;
    }

    /**
     * Get the pricing units supported by this provider.
     */
    public function getSupportedUnits(): array
    {
        return [
            PricingUnit::PER_1M_TOKENS,
        ];
    }

    /**
     * Validate the pricing configuration for this provider.
     */
    public function validatePricing(): array
    {
        $errors = [];

        foreach (static::$pricing as $model => $data) {
            if (! isset($data['unit']) || ! $data['unit'] instanceof PricingUnit) {
                $errors[] = "Model '{$model}' missing or invalid unit";
            }

            if (! isset($data['billing_model']) || ! $data['billing_model'] instanceof BillingModel) {
                $errors[] = "Model '{$model}' missing or invalid billing_model";
            }

            if (! isset($data['currency'])) {
                $errors[] = "Model '{$model}' missing currency";
            }

            // Unit-specific validation
            if (isset($data['unit']) && $data['unit'] === PricingUnit::PER_1M_TOKENS) {
                if (! isset($data['input']) || ! isset($data['output'])) {
                    $errors[] = "Model '{$model}' with token pricing must have 'input' and 'output' fields";
                }
            }
        }

        return $errors;
    }

    /**
     * Get the default currency used by this provider.
     */
    public function getDefaultCurrency(): string
    {
        return 'USD';
    }

    /**
     * Check if a model supports a specific pricing unit.
     */
    public function supportsUnit(string $model, PricingUnit $unit): bool
    {
        $pricing = $this->getModelPricing($model);

        return isset($pricing['unit']) && $pricing['unit'] === $unit;
    }

    /**
     * Get the effective date for pricing information.
     */
    public function getEffectiveDate(string $model): ?string
    {
        $pricing = $this->getModelPricing($model);

        return $pricing['effective_date'] ?? null;
    }

    /**
     * Normalize model name for pricing lookup.
     */
    protected function normalizeModelName(string $modelId): string
    {
        // Remove any version suffixes or prefixes that don't affect pricing
        $normalized = strtolower(trim($modelId));

        // Handle common variations
        $normalized = str_replace(['_', ' '], '-', $normalized);

        return $normalized;
    }

    /**
     * Calculate cost breakdown with detailed information.
     */
    public function calculateDetailedCost(string $model, array $usage): array
    {
        $pricing = $this->getModelPricing($model);
        $totalCost = $this->calculateCost($model, $usage);

        $inputTokens = $usage['input_tokens'] ?? 0;
        $outputTokens = $usage['output_tokens'] ?? 0;

        return [
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'input_cost' => ($inputTokens / 1000000) * ($pricing['input'] ?? 0),
            'output_cost' => ($outputTokens / 1000000) * ($pricing['output'] ?? 0),
            'total_cost' => $totalCost,
            'currency' => $pricing['currency'] ?? 'USD',
            'unit' => $pricing['unit']?->value ?? 'unknown',
            'billing_model' => $pricing['billing_model']?->value ?? 'unknown',
        ];
    }

    /**
     * Get pricing tiers if the provider uses tiered pricing.
     */
    public function getPricingTiers(string $model): array
    {
        // xAI doesn't use tiered pricing
        return [];
    }

    /**
     * Check if pricing data is current and up-to-date.
     */
    public function isPricingCurrent(): bool
    {
        // Consider pricing current if it's within the last 30 days
        $lastUpdated = $this->getLastUpdated();
        if (! $lastUpdated) {
            return false;
        }

        return $lastUpdated->diff(new \DateTime)->days <= 30;
    }

    /**
     * Get the last update timestamp for pricing data.
     */
    public function getLastUpdated(): ?\DateTimeInterface
    {
        // Return the most recent effective date from all models
        $dates = [];
        foreach (static::$pricing as $pricing) {
            if (isset($pricing['effective_date'])) {
                $dates[] = $pricing['effective_date'];
            }
        }

        if (empty($dates)) {
            return null;
        }

        $latestDate = max($dates);

        return \DateTime::createFromFormat('Y-m-d', $latestDate) ?: null;
    }

    /**
     * Estimate cost for a given input before making the actual request.
     */
    public function estimateCost(string $model, string $input, array $options = []): float
    {
        $pricing = $this->getModelPricing($model);

        if (empty($pricing) || ! isset($pricing['unit'])) {
            return 0.0;
        }

        // Rough estimation: 4 characters per token
        $estimatedTokens = strlen($input) / 4;
        $maxTokens = $options['max_tokens'] ?? ($estimatedTokens * 0.5); // Estimate output

        // Estimate 70% input, 30% output for cost estimation
        $estimatedInputTokens = (int) ($estimatedTokens);
        $estimatedOutputTokens = (int) ($maxTokens);

        return $this->calculateCost($model, [
            'input_tokens' => $estimatedInputTokens,
            'output_tokens' => $estimatedOutputTokens,
        ]);
    }

    /**
     * Get minimum billable unit for a model.
     */
    public function getMinimumBillableUnit(string $model): ?int
    {
        $pricing = $this->getModelPricing($model);

        if (! isset($pricing['unit'])) {
            return null;
        }

        return match ($pricing['unit']) {
            PricingUnit::PER_1M_TOKENS => 1000000, // Minimum 1M tokens
            default => 1,
        };
    }

    /**
     * Check if a model has pricing information.
     */
    public function hasPricing(string $modelId): bool
    {
        $normalizedId = $this->normalizeModelName($modelId);

        return isset(static::$pricing[$normalizedId]);
    }
}
