<?php

namespace JTD\LaravelAI\Exceptions\XAI;

/**
 * Exception thrown when xAI credentials are invalid.
 */
class XAIInvalidCredentialsException extends XAIException
{
    public function __construct(
        string $message = 'Invalid xAI credentials',
        array $details = [],
        int $code = 401,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 'authentication_error', $details, false, $code, $previous);
    }
}
