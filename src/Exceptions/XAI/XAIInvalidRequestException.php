<?php

namespace JTD\LaravelAI\Exceptions\XAI;

/**
 * Exception thrown when xAI request is invalid.
 */
class XAIInvalidRequestException extends XAIException
{
    public function __construct(
        string $message = 'Invalid request',
        ?string $code = null,
        array $details = [],
        int $httpCode = 400,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 'invalid_request_error', $details, false, $httpCode, $previous);

        if ($code) {
            $this->details['code'] = $code;
        }
    }
}
