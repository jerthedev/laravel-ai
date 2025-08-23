<?php

namespace JTD\LaravelAI\Exceptions\OpenAI;

use JTD\LaravelAI\Exceptions\InvalidCredentialsException;

/**
 * Exception thrown when OpenAI API credentials are invalid or expired.
 *
 * This exception extends the general InvalidCredentialsException and adds
 * OpenAI-specific credential validation and error handling.
 */
class OpenAIInvalidCredentialsException extends InvalidCredentialsException
{
    /**
     * OpenAI request ID for debugging.
     */
    public ?string $requestId = null;

    /**
     * OpenAI error type from API response.
     */
    public ?string $openaiErrorType = null;

    /**
     * Organization ID if applicable.
     */
    public ?string $organizationId = null;

    /**
     * Project ID if applicable.
     */
    public ?string $projectId = null;

    /**
     * Create a new OpenAI invalid credentials exception.
     *
     * @param  string  $message  Exception message
     * @param  string|null  $account  Account identifier
     * @param  string|null  $requestId  Request ID
     * @param  string|null  $openaiErrorType  OpenAI error type
     * @param  string|null  $organizationId  Organization ID
     * @param  string|null  $projectId  Project ID
     * @param  array  $details  Additional error details
     * @param  int  $code  Exception code
     * @param  \Exception|null  $previous  Previous exception
     */
    public function __construct(
        string $message = 'Invalid OpenAI API credentials',
        ?string $account = null,
        ?string $requestId = null,
        ?string $openaiErrorType = null,
        ?string $organizationId = null,
        ?string $projectId = null,
        array $details = [],
        int $code = 401,
        ?\Exception $previous = null
    ) {
        parent::__construct($message, 'openai', $account, $details, $code, $previous);

        $this->requestId = $requestId;
        $this->openaiErrorType = $openaiErrorType;
        $this->organizationId = $organizationId;
        $this->projectId = $projectId;
    }

    /**
     * Create exception from OpenAI authentication error.
     *
     * @param  array  $errorData  Error data from OpenAI API
     * @param  string|null  $requestId  Request ID
     * @param  string|null  $organizationId  Organization ID
     * @param  string|null  $projectId  Project ID
     */
    public static function fromApiError(
        array $errorData,
        ?string $requestId = null,
        ?string $organizationId = null,
        ?string $projectId = null
    ): static {
        $message = $errorData['message'] ?? 'Invalid OpenAI API credentials';
        $type = $errorData['type'] ?? null;

        // Enhance message based on error type
        $enhancedMessage = static::enhanceErrorMessage($message, $type, $organizationId, $projectId);

        return new static(
            $enhancedMessage,
            $organizationId ?? $projectId,
            $requestId,
            $type,
            $organizationId,
            $projectId,
            $errorData
        );
    }

    /**
     * Get the OpenAI request ID.
     */
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    /**
     * Get the OpenAI error type.
     */
    public function getOpenAIErrorType(): ?string
    {
        return $this->openaiErrorType;
    }

    /**
     * Get the organization ID.
     */
    public function getOrganizationId(): ?string
    {
        return $this->organizationId;
    }

    /**
     * Get the project ID.
     */
    public function getProjectId(): ?string
    {
        return $this->projectId;
    }

    /**
     * Enhance error message with specific guidance.
     */
    protected static function enhanceErrorMessage(
        string $message,
        ?string $errorType,
        ?string $organizationId,
        ?string $projectId
    ): string {
        $suggestions = [];

        switch ($errorType) {
            case 'invalid_api_key':
                $suggestions[] = 'Verify your API key is correct and active';
                $suggestions[] = 'Check if the API key has been revoked or expired';
                break;

            case 'insufficient_quota':
                $suggestions[] = 'Check your OpenAI account billing and usage limits';
                $suggestions[] = 'Upgrade your plan or add credits to your account';
                break;

            case 'invalid_organization':
                if ($organizationId) {
                    $suggestions[] = "Verify organization ID '{$organizationId}' is correct";
                }
                $suggestions[] = 'Check if you have access to the specified organization';
                break;

            case 'invalid_project':
                if ($projectId) {
                    $suggestions[] = "Verify project ID '{$projectId}' is correct";
                }
                $suggestions[] = 'Check if you have access to the specified project';
                break;
        }

        if (! empty($suggestions)) {
            $message .= '. Suggestions: ' . implode('; ', $suggestions);
        }

        return $message;
    }

    /**
     * Get troubleshooting steps for this credential error.
     */
    public function getTroubleshootingSteps(): array
    {
        $steps = [
            'Verify your OpenAI API key is correct',
            'Check your OpenAI account status and billing',
            'Ensure you have sufficient credits or quota',
        ];

        if ($this->organizationId) {
            $steps[] = "Verify organization ID '{$this->organizationId}' is correct";
            $steps[] = 'Check if you have access to the specified organization';
        }

        if ($this->projectId) {
            $steps[] = "Verify project ID '{$this->projectId}' is correct";
            $steps[] = 'Check if you have access to the specified project';
        }

        $steps[] = 'Try regenerating your API key if the issue persists';

        return $steps;
    }

    /**
     * Check if this is a quota-related error.
     */
    public function isQuotaError(): bool
    {
        return $this->openaiErrorType === 'insufficient_quota' ||
               str_contains(strtolower($this->getMessage()), 'quota') ||
               str_contains(strtolower($this->getMessage()), 'billing');
    }

    /**
     * Check if this is an organization/project access error.
     */
    public function isAccessError(): bool
    {
        return in_array($this->openaiErrorType, ['invalid_organization', 'invalid_project']) ||
               str_contains(strtolower($this->getMessage()), 'organization') ||
               str_contains(strtolower($this->getMessage()), 'project');
    }
}
