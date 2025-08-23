<?php

namespace JTD\LaravelAI\Contracts;

use JTD\LaravelAI\Enums\PricingUnit;

/**
 * Interface for AI provider pricing implementations.
 * 
 * This contract ensures consistent pricing behavior across all AI providers,
 * enabling centralized cost calculation and budget management.
 */
interface PricingInterface
{
    /**
     * Get pricing information for a specific model.
     * 
     * @param string $model The model identifier
     * @return array Pricing data including rates, units, and billing model
     * 
     * Example return format:
     * [
     *     'input' => 0.0025,
     *     'output' => 0.01,
     *     'unit' => PricingUnit::PER_1K_TOKENS,
     *     'currency' => 'USD',
     *     'billing_model' => BillingModel::PAY_PER_USE,
     *     'effective_date' => '2025-01-01',
     * ]
     */
    public function getModelPricing(string $model): array;

    /**
     * Calculate cost based on usage metrics.
     * 
     * @param string $model The model identifier
     * @param array $usage Usage metrics (tokens, requests, time, etc.)
     * @return float The calculated cost in the pricing currency
     * 
     * Usage array examples:
     * - Token-based: ['input_tokens' => 1000, 'output_tokens' => 500]
     * - Request-based: ['requests' => 10]
     * - Time-based: ['minutes' => 5]
     * - Image-based: ['images' => 3, 'size' => '1024x1024']
     */
    public function calculateCost(string $model, array $usage): float;

    /**
     * Get pricing information for all models supported by this provider.
     * 
     * @return array Associative array with model names as keys and pricing data as values
     */
    public function getAllModelPricing(): array;

    /**
     * Get the pricing units supported by this provider.
     * 
     * @return array<PricingUnit> Array of supported pricing units
     */
    public function getSupportedUnits(): array;

    /**
     * Validate the pricing configuration for this provider.
     * 
     * @return array Array of validation errors (empty if valid)
     * 
     * Example errors:
     * [
     *     "Model 'gpt-4o' missing required field: unit",
     *     "Model 'dall-e-3' unit must be PricingUnit enum",
     * ]
     */
    public function validatePricing(): array;

    /**
     * Get the default currency used by this provider.
     * 
     * @return string Currency code (e.g., 'USD', 'EUR')
     */
    public function getDefaultCurrency(): string;

    /**
     * Check if a model supports a specific pricing unit.
     * 
     * @param string $model The model identifier
     * @param PricingUnit $unit The pricing unit to check
     * @return bool True if the model supports the unit, false otherwise
     */
    public function supportsUnit(string $model, PricingUnit $unit): bool;

    /**
     * Get the effective date for pricing information.
     * 
     * @param string $model The model identifier
     * @return string|null The effective date in Y-m-d format, or null if not available
     */
    public function getEffectiveDate(string $model): ?string;

    /**
     * Calculate cost breakdown with detailed information.
     * 
     * @param string $model The model identifier
     * @param array $usage Usage metrics
     * @return array Detailed cost breakdown
     * 
     * Example return format:
     * [
     *     'model' => 'gpt-4o',
     *     'input_tokens' => 1000,
     *     'output_tokens' => 500,
     *     'input_cost' => 0.0025,
     *     'output_cost' => 0.005,
     *     'total_cost' => 0.0075,
     *     'currency' => 'USD',
     *     'unit' => '1k_tokens',
     *     'billing_model' => 'pay_per_use',
     * ]
     */
    public function calculateDetailedCost(string $model, array $usage): array;

    /**
     * Get pricing tiers if the provider uses tiered pricing.
     * 
     * @param string $model The model identifier
     * @return array Pricing tiers or empty array if not applicable
     * 
     * Example return format:
     * [
     *     ['min' => 0, 'max' => 1000000, 'rate' => 0.002],
     *     ['min' => 1000001, 'max' => 10000000, 'rate' => 0.0015],
     *     ['min' => 10000001, 'max' => null, 'rate' => 0.001],
     * ]
     */
    public function getPricingTiers(string $model): array;

    /**
     * Check if pricing data is current and up-to-date.
     * 
     * @return bool True if pricing is current, false if it may be outdated
     */
    public function isPricingCurrent(): bool;

    /**
     * Get the last update timestamp for pricing data.
     * 
     * @return \DateTimeInterface|null The last update time, or null if unknown
     */
    public function getLastUpdated(): ?\DateTimeInterface;

    /**
     * Estimate cost for a given input before making the actual request.
     * 
     * @param string $model The model identifier
     * @param string $input The input text or content
     * @param array $options Additional options (max_tokens, etc.)
     * @return float Estimated cost
     */
    public function estimateCost(string $model, string $input, array $options = []): float;

    /**
     * Get minimum billable unit for a model.
     * 
     * Some providers have minimum charges (e.g., minimum 1000 tokens).
     * 
     * @param string $model The model identifier
     * @return int|null Minimum billable amount, or null if no minimum
     */
    public function getMinimumBillableUnit(string $model): ?int;
}
