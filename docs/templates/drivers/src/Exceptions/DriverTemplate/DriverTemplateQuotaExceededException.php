<?php

namespace JTD\LaravelAI\Exceptions\DriverTemplate;

/**
 * Exception thrown when DriverTemplate API quota is exceeded.
 *
 * This exception is thrown when the user has exceeded their usage quota
 * or billing limits on the DriverTemplate platform.
 */
class DriverTemplateQuotaExceededException extends DriverTemplateException
{
    /**
     * Current usage amount.
     */
    public $currentUsage = null;

    /**
     * Usage limit.
     */
    public $usageLimit = null;

    /**
     * Quota type (requests, tokens, dollars, etc.).
     */
    public $quotaType = null;

    /**
     * Billing period (monthly, daily, etc.).
     */
    public $billingPeriod = null;

    /**
     * Organization ID if applicable.
     */
    public $organizationId = null;

    /**
     * Create a new DriverTemplate quota exceeded exception.
     *
     * @param  string  $message  Exception message
     * @param  float|null  $currentUsage  Current usage amount
     * @param  float|null  $usageLimit  Usage limit
     * @param  string|null  $quotaType  Type of quota
     * @param  string|null  $billingPeriod  Billing period
     * @param  string|null  $organizationId  Organization ID
     * @param  string|null  $requestId  Request ID
     * @param  array  $details  Additional error details
     * @param  int  $code  Exception code
     * @param  \Exception|null  $previous  Previous exception
     */
    public function __construct(string $message = 'OpenAI quota exceeded', ?float $currentUsage = null, ?float $usageLimit = null, ?string $quotaType = null, ?string $billingPeriod = null, ?string $organizationId = null, ?string $requestId = null, array $details = [], int $code = 429, ?Exception $previous = null)
    {
        // TODO: Implement __construct
    }

    /**
     * Create exception from DriverTemplate quota error.
     *
     * @param  array  $errorData  Error data from DriverTemplate API
     * @param  string|null  $requestId  Request ID
     * @param  string|null  $organizationId  Organization ID
     */
    public static function fromApiError(array $errorData, ?string $requestId = null, ?string $organizationId = null): static
    {
        // TODO: Implement fromApiError
    }

    /**
     * Get current usage amount.
     */
    public function getCurrentUsage(): float
    {
        // TODO: Implement getCurrentUsage
    }

    /**
     * Get usage limit.
     */
    public function getUsageLimit(): float
    {
        // TODO: Implement getUsageLimit
    }

    /**
     * Get quota type.
     */
    public function getQuotaType(): string
    {
        // TODO: Implement getQuotaType
    }

    /**
     * Get billing period.
     */
    public function getBillingPeriod(): string
    {
        // TODO: Implement getBillingPeriod
    }

    /**
     * Get organization ID.
     */
    public function getOrganizationId(): string
    {
        // TODO: Implement getOrganizationId
    }

    /**
     * Get usage percentage.
     */
    public function getUsagePercentage(): float
    {
        // TODO: Implement getUsagePercentage
    }

    /**
     * Get remaining quota.
     */
    public function getRemainingQuota(): float
    {
        // TODO: Implement getRemainingQuota
    }

    /**
     * Parse quota information from error message.
     */
    protected static function parseQuotaInfo(string $message): array
    {
        // TODO: Implement parseQuotaInfo
    }

    /**
     * Get resolution suggestions.
     */
    public function getResolutionSuggestions(): array
    {
        // TODO: Implement getResolutionSuggestions
    }

    /**
     * Check if this is a billing-related quota error.
     */
    public function isBillingError(): bool
    {
        // TODO: Implement isBillingError
    }

    /**
     * Check if this is a usage-based quota error.
     */
    public function isUsageError(): bool
    {
        // TODO: Implement isUsageError
    }
}
