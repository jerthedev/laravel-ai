<?php

namespace JTD\LaravelAI\Exceptions;

use Exception;

/**
 * Exception thrown when API credentials are invalid or expired.
 */
class InvalidCredentialsException extends Exception
{
    /**
     * @var string|null Provider name
     */
    public ?string $provider;

    /**
     * @var string|null Account identifier
     */
    public ?string $account;

    /**
     * @var array Additional error details
     */
    public array $details;

    /**
     * Create a new invalid credentials exception.
     *
     * @param  string  $message  Exception message
     * @param  string|null  $provider  Provider name
     * @param  string|null  $account  Account identifier
     * @param  array  $details  Additional error details
     * @param  int  $code  Exception code
     * @param  Exception|null  $previous  Previous exception
     */
    public function __construct(
        string $message = 'Invalid or expired credentials',
        ?string $provider = null,
        ?string $account = null,
        array $details = [],
        int $code = 401,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);

        $this->provider = $provider;
        $this->account = $account;
        $this->details = $details;
    }

    /**
     * Get the provider name.
     */
    public function getProvider(): ?string
    {
        return $this->provider;
    }

    /**
     * Get the account identifier.
     */
    public function getAccount(): ?string
    {
        return $this->account;
    }

    /**
     * Get additional error details.
     */
    public function getDetails(): array
    {
        return $this->details;
    }
}
