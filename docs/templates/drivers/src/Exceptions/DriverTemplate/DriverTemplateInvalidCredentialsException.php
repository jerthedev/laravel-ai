<?php

namespace JTD\LaravelAI\Exceptions\DriverTemplate;

use JTD\LaravelAI\Exceptions\InvalidCredentialsException;

/**
 * Exception thrown when DriverTemplate API credentials are invalid or expired.
 *
 * This exception extends the general InvalidCredentialsException and adds
 * DriverTemplate-specific credential validation and error handling.
 */
class DriverTemplateInvalidCredentialsException extends InvalidCredentialsException
{
    /**
     * OpenAI request ID for debugging.
     */
    public $requestId = null;

    /**
     * OpenAI error type from API response.
     */
    public $openaiErrorType = null;

    /**
     * Organization ID if applicable.
     */
    public $organizationId = null;

    /**
     * Project ID if applicable.
     */
    public $projectId = null;

    /**
     * Create a new DriverTemplate invalid credentials exception.
     *
     * @param  string  $message  Exception message
     * @param  string|null  $account  Account identifier
     * @param  string|null  $requestId  Request ID
     * @param  string|null  $drivertemplateErrorType  DriverTemplate error type
     * @param  string|null  $organizationId  Organization ID
     * @param  string|null  $projectId  Project ID
     * @param  array  $details  Additional error details
     * @param  int  $code  Exception code
     * @param  \Exception|null  $previous  Previous exception
     */
    public function __construct(string $message = 'Invalid OpenAI API credentials', string $account = null, string $requestId = null, string $openaiErrorType = null, string $organizationId = null, string $projectId = null, array $details = [], int $code = 401, Exception $previous = null)
    {
        // TODO: Implement __construct
    }

    /**
     * Create exception from DriverTemplate authentication error.
     *
     * @param  array  $errorData  Error data from DriverTemplate API
     * @param  string|null  $requestId  Request ID
     * @param  string|null  $organizationId  Organization ID
     * @param  string|null  $projectId  Project ID
     */
    public static function fromApiError(array $errorData, string $requestId = null, string $organizationId = null, string $projectId = null): static
    {
        // TODO: Implement fromApiError
    }

    /**
     * Get the DriverTemplate request ID.
     */
    public function getRequestId(): string
    {
        // TODO: Implement getRequestId
    }

    /**
     * Get the DriverTemplate error type.
     */
    public function getOpenAIErrorType(): string
    {
        // TODO: Implement getOpenAIErrorType
    }

    /**
     * Get the organization ID.
     */
    public function getOrganizationId(): string
    {
        // TODO: Implement getOrganizationId
    }

    /**
     * Get the project ID.
     */
    public function getProjectId(): string
    {
        // TODO: Implement getProjectId
    }

    /**
     * Enhance error message with specific guidance.
     */
    protected static function enhanceErrorMessage(string $message, string $errorType, string $organizationId, string $projectId): string
    {
        // TODO: Implement enhanceErrorMessage
    }

    /**
     * Get troubleshooting steps for this credential error.
     */
    public function getTroubleshootingSteps(): array
    {
        // TODO: Implement getTroubleshootingSteps
    }

    /**
     * Check if this is a quota-related error.
     */
    public function isQuotaError(): bool
    {
        // TODO: Implement isQuotaError
    }

    /**
     * Check if this is an organization/project access error.
     */
    public function isAccessError(): bool
    {
        // TODO: Implement isAccessError
    }

}
