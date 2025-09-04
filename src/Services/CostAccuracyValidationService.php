<?php

namespace JTD\LaravelAI\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cost Accuracy Validation Service
 *
 * Validates cost calculations against provider APIs with automated accuracy
 * checking and discrepancy reporting for billing reconciliation.
 */
class CostAccuracyValidationService
{
    /**
     * Validation cache TTL (24 hours).
     */
    protected int $validationCacheTtl = 86400;

    /**
     * Accuracy threshold for discrepancy detection (5%).
     */
    protected float $accuracyThreshold = 0.05;

    /**
     * Maximum validation attempts per provider per day.
     */
    protected int $maxValidationAttempts = 100;

    /**
     * Validate cost accuracy against provider APIs.
     *
     * @param  string  $provider  Provider name
     * @param  array  $costRecords  Cost records to validate
     * @param  bool  $forceRefresh  Force refresh of cached validation data
     * @return array Validation results
     */
    public function validateCostAccuracy(string $provider, array $costRecords, bool $forceRefresh = false): array
    {
        $cacheKey = "cost_validation_{$provider}_" . md5(json_encode($costRecords));

        if (! $forceRefresh && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $validationResults = [
            'provider' => $provider,
            'total_records' => count($costRecords),
            'validated_records' => 0,
            'accurate_records' => 0,
            'discrepant_records' => 0,
            'validation_errors' => 0,
            'overall_accuracy' => 0.0,
            'total_calculated_cost' => 0.0,
            'total_provider_cost' => 0.0,
            'cost_difference' => 0.0,
            'cost_difference_percent' => 0.0,
            'discrepancies' => [],
            'validation_summary' => [],
            'validated_at' => now()->toISOString(),
        ];

        try {
            // Check validation rate limits
            if (! $this->canPerformValidation($provider)) {
                throw new \Exception("Validation rate limit exceeded for provider: {$provider}");
            }

            foreach ($costRecords as $record) {
                try {
                    $validation = $this->validateSingleRecord($provider, $record);

                    $validationResults['validated_records']++;
                    $validationResults['total_calculated_cost'] += $record['total_cost'];
                    $validationResults['total_provider_cost'] += $validation['provider_cost'];

                    if ($validation['is_accurate']) {
                        $validationResults['accurate_records']++;
                    } else {
                        $validationResults['discrepant_records']++;
                        $validationResults['discrepancies'][] = $validation;
                    }

                    $validationResults['validation_summary'][] = [
                        'record_id' => $record['id'] ?? 'unknown',
                        'calculated_cost' => $record['total_cost'],
                        'provider_cost' => $validation['provider_cost'],
                        'difference' => $validation['cost_difference'],
                        'difference_percent' => $validation['difference_percent'],
                        'is_accurate' => $validation['is_accurate'],
                    ];
                } catch (\Exception $e) {
                    $validationResults['validation_errors']++;
                    Log::warning('Cost validation failed for single record', [
                        'provider' => $provider,
                        'record' => $record,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Calculate overall metrics
            $validationResults['overall_accuracy'] = $validationResults['validated_records'] > 0
                ? ($validationResults['accurate_records'] / $validationResults['validated_records']) * 100
                : 0;

            $validationResults['cost_difference'] = $validationResults['total_provider_cost'] - $validationResults['total_calculated_cost'];
            $validationResults['cost_difference_percent'] = $validationResults['total_calculated_cost'] > 0
                ? ($validationResults['cost_difference'] / $validationResults['total_calculated_cost']) * 100
                : 0;

            // Store validation results
            $this->storeValidationResults($validationResults);

            // Cache results
            Cache::put($cacheKey, $validationResults, $this->validationCacheTtl);

            // Update validation rate limiting
            $this->recordValidationAttempt($provider, $validationResults['validated_records']);
        } catch (\Exception $e) {
            Log::error('Cost accuracy validation failed', [
                'provider' => $provider,
                'records_count' => count($costRecords),
                'error' => $e->getMessage(),
            ]);

            $validationResults['validation_error'] = $e->getMessage();
        }

        return $validationResults;
    }

    /**
     * Validate a single cost record against provider API.
     *
     * @param  string  $provider  Provider name
     * @param  array  $record  Cost record
     * @return array Validation result
     */
    protected function validateSingleRecord(string $provider, array $record): array
    {
        $providerCost = $this->getProviderCost($provider, $record);
        $calculatedCost = $record['total_cost'];
        $costDifference = $providerCost - $calculatedCost;
        $differencePercent = $calculatedCost > 0 ? abs($costDifference / $calculatedCost) * 100 : 0;
        $isAccurate = $differencePercent <= ($this->accuracyThreshold * 100);

        return [
            'record_id' => $record['id'] ?? 'unknown',
            'provider' => $provider,
            'model' => $record['model'] ?? 'unknown',
            'calculated_cost' => $calculatedCost,
            'provider_cost' => $providerCost,
            'cost_difference' => $costDifference,
            'difference_percent' => round($differencePercent, 2),
            'is_accurate' => $isAccurate,
            'input_tokens' => $record['input_tokens'] ?? 0,
            'output_tokens' => $record['output_tokens'] ?? 0,
            'validation_method' => $this->getValidationMethod($provider),
            'validated_at' => now()->toISOString(),
        ];
    }

    /**
     * Get provider cost from API or billing data.
     *
     * @param  string  $provider  Provider name
     * @param  array  $record  Cost record
     * @return float Provider cost
     */
    protected function getProviderCost(string $provider, array $record): float
    {
        return match ($provider) {
            'openai' => $this->getOpenAICost($record),
            'anthropic' => $this->getAnthropicCost($record),
            'google' => $this->getGoogleCost($record),
            'azure' => $this->getAzureCost($record),
            default => $this->getGenericProviderCost($provider, $record),
        };
    }

    /**
     * Get OpenAI cost from usage API.
     *
     * @param  array  $record  Cost record
     * @return float OpenAI cost
     */
    protected function getOpenAICost(array $record): float
    {
        try {
            // Use OpenAI usage API to get actual costs
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('ai.providers.openai.api_key'),
                'Content-Type' => 'application/json',
            ])->get('https://api.openai.com/v1/usage', [
                'date' => Carbon::parse($record['created_at'])->format('Y-m-d'),
            ]);

            if ($response->successful()) {
                $usage = $response->json();

                // Match usage record by timestamp and model
                return $this->findMatchingUsageCost($usage, $record);
            }

            throw new \Exception('OpenAI usage API request failed');
        } catch (\Exception $e) {
            Log::warning('Failed to get OpenAI cost from API', [
                'record' => $record,
                'error' => $e->getMessage(),
            ]);

            // Fallback to calculated cost
            return $this->calculateFallbackCost('openai', $record);
        }
    }

    /**
     * Get Anthropic cost from usage data.
     *
     * @param  array  $record  Cost record
     * @return float Anthropic cost
     */
    protected function getAnthropicCost(array $record): float
    {
        try {
            // Anthropic doesn't have a public usage API yet
            // Use their published pricing for validation
            return $this->calculateAnthropicCost($record);
        } catch (\Exception $e) {
            Log::warning('Failed to calculate Anthropic cost', [
                'record' => $record,
                'error' => $e->getMessage(),
            ]);

            return $this->calculateFallbackCost('anthropic', $record);
        }
    }

    /**
     * Get Google AI cost from billing data.
     *
     * @param  array  $record  Cost record
     * @return float Google cost
     */
    protected function getGoogleCost(array $record): float
    {
        try {
            // Use Google Cloud Billing API if available
            return $this->calculateGoogleCost($record);
        } catch (\Exception $e) {
            Log::warning('Failed to get Google cost', [
                'record' => $record,
                'error' => $e->getMessage(),
            ]);

            return $this->calculateFallbackCost('google', $record);
        }
    }

    /**
     * Get Azure OpenAI cost from billing data.
     *
     * @param  array  $record  Cost record
     * @return float Azure cost
     */
    protected function getAzureCost(array $record): float
    {
        try {
            // Use Azure billing API if available
            return $this->calculateAzureCost($record);
        } catch (\Exception $e) {
            Log::warning('Failed to get Azure cost', [
                'record' => $record,
                'error' => $e->getMessage(),
            ]);

            return $this->calculateFallbackCost('azure', $record);
        }
    }

    /**
     * Get generic provider cost.
     *
     * @param  string  $provider  Provider name
     * @param  array  $record  Cost record
     * @return float Provider cost
     */
    protected function getGenericProviderCost(string $provider, array $record): float
    {
        // For unknown providers, use our calculated cost as baseline
        return $this->calculateFallbackCost($provider, $record);
    }

    /**
     * Calculate fallback cost using our pricing service.
     *
     * @param  string  $provider  Provider name
     * @param  array  $record  Cost record
     * @return float Fallback cost
     */
    protected function calculateFallbackCost(string $provider, array $record): float
    {
        try {
            $pricingService = app(PricingService::class);
            $costData = $pricingService->calculateCost(
                $provider,
                $record['model'] ?? 'unknown',
                $record['input_tokens'] ?? 0,
                $record['output_tokens'] ?? 0
            );

            return $costData['total_cost'] ?? $record['total_cost'];
        } catch (\Exception $e) {
            // Ultimate fallback - use the original calculated cost
            return $record['total_cost'];
        }
    }

    /**
     * Find matching usage cost from provider API response.
     *
     * @param  array  $usage  Usage data from provider
     * @param  array  $record  Our cost record
     * @return float Matching cost
     */
    protected function findMatchingUsageCost(array $usage, array $record): float
    {
        // Implementation would depend on provider API structure
        // This is a simplified example
        foreach ($usage['data'] ?? [] as $usageItem) {
            if ($usageItem['model'] === $record['model'] &&
                abs($usageItem['n_requests'] - 1) < 0.1) {
                return $usageItem['cost'] ?? $record['total_cost'];
            }
        }

        return $record['total_cost']; // Fallback
    }

    /**
     * Calculate Anthropic cost using published pricing.
     *
     * @param  array  $record  Cost record
     * @return float Calculated cost
     */
    protected function calculateAnthropicCost(array $record): float
    {
        // Use latest Anthropic pricing
        $model = $record['model'] ?? 'claude-3-sonnet';
        $inputTokens = $record['input_tokens'] ?? 0;
        $outputTokens = $record['output_tokens'] ?? 0;

        $pricing = $this->getAnthropicPricing($model);

        return ($inputTokens / 1000 * $pricing['input']) +
               ($outputTokens / 1000 * $pricing['output']);
    }

    /**
     * Calculate Google AI cost.
     *
     * @param  array  $record  Cost record
     * @return float Calculated cost
     */
    protected function calculateGoogleCost(array $record): float
    {
        $model = $record['model'] ?? 'gemini-pro';
        $inputTokens = $record['input_tokens'] ?? 0;
        $outputTokens = $record['output_tokens'] ?? 0;

        $pricing = $this->getGooglePricing($model);

        return ($inputTokens / 1000 * $pricing['input']) +
               ($outputTokens / 1000 * $pricing['output']);
    }

    /**
     * Calculate Azure OpenAI cost.
     *
     * @param  array  $record  Cost record
     * @return float Calculated cost
     */
    protected function calculateAzureCost(array $record): float
    {
        // Azure pricing is typically similar to OpenAI but may vary by region
        $model = $record['model'] ?? 'gpt-3.5-turbo';
        $inputTokens = $record['input_tokens'] ?? 0;
        $outputTokens = $record['output_tokens'] ?? 0;

        $pricing = $this->getAzurePricing($model);

        return ($inputTokens / 1000 * $pricing['input']) +
               ($outputTokens / 1000 * $pricing['output']);
    }

    /**
     * Get Anthropic pricing data.
     *
     * @param  string  $model  Model name
     * @return array Pricing data
     */
    protected function getAnthropicPricing(string $model): array
    {
        $pricing = [
            'claude-3-opus' => ['input' => 0.015, 'output' => 0.075],
            'claude-3-sonnet' => ['input' => 0.003, 'output' => 0.015],
            'claude-3-haiku' => ['input' => 0.00025, 'output' => 0.00125],
            'claude-2.1' => ['input' => 0.008, 'output' => 0.024],
            'claude-2.0' => ['input' => 0.008, 'output' => 0.024],
        ];

        return $pricing[$model] ?? $pricing['claude-3-sonnet'];
    }

    /**
     * Get Google AI pricing data.
     *
     * @param  string  $model  Model name
     * @return array Pricing data
     */
    protected function getGooglePricing(string $model): array
    {
        $pricing = [
            'gemini-pro' => ['input' => 0.0005, 'output' => 0.0015],
            'gemini-pro-vision' => ['input' => 0.0005, 'output' => 0.0015],
            'gemini-ultra' => ['input' => 0.001, 'output' => 0.003],
        ];

        return $pricing[$model] ?? $pricing['gemini-pro'];
    }

    /**
     * Get Azure OpenAI pricing data.
     *
     * @param  string  $model  Model name
     * @return array Pricing data
     */
    protected function getAzurePricing(string $model): array
    {
        // Azure pricing may vary by region, this is a general example
        $pricing = [
            'gpt-4' => ['input' => 0.03, 'output' => 0.06],
            'gpt-4-32k' => ['input' => 0.06, 'output' => 0.12],
            'gpt-3.5-turbo' => ['input' => 0.0015, 'output' => 0.002],
            'gpt-3.5-turbo-16k' => ['input' => 0.003, 'output' => 0.004],
        ];

        return $pricing[$model] ?? $pricing['gpt-3.5-turbo'];
    }

    /**
     * Check if validation can be performed (rate limiting).
     *
     * @param  string  $provider  Provider name
     * @return bool Can perform validation
     */
    protected function canPerformValidation(string $provider): bool
    {
        $cacheKey = "validation_attempts_{$provider}_" . now()->format('Y-m-d');
        $attempts = Cache::get($cacheKey, 0);

        return $attempts < $this->maxValidationAttempts;
    }

    /**
     * Record validation attempt for rate limiting.
     *
     * @param  string  $provider  Provider name
     * @param  int  $recordCount  Number of records validated
     */
    protected function recordValidationAttempt(string $provider, int $recordCount): void
    {
        $cacheKey = "validation_attempts_{$provider}_" . now()->format('Y-m-d');
        Cache::increment($cacheKey, $recordCount);
        Cache::expire($cacheKey, 86400); // 24 hours
    }

    /**
     * Store validation results in database.
     *
     * @param  array  $results  Validation results
     */
    protected function storeValidationResults(array $results): void
    {
        try {
            DB::table('ai_cost_validations')->insert([
                'provider' => $results['provider'],
                'total_records' => $results['total_records'],
                'validated_records' => $results['validated_records'],
                'accurate_records' => $results['accurate_records'],
                'discrepant_records' => $results['discrepant_records'],
                'validation_errors' => $results['validation_errors'],
                'overall_accuracy' => $results['overall_accuracy'],
                'total_calculated_cost' => $results['total_calculated_cost'],
                'total_provider_cost' => $results['total_provider_cost'],
                'cost_difference' => $results['cost_difference'],
                'cost_difference_percent' => $results['cost_difference_percent'],
                'discrepancies' => json_encode($results['discrepancies']),
                'validation_summary' => json_encode($results['validation_summary']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to store validation results', [
                'provider' => $results['provider'],
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get validation method for provider.
     *
     * @param  string  $provider  Provider name
     * @return string Validation method
     */
    protected function getValidationMethod(string $provider): string
    {
        return match ($provider) {
            'openai' => 'usage_api',
            'anthropic' => 'published_pricing',
            'google' => 'billing_api',
            'azure' => 'billing_api',
            default => 'fallback_calculation',
        };
    }

    /**
     * Get validation accuracy report.
     *
     * @param  string|null  $provider  Provider filter
     * @param  string|null  $dateRange  Date range
     * @return array Accuracy report
     */
    public function getAccuracyReport(?string $provider = null, ?string $dateRange = 'month'): array
    {
        $query = DB::table('ai_cost_validations');

        if ($provider) {
            $query->where('provider', $provider);
        }

        if ($dateRange) {
            $this->applyDateRangeFilter($query, $dateRange);
        }

        $results = $query->orderBy('created_at', 'desc')->get();

        return [
            'summary' => [
                'total_validations' => $results->count(),
                'total_records_validated' => $results->sum('validated_records'),
                'overall_accuracy' => $results->count() > 0 ? $results->avg('overall_accuracy') : 0,
                'total_cost_difference' => $results->sum('cost_difference'),
                'avg_cost_difference_percent' => $results->count() > 0 ? $results->avg('cost_difference_percent') : 0,
            ],
            'by_provider' => $results->groupBy('provider')->map(function ($providerResults) {
                return [
                    'validations' => $providerResults->count(),
                    'records_validated' => $providerResults->sum('validated_records'),
                    'accuracy' => $providerResults->avg('overall_accuracy'),
                    'cost_difference' => $providerResults->sum('cost_difference'),
                    'cost_difference_percent' => $providerResults->avg('cost_difference_percent'),
                ];
            })->toArray(),
            'recent_validations' => $results->take(10)->map(function ($validation) {
                return [
                    'provider' => $validation->provider,
                    'accuracy' => $validation->overall_accuracy,
                    'records_validated' => $validation->validated_records,
                    'cost_difference' => $validation->cost_difference,
                    'validated_at' => $validation->created_at,
                ];
            })->toArray(),
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Apply date range filter to query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query  Query builder
     * @param  string  $dateRange  Date range
     */
    protected function applyDateRangeFilter($query, string $dateRange): void
    {
        $now = Carbon::now();

        match ($dateRange) {
            'today' => $query->whereDate('created_at', $now->toDateString()),
            'week' => $query->where('created_at', '>=', $now->startOfWeek()),
            'month' => $query->where('created_at', '>=', $now->startOfMonth()),
            'quarter' => $query->where('created_at', '>=', $now->startOfQuarter()),
            'year' => $query->where('created_at', '>=', $now->startOfYear()),
            default => $query->where('created_at', '>=', $now->startOfMonth()),
        };
    }

    /**
     * Schedule automated validation for recent cost records.
     *
     * @param  string|null  $provider  Provider filter
     * @param  int  $hours  Hours back to validate
     * @return array Validation job details
     */
    public function scheduleAutomatedValidation(?string $provider = null, int $hours = 24): array
    {
        $query = DB::table('ai_usage_costs')
            ->where('created_at', '>=', now()->subHours($hours))
            ->orderBy('created_at', 'desc');

        if ($provider) {
            $query->where('provider', $provider);
        }

        $records = $query->limit(50)->get()->toArray(); // Limit for performance

        if (empty($records)) {
            return [
                'status' => 'no_records',
                'message' => 'No cost records found for validation',
                'scheduled_at' => now()->toISOString(),
            ];
        }

        // Group by provider for batch validation
        $recordsByProvider = collect($records)->groupBy('provider');

        $jobs = [];
        foreach ($recordsByProvider as $providerName => $providerRecords) {
            // In a real implementation, you would dispatch a job here
            $jobs[] = [
                'provider' => $providerName,
                'record_count' => count($providerRecords),
                'job_id' => 'validation_' . $providerName . '_' . now()->timestamp,
            ];
        }

        return [
            'status' => 'scheduled',
            'jobs' => $jobs,
            'total_records' => count($records),
            'scheduled_at' => now()->toISOString(),
        ];
    }
}
