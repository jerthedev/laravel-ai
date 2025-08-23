<?php

namespace JTD\LaravelAI\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service for fetching pricing information directly from provider APIs.
 *
 * This service attempts to fetch current pricing data directly from AI provider
 * APIs when available, with proper authentication, rate limiting, and error handling.
 */
class ProviderApiPricingFetcher
{
    protected array $rateLimits = [];

    protected int $defaultTimeout = 30;

    /**
     * Fetch pricing for a specific provider and model.
     *
     * @param  string  $provider  The AI provider name
     * @param  string  $model  The model identifier
     * @return array Pricing data or error information
     */
    public function fetchPricing(string $provider, string $model): array
    {
        if (! $this->checkRateLimit($provider)) {
            return [
                'status' => 'rate_limited',
                'message' => 'Rate limit exceeded for provider',
                'provider' => $provider,
                'retry_after' => $this->getRateLimitRetryAfter($provider),
            ];
        }

        try {
            $result = match (strtolower($provider)) {
                'openai' => $this->fetchOpenAIPricing($model),
                'gemini' => $this->fetchGeminiPricing($model),
                'xai' => $this->fetchXAIPricing($model),
                default => [
                    'status' => 'unsupported',
                    'message' => "Provider '{$provider}' not supported for API pricing fetch",
                ],
            };

            if ($result['status'] === 'success') {
                $this->updateRateLimit($provider);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Provider API pricing fetch failed', [
                'provider' => $provider,
                'model' => $model,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => 'API request failed',
                'error' => $e->getMessage(),
                'provider' => $provider,
                'model' => $model,
            ];
        }
    }

    /**
     * Fetch pricing from OpenAI API.
     */
    private function fetchOpenAIPricing(string $model): array
    {
        $apiKey = config('ai.providers.openai.api_key', env('OPENAI_API_KEY'));

        if (empty($apiKey)) {
            return [
                'status' => 'error',
                'message' => 'OpenAI API key not configured',
            ];
        }

        // OpenAI doesn't have a direct pricing API, so we'll try to get model info
        // and infer pricing from known patterns
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])
            ->timeout($this->defaultTimeout)
            ->get('https://api.openai.com/v1/models/' . $model);

        if (! $response->successful()) {
            return [
                'status' => 'error',
                'message' => 'OpenAI API request failed: ' . $response->status(),
                'api_response' => $response->body(),
            ];
        }

        $modelData = $response->json();

        // Since OpenAI doesn't provide pricing in the API, we'll return model info
        // and let the caller use static pricing or AI discovery
        return [
            'status' => 'no_pricing_api',
            'message' => 'OpenAI API does not provide pricing information',
            'model_info' => $modelData,
            'suggestion' => 'Use static pricing or AI discovery',
        ];
    }

    /**
     * Fetch pricing from Gemini API.
     */
    private function fetchGeminiPricing(string $model): array
    {
        $apiKey = config('ai.providers.gemini.api_key', env('GEMINI_API_KEY'));

        if (empty($apiKey)) {
            return [
                'status' => 'error',
                'message' => 'Gemini API key not configured',
            ];
        }

        // Gemini also doesn't have a direct pricing API
        // We'll try to get model info
        $response = Http::timeout($this->defaultTimeout)
            ->get('https://generativelanguage.googleapis.com/v1beta/models/' . $model, [
                'key' => $apiKey,
            ]);

        if (! $response->successful()) {
            return [
                'status' => 'error',
                'message' => 'Gemini API request failed: ' . $response->status(),
                'api_response' => $response->body(),
            ];
        }

        $modelData = $response->json();

        return [
            'status' => 'no_pricing_api',
            'message' => 'Gemini API does not provide pricing information',
            'model_info' => $modelData,
            'suggestion' => 'Use static pricing or AI discovery',
        ];
    }

    /**
     * Fetch pricing from xAI API.
     */
    private function fetchXAIPricing(string $model): array
    {
        $apiKey = config('ai.providers.xai.api_key', env('XAI_API_KEY'));

        if (empty($apiKey)) {
            return [
                'status' => 'error',
                'message' => 'xAI API key not configured',
            ];
        }

        // xAI follows OpenAI-compatible API structure
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])
            ->timeout($this->defaultTimeout)
            ->get('https://api.x.ai/v1/models/' . $model);

        if (! $response->successful()) {
            return [
                'status' => 'error',
                'message' => 'xAI API request failed: ' . $response->status(),
                'api_response' => $response->body(),
            ];
        }

        $modelData = $response->json();

        return [
            'status' => 'no_pricing_api',
            'message' => 'xAI API does not provide pricing information',
            'model_info' => $modelData,
            'suggestion' => 'Use static pricing or AI discovery',
        ];
    }

    /**
     * Fetch all available models for a provider.
     *
     * @param  string  $provider  The AI provider name
     * @return array List of available models
     */
    public function fetchAvailableModels(string $provider): array
    {
        if (! $this->checkRateLimit($provider)) {
            return [
                'status' => 'rate_limited',
                'message' => 'Rate limit exceeded for provider',
                'provider' => $provider,
            ];
        }

        try {
            $result = match (strtolower($provider)) {
                'openai' => $this->fetchOpenAIModels(),
                'gemini' => $this->fetchGeminiModels(),
                'xai' => $this->fetchXAIModels(),
                default => [
                    'status' => 'unsupported',
                    'message' => "Provider '{$provider}' not supported",
                ],
            };

            if ($result['status'] === 'success') {
                $this->updateRateLimit($provider);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Provider API models fetch failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => 'API request failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch available models from OpenAI.
     */
    private function fetchOpenAIModels(): array
    {
        $apiKey = config('ai.providers.openai.api_key', env('OPENAI_API_KEY'));

        if (empty($apiKey)) {
            return [
                'status' => 'error',
                'message' => 'OpenAI API key not configured',
            ];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
        ])
            ->timeout($this->defaultTimeout)
            ->get('https://api.openai.com/v1/models');

        if (! $response->successful()) {
            return [
                'status' => 'error',
                'message' => 'OpenAI API request failed: ' . $response->status(),
            ];
        }

        $data = $response->json();
        $models = collect($data['data'] ?? [])
            ->pluck('id')
            ->sort()
            ->values()
            ->toArray();

        return [
            'status' => 'success',
            'models' => $models,
            'total_models' => count($models),
        ];
    }

    /**
     * Fetch available models from Gemini.
     */
    private function fetchGeminiModels(): array
    {
        $apiKey = config('ai.providers.gemini.api_key', env('GEMINI_API_KEY'));

        if (empty($apiKey)) {
            return [
                'status' => 'error',
                'message' => 'Gemini API key not configured',
            ];
        }

        $response = Http::timeout($this->defaultTimeout)
            ->get('https://generativelanguage.googleapis.com/v1beta/models', [
                'key' => $apiKey,
            ]);

        if (! $response->successful()) {
            return [
                'status' => 'error',
                'message' => 'Gemini API request failed: ' . $response->status(),
            ];
        }

        $data = $response->json();
        $models = collect($data['models'] ?? [])
            ->pluck('name')
            ->map(fn ($name) => str_replace('models/', '', $name))
            ->sort()
            ->values()
            ->toArray();

        return [
            'status' => 'success',
            'models' => $models,
            'total_models' => count($models),
        ];
    }

    /**
     * Fetch available models from xAI.
     */
    private function fetchXAIModels(): array
    {
        $apiKey = config('ai.providers.xai.api_key', env('XAI_API_KEY'));

        if (empty($apiKey)) {
            return [
                'status' => 'error',
                'message' => 'xAI API key not configured',
            ];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
        ])
            ->timeout($this->defaultTimeout)
            ->get('https://api.x.ai/v1/models');

        if (! $response->successful()) {
            return [
                'status' => 'error',
                'message' => 'xAI API request failed: ' . $response->status(),
            ];
        }

        $data = $response->json();
        $models = collect($data['data'] ?? [])
            ->pluck('id')
            ->sort()
            ->values()
            ->toArray();

        return [
            'status' => 'success',
            'models' => $models,
            'total_models' => count($models),
        ];
    }

    /**
     * Check rate limiting for a provider.
     */
    private function checkRateLimit(string $provider): bool
    {
        $cacheKey = "api_pricing_rate_limit:{$provider}:" . now()->format('Y-m-d-H-i');
        $currentCount = Cache::get($cacheKey, 0);
        $limit = $this->getProviderRateLimit($provider);

        return $currentCount < $limit;
    }

    /**
     * Update rate limiting counter.
     */
    private function updateRateLimit(string $provider): void
    {
        $cacheKey = "api_pricing_rate_limit:{$provider}:" . now()->format('Y-m-d-H-i');
        $currentCount = Cache::get($cacheKey, 0);

        Cache::put($cacheKey, $currentCount + 1, 60); // Cache for 1 minute
    }

    /**
     * Get rate limit for a provider.
     */
    private function getProviderRateLimit(string $provider): int
    {
        return match (strtolower($provider)) {
            'openai' => 60, // 60 requests per minute
            'gemini' => 60,
            'xai' => 60,
            default => 30,
        };
    }

    /**
     * Get rate limit retry after time.
     */
    private function getRateLimitRetryAfter(string $provider): int
    {
        return 60 - now()->second; // Seconds until next minute
    }
}
