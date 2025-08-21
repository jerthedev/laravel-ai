<?php

namespace JTD\LaravelAI\Exceptions;

use Exception;

/**
 * Exception thrown when a requested provider is not found or not configured.
 */
class ProviderNotFoundException extends Exception
{
    /**
     * @var string|null Provider name
     */
    public ?string $provider;

    /**
     * @var array Available providers
     */
    public array $availableProviders;

    /**
     * Create a new provider not found exception.
     *
     * @param  string  $message  Exception message
     * @param  string|null  $provider  Provider name
     * @param  array  $availableProviders  Available providers
     * @param  int  $code  Exception code
     * @param  Exception|null  $previous  Previous exception
     */
    public function __construct(
        string $message = 'Provider not found',
        ?string $provider = null,
        array $availableProviders = [],
        int $code = 404,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);

        $this->provider = $provider;
        $this->availableProviders = $availableProviders;
    }

    /**
     * Get the provider name.
     */
    public function getProvider(): ?string
    {
        return $this->provider;
    }

    /**
     * Get available providers.
     */
    public function getAvailableProviders(): array
    {
        return $this->availableProviders;
    }
}
