<?php

namespace JTD\LaravelAI\Exceptions;

use Exception;

/**
 * Exception thrown when an invalid request is made.
 */
class InvalidRequestException extends Exception
{
    /**
     * @var string|null Provider name
     */
    public ?string $provider;

    /**
     * Create a new invalid request exception.
     */
    public function __construct(
        string $message = 'Invalid request',
        ?string $provider = null,
        int $code = 400,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->provider = $provider;
    }

    /**
     * Get the provider name.
     */
    public function getProvider(): ?string
    {
        return $this->provider;
    }
}
