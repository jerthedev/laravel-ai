<?php

namespace JTD\LaravelAI\Drivers\OpenAI\Support;

/**
 * OpenAI Model Capabilities and Metadata
 *
 * Centralized information about OpenAI model capabilities,
 * context lengths, and feature support.
 */
class ModelCapabilities
{
    /**
     * Model context lengths (in tokens).
     */
    public static array $contextLengths = [
        'gpt-3.5-turbo' => 4096,
        'gpt-3.5-turbo-16k' => 16384,
        'gpt-3.5-turbo-0125' => 16385,
        'gpt-3.5-turbo-1106' => 16385,
        'gpt-4' => 8192,
        'gpt-4-32k' => 32768,
        'gpt-4-turbo' => 128000,
        'gpt-4-turbo-preview' => 128000,
        'gpt-4-1106-preview' => 128000,
        'gpt-4-0125-preview' => 128000,
        'gpt-4o' => 128000,
        'gpt-4o-2024-05-13' => 128000,
        'gpt-4o-2024-08-06' => 128000,
        'gpt-4o-mini' => 128000,
        'gpt-4o-mini-2024-07-18' => 128000,
        'gpt-5' => 200000, // Estimated
        'gpt-5-2025-08-07' => 200000, // Estimated
    ];

    /**
     * Model display names.
     */
    public static array $displayNames = [
        'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
        'gpt-3.5-turbo-16k' => 'GPT-3.5 Turbo 16K',
        'gpt-3.5-turbo-0125' => 'GPT-3.5 Turbo (0125)',
        'gpt-3.5-turbo-1106' => 'GPT-3.5 Turbo (1106)',
        'gpt-4' => 'GPT-4',
        'gpt-4-32k' => 'GPT-4 32K',
        'gpt-4-turbo' => 'GPT-4 Turbo',
        'gpt-4-turbo-preview' => 'GPT-4 Turbo Preview',
        'gpt-4-1106-preview' => 'GPT-4 Turbo (1106)',
        'gpt-4-0125-preview' => 'GPT-4 Turbo (0125)',
        'gpt-4o' => 'GPT-4o',
        'gpt-4o-2024-05-13' => 'GPT-4o (2024-05-13)',
        'gpt-4o-2024-08-06' => 'GPT-4o (2024-08-06)',
        'gpt-4o-mini' => 'GPT-4o Mini',
        'gpt-4o-mini-2024-07-18' => 'GPT-4o Mini (2024-07-18)',
        'gpt-5' => 'GPT-5',
        'gpt-5-2025-08-07' => 'GPT-5 (2025-08-07)',
    ];

    /**
     * Model descriptions.
     */
    public static array $descriptions = [
        'gpt-3.5-turbo' => 'Fast, cost-effective model for simple tasks',
        'gpt-3.5-turbo-16k' => 'GPT-3.5 Turbo with 16K context window',
        'gpt-3.5-turbo-0125' => 'Latest GPT-3.5 Turbo with improved accuracy',
        'gpt-3.5-turbo-1106' => 'GPT-3.5 Turbo with function calling improvements',
        'gpt-4' => 'More capable model for complex reasoning tasks',
        'gpt-4-32k' => 'GPT-4 with extended 32K context window',
        'gpt-4-turbo' => 'Latest GPT-4 with improved speed and capabilities',
        'gpt-4-turbo-preview' => 'Preview of GPT-4 Turbo with latest features',
        'gpt-4-1106-preview' => 'GPT-4 Turbo with vision and improved function calling',
        'gpt-4-0125-preview' => 'GPT-4 Turbo with reduced laziness and improved task completion',
        'gpt-4o' => 'GPT-4 Omni with multimodal capabilities',
        'gpt-4o-2024-05-13' => 'GPT-4o with enhanced reasoning and multimodal support',
        'gpt-4o-2024-08-06' => 'Latest GPT-4o with improved performance and cost efficiency',
        'gpt-4o-mini' => 'Smaller, faster version of GPT-4o',
        'gpt-4o-mini-2024-07-18' => 'GPT-4o Mini with optimized performance',
        'gpt-5' => 'Next-generation model with advanced reasoning capabilities',
        'gpt-5-2025-08-07' => 'GPT-5 with enhanced reasoning and new API features',
    ];

    /**
     * Get capabilities for a specific model.
     */
    public static function getModelCapabilities(string $modelId): array
    {
        $capabilities = ['chat', 'text_generation'];

        // Add function calling for supported models
        if (static::supportsFunctionCalling($modelId)) {
            $capabilities[] = 'function_calling';
        }

        // Add vision for supported models
        if (static::supportsVision($modelId)) {
            $capabilities[] = 'vision';
        }

        // Add JSON mode for supported models
        if (static::supportsJsonMode($modelId)) {
            $capabilities[] = 'json_mode';
        }

        // Add streaming for all chat models
        if (static::isChatModel($modelId)) {
            $capabilities[] = 'streaming';
        }

        return $capabilities;
    }

    /**
     * Check if model supports function calling.
     */
    public static function supportsFunctionCalling(string $modelId): bool
    {
        return str_contains($modelId, 'gpt-3.5-turbo') || 
               str_contains($modelId, 'gpt-4') || 
               str_contains($modelId, 'gpt-5');
    }

    /**
     * Check if model supports vision.
     */
    public static function supportsVision(string $modelId): bool
    {
        return str_contains($modelId, 'gpt-4o') || 
               str_contains($modelId, 'gpt-4-turbo') ||
               str_contains($modelId, 'gpt-5');
    }

    /**
     * Check if model supports JSON mode.
     */
    public static function supportsJsonMode(string $modelId): bool
    {
        return str_contains($modelId, 'gpt-3.5-turbo-1106') ||
               str_contains($modelId, 'gpt-3.5-turbo-0125') ||
               str_contains($modelId, 'gpt-4-turbo') ||
               str_contains($modelId, 'gpt-4o') ||
               str_contains($modelId, 'gpt-5');
    }

    /**
     * Check if model is a chat model.
     */
    public static function isChatModel(string $modelId): bool
    {
        return str_contains($modelId, 'gpt-');
    }

    /**
     * Get context length for a model.
     */
    public static function getContextLength(string $modelId): int
    {
        // Try exact match first
        if (isset(static::$contextLengths[$modelId])) {
            return static::$contextLengths[$modelId];
        }

        // Try to find a base model match
        foreach (static::$contextLengths as $model => $length) {
            if (str_starts_with($modelId, $model)) {
                return $length;
            }
        }

        // Default fallback
        return 4096;
    }

    /**
     * Get display name for a model.
     */
    public static function getDisplayName(string $modelId): string
    {
        return static::$displayNames[$modelId] ?? ucfirst(str_replace('-', ' ', $modelId));
    }

    /**
     * Get description for a model.
     */
    public static function getDescription(string $modelId): string
    {
        return static::$descriptions[$modelId] ?? 'OpenAI language model';
    }

    /**
     * Get comprehensive model information.
     */
    public static function getModelInfo(string $modelId): array
    {
        return [
            'id' => $modelId,
            'name' => static::getDisplayName($modelId),
            'description' => static::getDescription($modelId),
            'context_length' => static::getContextLength($modelId),
            'capabilities' => static::getModelCapabilities($modelId),
            'supports_function_calling' => static::supportsFunctionCalling($modelId),
            'supports_vision' => static::supportsVision($modelId),
            'supports_json_mode' => static::supportsJsonMode($modelId),
            'is_chat_model' => static::isChatModel($modelId),
        ];
    }

    /**
     * Compare two models for sorting.
     */
    public static function compareModels(array $a, array $b): int
    {
        // Sort by model family first (GPT-4 > GPT-3.5)
        $familyOrder = ['gpt-5' => 3, 'gpt-4' => 2, 'gpt-3.5' => 1];
        
        $aFamily = 1;
        $bFamily = 1;
        
        foreach ($familyOrder as $family => $order) {
            if (str_contains($a['id'], $family)) {
                $aFamily = $order;
            }
            if (str_contains($b['id'], $family)) {
                $bFamily = $order;
            }
        }
        
        if ($aFamily !== $bFamily) {
            return $bFamily <=> $aFamily; // Higher family first
        }
        
        // Then by context length (larger first)
        $aContext = static::getContextLength($a['id']);
        $bContext = static::getContextLength($b['id']);
        
        if ($aContext !== $bContext) {
            return $bContext <=> $aContext;
        }
        
        // Finally by name
        return strcmp($a['id'], $b['id']);
    }
}
