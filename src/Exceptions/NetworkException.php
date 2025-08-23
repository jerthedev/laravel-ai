<?php

namespace JTD\LaravelAI\Exceptions;

use Exception;

/**
 * Exception thrown when network connectivity issues occur.
 */
class NetworkException extends Exception
{
    /**
     * @var string|null Provider name
     */
    public ?string $provider;

    /**
     * Create a new network exception.
     */
    public function __construct(
        string $message = 'Network error',
        ?string $provider = null,
        int $code = 0,
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
