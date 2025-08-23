<?php

namespace JTD\LaravelAI\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Brave Search MCP (Middleware Communication Protocol) integration service.
 *
 * This service provides integration with Brave Search API through MCP protocol
 * for AI-powered pricing discovery. It includes rate limiting, error handling,
 * and result caching for efficient operation.
 */
class BraveSearchMCPService
{
    protected string $baseUrl;

    protected string $apiKey;

    protected int $timeout;

    protected int $rateLimitPerMinute;

    public function __construct()
    {
        $this->baseUrl = config('ai.mcp.brave_search.base_url', 'https://api.search.brave.com/res/v1');
        $this->apiKey = config('ai.mcp.brave_search.api_key', env('BRAVE_SEARCH_API_KEY'));
        $this->timeout = config('ai.mcp.brave_search.timeout', 30);
        $this->rateLimitPerMinute = config('ai.mcp.brave_search.rate_limit', 60);
    }

    /**
     * Perform a web search using Brave Search API.
     *
     * @param  string  $query  The search query
     * @param  array  $options  Search options
     * @return array Search results with metadata
     */
    public function search(string $query, array $options = []): array
    {
        if (! $this->isConfigured()) {
            return [
                'status' => 'error',
                'message' => 'Brave Search API not configured',
                'error' => 'Missing API key or configuration',
            ];
        }

        // Check rate limiting
        if (! $this->checkRateLimit()) {
            return [
                'status' => 'rate_limited',
                'message' => 'Rate limit exceeded',
                'retry_after' => $this->getRateLimitRetryAfter(),
            ];
        }

        // Check cache first
        $cacheKey = $this->getCacheKey($query, $options);
        if ($cached = Cache::get($cacheKey)) {
            return array_merge($cached, ['cached' => true]);
        }

        try {
            $response = $this->makeSearchRequest($query, $options);

            if ($response['status'] === 'success') {
                // Cache successful results
                $this->cacheResult($cacheKey, $response);

                // Update rate limiting
                $this->updateRateLimit();
            }

            return $response;
        } catch (\Exception $e) {
            Log::error('Brave Search API request failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Search request failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Make the actual search request to Brave Search API.
     */
    private function makeSearchRequest(string $query, array $options): array
    {
        $params = array_merge([
            'q' => $query,
            'count' => $options['count'] ?? 10,
            'offset' => $options['offset'] ?? 0,
            'safesearch' => $options['safesearch'] ?? 'moderate',
            'freshness' => $options['freshness'] ?? 'py', // Past year
            'text_decorations' => $options['text_decorations'] ?? false,
            'spellcheck' => $options['spellcheck'] ?? true,
        ], $options['extra_params'] ?? []);

        $response = Http::withHeaders([
            'X-Subscription-Token' => $this->apiKey,
            'Accept' => 'application/json',
            'User-Agent' => 'Laravel-AI-Pricing-Discovery/1.0',
        ])
            ->timeout($this->timeout)
            ->get($this->baseUrl . '/web/search', $params);

        if (! $response->successful()) {
            throw new \Exception('API request failed: ' . $response->status() . ' - ' . $response->body());
        }

        $data = $response->json();

        return [
            'status' => 'success',
            'query' => $query,
            'results' => $this->processSearchResults($data),
            'metadata' => [
                'total_results' => $data['web']['total'] ?? 0,
                'query_time' => $data['query']['time'] ?? 0,
                'api_cost' => $this->calculateApiCost($params),
                'timestamp' => now()->toISOString(),
            ],
        ];
    }

    /**
     * Process and clean search results.
     */
    private function processSearchResults(array $data): array
    {
        $results = [];

        if (! isset($data['web']['results'])) {
            return $results;
        }

        foreach ($data['web']['results'] as $result) {
            $results[] = [
                'title' => $result['title'] ?? '',
                'url' => $result['url'] ?? '',
                'description' => $result['description'] ?? '',
                'content' => $result['extra_snippets'] ?? [],
                'published' => $result['age'] ?? null,
                'relevance_score' => $this->calculateRelevanceScore($result),
            ];
        }

        return $results;
    }

    /**
     * Calculate relevance score for a search result.
     */
    private function calculateRelevanceScore(array $result): float
    {
        $score = 0.5; // Base score

        // Boost score for official documentation
        if (str_contains(strtolower($result['url'] ?? ''), 'docs.') ||
            str_contains(strtolower($result['url'] ?? ''), 'documentation')) {
            $score += 0.3;
        }

        // Boost score for API-related content
        if (str_contains(strtolower($result['title'] ?? ''), 'api') ||
            str_contains(strtolower($result['description'] ?? ''), 'api')) {
            $score += 0.2;
        }

        // Boost score for pricing-related content
        if (str_contains(strtolower($result['title'] ?? ''), 'pricing') ||
            str_contains(strtolower($result['description'] ?? ''), 'pricing') ||
            str_contains(strtolower($result['description'] ?? ''), 'cost')) {
            $score += 0.2;
        }

        return min(1.0, $score);
    }

    /**
     * Calculate estimated API cost for the request.
     */
    private function calculateApiCost(array $params): float
    {
        // Brave Search API pricing (estimated)
        $baseSearchCost = 0.001; // $0.001 per search
        $resultCount = $params['count'] ?? 10;

        // Additional cost for more results
        $additionalCost = max(0, ($resultCount - 10) * 0.0001);

        return $baseSearchCost + $additionalCost;
    }

    /**
     * Check if the service is properly configured.
     */
    private function isConfigured(): bool
    {
        return ! empty($this->apiKey) && ! empty($this->baseUrl);
    }

    /**
     * Check rate limiting.
     */
    private function checkRateLimit(): bool
    {
        $cacheKey = 'brave_search_rate_limit:' . now()->format('Y-m-d-H-i');
        $currentCount = Cache::get($cacheKey, 0);

        return $currentCount < $this->rateLimitPerMinute;
    }

    /**
     * Update rate limiting counter.
     */
    private function updateRateLimit(): void
    {
        $cacheKey = 'brave_search_rate_limit:' . now()->format('Y-m-d-H-i');
        $currentCount = Cache::get($cacheKey, 0);

        Cache::put($cacheKey, $currentCount + 1, 60); // Cache for 1 minute
    }

    /**
     * Get rate limit retry after time.
     */
    private function getRateLimitRetryAfter(): int
    {
        return 60 - now()->second; // Seconds until next minute
    }

    /**
     * Generate cache key for search results.
     */
    private function getCacheKey(string $query, array $options): string
    {
        $keyData = [
            'query' => $query,
            'count' => $options['count'] ?? 10,
            'offset' => $options['offset'] ?? 0,
        ];

        return 'brave_search:' . md5(json_encode($keyData));
    }

    /**
     * Cache search results.
     */
    private function cacheResult(string $cacheKey, array $result): void
    {
        $cacheDuration = config('ai.mcp.brave_search.cache_duration', 3600); // 1 hour
        Cache::put($cacheKey, $result, $cacheDuration);
    }

    /**
     * Search specifically for pricing information.
     *
     * @param  string  $provider  AI provider name
     * @param  string  $model  Model name
     * @return array Pricing-focused search results
     */
    public function searchPricing(string $provider, string $model): array
    {
        $queries = [
            "{$provider} {$model} pricing API cost per token",
            "{$provider} {$model} API pricing documentation",
            "how much does {$model} cost {$provider} API",
        ];

        $allResults = [];
        $totalCost = 0.0;

        foreach ($queries as $query) {
            $result = $this->search($query, [
                'count' => 5,
                'freshness' => 'py', // Past year for current pricing
            ]);

            if ($result['status'] === 'success') {
                $allResults = array_merge($allResults, $result['results']);
                $totalCost += $result['metadata']['api_cost'] ?? 0.0;
            }
        }

        // Remove duplicates and sort by relevance
        $uniqueResults = $this->deduplicateResults($allResults);
        usort($uniqueResults, fn ($a, $b) => $b['relevance_score'] <=> $a['relevance_score']);

        return [
            'status' => 'success',
            'provider' => $provider,
            'model' => $model,
            'results' => array_slice($uniqueResults, 0, 10), // Top 10 results
            'total_results' => count($uniqueResults),
            'queries_executed' => count($queries),
            'total_cost' => $totalCost,
        ];
    }

    /**
     * Remove duplicate search results based on URL.
     */
    private function deduplicateResults(array $results): array
    {
        $seen = [];
        $unique = [];

        foreach ($results as $result) {
            $url = $result['url'] ?? '';
            if (! isset($seen[$url])) {
                $seen[$url] = true;
                $unique[] = $result;
            }
        }

        return $unique;
    }

    /**
     * Get service health status.
     */
    public function getHealthStatus(): array
    {
        return [
            'configured' => $this->isConfigured(),
            'api_key_present' => ! empty($this->apiKey),
            'base_url' => $this->baseUrl,
            'rate_limit_ok' => $this->checkRateLimit(),
            'last_request' => Cache::get('brave_search_last_request'),
        ];
    }

    /**
     * Clear all cached search results.
     */
    public function clearCache(): void
    {
        // This would need a more sophisticated cache clearing mechanism
        // For now, we'll clear known patterns
        Cache::flush();
    }
}
