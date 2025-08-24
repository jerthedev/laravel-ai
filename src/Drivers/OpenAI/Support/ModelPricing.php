<?php

namespace JTD\LaravelAI\Drivers\OpenAI\Support;

use JTD\LaravelAI\Contracts\PricingInterface;
use JTD\LaravelAI\Enums\BillingModel;
use JTD\LaravelAI\Enums\PricingUnit;

/**
 * OpenAI Model Pricing Data
 *
 * Centralized pricing information for OpenAI models implementing the standardized
 * pricing interface with proper enums and validation.
 */
class ModelPricing implements PricingInterface
{
    /**
     * OpenAI model pricing with standardized format using enums.
     */
    public static array $pricing = [
        'gpt-3.5-turbo' => [
            'input' => 0.0015,
            'output' => 0.002,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2025-01-01',
        ],
        'gpt-3.5-turbo-16k' => [
            'input' => 0.003,
            'output' => 0.004,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2025-01-01',
        ],
        'gpt-3.5-turbo-0125' => [
            'input' => 0.0005,
            'output' => 0.0015,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2025-01-01',
        ],
        'gpt-3.5-turbo-1106' => [
            'input' => 0.001,
            'output' => 0.002,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2025-01-01',
        ],
        'gpt-4' => [
            'input' => 0.03,
            'output' => 0.06,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2025-01-01',
        ],
        'gpt-4-32k' => [
            'input' => 0.06,
            'output' => 0.12,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2025-01-01',
        ],
        'gpt-4-turbo' => [
            'input' => 0.01,
            'output' => 0.03,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2025-01-01',
        ],
        'gpt-4-turbo-preview' => [
            'input' => 0.01,
            'output' => 0.03,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2025-01-01',
        ],
        'gpt-4-1106-preview' => [
            'input' => 0.01,
            'output' => 0.03,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2025-01-01',
        ],
        'gpt-4-0125-preview' => [
            'input' => 0.01,
            'output' => 0.03,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2025-01-01',
        ],
        'gpt-4o' => [
            'input' => 0.0025,
            'output' => 0.01,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2025-01-01',
        ],
        'gpt-4o-2024-11-20' => [
            'input' => 0.0025,
            'output' => 0.01,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2024-11-20',
        ],
        'gpt-4o-2024-08-06' => [
            'input' => 0.0025,
            'output' => 0.01,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2024-08-06',
        ],
        'gpt-4o-2024-05-13' => [
            'input' => 0.005,
            'output' => 0.015,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2024-05-13',
        ],
        'gpt-4o-mini' => [
            'input' => 0.00015,
            'output' => 0.0006,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2025-01-01',
        ],
        'gpt-4o-mini-2024-07-18' => [
            'input' => 0.00015,
            'output' => 0.0006,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2024-07-18',
        ],
        'gpt-5' => [
            'input' => 0.01,
            'output' => 0.03,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2025-01-01',
        ],
        'gpt-5-2025-08-07' => [
            'input' => 0.01,
            'output' => 0.03,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2025-08-07',
        ],
        'text-embedding-ada-002' => [
            'input' => 0.0001,
            'output' => 0.0001,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2025-01-01',
        ],
        'text-embedding-3-small' => [
            'input' => 0.00002,
            'output' => 0.00002,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2025-01-01',
        ],
        'text-embedding-3-large' => [
            'input' => 0.00013,
            'output' => 0.00013,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2025-01-01',
        ],
        'whisper-1' => [
            'cost' => 0.006,
            'unit' => PricingUnit::PER_MINUTE,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2025-01-01',
        ],
        'tts-1' => [
            'cost' => 0.015,
            'unit' => PricingUnit::PER_1K_CHARACTERS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2025-01-01',
        ],
        'tts-1-hd' => [
            'cost' => 0.030,
            'unit' => PricingUnit::PER_1K_CHARACTERS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2025-01-01',
        ],
        'dall-e-2' => [
            'cost' => 0.020,
            'unit' => PricingUnit::PER_IMAGE,
            'size' => '1024x1024',
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2025-01-01',
        ],
        'dall-e-3' => [
            'cost' => 0.040,
            'unit' => PricingUnit::PER_IMAGE,
            'size' => '1024x1024',
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2025-01-01',
        ],
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

        // Default fallback pricing (GPT-3.5 Turbo rates)
        return [
            'input' => 0.0015,
            'output' => 0.002,
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'effective_date' => '2025-01-01',
            'source' => 'fallback',
        ];
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
            PricingUnit::PER_1M_TOKENS => $this->calculateTokenCostPerMillion($pricing, $usage),
            PricingUnit::PER_IMAGE => $pricing['cost'] * ($usage['images'] ?? 1),
            PricingUnit::PER_MINUTE => $pricing['cost'] * ($usage['minutes'] ?? 0),
            PricingUnit::PER_1K_CHARACTERS => $pricing['cost'] * (($usage['characters'] ?? 0) / 1000),
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
     * Calculate cost for token-based models per million tokens.
     */
    private function calculateTokenCostPerMillion(array $pricing, array $usage): float
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
            PricingUnit::PER_1K_TOKENS,
            PricingUnit::PER_IMAGE,
            PricingUnit::PER_MINUTE,
            PricingUnit::PER_1K_CHARACTERS,
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
            if (isset($data['unit'])) {
                $unit = $data['unit'];
                if (in_array($unit, [PricingUnit::PER_1K_TOKENS])) {
                    if (! isset($data['input']) || ! isset($data['output'])) {
                        $errors[] = "Model '{$model}' with token pricing must have 'input' and 'output' fields";
                    }
                } elseif (in_array($unit, [PricingUnit::PER_IMAGE, PricingUnit::PER_MINUTE, PricingUnit::PER_1K_CHARACTERS])) {
                    if (! isset($data['cost'])) {
                        $errors[] = "Model '{$model}' with unit pricing must have 'cost' field";
                    }
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
     * Calculate cost breakdown with detailed information.
     */
    public function calculateDetailedCost(string $model, array $usage): array
    {
        $pricing = $this->getModelPricing($model);
        $totalCost = $this->calculateCost($model, $usage);

        $result = [
            'model' => $model,
            'total_cost' => $totalCost,
            'currency' => $pricing['currency'] ?? 'USD',
            'unit' => $pricing['unit']?->value ?? 'unknown',
            'billing_model' => $pricing['billing_model']?->value ?? 'unknown',
        ];

        // Add unit-specific details
        if (isset($pricing['unit'])) {
            $unit = $pricing['unit'];
            if ($unit === PricingUnit::PER_1K_TOKENS) {
                $inputTokens = $usage['input_tokens'] ?? 0;
                $outputTokens = $usage['output_tokens'] ?? 0;
                $result['input_tokens'] = $inputTokens;
                $result['output_tokens'] = $outputTokens;
                $result['input_cost'] = ($inputTokens / 1000) * ($pricing['input'] ?? 0);
                $result['output_cost'] = ($outputTokens / 1000) * ($pricing['output'] ?? 0);
            } elseif ($unit === PricingUnit::PER_IMAGE) {
                $result['images'] = $usage['images'] ?? 1;
                $result['cost_per_image'] = $pricing['cost'] ?? 0;
            } elseif ($unit === PricingUnit::PER_MINUTE) {
                $result['minutes'] = $usage['minutes'] ?? 0;
                $result['cost_per_minute'] = $pricing['cost'] ?? 0;
            } elseif ($unit === PricingUnit::PER_1K_CHARACTERS) {
                $result['characters'] = $usage['characters'] ?? 0;
                $result['cost_per_1k_characters'] = $pricing['cost'] ?? 0;
            }
        }

        return $result;
    }

    /**
     * Get pricing tiers if the provider uses tiered pricing.
     */
    public function getPricingTiers(string $model): array
    {
        // OpenAI doesn't use tiered pricing
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

        $unit = $pricing['unit'];

        if ($unit === PricingUnit::PER_1K_TOKENS) {
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
        } elseif ($unit === PricingUnit::PER_1K_CHARACTERS) {
            return $this->calculateCost($model, [
                'characters' => strlen($input),
            ]);
        }

        return 0.0;
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
            PricingUnit::PER_1K_CHARACTERS => 1000, // Minimum 1K characters
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
