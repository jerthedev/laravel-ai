<?php

namespace JTD\LaravelAI\Services;

use JTD\LaravelAI\Models\AIConversation;
use JTD\LaravelAI\Models\AIProviderModel;

/**
 * Context Configuration Service
 *
 * Manages context window configurations, defaults, and provider-specific settings.
 */
class ContextConfigurationService
{
    /**
     * Get default context configuration.
     */
    public function getDefaultConfiguration(): array
    {
        return [
            'window_size' => config('ai.conversation.default_context_window', 4096),
            'preservation_strategy' => config('ai.conversation.default_preservation_strategy', 'intelligent_truncation'),
            'context_ratio' => config('ai.conversation.default_context_ratio', 0.8),
            'search_enhanced' => config('ai.conversation.search_enhanced_context', true),
            'cache_ttl' => config('ai.conversation.context_cache_ttl', 300),
            'max_search_results' => config('ai.conversation.max_search_results', 10),
            'relevance_threshold' => config('ai.conversation.relevance_threshold', 0.7),
        ];
    }

    /**
     * Get context configuration for a specific conversation.
     */
    public function getConfigurationForConversation(AIConversation $conversation): array
    {
        $defaults = $this->getDefaultConfiguration();
        $conversationSettings = $conversation->getContextConfiguration();

        // Merge with provider-specific settings if available
        if ($conversation->currentModel) {
            $providerSettings = $this->getProviderSpecificConfiguration($conversation->currentModel);
            $defaults = array_merge($defaults, $providerSettings);
        }

        return array_merge($defaults, array_filter($conversationSettings, function ($value) {
            return $value !== null;
        }));
    }

    /**
     * Get provider-specific context configuration.
     */
    public function getProviderSpecificConfiguration(AIProviderModel $model): array
    {
        $config = [];

        // Set window size based on model capabilities
        if ($model->context_length) {
            $config['window_size'] = $model->context_length;
        } elseif ($model->context_window) {
            $config['window_size'] = $model->context_window;
        }

        // Provider-specific optimizations
        $providerName = strtolower($model->provider->name ?? '');

        switch ($providerName) {
            case 'openai':
                $config['context_ratio'] = 0.85; // OpenAI handles context well
                break;

            case 'gemini':
                $config['context_ratio'] = 0.8; // Conservative for Gemini
                $config['preservation_strategy'] = 'recent_messages'; // Gemini prefers recent context
                break;

            case 'xai':
                $config['context_ratio'] = 0.75; // More conservative for xAI
                break;
        }

        return $config;
    }

    /**
     * Get available preservation strategies.
     */
    public function getAvailableStrategies(): array
    {
        return [
            'recent_messages' => [
                'name' => 'Recent Messages',
                'description' => 'Preserve the most recent messages in the conversation',
                'best_for' => 'Ongoing conversations where recent context is most important',
            ],
            'important_messages' => [
                'name' => 'Important Messages',
                'description' => 'Preserve messages based on role priority (system > user > assistant)',
                'best_for' => 'Conversations with important system instructions',
            ],
            'summarized_context' => [
                'name' => 'Summarized Context',
                'description' => 'Create summaries of older messages while preserving recent ones',
                'best_for' => 'Long conversations where historical context matters',
            ],
            'intelligent_truncation' => [
                'name' => 'Intelligent Truncation',
                'description' => 'Combine multiple strategies for optimal context preservation',
                'best_for' => 'Most conversations - balances all factors',
            ],
            'search_enhanced_truncation' => [
                'name' => 'Search-Enhanced Truncation',
                'description' => 'Use conversation search to find and preserve relevant historical messages',
                'best_for' => 'Conversations where users reference previous topics',
            ],
        ];
    }

    /**
     * Validate context configuration.
     */
    public function validateConfiguration(array $config): array
    {
        $errors = [];

        // Validate window size
        if (isset($config['window_size'])) {
            if (! is_int($config['window_size']) || $config['window_size'] < 100) {
                $errors['window_size'] = 'Window size must be an integer >= 100';
            } elseif ($config['window_size'] > 200000) {
                $errors['window_size'] = 'Window size cannot exceed 200,000 tokens';
            }
        }

        // Validate context ratio
        if (isset($config['context_ratio'])) {
            if (! is_numeric($config['context_ratio']) || $config['context_ratio'] < 0.1 || $config['context_ratio'] > 1.0) {
                $errors['context_ratio'] = 'Context ratio must be between 0.1 and 1.0';
            }
        }

        // Validate preservation strategy
        if (isset($config['preservation_strategy'])) {
            $validStrategies = array_keys($this->getAvailableStrategies());
            if (! in_array($config['preservation_strategy'], $validStrategies)) {
                $errors['preservation_strategy'] = 'Invalid preservation strategy';
            }
        }

        // Validate cache TTL
        if (isset($config['cache_ttl'])) {
            if (! is_int($config['cache_ttl']) || $config['cache_ttl'] < 60) {
                $errors['cache_ttl'] = 'Cache TTL must be an integer >= 60 seconds';
            }
        }

        // Validate relevance threshold
        if (isset($config['relevance_threshold'])) {
            if (! is_numeric($config['relevance_threshold']) || $config['relevance_threshold'] < 0 || $config['relevance_threshold'] > 1) {
                $errors['relevance_threshold'] = 'Relevance threshold must be between 0 and 1';
            }
        }

        return $errors;
    }

    /**
     * Apply configuration to conversation.
     */
    public function applyConfiguration(AIConversation $conversation, array $config): bool
    {
        $errors = $this->validateConfiguration($config);

        if (! empty($errors)) {
            return false;
        }

        $conversation->updateContextConfiguration($config);

        return true;
    }

    /**
     * Reset conversation to default configuration.
     */
    public function resetToDefaults(AIConversation $conversation): void
    {
        $defaults = $this->getDefaultConfiguration();

        // Add provider-specific settings if available
        if ($conversation->currentModel) {
            $providerSettings = $this->getProviderSpecificConfiguration($conversation->currentModel);
            $defaults = array_merge($defaults, $providerSettings);
        }

        $conversation->updateContextConfiguration($defaults);
    }

    /**
     * Get recommended configuration for conversation type.
     */
    public function getRecommendedConfiguration(string $conversationType): array
    {
        $base = $this->getDefaultConfiguration();

        switch ($conversationType) {
            case 'chat':
                return array_merge($base, [
                    'preservation_strategy' => 'recent_messages',
                    'context_ratio' => 0.8,
                    'search_enhanced' => true,
                ]);

            case 'analysis':
                return array_merge($base, [
                    'preservation_strategy' => 'important_messages',
                    'context_ratio' => 0.9,
                    'search_enhanced' => false,
                ]);

            case 'coding':
                return array_merge($base, [
                    'preservation_strategy' => 'intelligent_truncation',
                    'context_ratio' => 0.85,
                    'search_enhanced' => true,
                ]);

            case 'creative':
                return array_merge($base, [
                    'preservation_strategy' => 'summarized_context',
                    'context_ratio' => 0.75,
                    'search_enhanced' => false,
                ]);

            default:
                return $base;
        }
    }
}
