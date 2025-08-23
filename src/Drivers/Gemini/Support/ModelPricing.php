<?php

namespace JTD\LaravelAI\Drivers\Gemini\Support;

use JTD\LaravelAI\Contracts\PricingInterface;
use JTD\LaravelAI\Enums\BillingModel;
use JTD\LaravelAI\Enums\PricingUnit;

/**
 * Gemini Model Pricing Data
 *
 * Centralized pricing information for Gemini models implementing the standardized
 * pricing interface with proper enums and validation.
 */
class ModelPricing implements PricingInterface
{
    /**
     * Gemini model pricing with standardized format using enums.
     * Prices are in USD per 1K tokens (updated 2025).
     */
    public static array $pricing = [
        // Current generation models (2025 pricing)
        'gemini-2.5-pro' => [
            'input' => 1.25,
            'output' => 10.00,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2025-01-01',
        ],
        'gemini-2.5-flash' => [
            'input' => 0.30,
            'output' => 2.50,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2025-01-01',
        ],
        'gemini-2.5-flash-lite' => [
            'input' => 0.10,
            'output' => 0.40,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2025-01-01',
        ],
        'gemini-2.0-flash' => [
            'input' => 0.075,
            'output' => 0.30,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2025-01-01',
        ],
        'gemini-2.0-flash-lite' => [
            'input' => 0.075,
            'output' => 0.30,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2025-01-01',
        ],
        'gemini-2.0-pro' => [
            'input' => 1.25,
            'output' => 10.00,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2025-01-01',
        ],

        // Previous generation models (updated pricing)
        'gemini-1.5-pro' => [
            'input' => 1.25,
            'output' => 5.00,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2024-08-01',
        ],
        'gemini-1.5-flash' => [
            'input' => 0.075,
            'output' => 0.30,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2024-08-01',
        ],
        'gemini-1.5-pro-exp-0801' => [
            'input' => 1.25,
            'output' => 5.00,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2024-08-01',
        ],
        'gemini-1.5-flash-exp-0827' => [
            'input' => 0.075,
            'output' => 0.30,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2024-08-27',
        ],

        // Legacy models (legacy pricing)
        'gemini-pro' => [
            'input' => 0.0005,
            'output' => 0.0015,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::FREE_TIER,
            'effective_date' => '2024-01-01',
        ],
        'gemini-pro-vision' => [
            'input' => 0.00025,
            'output' => 0.00025,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::FREE_TIER,
            'effective_date' => '2024-01-01',
        ],
        'gemini-1.0-pro' => [
            'input' => 0.0005,
            'output' => 0.0015,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::FREE_TIER,
            'effective_date' => '2024-01-01',
        ],
        'gemini-1.0-pro-vision' => [
            'input' => 0.00025,
            'output' => 0.00025,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::FREE_TIER,
            'effective_date' => '2024-01-01',
        ],
        'gemini-1.0-pro-001' => [
            'input' => 0.0005,
            'output' => 0.0015,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::FREE_TIER,
            'effective_date' => '2024-01-01',
        ],
        'gemini-1.0-pro-latest' => [
            'input' => 0.0005,
            'output' => 0.0015,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::FREE_TIER,
            'effective_date' => '2024-01-01',
        ],
        'gemini-1.0-pro-vision-latest' => [
            'input' => 0.00025,
            'output' => 0.00025,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::FREE_TIER,
            'effective_date' => '2024-01-01',
        ],
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
    public function getModelPricing(string $model): array
    {
        // Handle model variations and fallbacks
        $normalizedModel = $this->normalizeModelName($model);

        if (isset(static::$pricing[$normalizedModel])) {
            return static::$pricing[$normalizedModel];
        }

        // Try to find a base model match
        foreach (static::$pricing as $modelName => $pricing) {
            if (str_starts_with($normalizedModel, $modelName)) {
                return $pricing;
            }
        }

        // Default fallback pricing (Gemini Pro rates)
        return [
            'input' => 0.0005,
            'output' => 0.0015,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::FREE_TIER,
            'effective_date' => '2024-01-01',
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
            PricingUnit::PER_1K_TOKENS => $this->calculateTokenCost($pricing, $usage),
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

        $inputCost = ($inputTokens / 1000) * ($pricing['input'] ?? 0);
        $outputCost = ($outputTokens / 1000) * ($pricing['output'] ?? 0);

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
            PricingUnit::PER_1K_TOKENS,
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
            if (isset($data['unit']) && $data['unit'] === PricingUnit::PER_1K_TOKENS) {
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
        // Remove common suffixes that don't affect pricing
        $modelId = preg_replace('/-\d{4}-\d{2}-\d{2}$/', '', $modelId);
        $modelId = str_replace(['-preview', '-latest'], '', $modelId);

        return strtolower($modelId);
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
            'input_cost' => ($inputTokens / 1000) * ($pricing['input'] ?? 0),
            'output_cost' => ($outputTokens / 1000) * ($pricing['output'] ?? 0),
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
        // Gemini uses tiered pricing for some models
        $pricing = $this->getModelPricing($model);

        if (isset($pricing['billing_model']) && $pricing['billing_model'] === BillingModel::TIERED) {
            return [
                ['min' => 0, 'max' => 1000000, 'rate' => $pricing['input']],
                ['min' => 1000001, 'max' => 10000000, 'rate' => $pricing['input'] * 0.8],
                ['min' => 10000001, 'max' => null, 'rate' => $pricing['input'] * 0.6],
            ];
        }

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

        // Estimate 75% input, 25% output for cost estimation
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
            PricingUnit::PER_1K_TOKENS => 1000, // Minimum 1K tokens
            default => 1,
        };
    }

    /**
     * Check if a model has pricing information.
     */
    public function hasPricing(string $modelId): bool
    {
        $normalizedModel = $this->normalizeModelName($modelId);

        return isset(static::$pricing[$normalizedModel]);
    }
}
