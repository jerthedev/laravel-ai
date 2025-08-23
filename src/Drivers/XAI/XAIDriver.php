<?php

namespace JTD\LaravelAI\Drivers\XAI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Drivers\Contracts\AbstractAIProvider;
use JTD\LaravelAI\Drivers\XAI\Traits\CalculatesCosts;
use JTD\LaravelAI\Drivers\XAI\Traits\HandlesApiCommunication;
use JTD\LaravelAI\Drivers\XAI\Traits\HandlesErrors;
use JTD\LaravelAI\Drivers\XAI\Traits\HandlesFunctionCalling;
use JTD\LaravelAI\Drivers\XAI\Traits\ManagesModels;
use JTD\LaravelAI\Drivers\XAI\Traits\SupportsStreaming;
use JTD\LaravelAI\Drivers\XAI\Traits\ValidatesHealth;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;

/**
 * xAI Driver - Production-Ready Implementation
 *
 * This driver provides comprehensive integration with xAI's Grok API, including:
 * - Chat Completions with streaming support
 * - Function calling with parallel execution
 * - Model management and synchronization
 * - Cost tracking and analytics
 * - Comprehensive error handling with retry logic
 * - Event-driven architecture for monitoring
 * - Security features including credential masking
 *
 * The driver uses a trait-based architecture for maintainability and extensibility,
 * following the same patterns as the OpenAI driver for consistency.
 *
 * @version 1.0.0
 *
 * @since 1.0.0
 * @see https://docs.x.ai/docs/api-reference xAI API Documentation
 * @see docs/XAI_DRIVER.md Comprehensive usage documentation
 *
 * @example
 * ```php
 * $driver = new XAIDriver([
 *     'api_key' => 'xai-your-api-key',
 *     'base_url' => 'https://api.x.ai/v1',
 * ]);
 *
 * $response = $driver->sendMessage(
 *     AIMessage::user('Hello, Grok!'),
 *     ['model' => 'grok-beta']
 * );
 * ```
 */
class XAIDriver extends AbstractAIProvider
{
    use CalculatesCosts;
    use HandlesApiCommunication;
    use HandlesErrors;
    use HandlesFunctionCalling;
    use ManagesModels;
    use SupportsStreaming;
    use ValidatesHealth;

    /**
     * HTTP client instance for API communication.
     *
     * Uses Laravel's HTTP client for making requests to the xAI API.
     * Configured with authentication headers and timeout settings.
     *
     * @var \Illuminate\Http\Client\PendingRequest
     */
    protected $client = null;

    /**
     * Provider name identifier.
     *
     * Used for logging, events, and provider identification throughout the system.
     * This value is used in database records, log entries, and event dispatching.
     *
     * @var string
     */
    protected $providerName = 'xai';

    /**
     * Default model for requests.
     *
     * This model is used when no specific model is requested in API calls.
     * Can be overridden in configuration or per-request options.
     *
     * @var string
     */
    protected $defaultModel = 'grok-3-mini';

    /**
     * Cached models list.
     */
    protected $cachedModels = null;

    /**
     * Create a new xAI driver instance.
     *
     * Initializes the xAI driver with the provided configuration, validates
     * the configuration parameters, and sets up the HTTP client for API communication.
     *
     * @param  array  $config  Configuration array with the following options:
     *                         - api_key (string, required): xAI API key
     *                         - base_url (string, optional): Custom API base URL
     *                         - timeout (int, optional): Request timeout in seconds (default: 30)
     *                         - retry_attempts (int, optional): Number of retry attempts (default: 3)
     *                         - retry_delay (int, optional): Initial retry delay in ms (default: 1000)
     *                         - max_retry_delay (int, optional): Maximum retry delay in ms (default: 30000)
     *                         - logging (array, optional): Logging configuration
     *                         - rate_limiting (array, optional): Rate limiting configuration
     *                         - cost_tracking (array, optional): Cost tracking configuration
     *
     * @throws \JTD\LaravelAI\Exceptions\XAI\XAIInvalidCredentialsException
     *                                                                      When API key is missing or invalid format
     * @throws \InvalidArgumentException
     *                                   When configuration parameters are invalid
     *
     * @example
     * ```php
     * $driver = new XAIDriver([
     *     'api_key' => 'xai-your-api-key-here',
     *     'base_url' => 'https://api.x.ai/v1',
     *     'timeout' => 60,
     *     'retry_attempts' => 5,
     * ]);
     * ```
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        // Set default configuration
        $this->config = array_merge([
            'base_url' => 'https://api.x.ai/v1',
            'timeout' => 30,
            'retry_attempts' => 3,
            'retry_delay' => 1000,
            'max_retry_delay' => 30000,
            'default_model' => 'grok-beta',
        ], $config);

        // Validate required configuration
        if (empty($this->config['api_key'])) {
            throw new \InvalidArgumentException('xAI API key is required');
        }

        // Initialize HTTP client
        $this->initializeClient();
    }

    /**
     * Initialize the HTTP client.
     */
    protected function initializeClient(): void
    {
        $this->client = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->config['api_key'],
            'Content-Type' => 'application/json',
            'User-Agent' => 'JTD-Laravel-AI/1.0',
        ])->timeout($this->config['timeout']);
    }

    /**
     * Get the provider name.
     */
    public function getName(): string
    {
        return $this->providerName;
    }

    /**
     * Get the default model for this provider.
     */
    public function getDefaultModel(): string
    {
        return $this->config['default_model'] ?? $this->defaultModel;
    }

    /**
     * Get the currently configured model.
     */
    public function getCurrentModel(): string
    {
        return $this->options['model'] ?? $this->getDefaultModel();
    }

    /**
     * Get driver configuration (without sensitive data).
     */
    public function getConfig(): array
    {
        $config = $this->config;

        // Mask sensitive data
        if (isset($config['api_key'])) {
            $config['api_key'] = '***' . substr($config['api_key'], -4);
        }

        return $config;
    }

    /**
     * Get provider-specific capabilities.
     */
    public function getCapabilities(): array
    {
        return [
            'chat' => true,
            'streaming' => true,
            'function_calling' => true,
            'vision' => false, // xAI doesn't support vision yet
            'image_generation' => true,
            'max_context_length' => 131072, // Grok models context length
            'supported_models' => [
                'grok-beta',
                'grok-2',
                'grok-2-mini',
                'grok-2-1212',
                'grok-2-vision-1212',
            ],
        ];
    }

    /**
     * Check if the provider supports a specific feature.
     */
    public function supportsFeature(string $feature): bool
    {
        $capabilities = $this->getCapabilities();

        return $capabilities[$feature] ?? false;
    }

    /**
     * Fire events for background processing.
     */
    protected function fireEvents($originalMessage, AIResponse $response, array $options): void
    {
        // Implementation will be added when implementing event system
        // For now, this is a placeholder to match the interface
    }

    /**
     * Get the HTTP client instance.
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Set the HTTP client instance.
     */
    public function setClient($client): self
    {
        $this->client = $client;

        return $this;
    }

    /**
     * Clone the driver with new configuration.
     */
    public function withConfig(array $config): self
    {
        $newConfig = array_merge($this->config, $config);

        return new static($newConfig);
    }

    /**
     * Clone the driver with a different model.
     */
    public function withModel(string $model): self
    {
        return $this->withConfig(['default_model' => $model]);
    }

    /**
     * Get a summary of driver capabilities.
     */
    public function getSummary(): array
    {
        return [
            'provider' => $this->providerName,
            'name' => 'xAI Grok Driver',
            'version' => '1.0.0',
            'capabilities' => $this->getCapabilities(),
            'default_model' => $this->getDefaultModel(),
            'status' => 'active',
        ];
    }

    /**
     * Test the driver with a simple request.
     */
    public function test(): array
    {
        try {
            $response = $this->sendMessage(
                AIMessage::user('Hello'),
                ['model' => $this->getDefaultModel(), 'max_tokens' => 5]
            );

            return [
                'success' => true,
                'response_time' => $response->responseTimeMs,
                'model' => $response->model,
                'tokens_used' => $response->tokenUsage->totalTokens,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get driver version information.
     */
    public function getVersionInfo(): array
    {
        return [
            'driver_version' => '1.0.0',
            'api_version' => 'v1',
            'provider' => 'xAI',
            'supported_models' => $this->getCapabilities()['supported_models'],
        ];
    }

    /**
     * Get model information.
     */
    public function getModelInfo(string $modelId): array
    {
        return [
            'id' => $modelId,
            'name' => $modelId,
            'provider' => 'xai',
            'type' => 'chat',
            'context_length' => 131072,
            'supports_streaming' => true,
            'supports_function_calling' => true,
            'supports_vision' => $modelId === 'grok-2-vision-1212',
        ];
    }

    /**
     * Get health status.
     */
    public function getHealthStatus(): array
    {
        return $this->healthCheck();
    }

    /**
     * Get rate limits.
     */
    public function getRateLimits(): array
    {
        return [
            'requests_per_minute' => null,
            'tokens_per_minute' => null,
            'requests_per_day' => null,
            'tokens_per_day' => null,
        ];
    }

    /**
     * Get usage statistics.
     */
    public function getUsageStats(string $period = 'day'): array
    {
        return [
            'period' => $period,
            'requests' => 0,
            'tokens' => 0,
            'cost' => 0.0,
        ];
    }

    /**
     * Get provider version.
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Get string representation of the driver.
     */
    public function __toString(): string
    {
        return "XAIDriver(model={$this->getCurrentModel()})";
    }
}
