<?php

namespace JTD\LaravelAI\Contracts;

use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Models\TokenUsage;

/**
 * Interface for AI provider drivers.
 *
 * All AI provider drivers must implement this interface to ensure consistent
 * behavior across different providers (OpenAI, xAI, Gemini, Ollama, etc.).
 *
 * This interface follows Laravel's driver pattern similar to database connections,
 * providing a unified API while allowing provider-specific implementations.
 */
interface AIProviderInterface
{
    /**
     * Send a message to the AI provider and get a response.
     *
     * This is the core method for AI interaction. It handles both single messages
     * and conversation contexts, supporting various message types and configurations.
     *
     * @param  AIMessage|array  $message  Single message or array of messages for conversation
     * @param  array  $options  Request options (temperature, max_tokens, model, etc.)
     * @return AIResponse The AI provider's response
     *
     * @throws \JTD\LaravelAI\Exceptions\RateLimitException
     * @throws \JTD\LaravelAI\Exceptions\InvalidCredentialsException
     * @throws \JTD\LaravelAI\Exceptions\ProviderException
     * @throws \JTD\LaravelAI\Exceptions\InvalidConfigurationException
     */
    public function sendMessage($message, array $options = []): AIResponse;

    /**
     * Send a streaming message to the AI provider.
     *
     * Returns a generator that yields response chunks as they arrive,
     * enabling real-time streaming responses for better user experience.
     *
     * @param  AIMessage|array  $message  Single message or array of messages
     * @param  array  $options  Request options
     * @return \Generator<AIResponse> Generator yielding response chunks
     *
     * @throws \JTD\LaravelAI\Exceptions\StreamingNotSupportedException
     */
    public function sendStreamingMessage($message, array $options = []): \Generator;

    /**
     * Get all available models from the provider.
     *
     * Retrieves the current list of models available from the provider,
     * including their capabilities, pricing, and specifications.
     *
     * @param  bool  $forceRefresh  Force refresh from provider API (bypass cache)
     * @return array Array of model information
     *
     * @throws \JTD\LaravelAI\Exceptions\ProviderException
     */
    public function getAvailableModels(bool $forceRefresh = false): array;

    /**
     * Get detailed information about a specific model.
     *
     * @param  string  $modelId  The model identifier
     * @return array Model information including capabilities and pricing
     *
     * @throws \JTD\LaravelAI\Exceptions\ModelNotFoundException
     */
    public function getModelInfo(string $modelId): array;

    /**
     * Calculate the cost for a given message or token usage.
     *
     * Provides cost estimation before sending requests or calculates
     * actual costs after receiving responses.
     *
     * @param  AIMessage|TokenUsage|array  $input  Message, token usage, or array of messages
     * @param  string|null  $modelId  Model to use for calculation (uses default if null)
     * @return array Cost breakdown with total, input_cost, output_cost, currency
     *
     * @throws \JTD\LaravelAI\Exceptions\CostCalculationException
     */
    public function calculateCost($input, ?string $modelId = null): array;

    /**
     * Validate the provider configuration and credentials.
     *
     * Tests the connection to the provider and validates that credentials
     * are working correctly. Used for health checks and setup validation.
     *
     * @return array Validation result with status, message, and details
     */
    public function validateCredentials(): array;

    /**
     * Get the provider's current health status.
     *
     * Checks if the provider is operational and returns status information
     * including response times and any known issues.
     *
     * @return array Health status with status, response_time, message
     */
    public function getHealthStatus(): array;

    /**
     * Get provider-specific capabilities.
     *
     * Returns information about what features this provider supports,
     * such as streaming, function calling, vision, etc.
     *
     * @return array Capabilities array
     */
    public function getCapabilities(): array;

    /**
     * Get the default model for this provider.
     *
     * @return string Default model identifier
     */
    public function getDefaultModel(): string;

    /**
     * Set the model to use for requests.
     *
     * @param  string  $modelId  Model identifier
     * @return self For method chaining
     *
     * @throws \JTD\LaravelAI\Exceptions\ModelNotFoundException
     */
    public function setModel(string $modelId): self;

    /**
     * Get the currently configured model.
     *
     * @return string Current model identifier
     */
    public function getCurrentModel(): string;

    /**
     * Set request options (temperature, max_tokens, etc.).
     *
     * @param  array  $options  Request options
     * @return self For method chaining
     */
    public function setOptions(array $options): self;

    /**
     * Get current request options.
     *
     * @return array Current options
     */
    public function getOptions(): array;

    /**
     * Check if the provider supports a specific feature.
     *
     * @param  string  $feature  Feature name (streaming, function_calling, vision, etc.)
     * @return bool Whether the feature is supported
     */
    public function supportsFeature(string $feature): bool;

    /**
     * Get rate limit information for the current account.
     *
     * @return array Rate limit details (requests_per_minute, tokens_per_minute, etc.)
     */
    public function getRateLimits(): array;

    /**
     * Get current usage statistics for the account.
     *
     * @param  string  $period  Period for statistics (hour, day, month)
     * @return array Usage statistics
     */
    public function getUsageStats(string $period = 'day'): array;

    /**
     * Estimate tokens for a given input.
     *
     * Provides token count estimation before sending requests,
     * useful for cost calculation and request planning.
     *
     * @param  string|array  $input  Text or messages to estimate
     * @param  string|null  $modelId  Model to use for estimation
     * @return int Estimated token count
     */
    public function estimateTokens($input, ?string $modelId = null): int;

    /**
     * Get the provider name.
     *
     * @return string Provider name (openai, xai, gemini, etc.)
     */
    public function getName(): string;

    /**
     * Get the provider version or API version being used.
     *
     * @return string Provider/API version
     */
    public function getVersion(): string;

    /**
     * Synchronize models from the provider API to local cache/database.
     *
     * Fetches the latest model information from the provider's API and updates
     * the local cache with current models, capabilities, and pricing information.
     *
     * @param  bool  $forceRefresh  Force refresh even if recently synced
     * @return array Sync result with statistics (added, updated, removed counts)
     *
     * @throws \JTD\LaravelAI\Exceptions\ProviderException
     * @throws \JTD\LaravelAI\Exceptions\InvalidCredentialsException
     */
    public function syncModels(bool $forceRefresh = false): array;

    /**
     * Check if the provider has valid credentials configured.
     *
     * Performs a lightweight check to determine if the provider's credentials
     * are properly configured and valid for API access.
     *
     * @return bool True if credentials are valid and provider is accessible
     */
    public function hasValidCredentials(): bool;

    /**
     * Get the timestamp of the last successful model synchronization.
     *
     * @return \Carbon\Carbon|null Last sync time or null if never synced
     */
    public function getLastSyncTime(): ?\Carbon\Carbon;

    /**
     * Get models that can be synchronized from this provider.
     *
     * Returns a preview of models available for synchronization without
     * actually performing the sync operation. Useful for dry-run scenarios.
     *
     * @return array Array of syncable models with basic information
     *
     * @throws \JTD\LaravelAI\Exceptions\ProviderException
     */
    public function getSyncableModels(): array;
}
