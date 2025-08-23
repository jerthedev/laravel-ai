<?php

namespace JTD\LaravelAI\Enums;

/**
 * Billing model enumeration for different AI provider pricing structures.
 * 
 * This enum standardizes how different providers charge for their services,
 * enabling consistent cost calculation and budget management across providers.
 */
enum BillingModel: string
{
    case PAY_PER_USE = 'pay_per_use';
    case TIERED = 'tiered';
    case SUBSCRIPTION = 'subscription';
    case CREDITS = 'credits';
    case FREE_TIER = 'free_tier';
    case ENTERPRISE = 'enterprise';

    /**
     * Get human-readable label for the billing model.
     */
    public function label(): string
    {
        return match($this) {
            self::PAY_PER_USE => 'Pay Per Use',
            self::TIERED => 'Tiered Pricing',
            self::SUBSCRIPTION => 'Subscription',
            self::CREDITS => 'Credits',
            self::FREE_TIER => 'Free Tier',
            self::ENTERPRISE => 'Enterprise',
        };
    }

    /**
     * Get description of the billing model.
     */
    public function description(): string
    {
        return match($this) {
            self::PAY_PER_USE => 'Direct payment based on actual usage (tokens, requests, etc.)',
            self::TIERED => 'Different pricing tiers based on usage volume',
            self::SUBSCRIPTION => 'Fixed monthly/yearly subscription with usage limits',
            self::CREDITS => 'Pre-purchased credits that are consumed per use',
            self::FREE_TIER => 'Free usage with limitations (rate limits, quotas)',
            self::ENTERPRISE => 'Custom enterprise pricing with negotiated rates',
        };
    }

    /**
     * Check if this billing model supports automatic cost calculation.
     * 
     * Some models like enterprise or subscription may require manual configuration.
     */
    public function supportsAutomaticCalculation(): bool
    {
        return match($this) {
            self::PAY_PER_USE, self::TIERED, self::CREDITS => true,
            self::SUBSCRIPTION, self::FREE_TIER, self::ENTERPRISE => false,
        };
    }

    /**
     * Check if this billing model requires pre-payment.
     */
    public function requiresPrePayment(): bool
    {
        return match($this) {
            self::CREDITS, self::SUBSCRIPTION => true,
            self::PAY_PER_USE, self::TIERED, self::FREE_TIER, self::ENTERPRISE => false,
        };
    }

    /**
     * Check if this billing model has usage limits.
     */
    public function hasUsageLimits(): bool
    {
        return match($this) {
            self::SUBSCRIPTION, self::FREE_TIER, self::CREDITS => true,
            self::PAY_PER_USE, self::TIERED, self::ENTERPRISE => false,
        };
    }

    /**
     * Check if this billing model supports real-time cost tracking.
     */
    public function supportsRealTimeTracking(): bool
    {
        return match($this) {
            self::PAY_PER_USE, self::TIERED, self::CREDITS => true,
            self::SUBSCRIPTION, self::FREE_TIER, self::ENTERPRISE => false,
        };
    }

    /**
     * Get the cost calculation complexity level.
     * 
     * @return string 'simple'|'moderate'|'complex'
     */
    public function getCalculationComplexity(): string
    {
        return match($this) {
            self::PAY_PER_USE, self::FREE_TIER => 'simple',
            self::CREDITS, self::SUBSCRIPTION => 'moderate',
            self::TIERED, self::ENTERPRISE => 'complex',
        };
    }

    /**
     * Check if this billing model is suitable for budget enforcement.
     */
    public function supportsBudgetEnforcement(): bool
    {
        return match($this) {
            self::PAY_PER_USE, self::TIERED, self::CREDITS => true,
            self::SUBSCRIPTION, self::FREE_TIER, self::ENTERPRISE => false,
        };
    }

    /**
     * Get billing models that support automatic calculation.
     * 
     * @return array<self>
     */
    public static function getAutomaticCalculationModels(): array
    {
        return array_filter(
            self::cases(),
            fn(self $model) => $model->supportsAutomaticCalculation()
        );
    }

    /**
     * Get billing models that require pre-payment.
     * 
     * @return array<self>
     */
    public static function getPrePaymentModels(): array
    {
        return array_filter(
            self::cases(),
            fn(self $model) => $model->requiresPrePayment()
        );
    }

    /**
     * Get billing models that support real-time tracking.
     * 
     * @return array<self>
     */
    public static function getRealTimeTrackingModels(): array
    {
        return array_filter(
            self::cases(),
            fn(self $model) => $model->supportsRealTimeTracking()
        );
    }

    /**
     * Get billing models grouped by calculation complexity.
     * 
     * @return array<string, array<self>>
     */
    public static function getByComplexity(): array
    {
        $grouped = ['simple' => [], 'moderate' => [], 'complex' => []];
        
        foreach (self::cases() as $model) {
            $complexity = $model->getCalculationComplexity();
            $grouped[$complexity][] = $model;
        }
        
        return $grouped;
    }

    /**
     * Validate if a billing model is compatible with a pricing unit.
     * 
     * @param PricingUnit $unit The pricing unit to validate against
     * @return bool True if compatible, false otherwise
     */
    public function isCompatibleWith(PricingUnit $unit): bool
    {
        return match($this) {
            self::PAY_PER_USE, self::TIERED => true, // Compatible with all units
            self::CREDITS => $unit->isTokenBased() || $unit->isRequestBased(),
            self::SUBSCRIPTION => $unit->isTimeBased() || $unit->isRequestBased(),
            self::FREE_TIER => $unit->isRequestBased() || $unit->isTokenBased(),
            self::ENTERPRISE => true, // Custom compatibility
        };
    }

    /**
     * Get recommended pricing units for this billing model.
     * 
     * @return array<PricingUnit>
     */
    public function getRecommendedUnits(): array
    {
        return match($this) {
            self::PAY_PER_USE => [
                PricingUnit::PER_1K_TOKENS,
                PricingUnit::PER_REQUEST,
                PricingUnit::PER_IMAGE,
            ],
            self::TIERED => [
                PricingUnit::PER_1K_TOKENS,
                PricingUnit::PER_1M_TOKENS,
            ],
            self::CREDITS => [
                PricingUnit::PER_TOKEN,
                PricingUnit::PER_REQUEST,
            ],
            self::SUBSCRIPTION => [
                PricingUnit::PER_MINUTE,
                PricingUnit::PER_HOUR,
            ],
            self::FREE_TIER => [
                PricingUnit::PER_REQUEST,
                PricingUnit::PER_1K_TOKENS,
            ],
            self::ENTERPRISE => [], // Custom units
        };
    }
}
