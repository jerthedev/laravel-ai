<?php

namespace JTD\LaravelAI\Drivers\DriverTemplate\Support;

/**
 * DriverTemplate Model Capabilities and Metadata
 *
 * Centralized information about DriverTemplate model capabilities,
 * context lengths, and feature support.
 */
class ModelCapabilities
{
    /**
     * Model context lengths (in tokens).
     */
    public static $contextLengths = [];

    /**
     * Model display names.
     */
    public static $displayNames = [];

    /**
     * Model descriptions.
     */
    public static $descriptions = [];

    /**
     * Get capabilities for a specific model.
     */
    public static function getModelCapabilities(string $modelId): array
    {
        // TODO: Implement getModelCapabilities
    }

    /**
     * Check if model supports function calling.
     */
    public static function supportsFunctionCalling(string $modelId): bool
    {
        // TODO: Implement supportsFunctionCalling
    }

    /**
     * Check if model supports vision.
     */
    public static function supportsVision(string $modelId): bool
    {
        // TODO: Implement supportsVision
    }

    /**
     * Check if model supports JSON mode.
     */
    public static function supportsJsonMode(string $modelId): bool
    {
        // TODO: Implement supportsJsonMode
    }

    /**
     * Check if model is a chat model.
     */
    public static function isChatModel(string $modelId): bool
    {
        // TODO: Implement isChatModel
    }

    /**
     * Get context length for a model.
     */
    public static function getContextLength(string $modelId): int
    {
        // TODO: Implement getContextLength
    }

    /**
     * Get display name for a model.
     */
    public static function getDisplayName(string $modelId): string
    {
        // TODO: Implement getDisplayName
    }

    /**
     * Get description for a model.
     */
    public static function getDescription(string $modelId): string
    {
        // TODO: Implement getDescription
    }

    /**
     * Get comprehensive model information.
     */
    public static function getModelInfo(string $modelId): array
    {
        // TODO: Implement getModelInfo
    }

    /**
     * Compare two models for sorting.
     */
    public static function compareModels(array $a, array $b): int
    {
        // TODO: Implement compareModels
    }
}
