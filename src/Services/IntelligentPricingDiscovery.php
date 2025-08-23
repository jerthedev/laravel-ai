<?php

namespace JTD\LaravelAI\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Intelligent pricing discovery service using AI-powered search capabilities.
 *
 * This service uses MCP (Brave Search) integration to discover current pricing
 * information when provider APIs don't include pricing data. It includes cost
 * estimation, confidence scoring, and user confirmation workflows.
 */
class IntelligentPricingDiscovery
{
    public function __construct(
        protected PricingService $pricingService,
        protected PricingValidator $pricingValidator,
        protected BraveSearchMCPService $braveSearchService,
        protected PricingExtractionService $pricingExtractionService
    ) {}

    /**
     * Discover pricing for a model using AI-powered search.
     *
     * @param  string  $provider  The AI provider name
     * @param  string  $model  The model identifier
     * @param  array  $options  Discovery options
     * @return array Discovery result with pricing data and confidence score
     */
    public function discoverPricing(string $provider, string $model, array $options = []): array
    {
        // Check if AI discovery is enabled
        if (! $this->isDiscoveryEnabled()) {
            return [
                'status' => 'disabled',
                'message' => 'AI-powered pricing discovery is disabled',
            ];
        }

        // Check cache first if enabled
        if ($this->isCacheEnabled() && ! ($options['force'] ?? false)) {
            $cached = $this->getCachedDiscovery($provider, $model);
            if ($cached) {
                return $cached;
            }
        }

        // Estimate cost of discovery operation
        $estimatedCost = $this->estimateDiscoveryCost($provider, $model);
        $maxCost = $this->getMaxDiscoveryCost();

        if ($estimatedCost > $maxCost) {
            return [
                'status' => 'cost_exceeded',
                'message' => "Discovery cost ({$estimatedCost}) exceeds maximum ({$maxCost})",
                'estimated_cost' => $estimatedCost,
                'max_cost' => $maxCost,
            ];
        }

        // Require confirmation if enabled
        if ($this->requiresConfirmation() && ! ($options['confirmed'] ?? false)) {
            return [
                'status' => 'confirmation_required',
                'message' => 'User confirmation required for AI discovery',
                'estimated_cost' => $estimatedCost,
                'confirmation_prompt' => $this->getConfirmationPrompt($provider, $model, $estimatedCost),
            ];
        }

        try {
            // Perform the actual discovery
            $discoveryResult = $this->performDiscovery($provider, $model, $options);

            // Cache the result if successful and caching is enabled
            if ($this->isCacheEnabled() && $discoveryResult['status'] === 'success') {
                $this->cacheDiscovery($provider, $model, $discoveryResult);
            }

            return $discoveryResult;
        } catch (\Exception $e) {
            Log::error('AI pricing discovery failed', [
                'provider' => $provider,
                'model' => $model,
                'error' => $e->getMessage(),
            ]);

            if ($this->shouldFallbackOnFailure()) {
                return $this->getFallbackPricing($provider, $model);
            }

            return [
                'status' => 'error',
                'message' => 'Discovery failed: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Perform the actual pricing discovery using AI search.
     */
    private function performDiscovery(string $provider, string $model, array $options): array
    {
        // Build search queries for pricing information
        $searchQueries = $this->buildSearchQueries($provider, $model);

        $allResults = [];
        $totalCost = 0.0;

        foreach ($searchQueries as $query) {
            // Use Brave Search MCP to find pricing information
            $searchResult = $this->performBraveSearch($query);

            if ($searchResult['status'] === 'success') {
                $allResults[] = $searchResult;
                $totalCost += $searchResult['cost'] ?? 0.0;
            }
        }

        if (empty($allResults)) {
            return [
                'status' => 'no_results',
                'message' => 'No pricing information found',
                'queries_tried' => count($searchQueries),
                'total_cost' => $totalCost,
            ];
        }

        // Parse and extract pricing from search results
        $extractedPricing = $this->extractPricingFromResults($allResults, $provider, $model);

        if (empty($extractedPricing)) {
            return [
                'status' => 'no_pricing_extracted',
                'message' => 'Found results but could not extract pricing',
                'results_found' => count($allResults),
                'total_cost' => $totalCost,
            ];
        }

        // Calculate confidence score
        $confidenceScore = $this->calculateConfidenceScore($extractedPricing, $allResults);
        $confidenceThreshold = $this->getConfidenceThreshold();

        if ($confidenceScore < $confidenceThreshold) {
            return [
                'status' => 'low_confidence',
                'message' => "Confidence score ({$confidenceScore}) below threshold ({$confidenceThreshold})",
                'confidence_score' => $confidenceScore,
                'confidence_threshold' => $confidenceThreshold,
                'extracted_pricing' => $extractedPricing,
                'total_cost' => $totalCost,
            ];
        }

        // Validate the extracted pricing
        $validationErrors = $this->pricingValidator->validateModelPricing($model, $extractedPricing);

        return [
            'status' => 'success',
            'pricing' => $extractedPricing,
            'confidence_score' => $confidenceScore,
            'validation_errors' => $validationErrors,
            'sources' => count($allResults),
            'total_cost' => $totalCost,
            'discovered_at' => now()->toISOString(),
        ];
    }

    /**
     * Build search queries for finding pricing information.
     */
    private function buildSearchQueries(string $provider, string $model): array
    {
        $providerName = ucfirst($provider);

        return [
            "{$providerName} {$model} pricing cost per token API",
            "{$providerName} {$model} price rate limit API documentation",
            "{$model} pricing {$providerName} API cost 2024 2025",
            "how much does {$model} cost {$providerName} API pricing",
            "{$providerName} API pricing {$model} input output tokens",
        ];
    }

    /**
     * Perform Brave Search using MCP integration.
     */
    private function performBraveSearch(string $query): array
    {
        try {
            $result = $this->braveSearchService->search($query, [
                'count' => 5,
                'freshness' => 'py', // Past year for current pricing
            ]);

            if ($result['status'] === 'success') {
                return [
                    'status' => 'success',
                    'query' => $query,
                    'results' => $result['results'],
                    'cost' => $result['metadata']['api_cost'] ?? 0.001,
                    'total_results' => $result['metadata']['total_results'] ?? 0,
                ];
            }

            return [
                'status' => 'error',
                'query' => $query,
                'error' => $result['message'] ?? 'Search failed',
                'cost' => 0.0,
            ];
        } catch (\Exception $e) {
            Log::error('Brave Search failed in pricing discovery', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'query' => $query,
                'error' => $e->getMessage(),
                'cost' => 0.0,
            ];
        }
    }

    /**
     * Extract pricing information from search results.
     */
    private function extractPricingFromResults(array $results, string $provider, string $model): array
    {
        // Use the PricingExtractionService to extract pricing from search results
        $extractedPricing = $this->pricingExtractionService->extractPricing($results, $provider, $model);

        if (empty($extractedPricing)) {
            return [];
        }

        // Add discovery-specific metadata
        $extractedPricing['source'] = 'ai_discovery';
        $extractedPricing['effective_date'] = now()->toDateString();

        return $extractedPricing;
    }

    /**
     * Calculate confidence score for extracted pricing.
     */
    private function calculateConfidenceScore(array $pricing, array $results): float
    {
        // Implement confidence scoring based on:
        // - Number of sources confirming the pricing
        // - Consistency across sources
        // - Recency of information
        // - Authority of sources

        $baseScore = 0.5;
        $sourceCount = count($results);

        // More sources = higher confidence
        $sourceBonus = min(0.3, $sourceCount * 0.1);

        return min(1.0, $baseScore + $sourceBonus);
    }

    /**
     * Check if AI discovery is enabled.
     */
    private function isDiscoveryEnabled(): bool
    {
        return config('ai.model_sync.ai_discovery.enabled', false);
    }

    /**
     * Check if caching is enabled for discoveries.
     */
    private function isCacheEnabled(): bool
    {
        return config('ai.model_sync.ai_discovery.cache_discoveries', true);
    }

    /**
     * Check if user confirmation is required.
     */
    private function requiresConfirmation(): bool
    {
        return config('ai.model_sync.ai_discovery.require_confirmation', true);
    }

    /**
     * Check if fallback should be used on failure.
     */
    private function shouldFallbackOnFailure(): bool
    {
        return config('ai.model_sync.ai_discovery.fallback_on_failure', true);
    }

    /**
     * Get maximum cost per discovery operation.
     */
    private function getMaxDiscoveryCost(): float
    {
        return config('ai.model_sync.ai_discovery.max_cost_per_discovery', 0.01);
    }

    /**
     * Get confidence threshold for accepting discovered pricing.
     */
    private function getConfidenceThreshold(): float
    {
        return config('ai.model_sync.ai_discovery.confidence_threshold', 0.8);
    }

    /**
     * Estimate the cost of a discovery operation.
     */
    private function estimateDiscoveryCost(string $provider, string $model): float
    {
        // Estimate based on number of search queries and expected API costs
        $searchQueries = $this->buildSearchQueries($provider, $model);
        $costPerSearch = 0.001; // $0.001 per search query

        return count($searchQueries) * $costPerSearch;
    }

    /**
     * Get confirmation prompt for user.
     */
    private function getConfirmationPrompt(string $provider, string $model, float $cost): string
    {
        return "Discover pricing for {$provider} {$model}? Estimated cost: \${$cost}";
    }

    /**
     * Get cached discovery result.
     */
    private function getCachedDiscovery(string $provider, string $model): ?array
    {
        $cacheKey = "ai_discovery:{$provider}:{$model}";
        $cacheDuration = config('ai.model_sync.ai_discovery.cache_duration', 86400);

        return Cache::get($cacheKey);
    }

    /**
     * Cache discovery result.
     */
    private function cacheDiscovery(string $provider, string $model, array $result): void
    {
        $cacheKey = "ai_discovery:{$provider}:{$model}";
        $cacheDuration = config('ai.model_sync.ai_discovery.cache_duration', 86400);

        Cache::put($cacheKey, $result, $cacheDuration);
    }

    /**
     * Get fallback pricing when discovery fails.
     */
    private function getFallbackPricing(string $provider, string $model): array
    {
        $fallbackPricing = $this->pricingService->getModelPricing($provider, $model);

        return [
            'status' => 'fallback',
            'message' => 'Using fallback pricing due to discovery failure',
            'pricing' => $fallbackPricing,
            'source' => $fallbackPricing['source'] ?? 'unknown',
        ];
    }
}
