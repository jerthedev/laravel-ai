<?php

namespace JTD\LaravelAI\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use JTD\LaravelAI\Enums\BillingModel;
use JTD\LaravelAI\Enums\PricingUnit;

/**
 * Enhanced pricing service with database-first fallback chain.
 *
 * This service implements a three-tier fallback system:
 * 1. Database pricing (ai_provider_model_costs)
 * 2. Driver static defaults
 * 3. Universal fallback
 *
 * Includes caching, unit normalization, and comprehensive cost calculation.
 */
class PricingService
{
    public function __construct(
        protected DriverManager $driverManager,
        protected PricingValidator $validator
    ) {}

    /**
     * Get pricing with database-first fallback chain.
     *
     * @param  string  $provider  The AI provider name
     * @param  string  $model  The model identifier
     * @return array Pricing data with source information
     */
    public function getModelPricing(string $provider, string $model): array
    {
        // 1. Try database first
        if ($dbPricing = $this->getFromDatabase($provider, $model)) {
            return $dbPricing;
        }

        // 2. Fallback to driver static defaults
        if ($driverPricing = $this->getFromDriver($provider, $model)) {
            return $driverPricing;
        }

        // 3. Universal fallback
        return $this->getUniversalFallback();
    }

    /**
     * Calculate cost using database-first pricing.
     *
     * @param  string  $provider  The AI provider name
     * @param  string  $model  The model identifier
     * @param  int  $inputTokens  Number of input tokens
     * @param  int  $outputTokens  Number of output tokens
     * @return array Detailed cost breakdown
     */
    public function calculateCost(string $provider, string $model, int $inputTokens, int $outputTokens): array
    {
        $pricing = $this->getModelPricing($provider, $model);

        if (empty($pricing)) {
            return $this->getDefaultCostCalculation($inputTokens, $outputTokens);
        }

        $usage = [
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
        ];

        // Use driver's calculation method if available
        if ($driver = $this->getDriverPricingClass($provider)) {
            $totalCost = $driver->calculateCost($model, $usage);
        } else {
            $totalCost = $this->calculateGenericCost($pricing, $usage);
        }

        return [
            'model' => $model,
            'provider' => $provider,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'input_cost' => $this->calculateInputCost($pricing, $inputTokens),
            'output_cost' => $this->calculateOutputCost($pricing, $outputTokens),
            'total_cost' => $totalCost,
            'currency' => $pricing['currency'] ?? 'USD',
            'unit' => $pricing['unit']?->value ?? 'unknown',
            'source' => $this->getPricingSource($provider, $model),
        ];
    }

    /**
     * Get pricing from database.
     */
    private function getFromDatabase(string $provider, string $model): ?array
    {
        $cacheKey = "pricing:db:{$provider}:{$model}";

        return Cache::remember($cacheKey, 3600, function () use ($provider, $model) {
            $result = DB::table('ai_provider_model_costs as costs')
                ->join('ai_provider_models as models', 'costs.ai_provider_model_id', '=', 'models.id')
                ->join('ai_providers as providers', 'models.ai_provider_id', '=', 'providers.id')
                ->where('providers.name', $provider)
                ->where('models.name', $model)
                ->where('costs.is_current', true)
                ->select([
                    'costs.cost_per_unit',
                    'costs.unit_type',
                    'costs.cost_type',
                    'costs.currency',
                    'costs.billing_model',
                    'costs.effective_from',
                ])
                ->get();

            if ($result->isEmpty()) {
                return;
            }

            // Transform database results to pricing array
            return $this->transformDatabasePricing($result);
        });
    }

    /**
     * Get pricing from driver static defaults.
     */
    private function getFromDriver(string $provider, string $model): ?array
    {
        try {
            $pricingClass = $this->getDriverPricingClass($provider);

            if ($pricingClass) {
                return $pricingClass->getModelPricing($model);
            }
        } catch (\Exception $e) {
            // Driver not available or no pricing class
        }

        return null;
    }

    /**
     * Get driver pricing class instance.
     */
    private function getDriverPricingClass(string $provider): ?object
    {
        $pricingClass = '\\JTD\\LaravelAI\\Drivers\\' . ucfirst($provider) . '\\Support\\ModelPricing';

        if (class_exists($pricingClass)) {
            return new $pricingClass;
        }

        return null;
    }

    /**
     * Get universal fallback pricing.
     */
    private function getUniversalFallback(): array
    {
        return [
            'input' => 0.00001,  // $0.01 per 1K tokens
            'output' => 0.00002, // $0.02 per 1K tokens
            'unit' => PricingUnit::PER_1K_TOKENS,
            'currency' => 'USD',
            'billing_model' => BillingModel::PAY_PER_USE,
            'source' => 'universal_fallback',
            'effective_date' => now()->toDateString(),
        ];
    }

    /**
     * Transform database pricing results to standardized format.
     */
    private function transformDatabasePricing($results): array
    {
        $pricing = [
            'currency' => $results->first()->currency ?? 'USD',
            'billing_model' => BillingModel::from($results->first()->billing_model ?? 'pay_per_use'),
            'unit' => PricingUnit::from($results->first()->unit_type ?? '1k_tokens'),
            'effective_date' => $results->first()->effective_from,
            'source' => 'database',
        ];

        foreach ($results as $cost) {
            if ($cost->cost_type === 'input') {
                $pricing['input'] = $cost->cost_per_unit;
            } elseif ($cost->cost_type === 'output') {
                $pricing['output'] = $cost->cost_per_unit;
            } else {
                $pricing['cost'] = $cost->cost_per_unit;
            }
        }

        return $this->validateAndCleanPricing($pricing);
    }

    /**
     * Validate and clean pricing data from database.
     */
    private function validateAndCleanPricing(array $pricing): array
    {
        // Ensure numeric values are properly typed
        if (isset($pricing['input'])) {
            $pricing['input'] = (float) $pricing['input'];
        }

        if (isset($pricing['output'])) {
            $pricing['output'] = (float) $pricing['output'];
        }

        if (isset($pricing['cost'])) {
            $pricing['cost'] = (float) $pricing['cost'];
        }

        // Validate enum values
        if (isset($pricing['unit']) && ! $pricing['unit'] instanceof PricingUnit) {
            try {
                $pricing['unit'] = PricingUnit::from($pricing['unit']);
            } catch (\ValueError $e) {
                $pricing['unit'] = PricingUnit::PER_1K_TOKENS; // Default fallback
            }
        }

        if (isset($pricing['billing_model']) && ! $pricing['billing_model'] instanceof BillingModel) {
            try {
                $pricing['billing_model'] = BillingModel::from($pricing['billing_model']);
            } catch (\ValueError $e) {
                $pricing['billing_model'] = BillingModel::PAY_PER_USE; // Default fallback
            }
        }

        return $pricing;
    }

    /**
     * Store pricing data to database.
     *
     * @param  string  $provider  The provider name
     * @param  string  $model  The model name
     * @param  array  $pricing  The pricing data to store
     * @return bool Success status
     */
    public function storePricingToDatabase(string $provider, string $model, array $pricing): bool
    {
        try {
            // Validate pricing data first
            $errors = $this->validator->validateModelPricing($model, $pricing);
            if (! empty($errors)) {
                logger()->warning('Invalid pricing data, cannot store to database', [
                    'provider' => $provider,
                    'model' => $model,
                    'errors' => $errors,
                ]);

                return false;
            }

            // Get provider and model IDs
            $providerId = $this->getOrCreateProviderId($provider);
            $modelId = $this->getOrCreateModelId($providerId, $model);

            // Mark existing costs as not current
            DB::table('ai_provider_model_costs')
                ->where('ai_provider_model_id', $modelId)
                ->update(['is_current' => false]);

            // Insert new pricing data
            $this->insertPricingData($modelId, $pricing);

            // Clear cache for this model
            $this->clearCache($provider, $model);

            return true;
        } catch (\Exception $e) {
            logger()->error('Failed to store pricing to database', [
                'provider' => $provider,
                'model' => $model,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get or create provider ID.
     */
    private function getOrCreateProviderId(string $provider): int
    {
        $providerId = DB::table('ai_providers')
            ->where('name', $provider)
            ->value('id');

        if (! $providerId) {
            $providerId = DB::table('ai_providers')->insertGetId([
                'name' => $provider,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $providerId;
    }

    /**
     * Get or create model ID.
     */
    private function getOrCreateModelId(int $providerId, string $model): int
    {
        $modelId = DB::table('ai_provider_models')
            ->where('ai_provider_id', $providerId)
            ->where('name', $model)
            ->value('id');

        if (! $modelId) {
            $modelId = DB::table('ai_provider_models')->insertGetId([
                'ai_provider_id' => $providerId,
                'name' => $model,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $modelId;
    }

    /**
     * Insert pricing data into database.
     */
    private function insertPricingData(int $modelId, array $pricing): void
    {
        $baseData = [
            'ai_provider_model_id' => $modelId,
            'currency' => $pricing['currency'] ?? 'USD',
            'unit_type' => $pricing['unit']->value,
            'billing_model' => $pricing['billing_model']->value,
            'effective_from' => $pricing['effective_date'] ?? now()->toDateString(),
            'is_current' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Insert input cost if present
        if (isset($pricing['input'])) {
            DB::table('ai_provider_model_costs')->insert(array_merge($baseData, [
                'cost_type' => 'input',
                'cost_per_unit' => $pricing['input'],
            ]));
        }

        // Insert output cost if present
        if (isset($pricing['output'])) {
            DB::table('ai_provider_model_costs')->insert(array_merge($baseData, [
                'cost_type' => 'output',
                'cost_per_unit' => $pricing['output'],
            ]));
        }

        // Insert single cost if present
        if (isset($pricing['cost'])) {
            DB::table('ai_provider_model_costs')->insert(array_merge($baseData, [
                'cost_type' => 'cost',
                'cost_per_unit' => $pricing['cost'],
            ]));
        }
    }

    /**
     * Calculate generic cost based on pricing unit.
     */
    private function calculateGenericCost(array $pricing, array $usage): float
    {
        $unit = $pricing['unit'];

        return match ($unit) {
            PricingUnit::PER_1K_TOKENS, PricingUnit::PER_1M_TOKENS => $this->calculateTokenBasedCost($pricing, $usage),
            PricingUnit::PER_IMAGE, PricingUnit::PER_REQUEST => $pricing['cost'] * ($usage['requests'] ?? 1),
            PricingUnit::PER_MINUTE => $pricing['cost'] * ($usage['minutes'] ?? 0),
            default => 0.0,
        };
    }

    /**
     * Calculate token-based cost with unit normalization.
     */
    private function calculateTokenBasedCost(array $pricing, array $usage): float
    {
        $inputTokens = $usage['input_tokens'] ?? 0;
        $outputTokens = $usage['output_tokens'] ?? 0;
        $unit = $pricing['unit'];

        $divisor = match ($unit) {
            PricingUnit::PER_1K_TOKENS => 1000,
            PricingUnit::PER_1M_TOKENS => 1000000,
            default => 1000,
        };

        $inputCost = ($inputTokens / $divisor) * ($pricing['input'] ?? 0);
        $outputCost = ($outputTokens / $divisor) * ($pricing['output'] ?? 0);

        return $inputCost + $outputCost;
    }

    /**
     * Calculate input cost.
     */
    private function calculateInputCost(array $pricing, int $inputTokens): float
    {
        if (! isset($pricing['input']) || ! isset($pricing['unit'])) {
            return 0.0;
        }

        $unit = $pricing['unit'];
        $divisor = match ($unit) {
            PricingUnit::PER_1K_TOKENS => 1000,
            PricingUnit::PER_1M_TOKENS => 1000000,
            default => 1000,
        };

        return ($inputTokens / $divisor) * $pricing['input'];
    }

    /**
     * Calculate output cost.
     */
    private function calculateOutputCost(array $pricing, int $outputTokens): float
    {
        if (! isset($pricing['output']) || ! isset($pricing['unit'])) {
            return 0.0;
        }

        $unit = $pricing['unit'];
        $divisor = match ($unit) {
            PricingUnit::PER_1K_TOKENS => 1000,
            PricingUnit::PER_1M_TOKENS => 1000000,
            default => 1000,
        };

        return ($outputTokens / $divisor) * $pricing['output'];
    }

    /**
     * Get pricing source for tracking.
     */
    private function getPricingSource(string $provider, string $model): string
    {
        if ($this->getFromDatabase($provider, $model)) {
            return 'database';
        } elseif ($this->getFromDriver($provider, $model)) {
            return 'driver_static';
        } else {
            return 'universal_fallback';
        }
    }

    /**
     * Get default cost calculation when no pricing available.
     */
    private function getDefaultCostCalculation(int $inputTokens, int $outputTokens): array
    {
        $fallback = $this->getUniversalFallback();
        $inputCost = ($inputTokens / 1000) * $fallback['input'];
        $outputCost = ($outputTokens / 1000) * $fallback['output'];

        return [
            'model' => 'unknown',
            'provider' => 'unknown',
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'input_cost' => $inputCost,
            'output_cost' => $outputCost,
            'total_cost' => $inputCost + $outputCost,
            'currency' => 'USD',
            'unit' => 'per_1k_tokens',
            'source' => 'universal_fallback',
        ];
    }

    /**
     * Clear pricing cache with advanced options.
     */
    public function clearCache(?string $provider = null, ?string $model = null): void
    {
        if ($provider && $model) {
            Cache::forget("pricing:db:{$provider}:{$model}");
        } elseif ($provider) {
            // Clear all models for provider
            $this->clearProviderCache($provider);
        } else {
            // Clear all pricing cache
            $this->clearAllPricingCache();
        }
    }

    /**
     * Clear cache for a specific provider.
     */
    private function clearProviderCache(string $provider): void
    {
        // Common models to clear
        $commonModels = [
            'openai' => ['gpt-4o', 'gpt-4o-mini', 'gpt-3.5-turbo', 'gpt-4-turbo'],
            'gemini' => ['gemini-2.0-flash', 'gemini-1.5-pro', 'gemini-pro'],
            'xai' => ['grok-2-1212', 'grok-2', 'grok-beta'],
        ];

        $models = $commonModels[strtolower($provider)] ?? [];

        foreach ($models as $model) {
            Cache::forget("pricing:db:{$provider}:{$model}");
        }
    }

    /**
     * Clear all pricing cache entries.
     */
    private function clearAllPricingCache(): void
    {
        // Clear known pricing cache keys
        $providers = ['openai', 'gemini', 'xai'];

        foreach ($providers as $provider) {
            $this->clearProviderCache($provider);
        }
    }

    /**
     * Warm up pricing cache for common models.
     */
    public function warmCache(): void
    {
        $commonCombinations = [
            ['openai', 'gpt-4o-mini'],
            ['openai', 'gpt-4o'],
            ['gemini', 'gemini-2.0-flash'],
            ['xai', 'grok-2-1212'],
        ];

        foreach ($commonCombinations as [$provider, $model]) {
            $this->getModelPricing($provider, $model);
        }
    }

    /**
     * Get cache statistics.
     */
    public function getCacheStats(): array
    {
        $stats = [
            'cache_hits' => 0,
            'cache_misses' => 0,
            'cached_models' => [],
        ];

        // This would need to be implemented based on cache driver
        // For now, return basic structure
        return $stats;
    }

    /**
     * Normalize pricing to a common unit for comparison.
     *
     * @param  array  $pricing  The pricing data
     * @param  PricingUnit  $targetUnit  The target unit to normalize to
     * @return array Normalized pricing data
     */
    public function normalizePricing(array $pricing, PricingUnit $targetUnit): array
    {
        if (! isset($pricing['unit'])) {
            return $pricing;
        }

        $sourceUnit = $pricing['unit'];

        if ($sourceUnit === $targetUnit) {
            return $pricing; // Already in target unit
        }

        // Only normalize compatible units
        if ($sourceUnit->getBaseUnit() !== $targetUnit->getBaseUnit()) {
            return $pricing; // Cannot normalize incompatible units
        }

        $normalized = $pricing;
        $normalized['unit'] = $targetUnit;

        // Convert pricing values
        if (isset($pricing['input'])) {
            $normalized['input'] = $this->convertPricingValue(
                $pricing['input'],
                $sourceUnit,
                $targetUnit
            );
        }

        if (isset($pricing['output'])) {
            $normalized['output'] = $this->convertPricingValue(
                $pricing['output'],
                $sourceUnit,
                $targetUnit
            );
        }

        if (isset($pricing['cost'])) {
            $normalized['cost'] = $this->convertPricingValue(
                $pricing['cost'],
                $sourceUnit,
                $targetUnit
            );
        }

        return $normalized;
    }

    /**
     * Convert a pricing value from one unit to another.
     */
    private function convertPricingValue(float $value, PricingUnit $fromUnit, PricingUnit $toUnit): float
    {
        // Convert to base unit first, then to target unit
        $baseValue = $fromUnit->convertToBaseUnit($value);

        return $toUnit->convertFromBaseUnit($baseValue);
    }

    /**
     * Compare pricing across different providers and models.
     *
     * @param  array  $comparisons  Array of ['provider' => string, 'model' => string]
     * @param  int  $inputTokens  Number of input tokens for comparison
     * @param  int  $outputTokens  Number of output tokens for comparison
     * @return array Sorted comparison results
     */
    public function comparePricing(array $comparisons, int $inputTokens, int $outputTokens): array
    {
        $results = [];

        foreach ($comparisons as $comparison) {
            $provider = $comparison['provider'];
            $model = $comparison['model'];

            try {
                $costData = $this->calculateCost($provider, $model, $inputTokens, $outputTokens);

                $results[] = [
                    'provider' => $provider,
                    'model' => $model,
                    'total_cost' => $costData['total_cost'],
                    'input_cost' => $costData['input_cost'],
                    'output_cost' => $costData['output_cost'],
                    'currency' => $costData['currency'],
                    'source' => $costData['source'],
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'provider' => $provider,
                    'model' => $model,
                    'total_cost' => null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Sort by total cost (lowest first)
        usort($results, function ($a, $b) {
            if ($a['total_cost'] === null) {
                return 1;
            }
            if ($b['total_cost'] === null) {
                return -1;
            }

            return $a['total_cost'] <=> $b['total_cost'];
        });

        return $results;
    }
}
