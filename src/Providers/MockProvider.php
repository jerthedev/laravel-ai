<?php

namespace JTD\LaravelAI\Providers;

use JTD\LaravelAI\Drivers\Contracts\AbstractAIProvider;
use JTD\LaravelAI\Exceptions\InvalidCredentialsException;
use JTD\LaravelAI\Exceptions\ProviderException;
use JTD\LaravelAI\Exceptions\RateLimitException;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Models\TokenUsage;
use JTD\LaravelAI\Testing\ResponseFixtures;

/**
 * Mock AI Provider for testing and development.
 *
 * Provides predictable responses without making actual API calls.
 * Supports configurable responses, error simulation, and streaming.
 */
class MockProvider extends AbstractAIProvider
{
    /**
     * Error simulation configuration.
     */
    protected array $errorSimulation = [];

    /**
     * Response delay simulation (in milliseconds).
     */
    protected int $responseDelay = 0;

    /**
     * Create a new mock provider instance.
     */
    public function __construct(array $config = [])
    {
        $defaultConfig = [
            'valid_credentials' => true,
            'simulate_errors' => false,
            'response_delay' => 0,
            'streaming_chunk_size' => 10,
            'streaming_delay' => 50, // milliseconds
            'mock_responses' => [
                'default' => [
                    'content' => 'This is a mock response from the AI provider.',
                    'tokens_used' => 25,
                    'input_tokens' => 10,
                    'output_tokens' => 15,
                    'cost' => 0.0025,
                    'finish_reason' => 'stop',
                ],
                'hello' => [
                    'content' => 'Hello! How can I assist you today?',
                    'tokens_used' => 15,
                    'input_tokens' => 5,
                    'output_tokens' => 10,
                    'cost' => 0.0015,
                    'finish_reason' => 'stop',
                ],
                'error' => [
                    'error' => 'rate_limit',
                    'message' => 'Rate limit exceeded',
                    'retry_after' => 60,
                ],
                'timeout' => [
                    'error' => 'timeout',
                    'message' => 'Request timeout',
                ],
                'invalid_credentials' => [
                    'error' => 'invalid_credentials',
                    'message' => 'Invalid API key',
                ],
            ],
            'error_scenarios' => [
                'rate_limit' => [
                    'probability' => 0.0,
                    'retry_after' => 60,
                ],
                'timeout' => [
                    'probability' => 0.0,
                    'delay' => 30000, // 30 seconds
                ],
                'invalid_credentials' => [
                    'probability' => 0.0,
                ],
                'provider_error' => [
                    'probability' => 0.0,
                    'retryable' => true,
                ],
            ],
        ];

        parent::__construct(array_merge($defaultConfig, $config));

        $this->errorSimulation = $this->config['error_scenarios'] ?? [];
        $this->responseDelay = $this->config['response_delay'] ?? 0;
    }

    /**
     * Actually send the message to the provider.
     */
    protected function doSendMessage(array $messages, array $options): AIResponse
    {
        $this->simulateErrors();
        $this->simulateDelay();

        $lastMessage = end($messages);
        $content = $lastMessage instanceof AIMessage
            ? $lastMessage->getContentAsString()
            : (string) $lastMessage;

        $responseData = $this->getMockResponse($content, $options);

        // Check if this is an error response
        if (isset($responseData['error'])) {
            $this->throwMockError($responseData);
        }

        $tokenUsage = TokenUsage::withCosts(
            $responseData['input_tokens'],
            $responseData['output_tokens'],
            $responseData['cost'] * 0.4, // 40% for input
            $responseData['cost'] * 0.6, // 60% for output
            'USD'
        );

        $response = AIResponse::success(
            $responseData['content'],
            $tokenUsage,
            $this->model,
            'mock',
            ['mock_response' => true, 'response_data' => $responseData]
        );

        // Add tool calls if configured
        if (isset($responseData['tool_calls'])) {
            $response->toolCalls = $responseData['tool_calls'];
        }

        if (isset($responseData['function_calls'])) {
            $response->functionCalls = $responseData['function_calls'];
        }

        return $response;
    }

    /**
     * Actually send the streaming message to the provider.
     */
    protected function doSendStreamingMessage(array $messages, array $options): \Generator
    {
        $this->simulateErrors();

        $lastMessage = end($messages);
        $content = $lastMessage instanceof AIMessage
            ? $lastMessage->getContentAsString()
            : (string) $lastMessage;

        $responseData = $this->getMockResponse($content, $options);

        // Check if this is an error response
        if (isset($responseData['error'])) {
            $this->throwMockError($responseData);
        }

        $fullContent = $responseData['content'];
        $chunkSize = $this->config['streaming_chunk_size'] ?? 10;
        $streamingDelay = $this->config['streaming_delay'] ?? 50;

        // Split content into chunks for streaming simulation
        $chunks = str_split($fullContent, $chunkSize);

        foreach ($chunks as $index => $chunk) {
            $isLast = $index === count($chunks) - 1;

            yield AIResponse::streamingChunk(
                $chunk,
                $this->model,
                'mock',
                $isLast
            );

            // Simulate streaming delay
            if (! $isLast) {
                usleep($streamingDelay * 1000);
            }
        }
    }

    /**
     * Actually get available models from the provider.
     */
    protected function doGetAvailableModels(): array
    {
        return [
            [
                'id' => 'mock-model',
                'name' => 'Mock Model',
                'description' => 'A mock model for testing',
                'max_tokens' => 4096,
                'context_length' => 8192,
                'supports_streaming' => true,
                'supports_function_calling' => true,
                'supports_vision' => false,
                'supports_audio' => false,
                'cost_per_1k_input_tokens' => 0.001,
                'cost_per_1k_output_tokens' => 0.002,
            ],
            [
                'id' => 'mock-advanced',
                'name' => 'Mock Advanced Model',
                'description' => 'An advanced mock model for testing',
                'max_tokens' => 8192,
                'context_length' => 16384,
                'supports_streaming' => true,
                'supports_function_calling' => true,
                'supports_vision' => true,
                'supports_audio' => true,
                'cost_per_1k_input_tokens' => 0.002,
                'cost_per_1k_output_tokens' => 0.004,
            ],
            [
                'id' => 'mock-basic',
                'name' => 'Mock Basic Model',
                'description' => 'A basic mock model for testing',
                'max_tokens' => 2048,
                'context_length' => 4096,
                'supports_streaming' => false,
                'supports_function_calling' => false,
                'supports_vision' => false,
                'supports_audio' => false,
                'cost_per_1k_input_tokens' => 0.0005,
                'cost_per_1k_output_tokens' => 0.001,
            ],
        ];
    }

    /**
     * Actually calculate cost for the given input.
     */
    protected function doCalculateCost(int $tokens, string $model): array
    {
        $models = $this->doGetAvailableModels();
        $modelInfo = null;

        foreach ($models as $m) {
            if ($m['id'] === $model) {
                $modelInfo = $m;
                break;
            }
        }

        if (! $modelInfo) {
            $modelInfo = $models[0]; // Default to first model
        }

        $inputCost = ($tokens * 0.6) * ($modelInfo['cost_per_1k_input_tokens'] ?? 0.001);
        $outputCost = ($tokens * 0.4) * ($modelInfo['cost_per_1k_output_tokens'] ?? 0.002);

        return [
            'total' => $inputCost + $outputCost,
            'input_cost' => $inputCost,
            'output_cost' => $outputCost,
            'currency' => 'USD',
            'tokens' => $tokens,
            'model' => $model,
        ];
    }

    /**
     * Get cost rates for a specific model.
     */
    protected function getCostRates(string $model): array
    {
        $models = $this->doGetAvailableModels();

        foreach ($models as $m) {
            if ($m['id'] === $model) {
                return [
                    'input' => $m['cost_per_1k_input_tokens'] ?? 0.001,
                    'output' => $m['cost_per_1k_output_tokens'] ?? 0.002,
                    'currency' => 'USD',
                ];
            }
        }

        return [
            'input' => 0.001,
            'output' => 0.002,
            'currency' => 'USD',
        ];
    }

    /**
     * Simulate errors based on configuration.
     */
    protected function simulateErrors(): void
    {
        if (! $this->config['simulate_errors']) {
            return;
        }

        foreach ($this->errorSimulation as $errorType => $config) {
            $probability = $config['probability'] ?? 0.0;

            if ($probability > 0 && (mt_rand() / mt_getrandmax()) < $probability) {
                $this->throwSimulatedError($errorType, $config);
            }
        }
    }

    /**
     * Throw a simulated error.
     */
    protected function throwSimulatedError(string $errorType, array $config): void
    {
        switch ($errorType) {
            case 'rate_limit':
                throw new RateLimitException(
                    'Mock rate limit exceeded',
                    60,
                    0,
                    $config['retry_after'] ?? 60,
                    'requests'
                );

            case 'timeout':
                if (isset($config['delay'])) {
                    usleep($config['delay'] * 1000);
                }
                throw new ProviderException(
                    'Mock request timeout',
                    'mock',
                    'timeout',
                    ['simulated' => true],
                    true
                );

            case 'invalid_credentials':
                throw new InvalidCredentialsException(
                    'Mock invalid credentials',
                    'mock',
                    'test-account',
                    ['simulated' => true]
                );

            case 'provider_error':
                throw new ProviderException(
                    'Mock provider error',
                    'mock',
                    'api_error',
                    ['simulated' => true],
                    $config['retryable'] ?? true
                );
        }
    }

    /**
     * Simulate response delay.
     */
    protected function simulateDelay(): void
    {
        if ($this->responseDelay > 0) {
            usleep($this->responseDelay * 1000);
        }
    }

    /**
     * Throw a mock error based on response data.
     */
    protected function throwMockError(array $responseData): void
    {
        $errorType = $responseData['error'];
        $message = $responseData['message'] ?? 'Mock error';

        switch ($errorType) {
            case 'rate_limit':
                throw new RateLimitException(
                    $message,
                    60,
                    0,
                    $responseData['retry_after'] ?? 60,
                    'requests'
                );

            case 'timeout':
                throw new ProviderException(
                    $message,
                    'mock',
                    'timeout',
                    ['mock_response' => true],
                    true
                );

            case 'invalid_credentials':
                throw new InvalidCredentialsException(
                    $message,
                    'mock',
                    'test-account',
                    ['mock_response' => true]
                );

            default:
                throw new ProviderException(
                    $message,
                    'mock',
                    $errorType,
                    ['mock_response' => true],
                    true
                );
        }
    }

    /**
     * Validate the provider configuration and credentials.
     */
    public function validateCredentials(): array
    {
        return [
            'status' => $this->config['valid_credentials'] ? 'valid' : 'invalid',
            'message' => $this->config['valid_credentials']
                ? 'Mock credentials are valid'
                : 'Mock credentials are invalid',
            'provider' => 'mock',
        ];
    }

    /**
     * Get the provider's current health status.
     */
    public function getHealthStatus(): array
    {
        $healthStatus = $this->config['health_status'] ?? 'healthy';
        $responseTime = $this->config['health_response_time'] ?? 50;

        return [
            'status' => $healthStatus,
            'response_time' => $responseTime,
            'message' => $healthStatus === 'healthy'
                ? 'Mock provider is healthy'
                : 'Mock provider is experiencing issues',
            'provider' => 'mock',
            'timestamp' => time(),
            'details' => [
                'simulated' => true,
                'configurable' => true,
            ],
        ];
    }

    /**
     * Get provider-specific capabilities.
     */
    public function getCapabilities(): array
    {
        return [
            'streaming' => true,
            'function_calling' => true,
            'vision' => $this->config['supports_vision'] ?? false,
            'audio' => $this->config['supports_audio'] ?? false,
            'embeddings' => $this->config['supports_embeddings'] ?? false,
            'fine_tuning' => $this->config['supports_fine_tuning'] ?? false,
            'batch_processing' => $this->config['supports_batch'] ?? true,
            'error_simulation' => true,
            'configurable_responses' => true,
        ];
    }

    /**
     * Get the default model for this provider.
     */
    public function getDefaultModel(): string
    {
        return $this->config['default_model'] ?? 'mock-model';
    }

    /**
     * Get detailed information about a specific model.
     */
    public function getModelInfo(string $modelId): array
    {
        $models = $this->doGetAvailableModels();

        foreach ($models as $model) {
            if ($model['id'] === $modelId) {
                return $model;
            }
        }

        throw new \JTD\LaravelAI\Exceptions\ModelNotFoundException(
            "Model '{$modelId}' not found",
            $modelId,
            'mock',
            array_column($models, 'id')
        );
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
     * Get rate limit information for the current account.
     */
    public function getRateLimits(): array
    {
        return [
            'requests_per_minute' => $this->config['rate_limits']['requests_per_minute'] ?? 1000,
            'tokens_per_minute' => $this->config['rate_limits']['tokens_per_minute'] ?? 100000,
            'requests_per_hour' => $this->config['rate_limits']['requests_per_hour'] ?? 10000,
            'requests_per_day' => $this->config['rate_limits']['requests_per_day'] ?? 100000,
        ];
    }

    /**
     * Get current usage statistics for the account.
     */
    public function getUsageStats(string $period = 'day'): array
    {
        $baseUsage = [
            'period' => $period,
            'requests' => 0,
            'tokens' => 0,
            'cost' => 0.0,
            'provider' => 'mock',
        ];

        return array_merge($baseUsage, $this->config['usage_stats'] ?? []);
    }

    /**
     * Estimate tokens for a given input.
     */
    public function estimateTokens($input, ?string $modelId = null): int
    {
        if (is_string($input)) {
            return (int) ceil(strlen($input) / 4);
        }

        if ($input instanceof AIMessage) {
            return $input->getEstimatedTokenCount();
        }

        if (is_array($input)) {
            $total = 0;
            foreach ($input as $item) {
                $total += $this->estimateTokens($item, $modelId);
            }

            return $total;
        }

        return 0;
    }

    /**
     * Get the provider name.
     */
    public function getName(): string
    {
        return 'mock';
    }

    /**
     * Get the provider version or API version being used.
     */
    public function getVersion(): string
    {
        return $this->config['version'] ?? '1.0.0';
    }

    /**
     * Configure error simulation.
     */
    public function configureErrorSimulation(array $errorConfig): self
    {
        $this->errorSimulation = array_merge($this->errorSimulation, $errorConfig);
        $this->config['error_scenarios'] = $this->errorSimulation;

        return $this;
    }

    /**
     * Set response delay for testing.
     */
    public function setResponseDelay(int $milliseconds): self
    {
        $this->responseDelay = $milliseconds;
        $this->config['response_delay'] = $milliseconds;

        return $this;
    }

    /**
     * Add a custom mock response.
     */
    public function addMockResponse(string $trigger, array $response): self
    {
        $this->config['mock_responses'][$trigger] = $response;

        return $this;
    }

    /**
     * Enable or disable error simulation.
     */
    public function setErrorSimulation(bool $enabled): self
    {
        $this->config['simulate_errors'] = $enabled;

        return $this;
    }

    /**
     * Get mock response for given content.
     */
    protected function getMockResponse(string $content, array $options = []): array
    {
        // First check configured responses
        $responses = $this->config['mock_responses'];

        // Check if tools are enabled and simulate tool calls
        if (isset($options['tools']) && ! empty($options['tools'])) {
            $toolCallResponse = $this->simulateToolCalls($content, $options['tools']);
            if ($toolCallResponse) {
                return $toolCallResponse;
            }
        }

        // Check for specific responses first
        foreach ($responses as $trigger => $response) {
            if ($trigger !== 'default' && stripos($content, $trigger) !== false) {
                return $response;
            }
        }

        // Try to get realistic responses from fixtures
        $provider = $this->config['fixture_provider'] ?? 'generic';
        $fixtures = ResponseFixtures::forProvider($provider);

        // Match content to appropriate fixture
        $contentLower = strtolower($content);

        if (stripos($contentLower, 'hello') !== false || stripos($contentLower, 'hi') !== false) {
            return $fixtures['hello'] ?? $responses['default'];
        }

        if (stripos($contentLower, 'code') !== false || stripos($contentLower, 'programming') !== false) {
            return $fixtures['code_help'] ?? $responses['default'];
        }

        if (stripos($contentLower, 'explain') !== false || stripos($contentLower, 'what is') !== false) {
            return $fixtures['explain_concept'] ?? $fixtures['structured_response'] ?? $responses['default'];
        }

        if (stripos($contentLower, 'function') !== false && stripos($contentLower, 'call') !== false) {
            return $fixtures['function_call'] ?? $responses['default'];
        }

        if (strlen($content) < 20) {
            return $fixtures['short'] ?? $responses['default'];
        }

        if (strlen($content) > 100) {
            return $fixtures['long'] ?? $responses['default'];
        }

        // Return default response
        return $responses['default'];
    }

    /**
     * Load fixtures for a specific provider.
     */
    public function loadFixtures(string $provider): self
    {
        $this->config['fixture_provider'] = $provider;
        $fixtures = ResponseFixtures::forProvider($provider);

        // Merge fixtures into mock_responses
        $this->config['mock_responses'] = array_merge(
            $this->config['mock_responses'],
            $fixtures
        );

        return $this;
    }

    /**
     * Synchronize models from the provider API to local cache/database.
     */
    public function syncModels(bool $forceRefresh = false): array
    {
        $models = $this->doGetAvailableModels();

        return [
            'status' => 'success',
            'models_synced' => count($models),
            'statistics' => [
                'total_models' => count($models),
                'mock_models' => count($models),
                'updated_at' => now()->toISOString(),
            ],
            'cached_until' => now()->addHours(24),
            'last_sync' => now(),
        ];
    }

    /**
     * Check if the provider has valid credentials configured.
     */
    public function hasValidCredentials(): bool
    {
        return $this->config['valid_credentials'] ?? true;
    }

    /**
     * Get the timestamp of the last successful model synchronization.
     */
    public function getLastSyncTime(): ?\Carbon\Carbon
    {
        return $this->config['last_sync_time'] ?? now()->subHours(1);
    }

    /**
     * Get models that can be synchronized from this provider.
     */
    public function getSyncableModels(): array
    {
        $models = $this->doGetAvailableModels();

        return array_map(function ($model) {
            return [
                'id' => $model['id'],
                'name' => $model['name'],
                'owned_by' => 'mock',
                'created' => time(),
            ];
        }, $models);
    }

    /**
     * Format resolved tools for Mock provider API.
     *
     * @param  array  $resolvedTools  Resolved tool definitions from UnifiedToolRegistry
     * @return array Formatted tools for Mock provider
     */
    protected function formatToolsForAPI(array $resolvedTools): array
    {
        $formattedTools = [];

        foreach ($resolvedTools as $toolName => $tool) {
            $formattedTools[] = [
                'type' => 'function',
                'function' => [
                    'name' => $tool['name'] ?? $toolName,
                    'description' => $tool['description'] ?? '',
                    'parameters' => $tool['parameters'] ?? [
                        'type' => 'object',
                        'properties' => [],
                    ],
                ],
                // Add mock-specific metadata
                'mock_metadata' => [
                    'tool_type' => $tool['type'] ?? 'unknown',
                    'execution_mode' => $tool['execution_mode'] ?? 'unknown',
                    'source' => $tool['source'] ?? 'unknown',
                ],
            ];
        }

        return $formattedTools;
    }

    /**
     * Simulate tool calls based on content and available tools.
     *
     * @param  string  $content  Message content
     * @param  array  $tools  Available tools
     * @return array|null Tool call response or null if no simulation
     */
    protected function simulateToolCalls(string $content, array $tools): ?array
    {
        // Check if content suggests tool usage
        $contentLower = strtolower($content);
        $shouldSimulateToolCall = false;
        $toolsToCall = [];

        // Look for tool-related keywords
        foreach ($tools as $tool) {
            $toolName = $tool['function']['name'] ?? '';
            $toolDescription = $tool['function']['description'] ?? '';

            // Simple keyword matching for simulation
            if (stripos($content, $toolName) !== false ||
                stripos($contentLower, 'use') !== false ||
                stripos($contentLower, 'call') !== false ||
                stripos($contentLower, 'execute') !== false) {
                $shouldSimulateToolCall = true;
                $toolsToCall[] = $tool;
                break; // Simulate only one tool call for simplicity
            }
        }

        // Check for specific tool simulation patterns
        if (stripos($contentLower, 'sequential_thinking') !== false ||
            stripos($contentLower, 'think') !== false) {
            $shouldSimulateToolCall = true;
            $toolsToCall = array_filter($tools, function ($tool) {
                return stripos($tool['function']['name'] ?? '', 'sequential_thinking') !== false;
            });
        }

        if (stripos($contentLower, 'send_email') !== false ||
            stripos($contentLower, 'email') !== false) {
            $shouldSimulateToolCall = true;
            $toolsToCall = array_filter($tools, function ($tool) {
                return stripos($tool['function']['name'] ?? '', 'send_email') !== false;
            });
        }

        if (! $shouldSimulateToolCall || empty($toolsToCall)) {
            return null;
        }

        // Generate mock tool calls
        $toolCalls = [];
        foreach (array_slice($toolsToCall, 0, 1) as $tool) { // Limit to 1 tool call
            $toolCalls[] = [
                'id' => 'call_' . uniqid(),
                'type' => 'function',
                'function' => [
                    'name' => $tool['function']['name'],
                    'arguments' => json_encode($this->generateMockArguments($tool['function'])),
                ],
            ];
        }

        return [
            'content' => 'I\'ll help you with that. Let me use the appropriate tool.',
            'input_tokens' => 15,
            'output_tokens' => 12,
            'cost' => 0.0027,
            'finish_reason' => 'tool_calls',
            'tool_calls' => $toolCalls,
        ];
    }

    /**
     * Generate mock arguments for a tool function.
     *
     * @param  array  $functionDef  Function definition
     * @return array Mock arguments
     */
    protected function generateMockArguments(array $functionDef): array
    {
        $parameters = $functionDef['parameters'] ?? [];
        $properties = $parameters['properties'] ?? [];
        $required = $parameters['required'] ?? [];

        $arguments = [];

        foreach ($properties as $paramName => $paramDef) {
            $paramType = $paramDef['type'] ?? 'string';

            // Generate mock values based on parameter type
            switch ($paramType) {
                case 'string':
                    $arguments[$paramName] = 'mock_' . $paramName . '_value';
                    break;
                case 'integer':
                case 'number':
                    $arguments[$paramName] = rand(1, 100);
                    break;
                case 'boolean':
                    $arguments[$paramName] = true;
                    break;
                case 'array':
                    $arguments[$paramName] = ['mock_item_1', 'mock_item_2'];
                    break;
                case 'object':
                    $arguments[$paramName] = ['mock_key' => 'mock_value'];
                    break;
                default:
                    $arguments[$paramName] = 'mock_value';
            }
        }

        // Ensure required parameters are included
        foreach ($required as $requiredParam) {
            if (! isset($arguments[$requiredParam])) {
                $arguments[$requiredParam] = 'required_mock_value';
            }
        }

        return $arguments;
    }

    /**
     * Check if response contains tool calls.
     *
     * @param  \JTD\LaravelAI\Models\AIResponse  $response  AI response
     * @return bool True if response has tool calls
     */
    protected function hasToolCalls($response): bool
    {
        return ! empty($response->toolCalls) || ! empty($response->functionCalls);
    }

    /**
     * Extract tool calls from Mock response.
     *
     * @param  \JTD\LaravelAI\Models\AIResponse  $response  AI response
     * @return array Extracted tool calls in unified format
     */
    protected function extractToolCalls($response): array
    {
        $calls = [];

        // Handle legacy function_call format
        if (! empty($response->functionCalls)) {
            $calls[] = [
                'name' => $response->functionCalls['name'] ?? '',
                'arguments' => $response->functionCalls['arguments'] ?? [],
                'id' => null,
            ];
        }

        // Handle new tool_calls format
        if (! empty($response->toolCalls)) {
            foreach ($response->toolCalls as $toolCall) {
                if (($toolCall['type'] ?? '') === 'function') {
                    $arguments = $toolCall['function']['arguments'] ?? '{}';
                    if (is_string($arguments)) {
                        try {
                            $arguments = json_decode($arguments, true) ?? [];
                        } catch (\Exception $e) {
                            $arguments = [];
                        }
                    }

                    $calls[] = [
                        'name' => $toolCall['function']['name'] ?? '',
                        'arguments' => $arguments,
                        'id' => $toolCall['id'] ?? null,
                    ];
                }
            }
        }

        return $calls;
    }
}
