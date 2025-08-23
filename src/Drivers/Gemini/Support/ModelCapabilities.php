<?php

namespace JTD\LaravelAI\Drivers\Gemini\Support;

/**
 * Gemini Model Capabilities and Metadata
 *
 * Centralized information about Gemini model capabilities,
 * context lengths, and feature support.
 */
class ModelCapabilities
{
    /**
     * Model context lengths (in tokens).
     */
    public static array $contextLengths = [
        // Current generation models (2025)
        'gemini-2.5-pro' => 2097152, // 2M tokens
        'gemini-2.5-flash' => 1048576, // 1M tokens
        'gemini-2.5-flash-lite' => 1048576, // 1M tokens
        'gemini-2.0-flash' => 1048576, // 1M tokens
        'gemini-2.0-flash-lite' => 1048576, // 1M tokens

        // Previous generation models
        'gemini-1.5-pro' => 2097152, // 2M tokens (updated)
        'gemini-1.5-flash' => 1048576, // 1M tokens
        'gemini-1.5-pro-exp-0801' => 2097152,
        'gemini-1.5-flash-exp-0827' => 1048576,

        // Legacy models
        'gemini-pro' => 30720,
        'gemini-pro-vision' => 12288,
        'gemini-1.0-pro' => 30720,
        'gemini-1.0-pro-vision' => 12288,
        'gemini-1.0-pro-001' => 30720,
        'gemini-1.0-pro-latest' => 30720,
        'gemini-1.0-pro-vision-latest' => 12288,
    ];

    /**
     * Model display names.
     */
    public static array $displayNames = [
        // Current generation models (2025)
        'gemini-2.5-pro' => 'Gemini 2.5 Pro',
        'gemini-2.5-flash' => 'Gemini 2.5 Flash',
        'gemini-2.5-flash-lite' => 'Gemini 2.5 Flash-Lite',
        'gemini-2.0-flash' => 'Gemini 2.0 Flash',
        'gemini-2.0-flash-lite' => 'Gemini 2.0 Flash-Lite',

        // Previous generation models
        'gemini-1.5-pro' => 'Gemini 1.5 Pro',
        'gemini-1.5-flash' => 'Gemini 1.5 Flash',
        'gemini-1.5-pro-exp-0801' => 'Gemini 1.5 Pro (Experimental)',
        'gemini-1.5-flash-exp-0827' => 'Gemini 1.5 Flash (Experimental)',

        // Legacy models
        'gemini-pro' => 'Gemini Pro',
        'gemini-pro-vision' => 'Gemini Pro Vision',
        'gemini-1.0-pro' => 'Gemini 1.0 Pro',
        'gemini-1.0-pro-vision' => 'Gemini 1.0 Pro Vision',
        'gemini-1.0-pro-001' => 'Gemini 1.0 Pro (001)',
        'gemini-1.0-pro-latest' => 'Gemini 1.0 Pro (Latest)',
        'gemini-1.0-pro-vision-latest' => 'Gemini 1.0 Pro Vision (Latest)',
    ];

    /**
     * Model descriptions.
     */
    public static array $descriptions = [
        'gemini-pro' => 'Best model for scaling across a wide range of tasks',
        'gemini-pro-vision' => 'Best model for multimodal tasks including images',
        'gemini-1.5-pro' => 'Mid-size multimodal model with improved performance and 1M token context',
        'gemini-1.5-flash' => 'Fast and versatile multimodal model with 1M token context',
        'gemini-1.5-pro-exp-0801' => 'Experimental version of Gemini 1.5 Pro with latest features',
        'gemini-1.5-flash-exp-0827' => 'Experimental version of Gemini 1.5 Flash with enhanced speed',
        'gemini-1.0-pro' => 'Stable version of Gemini Pro for production use',
        'gemini-1.0-pro-vision' => 'Stable version of Gemini Pro Vision for multimodal tasks',
        'gemini-1.0-pro-001' => 'Specific version of Gemini 1.0 Pro with consistent behavior',
        'gemini-1.0-pro-latest' => 'Latest stable version of Gemini 1.0 Pro',
        'gemini-1.0-pro-vision-latest' => 'Latest stable version of Gemini 1.0 Pro Vision',
    ];

    /**
     * Get capabilities for a specific model.
     */
    public static function getModelCapabilities(string $modelId): array
    {
        $capabilities = ['chat', 'text_generation'];

        // Add vision for supported models
        if (static::supportsVision($modelId)) {
            $capabilities[] = 'vision';
            $capabilities[] = 'multimodal';
        }

        // Add safety settings for all models
        $capabilities[] = 'safety_settings';

        // Add JSON mode for supported models
        if (static::supportsJsonMode($modelId)) {
            $capabilities[] = 'json_mode';
        }

        // Add code generation for supported models
        if (static::supportsCodeGeneration($modelId)) {
            $capabilities[] = 'code_generation';
        }

        return $capabilities;
    }

    /**
     * Check if model supports vision/multimodal input.
     */
    public static function supportsVision(string $modelId): bool
    {
        $visionModels = [
            'gemini-pro-vision',
            'gemini-1.5-pro',
            'gemini-1.5-flash',
            'gemini-1.5-pro-exp-0801',
            'gemini-1.5-flash-exp-0827',
            'gemini-1.0-pro-vision',
            'gemini-1.0-pro-vision-latest',
        ];

        return in_array($modelId, $visionModels) || str_contains($modelId, 'vision');
    }

    /**
     * Check if model supports JSON mode.
     */
    public static function supportsJsonMode(string $modelId): bool
    {
        // Most Gemini models support structured output
        $jsonModeModels = [
            'gemini-1.5-pro',
            'gemini-1.5-flash',
            'gemini-1.5-pro-exp-0801',
            'gemini-1.5-flash-exp-0827',
        ];

        return in_array($modelId, $jsonModeModels);
    }

    /**
     * Check if model supports code generation.
     */
    public static function supportsCodeGeneration(string $modelId): bool
    {
        // All Gemini models support code generation
        return true;
    }

    /**
     * Check if model supports safety settings.
     */
    public static function supportsSafetySettings(string $modelId): bool
    {
        // All Gemini models support safety settings
        return true;
    }

    /**
     * Check if model supports streaming.
     */
    public static function supportsStreaming(string $modelId): bool
    {
        // All current Gemini models support streaming
        return true;
    }

    /**
     * Check if model supports function calling.
     */
    public static function supportsFunctionCalling(string $modelId): bool
    {
        // Most Gemini models support function calling except some lite versions
        $nonFunctionCallingModels = [
            'gemini-2.0-flash-lite', // Explicitly mentioned as not supporting function calling
        ];

        return ! in_array($modelId, $nonFunctionCallingModels);
    }

    /**
     * Get context length for a model.
     */
    public static function getContextLength(string $modelId): int
    {
        return static::$contextLengths[$modelId] ?? 30720; // Default to Gemini Pro
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
        return static::$descriptions[$modelId] ?? 'Google Gemini model';
    }

    /**
     * Get all supported models.
     */
    public static function getAllModels(): array
    {
        return array_keys(static::$contextLengths);
    }

    /**
     * Get models that support vision.
     */
    public static function getVisionModels(): array
    {
        return array_filter(static::getAllModels(), function ($model) {
            return static::supportsVision($model);
        });
    }

    /**
     * Get models with large context windows (>100K tokens).
     */
    public static function getLargeContextModels(): array
    {
        return array_filter(static::getAllModels(), function ($model) {
            return static::getContextLength($model) > 100000;
        });
    }

    /**
     * Get the most capable model for a given task.
     */
    public static function getBestModelForTask(string $task): string
    {
        return match ($task) {
            'vision', 'multimodal' => 'gemini-1.5-pro',
            'speed', 'fast' => 'gemini-1.5-flash',
            'large_context', 'long_text' => 'gemini-1.5-pro',
            'code', 'programming' => 'gemini-1.5-pro',
            'general', 'chat' => 'gemini-pro',
            default => 'gemini-pro',
        };
    }

    /**
     * Get model family information.
     */
    public static function getModelFamily(string $modelId): array
    {
        if (str_starts_with($modelId, 'gemini-1.5')) {
            return [
                'family' => 'gemini-1.5',
                'generation' => '1.5',
                'release_date' => '2024-02-15',
                'features' => ['large_context', 'multimodal', 'improved_reasoning'],
            ];
        }

        if (str_starts_with($modelId, 'gemini-1.0') || str_starts_with($modelId, 'gemini-pro')) {
            return [
                'family' => 'gemini-1.0',
                'generation' => '1.0',
                'release_date' => '2023-12-06',
                'features' => ['chat', 'text_generation', 'safety_settings'],
            ];
        }

        return [
            'family' => 'unknown',
            'generation' => 'unknown',
            'release_date' => null,
            'features' => [],
        ];
    }

    /**
     * Check if model is experimental.
     */
    public static function isExperimental(string $modelId): bool
    {
        return str_contains($modelId, 'exp-') || str_contains($modelId, 'experimental');
    }

    /**
     * Check if model is deprecated.
     */
    public static function isDeprecated(string $modelId): bool
    {
        // No deprecated models yet, but this will be useful for future versions
        $deprecatedModels = [];

        return in_array($modelId, $deprecatedModels);
    }

    /**
     * Get recommended replacement for deprecated model.
     */
    public static function getRecommendedReplacement(string $modelId): ?string
    {
        $replacements = [
            // Future deprecation mappings will go here
        ];

        return $replacements[$modelId] ?? null;
    }

    /**
     * Get model performance characteristics.
     */
    public static function getPerformanceProfile(string $modelId): array
    {
        $profiles = [
            'gemini-pro' => [
                'speed' => 'medium',
                'quality' => 'high',
                'cost' => 'low',
                'context_efficiency' => 'medium',
            ],
            'gemini-pro-vision' => [
                'speed' => 'medium',
                'quality' => 'high',
                'cost' => 'low',
                'context_efficiency' => 'low',
            ],
            'gemini-1.5-pro' => [
                'speed' => 'medium',
                'quality' => 'very_high',
                'cost' => 'high',
                'context_efficiency' => 'very_high',
            ],
            'gemini-1.5-flash' => [
                'speed' => 'very_high',
                'quality' => 'high',
                'cost' => 'medium',
                'context_efficiency' => 'very_high',
            ],
        ];

        return $profiles[$modelId] ?? [
            'speed' => 'unknown',
            'quality' => 'unknown',
            'cost' => 'unknown',
            'context_efficiency' => 'unknown',
        ];
    }

    /**
     * Get safety categories supported by model.
     */
    public static function getSafetyCategories(string $modelId): array
    {
        // All Gemini models support the same safety categories
        return [
            'HARM_CATEGORY_HARASSMENT',
            'HARM_CATEGORY_HATE_SPEECH',
            'HARM_CATEGORY_SEXUALLY_EXPLICIT',
            'HARM_CATEGORY_DANGEROUS_CONTENT',
        ];
    }

    /**
     * Get supported MIME types for vision models.
     */
    public static function getSupportedImageTypes(string $modelId): array
    {
        if (! static::supportsVision($modelId)) {
            return [];
        }

        return [
            'image/png',
            'image/jpeg',
            'image/jpg',
            'image/webp',
            'image/heic',
            'image/heif',
        ];
    }
}
