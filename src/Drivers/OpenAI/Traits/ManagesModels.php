<?php

namespace JTD\LaravelAI\Drivers\OpenAI\Traits;

use JTD\LaravelAI\Drivers\OpenAI\Support\ModelCapabilities;
use JTD\LaravelAI\Drivers\OpenAI\Support\ModelPricing;
use JTD\LaravelAI\Models\AIMessage;

/**
 * Manages OpenAI Models and Capabilities
 *
 * Handles model listing, capabilities, token estimation,
 * and model-specific functionality.
 */
trait ManagesModels
{
    /**
     * Cached models list.
     */
    protected ?array $cachedModels = null;

    /**
     * Get available models from OpenAI.
     */
    public function getAvailableModels(bool $forceRefresh = false): array
    {
        if (!$forceRefresh && $this->cachedModels !== null) {
            return $this->cachedModels;
        }

        try {
            $this->cachedModels = $this->doGetAvailableModels();
            return $this->cachedModels;
        } catch (\Exception $e) {
            $this->handleApiError($e);
        }
    }

    /**
     * Actually get available models from the provider.
     */
    protected function doGetAvailableModels(): array
    {
        $response = $this->executeWithRetry(function () {
            return $this->client->models()->list();
        });

        $models = [];
        foreach ($response->data as $model) {
            // Only include chat models
            if (ModelCapabilities::isChatModel($model->id)) {
                $models[] = [
                    'id' => $model->id,
                    'name' => ModelCapabilities::getDisplayName($model->id),
                    'description' => ModelCapabilities::getDescription($model->id),
                    'context_length' => ModelCapabilities::getContextLength($model->id),
                    'capabilities' => ModelCapabilities::getModelCapabilities($model->id),
                    'pricing' => ModelPricing::getModelPricing($model->id),
                    'created' => $model->created ?? null,
                    'owned_by' => $model->ownedBy ?? 'openai',
                ];
            }
        }

        // Sort models by preference
        usort($models, [ModelCapabilities::class, 'compareModels']);

        return $models;
    }

    /**
     * Get detailed information about a specific model.
     */
    public function getModelInfo(string $modelId): array
    {
        // For backward compatibility with existing tests
        throw new \BadMethodCallException('Method not yet implemented');
    }

    /**
     * Get provider-specific capabilities.
     */
    public function getCapabilities(): array
    {
        return [
            'streaming' => true,
            'function_calling' => true,
            'vision' => str_contains($this->getCurrentModel(), 'gpt-4o'),
            'json_mode' => true,
            'system_messages' => true,
            'conversation_history' => true,
            'temperature_control' => true,
            'max_tokens_control' => true,
            'top_p_control' => true,
            'frequency_penalty' => true,
            'presence_penalty' => true,
        ];
    }

    /**
     * Get the default model for this provider.
     */
    public function getDefaultModel(): string
    {
        return $this->config['default_model'] ?? $this->defaultModel;
    }

    /**
     * Set the model to use for requests.
     */
    public function setModel(string $modelId): self
    {
        $this->config['default_model'] = $modelId;
        return $this;
    }

    /**
     * Get the currently configured model.
     */
    public function getCurrentModel(): string
    {
        return $this->config['default_model'] ?? $this->defaultModel;
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
     * Estimate tokens for input.
     */
    public function estimateTokens($input, ?string $modelId = null): int
    {
        if ($input instanceof AIMessage) {
            return $this->estimateMessageTokens($input);
        }

        if (is_array($input)) {
            $totalTokens = 0;
            foreach ($input as $message) {
                $totalTokens += $this->estimateMessageTokens($message);
            }
            return $totalTokens;
        }

        if (is_string($input)) {
            return $this->estimateStringTokens($input);
        }

        throw new \InvalidArgumentException('Input must be a string, AIMessage, or array of AIMessages');
    }

    /**
     * Estimate tokens for a single message.
     */
    protected function estimateMessageTokens(AIMessage $message): int
    {
        $tokens = $this->estimateStringTokens($message->content);

        // Add overhead for message structure
        $tokens += 4; // Role and message wrapper tokens

        // Add tokens for function calls if present
        if ($message->functionCalls) {
            $tokens += $this->estimateStringTokens(json_encode($message->functionCalls));
        }

        if ($message->toolCalls) {
            $tokens += $this->estimateStringTokens(json_encode($message->toolCalls));
        }

        return $tokens;
    }

    /**
     * Estimate tokens for a string using a simple approximation.
     */
    protected function estimateStringTokens(string $text): int
    {
        // Simple approximation: ~4 characters per token for English text
        // This is a rough estimate and may not be perfectly accurate
        return (int) ceil(strlen($text) / 4);
    }

    /**
     * Estimate response tokens based on input and model.
     */
    protected function estimateResponseTokens($input, string $modelId): int
    {
        $inputTokens = $this->estimateTokens($input, $modelId);
        return $this->estimateResponseTokensFromCount($inputTokens, $modelId);
    }

    /**
     * Estimate response tokens from input token count.
     */
    protected function estimateResponseTokensFromCount(int $inputTokens, string $modelId): int
    {
        // Estimate response length based on input length and model characteristics
        if (str_contains($modelId, 'gpt-4')) {
            // GPT-4 tends to give more detailed responses
            return (int) ($inputTokens * 0.6);
        } elseif (str_contains($modelId, 'gpt-3.5')) {
            // GPT-3.5 gives more concise responses
            return (int) ($inputTokens * 0.4);
        }

        // Default estimation
        return (int) ($inputTokens * 0.5);
    }

    /**
     * Check if model is a chat model.
     */
    protected function isChatModel(string $modelId): bool
    {
        return ModelCapabilities::isChatModel($modelId);
    }

    /**
     * Get model display name.
     */
    protected function getModelDisplayName(string $modelId): string
    {
        return ModelCapabilities::getDisplayName($modelId);
    }

    /**
     * Get model description.
     */
    protected function getModelDescription(string $modelId): string
    {
        return ModelCapabilities::getDescription($modelId);
    }

    /**
     * Get model context length.
     */
    protected function getModelContextLength(string $modelId): int
    {
        return ModelCapabilities::getContextLength($modelId);
    }

    /**
     * Get model capabilities.
     */
    protected function getModelCapabilities(string $modelId): array
    {
        return ModelCapabilities::getModelCapabilities($modelId);
    }

    /**
     * Compare models for sorting.
     */
    protected function compareModels(array $a, array $b): int
    {
        return ModelCapabilities::compareModels($a, $b);
    }

    /**
     * Get rate limits for the current configuration.
     */
    public function getRateLimits(): array
    {
        // For backward compatibility with existing tests
        throw new \BadMethodCallException('Method not yet implemented');
    }

    /**
     * Get the API version being used.
     */
    public function getVersion(): string
    {
        return 'v1'; // OpenAI API version
    }

    /**
     * Set options for the driver.
     */
    public function setOptions(array $options): self
    {
        $this->config = array_merge($this->config, $options);
        return $this;
    }

    /**
     * Get current driver options.
     */
    public function getOptions(): array
    {
        return $this->config;
    }

    /**
     * Clear the models cache.
     */
    public function clearModelsCache(): void
    {
        $this->cachedModels = null;
    }

    /**
     * Refresh the models cache.
     */
    public function refreshModelsCache(): array
    {
        return $this->getAvailableModels(true);
    }
}
