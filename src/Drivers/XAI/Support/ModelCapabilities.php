<?php

namespace JTD\LaravelAI\Drivers\XAI\Support;

/**
 * xAI Model Capabilities and Metadata
 *
 * Centralized information about xAI Grok model capabilities,
 * context lengths, and feature support.
 */
class ModelCapabilities
{
    /**
     * Model context lengths (in tokens).
     */
    public static $contextLengths = [
        'grok-beta' => 131072,
        'grok-2' => 131072,
        'grok-2-mini' => 131072,
        'grok-2-1212' => 131072,
        'grok-2-vision-1212' => 131072,
    ];

    /**
     * Model display names.
     */
    public static $displayNames = [
        'grok-beta' => 'Grok Beta',
        'grok-2' => 'Grok 2',
        'grok-2-mini' => 'Grok 2 Mini',
        'grok-2-1212' => 'Grok 2 (December 2024)',
        'grok-2-vision-1212' => 'Grok 2 Vision (December 2024)',
    ];

    /**
     * Model descriptions.
     */
    public static $descriptions = [
        'grok-beta' => 'xAI\'s flagship conversational AI model (beta version) with advanced reasoning capabilities',
        'grok-2' => 'Advanced reasoning and conversation model with improved performance and reliability',
        'grok-2-mini' => 'Smaller, faster version of Grok 2 optimized for speed and cost-effectiveness',
        'grok-2-1212' => 'Latest version of Grok 2 with enhanced capabilities and December 2024 improvements',
        'grok-2-vision-1212' => 'Grok 2 with vision capabilities for image understanding and multimodal tasks',
    ];

    /**
     * Get capabilities for a specific model.
     */
    public static function getModelCapabilities(string $modelId): array
    {
        $baseCapabilities = [
            'chat' => true,
            'function_calling' => true,
            'streaming' => true,
            'json_mode' => true,
            'system_messages' => true,
            'temperature_control' => true,
            'max_tokens_control' => true,
            'stop_sequences' => true,
            'presence_penalty' => true,
            'frequency_penalty' => true,
        ];

        return match ($modelId) {
            'grok-2-vision-1212' => array_merge($baseCapabilities, [
                'vision' => true,
                'image_understanding' => true,
                'multimodal' => true,
            ]),
            'grok-beta', 'grok-2', 'grok-2-mini', 'grok-2-1212' => $baseCapabilities,
            default => $baseCapabilities,
        };
    }

    /**
     * Check if model supports function calling.
     */
    public static function supportsFunctionCalling(string $modelId): bool
    {
        // All current xAI models support function calling
        return in_array($modelId, [
            'grok-beta',
            'grok-2',
            'grok-2-mini',
            'grok-2-1212',
            'grok-2-vision-1212',
        ]);
    }

    /**
     * Check if model supports vision.
     */
    public static function supportsVision(string $modelId): bool
    {
        return in_array($modelId, [
            'grok-2-vision-1212',
        ]);
    }

    /**
     * Check if model supports JSON mode.
     */
    public static function supportsJsonMode(string $modelId): bool
    {
        // All current xAI models support JSON mode
        return in_array($modelId, [
            'grok-beta',
            'grok-2',
            'grok-2-mini',
            'grok-2-1212',
            'grok-2-vision-1212',
        ]);
    }

    /**
     * Check if model is a chat model.
     */
    public static function isChatModel(string $modelId): bool
    {
        // All current xAI models are chat models
        return in_array($modelId, [
            'grok-beta',
            'grok-2',
            'grok-2-mini',
            'grok-2-1212',
            'grok-2-vision-1212',
        ]);
    }

    /**
     * Get context length for a model.
     */
    public static function getContextLength(string $modelId): int
    {
        return self::$contextLengths[$modelId] ?? 131072; // Default for Grok models
    }

    /**
     * Get display name for a model.
     */
    public static function getDisplayName(string $modelId): string
    {
        return self::$displayNames[$modelId] ?? ucfirst(str_replace(['-', '_'], ' ', $modelId));
    }

    /**
     * Get description for a model.
     */
    public static function getDescription(string $modelId): string
    {
        return self::$descriptions[$modelId] ?? "xAI model: {$modelId}";
    }

    /**
     * Get comprehensive model information.
     */
    public static function getModelInfo(string $modelId): array
    {
        return [
            'id' => $modelId,
            'name' => self::getDisplayName($modelId),
            'description' => self::getDescription($modelId),
            'provider' => 'xai',
            'type' => 'chat',
            'context_length' => self::getContextLength($modelId),
            'capabilities' => self::getModelCapabilities($modelId),
            'tier' => self::getModelTier($modelId),
            'use_cases' => self::getModelUseCases($modelId),
            'performance' => self::getModelPerformance($modelId),
        ];
    }

    /**
     * Compare two models for sorting.
     */
    public static function compareModels(array $a, array $b): int
    {
        // Sort by tier first (premium > standard > economy)
        $tierOrder = ['premium' => 3, 'standard' => 2, 'economy' => 1];
        $aTier = $tierOrder[$a['tier'] ?? 'standard'] ?? 2;
        $bTier = $tierOrder[$b['tier'] ?? 'standard'] ?? 2;

        if ($aTier !== $bTier) {
            return $bTier <=> $aTier; // Descending order (premium first)
        }

        // Then sort by context length (larger first)
        $aContext = $a['context_length'] ?? 0;
        $bContext = $b['context_length'] ?? 0;

        if ($aContext !== $bContext) {
            return $bContext <=> $aContext;
        }

        // Finally sort by name
        return strcmp($a['name'] ?? '', $b['name'] ?? '');
    }

    /**
     * Get model tier (economy, standard, premium).
     */
    public static function getModelTier(string $modelId): string
    {
        return match ($modelId) {
            'grok-2-mini' => 'economy',
            'grok-2', 'grok-2-1212' => 'standard',
            'grok-beta', 'grok-2-vision-1212' => 'premium',
            default => 'standard',
        };
    }

    /**
     * Get recommended use cases for a model.
     */
    public static function getModelUseCases(string $modelId): array
    {
        return match ($modelId) {
            'grok-2-mini' => [
                'Simple chat and Q&A',
                'Content generation',
                'Basic reasoning tasks',
                'High-volume applications',
            ],
            'grok-2' => [
                'Complex reasoning',
                'Analysis and research',
                'Function calling',
                'General-purpose AI tasks',
            ],
            'grok-2-1212' => [
                'Advanced reasoning',
                'Complex problem solving',
                'Research and analysis',
                'Professional applications',
            ],
            'grok-beta' => [
                'Cutting-edge AI capabilities',
                'Research and experimentation',
                'Advanced reasoning tasks',
                'Beta testing new features',
            ],
            'grok-2-vision-1212' => [
                'Image analysis and understanding',
                'Multimodal tasks',
                'Visual reasoning',
                'Document processing with images',
            ],
            default => [
                'General AI tasks',
                'Chat and conversation',
                'Text processing',
            ],
        };
    }

    /**
     * Get model performance characteristics.
     */
    public static function getModelPerformance(string $modelId): array
    {
        return match ($modelId) {
            'grok-2-mini' => [
                'speed' => 'fast',
                'cost' => 'low',
                'quality' => 'good',
                'reasoning' => 'basic',
            ],
            'grok-2' => [
                'speed' => 'medium',
                'cost' => 'medium',
                'quality' => 'high',
                'reasoning' => 'advanced',
            ],
            'grok-2-1212' => [
                'speed' => 'medium',
                'cost' => 'medium',
                'quality' => 'high',
                'reasoning' => 'advanced',
            ],
            'grok-beta' => [
                'speed' => 'medium',
                'cost' => 'high',
                'quality' => 'highest',
                'reasoning' => 'cutting-edge',
            ],
            'grok-2-vision-1212' => [
                'speed' => 'medium',
                'cost' => 'high',
                'quality' => 'high',
                'reasoning' => 'advanced',
                'vision' => 'excellent',
            ],
            default => [
                'speed' => 'medium',
                'cost' => 'medium',
                'quality' => 'good',
                'reasoning' => 'good',
            ],
        };
    }

    /**
     * Get all supported models.
     */
    public static function getAllModels(): array
    {
        return array_keys(self::$contextLengths);
    }

    /**
     * Check if model exists.
     */
    public static function modelExists(string $modelId): bool
    {
        return isset(self::$contextLengths[$modelId]);
    }

    /**
     * Get models by capability.
     */
    public static function getModelsByCapability(string $capability): array
    {
        $models = [];

        foreach (self::getAllModels() as $modelId) {
            $capabilities = self::getModelCapabilities($modelId);
            if ($capabilities[$capability] ?? false) {
                $models[] = $modelId;
            }
        }

        return $models;
    }

    /**
     * Get models by tier.
     */
    public static function getModelsByTier(string $tier): array
    {
        $models = [];

        foreach (self::getAllModels() as $modelId) {
            if (self::getModelTier($modelId) === $tier) {
                $models[] = $modelId;
            }
        }

        return $models;
    }
}
