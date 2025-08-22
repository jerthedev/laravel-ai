<?php

namespace JTD\LaravelAI\Exceptions\OpenAI;

/**
 * Exception thrown when OpenAI API quota is exceeded.
 *
 * This exception is thrown when the user has exceeded their usage quota
 * or billing limits on the OpenAI platform.
 */
class OpenAIQuotaExceededException extends OpenAIException
{
    /**
     * Current usage amount.
     */
    public ?float $currentUsage = null;

    /**
     * Usage limit.
     */
    public ?float $usageLimit = null;

    /**
     * Quota type (requests, tokens, dollars, etc.).
     */
    public ?string $quotaType = null;

    /**
     * Billing period (monthly, daily, etc.).
     */
    public ?string $billingPeriod = null;

    /**
     * Organization ID if applicable.
     */
    public ?string $organizationId = null;

    /**
     * Create a new OpenAI quota exceeded exception.
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
    public function __construct(
        string $message = 'OpenAI quota exceeded',
        ?float $currentUsage = null,
        ?float $usageLimit = null,
        ?string $quotaType = null,
        ?string $billingPeriod = null,
        ?string $organizationId = null,
        ?string $requestId = null,
        array $details = [],
        int $code = 429,
        ?\Exception $previous = null
    ) {
        parent::__construct($message, 'insufficient_quota', null, $requestId, $details, false, $code, $previous);

        $this->currentUsage = $currentUsage;
        $this->usageLimit = $usageLimit;
        $this->quotaType = $quotaType;
        $this->billingPeriod = $billingPeriod;
        $this->organizationId = $organizationId;
    }

    /**
     * Create exception from OpenAI quota error.
     *
     * @param  array  $errorData  Error data from OpenAI API
     * @param  string|null  $requestId  Request ID
     * @param  string|null  $organizationId  Organization ID
     * @return static
     */
    public static function fromApiError(
        array $errorData,
        ?string $requestId = null,
        ?string $organizationId = null
    ): static {
        $message = $errorData['message'] ?? 'OpenAI quota exceeded';
        
        // Extract quota information from error message if available
        $quotaInfo = static::parseQuotaInfo($message);

        return new static(
            $message,
            $quotaInfo['current_usage'] ?? null,
            $quotaInfo['usage_limit'] ?? null,
            $quotaInfo['quota_type'] ?? null,
            $quotaInfo['billing_period'] ?? null,
            $organizationId,
            $requestId,
            $errorData
        );
    }

    /**
     * Get current usage amount.
     */
    public function getCurrentUsage(): ?float
    {
        return $this->currentUsage;
    }

    /**
     * Get usage limit.
     */
    public function getUsageLimit(): ?float
    {
        return $this->usageLimit;
    }

    /**
     * Get quota type.
     */
    public function getQuotaType(): ?string
    {
        return $this->quotaType;
    }

    /**
     * Get billing period.
     */
    public function getBillingPeriod(): ?string
    {
        return $this->billingPeriod;
    }

    /**
     * Get organization ID.
     */
    public function getOrganizationId(): ?string
    {
        return $this->organizationId;
    }

    /**
     * Get usage percentage.
     */
    public function getUsagePercentage(): ?float
    {
        if ($this->currentUsage === null || $this->usageLimit === null || $this->usageLimit == 0) {
            return null;
        }

        return ($this->currentUsage / $this->usageLimit) * 100;
    }

    /**
     * Get remaining quota.
     */
    public function getRemainingQuota(): ?float
    {
        if ($this->currentUsage === null || $this->usageLimit === null) {
            return null;
        }

        return max(0, $this->usageLimit - $this->currentUsage);
    }

    /**
     * Parse quota information from error message.
     */
    protected static function parseQuotaInfo(string $message): array
    {
        $info = [];

        // Try to extract quota type
        if (preg_match('/\b(token|request|dollar|credit)s?\b/i', $message, $matches)) {
            $info['quota_type'] = strtolower($matches[1]);
        }

        // Try to extract billing period
        if (preg_match('/\b(monthly|daily|hourly)\b/i', $message, $matches)) {
            $info['billing_period'] = strtolower($matches[1]);
        }

        // Try to extract usage numbers
        if (preg_match('/(\d+(?:\.\d+)?)\s*\/\s*(\d+(?:\.\d+)?)/', $message, $matches)) {
            $info['current_usage'] = (float) $matches[1];
            $info['usage_limit'] = (float) $matches[2];
        }

        return $info;
    }

    /**
     * Get resolution suggestions.
     */
    public function getResolutionSuggestions(): array
    {
        $suggestions = [
            'Check your OpenAI account billing and usage dashboard',
            'Consider upgrading your OpenAI plan for higher limits',
        ];

        if ($this->quotaType === 'dollar' || str_contains(strtolower($this->getMessage()), 'billing')) {
            $suggestions[] = 'Add credits to your OpenAI account';
            $suggestions[] = 'Set up automatic billing to avoid interruptions';
        }

        if ($this->quotaType === 'token') {
            $suggestions[] = 'Optimize your prompts to use fewer tokens';
            $suggestions[] = 'Consider using a more efficient model';
        }

        if ($this->quotaType === 'request') {
            $suggestions[] = 'Implement request batching where possible';
            $suggestions[] = 'Add delays between requests to stay within limits';
        }

        $suggestions[] = 'Contact OpenAI support if you need higher limits';

        return $suggestions;
    }

    /**
     * Check if this is a billing-related quota error.
     */
    public function isBillingError(): bool
    {
        return $this->quotaType === 'dollar' ||
               str_contains(strtolower($this->getMessage()), 'billing') ||
               str_contains(strtolower($this->getMessage()), 'credit') ||
               str_contains(strtolower($this->getMessage()), 'payment');
    }

    /**
     * Check if this is a usage-based quota error.
     */
    public function isUsageError(): bool
    {
        return in_array($this->quotaType, ['token', 'request']) ||
               str_contains(strtolower($this->getMessage()), 'usage') ||
               str_contains(strtolower($this->getMessage()), 'limit');
    }
}
