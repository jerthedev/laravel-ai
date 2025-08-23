<?php

namespace JTD\LaravelAI\Services;

use Illuminate\Contracts\Foundation\Application;
use JTD\LaravelAI\Contracts\AIProviderInterface;
use JTD\LaravelAI\Exceptions\ProviderNotFoundException;

/**
 * Driver Manager
 *
 * Manages AI provider driver registration, resolution, and configuration.
 * Follows Laravel's driver pattern for consistent provider management.
 */
class DriverManager
{
    /**
     * The application instance.
     */
    protected Application $app;

    /**
     * The registered custom driver creators.
     */
    protected array $customCreators = [];

    /**
     * The resolved driver instances.
     */
    protected array $drivers = [];

    /**
     * The provider registry.
     */
    protected array $providerRegistry = [];

    /**
     * Create a new driver manager instance.
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->registerBuiltInProviders();
    }

    /**
     * Get a driver instance.
     */
    public function driver(?string $name = null): AIProviderInterface
    {
        $name = $name ?: $this->getDefaultDriver();

        if (! isset($this->drivers[$name])) {
            $this->drivers[$name] = $this->createDriver($name);
        }

        return $this->drivers[$name];
    }

    /**
     * Create a driver instance.
     */
    protected function createDriver(string $name): AIProviderInterface
    {
        $config = $this->getProviderConfig($name);

        // Check for custom driver creators first
        if (isset($this->customCreators[$name])) {
            return $this->callCustomCreator($name, $config);
        }

        // Check for built-in drivers
        $driver = $config['driver'] ?? $name;
        $method = 'create' . ucfirst($driver) . 'Driver';

        if (method_exists($this, $method)) {
            return $this->$method($config);
        }

        throw new ProviderNotFoundException(
            "Driver [{$driver}] not supported.",
            $name,
            $this->getAvailableProviders()
        );
    }

    /**
     * Call a custom driver creator.
     */
    protected function callCustomCreator(string $name, array $config): AIProviderInterface
    {
        return $this->customCreators[$name]($this->app, $config);
    }

    /**
     * Register a custom driver creator.
     */
    public function extend(string $name, \Closure $callback): self
    {
        $this->customCreators[$name] = $callback;

        return $this;
    }

    /**
     * Get the provider configuration.
     */
    protected function getProviderConfig(string $name): array
    {
        $config = $this->app['config']["ai.providers.{$name}"];

        if (is_null($config)) {
            throw new ProviderNotFoundException(
                "Provider [{$name}] is not configured.",
                $name,
                $this->getAvailableProviders()
            );
        }

        return $config;
    }

    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->app['config']['ai.default'] ?? 'mock';
    }

    /**
     * Get all available providers.
     */
    public function getAvailableProviders(): array
    {
        return array_keys($this->app['config']['ai.providers'] ?? []);
    }

    /**
     * Get all registered providers with their metadata.
     */
    public function getProviderRegistry(): array
    {
        return $this->providerRegistry;
    }

    /**
     * Register a provider in the registry.
     */
    public function registerProvider(string $name, array $metadata): self
    {
        $this->providerRegistry[$name] = array_merge([
            'name' => $name,
            'driver' => $name,
            'description' => null,
            'website' => null,
            'documentation' => null,
            'supports_streaming' => false,
            'supports_function_calling' => false,
            'supports_vision' => false,
            'supports_audio' => false,
            'max_tokens' => null,
            'context_length' => null,
        ], $metadata);

        return $this;
    }

    /**
     * Check if a provider is registered.
     */
    public function hasProvider(string $name): bool
    {
        return isset($this->providerRegistry[$name]) ||
               isset($this->customCreators[$name]) ||
               $this->hasBuiltInProvider($name);
    }

    /**
     * Check if a built-in provider exists.
     */
    protected function hasBuiltInProvider(string $name): bool
    {
        $config = $this->app['config']["ai.providers.{$name}"];
        if (is_null($config)) {
            return false;
        }

        $driver = $config['driver'] ?? $name;
        $method = 'create' . ucfirst($driver) . 'Driver';

        return method_exists($this, $method);
    }

    /**
     * Get provider information.
     */
    public function getProviderInfo(string $name): array
    {
        if (isset($this->providerRegistry[$name])) {
            return $this->providerRegistry[$name];
        }

        if ($this->hasBuiltInProvider($name)) {
            $config = $this->getProviderConfig($name);

            return [
                'name' => $name,
                'driver' => $config['driver'] ?? $name,
                'description' => $config['description'] ?? null,
                'built_in' => true,
            ];
        }

        throw new ProviderNotFoundException(
            "Provider [{$name}] not found.",
            $name,
            $this->getAvailableProviders()
        );
    }

    /**
     * Validate provider configuration.
     */
    public function validateProvider(string $name): array
    {
        try {
            $driver = $this->driver($name);

            return $driver->validateCredentials();
        } catch (\Exception $e) {
            return [
                'status' => 'invalid',
                'message' => $e->getMessage(),
                'provider' => $name,
            ];
        }
    }

    /**
     * Get provider health status.
     */
    public function getProviderHealth(string $name): array
    {
        try {
            $driver = $this->driver($name);

            return $driver->getHealthStatus();
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => $e->getMessage(),
                'provider' => $name,
            ];
        }
    }

    /**
     * Refresh a driver instance.
     */
    public function refreshDriver(string $name): self
    {
        unset($this->drivers[$name]);

        return $this;
    }

    /**
     * Refresh all driver instances.
     */
    public function refreshAllDrivers(): self
    {
        $this->drivers = [];

        return $this;
    }

    /**
     * Register built-in providers.
     */
    protected function registerBuiltInProviders(): void
    {
        $this->registerProvider('mock', [
            'description' => 'Mock AI provider for testing and development',
            'supports_streaming' => true,
            'supports_function_calling' => true,
            'supports_vision' => false,
            'supports_audio' => false,
            'max_tokens' => 4096,
            'context_length' => 8192,
        ]);

        $this->registerProvider('openai', [
            'description' => 'OpenAI GPT models including GPT-4 and GPT-3.5',
            'website' => 'https://openai.com',
            'documentation' => 'https://platform.openai.com/docs',
            'supports_streaming' => true,
            'supports_function_calling' => true,
            'supports_vision' => true,
            'supports_audio' => true,
            'max_tokens' => 4096,
            'context_length' => 128000,
        ]);

        $this->registerProvider('xai', [
            'description' => 'xAI Grok models for advanced reasoning',
            'website' => 'https://x.ai',
            'documentation' => 'https://docs.x.ai',
            'supports_streaming' => true,
            'supports_function_calling' => true,
            'supports_vision' => false,
            'supports_audio' => false,
            'max_tokens' => 4096,
            'context_length' => 131072,
        ]);

        $this->registerProvider('gemini', [
            'description' => 'Google Gemini models for multimodal AI',
            'website' => 'https://ai.google.dev',
            'documentation' => 'https://ai.google.dev/docs',
            'supports_streaming' => true,
            'supports_function_calling' => true,
            'supports_vision' => true,
            'supports_audio' => true,
            'max_tokens' => 8192,
            'context_length' => 1000000,
        ]);

        $this->registerProvider('xai', [
            'description' => 'xAI Grok models for advanced reasoning and conversation',
            'website' => 'https://x.ai',
            'documentation' => 'https://docs.x.ai',
            'supports_streaming' => true,
            'supports_function_calling' => true,
            'supports_vision' => false, // Only grok-2-vision-1212 supports vision
            'supports_audio' => false,
            'max_tokens' => 4096,
            'context_length' => 131072,
        ]);

        $this->registerProvider('ollama', [
            'description' => 'Ollama local AI models',
            'website' => 'https://ollama.ai',
            'documentation' => 'https://github.com/ollama/ollama',
            'supports_streaming' => true,
            'supports_function_calling' => false,
            'supports_vision' => false,
            'supports_audio' => false,
            'max_tokens' => 4096,
            'context_length' => 4096,
        ]);
    }

    /**
     * Get providers with valid credentials for synchronization.
     */
    public function getProvidersWithValidCredentials(): array
    {
        $validProviders = [];
        $availableProviders = $this->getAvailableProviders();

        foreach ($availableProviders as $providerName) {
            try {
                $driver = $this->driver($providerName);

                // Skip mock provider in production
                if ($providerName === 'mock' && app()->environment('production')) {
                    continue;
                }

                if ($driver->hasValidCredentials()) {
                    $validProviders[] = $providerName;
                }
            } catch (\Exception $e) {
                // Skip providers that can't be instantiated
                continue;
            }
        }

        return $validProviders;
    }

    /**
     * Check if a provider has valid credentials.
     */
    public function hasValidCredentials(string $name): bool
    {
        try {
            $driver = $this->driver($name);

            return $driver->hasValidCredentials();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get syncable models from all providers with valid credentials.
     */
    public function getAllSyncableModels(): array
    {
        $allModels = [];
        $providers = $this->getProvidersWithValidCredentials();

        foreach ($providers as $providerName) {
            try {
                $driver = $this->driver($providerName);
                $models = $driver->getSyncableModels();

                $allModels[$providerName] = $models;
            } catch (\Exception $e) {
                // Skip providers that fail to get syncable models
                continue;
            }
        }

        return $allModels;
    }

    /**
     * Sync models for all providers with valid credentials.
     */
    public function syncAllProviderModels(bool $forceRefresh = false): array
    {
        $results = [];
        $providers = $this->getProvidersWithValidCredentials();

        foreach ($providers as $providerName) {
            try {
                $driver = $this->driver($providerName);
                $results[$providerName] = $driver->syncModels($forceRefresh);
            } catch (\Exception $e) {
                $results[$providerName] = [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Get last sync times for all providers.
     */
    public function getAllLastSyncTimes(): array
    {
        $syncTimes = [];
        $providers = $this->getAvailableProviders();

        foreach ($providers as $providerName) {
            try {
                $driver = $this->driver($providerName);
                $syncTimes[$providerName] = $driver->getLastSyncTime();
            } catch (\Exception $e) {
                $syncTimes[$providerName] = null;
            }
        }

        return $syncTimes;
    }

    /**
     * Create the mock driver.
     */
    protected function createMockDriver(array $config): AIProviderInterface
    {
        return new \JTD\LaravelAI\Providers\MockProvider($config);
    }

    /**
     * Create the OpenAI driver.
     */
    protected function createOpenaiDriver(array $config): AIProviderInterface
    {
        return new \JTD\LaravelAI\Drivers\OpenAI\OpenAIDriver($config);
    }

    /**
     * Create the xAI driver.
     */
    protected function createXaiDriver(array $config): AIProviderInterface
    {
        return new \JTD\LaravelAI\Drivers\XAI\XAIDriver($config);
    }

    /**
     * Create the Gemini driver.
     */
    protected function createGeminiDriver(array $config): AIProviderInterface
    {
        return new \JTD\LaravelAI\Drivers\Gemini\GeminiDriver($config);
    }

    /**
     * Create the Ollama driver.
     */
    protected function createOllamaDriver(array $config): AIProviderInterface
    {
        // This will be implemented when we create the Ollama provider
        throw new \BadMethodCallException('Ollama driver not yet implemented');
    }
}
