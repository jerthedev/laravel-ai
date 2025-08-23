<?php

namespace JTD\LaravelAI\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Events\ProviderFallbackTriggered;
use JTD\LaravelAI\Exceptions\NoFallbackAvailableException;
use JTD\LaravelAI\Models\AIConversation;
use JTD\LaravelAI\Models\AIProvider;

/**
 * Provider Fallback Service
 *
 * Manages automatic fallback to alternative providers when the primary
 * provider fails or becomes unavailable.
 */
class ProviderFallbackService
{
    protected DriverManager $driverManager;

    protected ProviderSwitchingService $switchingService;

    public function __construct(
        DriverManager $driverManager,
        ProviderSwitchingService $switchingService
    ) {
        $this->driverManager = $driverManager;
        $this->switchingService = $switchingService;
    }

    /**
     * Execute fallback strategy for a conversation.
     */
    public function executeFallback(
        AIConversation $conversation,
        \Exception $originalException,
        array $options = []
    ): AIConversation {
        $fallbackStrategy = $options['strategy'] ?? 'auto';
        $maxAttempts = $options['max_attempts'] ?? 3;

        Log::warning('Executing provider fallback', [
            'conversation_id' => $conversation->id,
            'current_provider' => $conversation->provider_name,
            'original_error' => $originalException->getMessage(),
            'strategy' => $fallbackStrategy,
        ]);

        $fallbackProviders = $this->getFallbackProviders($conversation, $fallbackStrategy);

        if (empty($fallbackProviders)) {
            throw new NoFallbackAvailableException(
                'No fallback providers available for conversation ' . $conversation->id
            );
        }

        $attempts = 0;
        $lastException = $originalException;

        foreach ($fallbackProviders as $fallbackConfig) {
            if ($attempts >= $maxAttempts) {
                break;
            }

            try {
                $attempts++;

                // Fire fallback triggered event
                Event::dispatch(new ProviderFallbackTriggered(
                    $conversation,
                    $conversation->provider_name,
                    $fallbackConfig['provider'],
                    $originalException,
                    $attempts
                ));

                // Attempt to switch to fallback provider
                $updatedConversation = $this->switchingService->switchProvider(
                    $conversation,
                    $fallbackConfig['provider'],
                    $fallbackConfig['model'] ?? null,
                    array_merge($options, [
                        'reason' => 'fallback',
                        'original_error' => $originalException->getMessage(),
                        'attempt' => $attempts,
                    ])
                );

                // Test the new provider with a simple health check
                if ($this->validateFallbackProvider($updatedConversation)) {
                    Log::info('Fallback successful', [
                        'conversation_id' => $conversation->id,
                        'fallback_provider' => $fallbackConfig['provider'],
                        'attempts' => $attempts,
                    ]);

                    return $updatedConversation;
                }
            } catch (\Exception $e) {
                $lastException = $e;
                Log::warning('Fallback attempt failed', [
                    'conversation_id' => $conversation->id,
                    'fallback_provider' => $fallbackConfig['provider'],
                    'attempt' => $attempts,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }
        }

        throw new NoFallbackAvailableException(
            "All fallback attempts failed after {$attempts} attempts. Last error: " . $lastException->getMessage(),
            0,
            $lastException
        );
    }

    /**
     * Get fallback providers based on strategy.
     */
    protected function getFallbackProviders(AIConversation $conversation, string $strategy): array
    {
        $currentProvider = $conversation->provider_name;

        return match ($strategy) {
            'cost_optimized' => $this->getCostOptimizedFallbacks($currentProvider),
            'performance_optimized' => $this->getPerformanceOptimizedFallbacks($currentProvider),
            'capability_matched' => $this->getCapabilityMatchedFallbacks($conversation),
            'user_preference' => $this->getUserPreferenceFallbacks($conversation),
            default => $this->getAutoFallbacks($currentProvider),
        };
    }

    /**
     * Get automatic fallback providers.
     */
    protected function getAutoFallbacks(string $currentProvider): array
    {
        $fallbackOrder = config('ai.fallback.auto_order', [
            'openai' => ['gemini', 'xai'],
            'gemini' => ['openai', 'xai'],
            'xai' => ['openai', 'gemini'],
        ]);

        $fallbacks = $fallbackOrder[$currentProvider] ?? [];

        return $this->filterAvailableProviders($fallbacks);
    }

    /**
     * Get cost-optimized fallback providers.
     */
    protected function getCostOptimizedFallbacks(string $currentProvider): array
    {
        // Get providers sorted by cost (cheapest first)
        $providers = AIProvider::where('status', 'active')
            ->where('name', '!=', $currentProvider)
            ->with(['models' => function ($query) {
                $query->where('status', 'active')
                    ->with('costs')
                    ->orderBy('input_cost_per_token', 'asc');
            }])
            ->get();

        return $providers->map(function ($provider) {
            $cheapestModel = $provider->models->first();

            return [
                'provider' => $provider->name,
                'model' => $cheapestModel?->name,
                'priority' => 'cost_optimized',
            ];
        })->toArray();
    }

    /**
     * Get performance-optimized fallback providers.
     */
    protected function getPerformanceOptimizedFallbacks(string $currentProvider): array
    {
        // Get providers sorted by performance metrics
        $providers = AIProvider::where('status', 'active')
            ->where('name', '!=', $currentProvider)
            ->with(['models' => function ($query) {
                $query->where('status', 'active')
                    ->orderBy('avg_response_time_ms', 'asc');
            }])
            ->get();

        return $providers->map(function ($provider) {
            $fastestModel = $provider->models->first();

            return [
                'provider' => $provider->name,
                'model' => $fastestModel?->name,
                'priority' => 'performance_optimized',
            ];
        })->toArray();
    }

    /**
     * Get capability-matched fallback providers.
     */
    protected function getCapabilityMatchedFallbacks(AIConversation $conversation): array
    {
        $currentModel = $conversation->model;
        if (! $currentModel) {
            return $this->getAutoFallbacks($conversation->provider_name);
        }

        $currentCapabilities = $currentModel->capabilities ?? [];

        // Find providers with similar capabilities
        $providers = AIProvider::where('status', 'active')
            ->where('name', '!=', $conversation->provider_name)
            ->with(['models' => function ($query) {
                $query->where('status', 'active');
                // Add capability matching logic here
            }])
            ->get();

        return $providers->map(function ($provider) {
            $compatibleModel = $provider->models->first();

            return [
                'provider' => $provider->name,
                'model' => $compatibleModel?->name,
                'priority' => 'capability_matched',
            ];
        })->toArray();
    }

    /**
     * Get user preference fallback providers.
     */
    protected function getUserPreferenceFallbacks(AIConversation $conversation): array
    {
        // Get user's preferred fallback order from conversation metadata or user settings
        $metadata = $conversation->metadata ?? [];
        $userFallbacks = $metadata['fallback_preferences'] ?? [];

        if (empty($userFallbacks)) {
            return $this->getAutoFallbacks($conversation->provider_name);
        }

        return $this->filterAvailableProviders($userFallbacks);
    }

    /**
     * Filter providers to only include available ones.
     */
    protected function filterAvailableProviders(array $providers): array
    {
        $availableProviders = [];

        foreach ($providers as $providerConfig) {
            $providerName = is_string($providerConfig) ? $providerConfig : $providerConfig['provider'];

            if ($this->isProviderAvailable($providerName)) {
                $availableProviders[] = is_string($providerConfig)
                    ? ['provider' => $providerConfig, 'model' => null]
                    : $providerConfig;
            }
        }

        return $availableProviders;
    }

    /**
     * Check if a provider is available for fallback.
     */
    protected function isProviderAvailable(string $providerName): bool
    {
        $cacheKey = "provider_health:{$providerName}";

        return Cache::remember($cacheKey, 300, function () use ($providerName) {
            try {
                $provider = AIProvider::where('name', $providerName)
                    ->where('status', 'active')
                    ->first();

                if (! $provider) {
                    return false;
                }

                $driver = $this->driverManager->driver($providerName);

                return $driver->validateHealth();
            } catch (\Exception $e) {
                Log::debug('Provider availability check failed', [
                    'provider' => $providerName,
                    'error' => $e->getMessage(),
                ]);

                return false;
            }
        });
    }

    /**
     * Validate that the fallback provider is working.
     */
    protected function validateFallbackProvider(AIConversation $conversation): bool
    {
        try {
            $driver = $this->driverManager->driver($conversation->provider_name);

            return $driver->validateHealth();
        } catch (\Exception $e) {
            Log::warning('Fallback provider validation failed', [
                'conversation_id' => $conversation->id,
                'provider' => $conversation->provider_name,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get fallback statistics for monitoring.
     */
    public function getFallbackStatistics(array $filters = []): array
    {
        // This would typically query a fallback_events table or similar
        // For now, return a placeholder structure
        return [
            'total_fallbacks' => 0,
            'success_rate' => 0.0,
            'most_common_triggers' => [],
            'provider_reliability' => [],
            'average_fallback_time' => 0,
        ];
    }
}
