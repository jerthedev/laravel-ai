<?php

namespace JTD\LaravelAI\Services;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Events\ProviderSwitched;
use JTD\LaravelAI\Exceptions\ProviderException;
use JTD\LaravelAI\Exceptions\ProviderSwitchException;
use JTD\LaravelAI\Models\AIConversation;
use JTD\LaravelAI\Models\AIProvider;
use JTD\LaravelAI\Models\AIProviderModel;

/**
 * Provider Switching Service
 *
 * Handles switching between AI providers within conversations while
 * preserving context and implementing fallback mechanisms.
 */
class ProviderSwitchingService
{
    protected DriverManager $driverManager;

    protected ConversationService $conversationService;

    protected ConversationContextManager $contextManager;

    public function __construct(
        DriverManager $driverManager,
        ConversationService $conversationService,
        ConversationContextManager $contextManager
    ) {
        $this->driverManager = $driverManager;
        $this->conversationService = $conversationService;
        $this->contextManager = $contextManager;
    }

    /**
     * Switch provider for a conversation.
     */
    public function switchProvider(
        AIConversation $conversation,
        string $newProviderName,
        ?string $newModelName = null,
        array $options = []
    ): AIConversation {
        $originalProvider = $conversation->provider_name;
        $originalModel = $conversation->model_name;

        try {
            // Validate new provider and model
            $newProvider = $this->validateProvider($newProviderName);
            $newModel = $this->validateModel($newProvider, $newModelName);

            // Preserve context if requested
            $preserveContext = $options['preserve_context'] ?? true;
            if ($preserveContext) {
                $this->preserveConversationContext($conversation, $newProvider, $newModel);
            }

            // Update conversation with new provider
            $conversation = $this->updateConversationProvider($conversation, $newProvider, $newModel);

            // Track provider switch
            $this->trackProviderSwitch($conversation, $originalProvider, $originalModel, $options);

            // Fire provider switched event
            Event::dispatch(new ProviderSwitched(
                $conversation,
                $originalProvider,
                $newProviderName,
                $originalModel,
                $newModelName,
                $options
            ));

            Log::info('Provider switched successfully', [
                'conversation_id' => $conversation->id,
                'from_provider' => $originalProvider,
                'to_provider' => $newProviderName,
                'from_model' => $originalModel,
                'to_model' => $newModelName,
            ]);

            return $conversation;
        } catch (\Exception $e) {
            Log::error('Provider switch failed', [
                'conversation_id' => $conversation->id,
                'from_provider' => $originalProvider,
                'to_provider' => $newProviderName,
                'error' => $e->getMessage(),
            ]);

            throw new ProviderSwitchException(
                "Failed to switch provider from {$originalProvider} to {$newProviderName}: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Switch provider with automatic fallback on failure.
     */
    public function switchWithFallback(
        AIConversation $conversation,
        array $providerPriority,
        array $options = []
    ): AIConversation {
        $lastException = null;

        foreach ($providerPriority as $providerConfig) {
            $providerName = is_string($providerConfig) ? $providerConfig : $providerConfig['provider'];
            $modelName = is_array($providerConfig) ? ($providerConfig['model'] ?? null) : null;

            try {
                return $this->switchProvider($conversation, $providerName, $modelName, $options);
            } catch (\Exception $e) {
                $lastException = $e;
                Log::warning('Provider switch attempt failed, trying next fallback', [
                    'conversation_id' => $conversation->id,
                    'attempted_provider' => $providerName,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }
        }

        throw new ProviderSwitchException(
            'All provider fallback attempts failed. Last error: ' . ($lastException?->getMessage() ?? 'Unknown error'),
            0,
            $lastException
        );
    }

    /**
     * Execute automatic fallback using the fallback service.
     */
    public function executeAutoFallback(
        AIConversation $conversation,
        \Exception $originalException,
        array $options = []
    ): AIConversation {
        // This method will be implemented when we inject the ProviderFallbackService
        // For now, use the existing switchWithFallback logic
        $fallbackProviders = $this->getAvailableProviders($conversation);

        if (empty($fallbackProviders)) {
            throw new ProviderSwitchException(
                'No fallback providers available for conversation ' . $conversation->id,
                0,
                $originalException
            );
        }

        return $this->switchWithFallback($conversation, $fallbackProviders, $options);
    }

    /**
     * Get available providers for switching.
     */
    public function getAvailableProviders(AIConversation $conversation): array
    {
        $currentProvider = $conversation->provider_name;

        return AIProvider::where('status', 'active')
            ->where('name', '!=', $currentProvider)
            ->with(['models' => function ($query) {
                $query->where('status', 'active');
            }])
            ->get()
            ->map(function ($provider) {
                return [
                    'name' => $provider->name,
                    'display_name' => $provider->display_name,
                    'models' => $provider->models->map(function ($model) {
                        return [
                            'name' => $model->name,
                            'display_name' => $model->display_name,
                            'capabilities' => $model->capabilities,
                            'context_window' => $model->context_window,
                        ];
                    })->toArray(),
                ];
            })
            ->toArray();
    }

    /**
     * Validate provider exists and is active.
     */
    protected function validateProvider(string $providerName): AIProvider
    {
        $provider = AIProvider::where('name', $providerName)
            ->where('status', 'active')
            ->first();

        if (! $provider) {
            throw new ProviderException("Provider '{$providerName}' not found or inactive");
        }

        // Test provider connection
        try {
            $driver = $this->driverManager->driver($providerName);
            $validation = $driver->validateCredentials();
            if (! $validation['valid']) {
                throw new ProviderException("Provider '{$providerName}' validation failed: " . implode(', ', $validation['errors'] ?? []));
            }
        } catch (\Exception $e) {
            throw new ProviderException("Provider '{$providerName}' is not available: " . $e->getMessage());
        }

        return $provider;
    }

    /**
     * Validate model exists and is compatible.
     */
    protected function validateModel(AIProvider $provider, ?string $modelName = null): AIProviderModel
    {
        if (! $modelName) {
            // Get default model for provider
            $model = $provider->models()
                ->where('status', 'active')
                ->where('is_default', true)
                ->first();

            if (! $model) {
                // Fallback to first active model
                $model = $provider->models()
                    ->where('status', 'active')
                    ->first();
            }
        } else {
            $model = $provider->models()
                ->where('name', $modelName)
                ->where('status', 'active')
                ->first();
        }

        if (! $model) {
            throw new ProviderException(
                "No suitable model found for provider '{$provider->name}'" .
                ($modelName ? " with model '{$modelName}'" : '')
            );
        }

        return $model;
    }

    /**
     * Preserve conversation context when switching providers.
     */
    protected function preserveConversationContext(
        AIConversation $conversation,
        AIProvider $newProvider,
        AIProviderModel $newModel
    ): void {
        $contextResult = $this->contextManager->preserveContextForSwitch(
            $conversation,
            $newModel,
            [
                'preservation_strategy' => 'intelligent_truncation',
                'context_ratio' => 0.8,
                'include_system' => true,
            ]
        );

        // Store context preservation metadata
        $metadata = $conversation->metadata ?? [];
        $metadata['last_context_preservation'] = [
            'timestamp' => now()->toISOString(),
            'strategy' => $contextResult['preservation_strategy'],
            'original_messages' => $contextResult['original_count'],
            'preserved_messages' => $contextResult['preserved_count'],
            'total_tokens' => $contextResult['total_tokens'],
            'truncated' => $contextResult['truncated'],
            'target_model' => $newModel->name,
            'context_window' => $newModel->context_window,
        ];

        $conversation->update(['metadata' => $metadata]);

        Log::info('Context preserved for provider switch', [
            'conversation_id' => $conversation->id,
            'strategy' => $contextResult['preservation_strategy'],
            'original_messages' => $contextResult['original_count'],
            'preserved_messages' => $contextResult['preserved_count'],
            'total_tokens' => $contextResult['total_tokens'],
            'truncated' => $contextResult['truncated'],
            'new_model_context_window' => $newModel->context_window,
        ]);
    }

    /**
     * Update conversation with new provider information.
     */
    protected function updateConversationProvider(
        AIConversation $conversation,
        AIProvider $provider,
        AIProviderModel $model
    ): AIConversation {
        $conversation->update([
            'ai_provider_id' => $provider->id,
            'ai_provider_model_id' => $model->id,
            'provider_name' => $provider->name,
            'model_name' => $model->name,
            'last_activity_at' => now(),
        ]);

        return $conversation->fresh();
    }

    /**
     * Track provider switch in conversation metadata.
     */
    protected function trackProviderSwitch(
        AIConversation $conversation,
        ?string $originalProvider,
        ?string $originalModel,
        array $options
    ): void {
        $metadata = $conversation->metadata ?? [];

        if (! isset($metadata['provider_switches'])) {
            $metadata['provider_switches'] = [];
        }

        $metadata['provider_switches'][] = [
            'timestamp' => now()->toISOString(),
            'from_provider' => $originalProvider,
            'to_provider' => $conversation->provider_name,
            'from_model' => $originalModel,
            'to_model' => $conversation->model_name,
            'reason' => $options['reason'] ?? 'manual',
            'preserve_context' => $options['preserve_context'] ?? true,
        ];

        $conversation->update(['metadata' => $metadata]);
    }
}
