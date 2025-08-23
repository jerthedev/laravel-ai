<?php

namespace JTD\LaravelAI\Exceptions;

use Exception;

/**
 * Exception thrown when server errors occur.
 */
class ServerException extends Exception
{
    /**
     * @var string|null Provider name
     */
    public ?string $provider;

    /**
     * Create a new server exception.
     */
    public function __construct(
        string $message = 'Server error',
        ?string $provider = null,
        int $code = 500,
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
