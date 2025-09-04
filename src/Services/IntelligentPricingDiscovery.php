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
     * Perform the actual pricing discovery using AI with MCP tools or manual search.
     */
    private function performDiscovery(string $provider, string $model, array $options): array
    {
        // Check if we should use AI-powered discovery or manual search
        if ($this->shouldUseAIDiscovery()) {
            return $this->performAIDiscovery($provider, $model, $options);
        } else {
            return $this->performManualDiscovery($provider, $model, $options);
        }
    }

    /**
     * Check if AI-powered discovery should be used.
     */
    private function shouldUseAIDiscovery(): bool
    {
        return config('ai.model_sync.ai_discovery.use_ai_discovery', false) &&
               app()->bound('ai');
    }

    /**
     * Perform manual discovery using direct search calls (legacy approach).
     */
    private function performManualDiscovery(string $provider, string $model, array $options): array
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
     * Perform AI-powered pricing discovery using MCP tools.
     */
    private function performAIDiscovery(string $provider, string $model, array $options): array
    {
        // Create the pricing discovery prompt
        $prompt = $this->buildDiscoveryPrompt($provider, $model);

        // Check if Brave Search MCP tool is available
        if (! $this->isBraveSearchAvailable()) {
            return [
                'status' => 'no_mcp_tools',
                'message' => 'Brave Search MCP tool not available for AI discovery',
            ];
        }

        try {
            // Use AI with MCP tools - reference tools by name, not schemas
            // The MCP system handles tool discovery and execution automatically
            $response = app('ai')->conversation()
                ->provider($this->getDiscoveryProvider())
                ->model($this->getDiscoveryModel())
                ->tools(['web_search']) // Reference registered MCP tool by name
                ->message($prompt)
                ->send();

            // Parse the AI response and extract pricing information
            return $this->parseAIDiscoveryResponse($response, $provider, $model);
        } catch (\Exception $e) {
            Log::error('AI pricing discovery failed', [
                'provider' => $provider,
                'model' => $model,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => 'AI discovery failed: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Build the AI discovery prompt for pricing information.
     */
    private function buildDiscoveryPrompt(string $provider, string $model): string
    {
        return "I need to find the current pricing information for the {$provider} {$model} AI model.

Please search for the most recent and accurate pricing information including:
- Input token cost (per 1K or 1M tokens)
- Output token cost (per 1K or 1M tokens)
- Any special pricing tiers or volume discounts
- The pricing unit (per 1K tokens, per 1M tokens, etc.)
- The currency (usually USD)
- The effective date of the pricing

Focus on official sources like the provider's website, documentation, or recent announcements.
Please search multiple times with different queries to ensure accuracy and cross-reference the information.

After gathering the information, provide a structured response with:
1. The pricing data you found
2. Your confidence level in the accuracy (0.0 to 1.0)
3. The sources you used
4. Any notes about pricing variations or special conditions

Provider: {$provider}
Model: {$model}
Current date: " . now()->format('Y-m-d');
    }

    /**
     * Check if Brave Search MCP is available.
     */
    private function isBraveSearchAvailable(): bool
    {
        return $this->braveSearchService !== null;
    }

    /**
     * Get the AI provider to use for discovery.
     */
    private function getDiscoveryProvider(): string
    {
        return config('ai.model_sync.ai_discovery.provider', 'openai');
    }

    /**
     * Get the AI model to use for discovery.
     */
    private function getDiscoveryModel(): string
    {
        return config('ai.model_sync.ai_discovery.model', 'gpt-4o-mini');
    }

    /**
     * Perform Brave Search using direct service call (manual discovery).
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
     * Parse AI discovery response and extract pricing information.
     */
    private function parseAIDiscoveryResponse($response, string $provider, string $model): array
    {
        // Extract pricing information from the AI's response
        $content = $response->content ?? '';

        // Use the existing pricing extraction service to parse the AI's findings
        $mockResults = [
            [
                'status' => 'success',
                'results' => [
                    [
                        'title' => 'AI Discovery Result',
                        'snippet' => $content,
                        'url' => 'ai://discovery',
                    ],
                ],
            ],
        ];

        $extractedPricing = $this->extractPricingFromResults($mockResults, $provider, $model);

        if (empty($extractedPricing)) {
            return [
                'status' => 'no_pricing_extracted',
                'message' => 'AI found information but could not extract structured pricing',
                'ai_response' => $content,
            ];
        }

        return [
            'status' => 'success',
            'pricing' => $extractedPricing,
            'confidence_score' => 0.8, // Could be extracted from AI response
            'sources' => 1,
            'total_cost' => 0.001, // Estimate based on AI model usage
            'ai_response' => $content,
        ];
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
