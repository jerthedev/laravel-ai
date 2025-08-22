# Driver Interface Specification

## Overview

All AI provider drivers must implement the comprehensive `AIProviderInterface`. This interface ensures consistent behavior across all providers while allowing for provider-specific optimizations.

## Complete Interface

```php
<?php

namespace JTD\LaravelAI\Contracts;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Models\TokenUsage;

interface AIProviderInterface
{
    /**
     * Send a single message to the AI provider.
     *
     * @param  AIMessage  $message  The message to send
     * @param  array  $options  Additional options (model, temperature, etc.)
     * @return AIResponse The AI response
     *
     * @throws \JTD\LaravelAI\Exceptions\ProviderException
     * @throws \JTD\LaravelAI\Exceptions\InvalidCredentialsException
     * @throws \JTD\LaravelAI\Exceptions\RateLimitException
     */
    public function sendMessage(AIMessage $message, array $options = []): AIResponse;

    /**
     * Send multiple messages in a conversation.
     *
     * @param  array  $messages  Array of AIMessage objects
     * @param  array  $options  Additional options
     * @return AIResponse The AI response
     */
    public function sendMessages(array $messages, array $options = []): AIResponse;

    /**
     * Send streaming message with real-time response chunks.
     *
     * @param  AIMessage  $message  The message to send
     * @param  array  $options  Additional options
     * @return \Generator Generator yielding AIResponse chunks
     */
    public function sendStreamingMessage(AIMessage $message, array $options = []): \Generator;

    /**
     * Send streaming conversation with real-time response chunks.
     *
     * @param  array  $messages  Array of AIMessage objects
     * @param  array  $options  Additional options
     * @return \Generator Generator yielding AIResponse chunks
     */
    public function sendStreamingMessages(array $messages, array $options = []): \Generator;

    /**
     * Get available models from the provider.
     *
     * @param  bool  $refresh  Force refresh from API
     * @return Collection Collection of available models
     */
    public function getAvailableModels(bool $refresh = false): Collection;

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
    public function getLastSyncTime(): ?Carbon;

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

    /**
     * Calculate cost for token usage.
     *
     * @param  TokenUsage  $usage  Token usage information
     * @param  string  $modelId  Model identifier
     * @return float Cost in USD
     */
    public function calculateCost(TokenUsage $usage, string $modelId): float;

    /**
     * Validate provider credentials and configuration.
     *
     * @return array Validation result with status and details
     */
    public function validateCredentials(): array;

    /**
     * Get provider capabilities and features.
     *
     * @return array Array of capabilities
     */
    public function getCapabilities(): array;

    /**
     * Get provider configuration (with sensitive data masked).
     *
     * @return array Masked configuration array
     */
    public function getConfig(): array;

    /**
     * Get provider name/identifier.
     *
     * @return string Provider name
     */
    public function getName(): string;

    /**
     * Check if provider supports streaming responses.
     *
     * @return bool True if streaming is supported
     */
    public function supportsStreaming(): bool;

    /**
     * Check if provider supports function calling.
     *
     * @return bool True if function calling is supported
     */
    public function supportsFunctionCalling(): bool;

    /**
     * Check if provider supports vision/image inputs.
     *
     * @return bool True if vision is supported
     */
    public function supportsVision(): bool;

    /**
     * Get the provider version or API version being used.
     *
     * @return string Provider/API version
     */
    public function getVersion(): string;
}
```

## Method Categories

### Core Messaging Methods

#### `sendMessage(AIMessage $message, array $options = []): AIResponse`
- **Purpose**: Send a single message to the AI provider
- **Parameters**: 
  - `$message`: The message to send
  - `$options`: Additional options (model, temperature, max_tokens, etc.)
- **Returns**: Complete AI response
- **Exceptions**: ProviderException, InvalidCredentialsException, RateLimitException

#### `sendMessages(array $messages, array $options = []): AIResponse`
- **Purpose**: Send multiple messages in a conversation context
- **Parameters**:
  - `$messages`: Array of AIMessage objects
  - `$options`: Additional options
- **Returns**: AI response considering full conversation context

### Streaming Methods

#### `sendStreamingMessage(AIMessage $message, array $options = []): \Generator`
- **Purpose**: Send message with real-time streaming response
- **Returns**: Generator yielding AIResponse chunks as they arrive
- **Use Case**: Real-time chat interfaces, progressive content generation

#### `sendStreamingMessages(array $messages, array $options = []): \Generator`
- **Purpose**: Stream response for conversation context
- **Returns**: Generator yielding response chunks

### Model Management Methods

#### `getAvailableModels(bool $refresh = false): Collection`
- **Purpose**: Get list of available models from provider
- **Parameters**: `$refresh` - Force refresh from API vs cache
- **Returns**: Collection of model information

#### `syncModels(bool $forceRefresh = false): array`
- **Purpose**: Synchronize models from API to local cache/database
- **Returns**: Sync statistics and status information
- **Use Case**: Automated model discovery and caching

#### `hasValidCredentials(): bool`
- **Purpose**: Quick check if provider credentials are valid
- **Returns**: Boolean indicating credential validity
- **Use Case**: Health checks, provider discovery

#### `getLastSyncTime(): ?Carbon`
- **Purpose**: Get timestamp of last successful model sync
- **Returns**: Carbon timestamp or null if never synced

#### `getSyncableModels(): array`
- **Purpose**: Preview models available for sync without actually syncing
- **Returns**: Array of basic model information
- **Use Case**: Dry-run operations, sync planning

### Cost & Analytics Methods

#### `calculateCost(TokenUsage $usage, string $modelId): float`
- **Purpose**: Calculate cost for token usage
- **Parameters**:
  - `$usage`: Token usage information
  - `$modelId`: Specific model identifier
- **Returns**: Cost in USD

### Health & Validation Methods

#### `validateCredentials(): array`
- **Purpose**: Comprehensive credential validation
- **Returns**: Detailed validation results with status and error information

#### `getCapabilities(): array`
- **Purpose**: Get provider capabilities and feature support
- **Returns**: Array of supported features (streaming, function_calling, vision, etc.)

### Configuration Methods

#### `getConfig(): array`
- **Purpose**: Get provider configuration with sensitive data masked
- **Returns**: Safe configuration array for debugging/logging

#### `getName(): string`
- **Purpose**: Get provider identifier
- **Returns**: Provider name (e.g., 'openai', 'gemini', 'xai')

### Feature Detection Methods

#### `supportsStreaming(): bool`
- **Purpose**: Check if provider supports real-time streaming
- **Returns**: Boolean indicating streaming support

#### `supportsFunctionCalling(): bool`
- **Purpose**: Check if provider supports AI function calling
- **Returns**: Boolean indicating function calling support

#### `supportsVision(): bool`
- **Purpose**: Check if provider supports image/vision inputs
- **Returns**: Boolean indicating vision support

#### `getVersion(): string`
- **Purpose**: Get provider or API version information
- **Returns**: Version string

## Implementation Requirements

### Error Handling
All methods must properly handle and map provider-specific errors to the package's exception hierarchy.

### Logging
All operations should include appropriate logging for monitoring and debugging.

### Configuration
All drivers must read configuration from Laravel's config system and validate required parameters.

### Events
Major operations should fire appropriate events for monitoring and integration.

### Caching
Model information and other expensive operations should be cached appropriately.

## Related Documentation

- **[Sync System Overview](04-Sync-System.md)**: Understanding model synchronization
- **[OpenAI Driver](07-OpenAI-Driver.md)**: Reference implementation
- **[Creating Custom Drivers](09-Custom-Drivers.md)**: Implementing the interface
- **[Error Handling](11-Error-Handling.md)**: Exception handling patterns
