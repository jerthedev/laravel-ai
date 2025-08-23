<?php

namespace JTD\LaravelAI\Exceptions\XAI;

/**
 * Exception thrown when xAI quota is exceeded.
 */
class XAIQuotaExceededException extends XAIException
{
    protected string $quotaType;

    protected ?int $currentUsage;

    public function __construct(
        string $message = 'Quota exceeded',
        string $quotaType = 'requests',
        ?int $currentUsage = null,
        array $details = [],
        int $code = 403,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 'quota_exceeded', $details, false, $code, $previous);

        $this->quotaType = $quotaType;
        $this->currentUsage = $currentUsage;
    }

    public function getQuotaType(): string
    {
        return $this->quotaType;
    }

    public function getCurrentUsage(): ?int
    {
        return $this->currentUsage;
    }
}
