<?php

namespace JTD\LaravelAI\Exceptions;

use Exception;

/**
 * Exception thrown when a feature is not supported by a provider.
 */
class UnsupportedFeatureException extends Exception
{
    /**
     * @var string|null Provider name
     */
    public ?string $provider;

    /**
     * @var string|null Feature name
     */
    public ?string $feature;

    /**
     * Create a new unsupported feature exception.
     *
     * @param  string  $message  Exception message
     * @param  string|null  $provider  Provider name
     * @param  string|null  $feature  Feature name
     * @param  int  $code  Exception code
     * @param  Exception|null  $previous  Previous exception
     */
    public function __construct(
        string $message = 'Feature not supported',
        ?string $provider = null,
        ?string $feature = null,
        int $code = 400,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);

        $this->provider = $provider;
        $this->feature = $feature;
    }

    /**
     * Get the provider name.
     */
    public function getProvider(): ?string
    {
        return $this->provider;
    }

    /**
     * Get the feature name.
     */
    public function getFeature(): ?string
    {
        return $this->feature;
    }

    /**
     * Create exception for streaming not supported.
     */
    public static function streaming(?string $provider = null): static
    {
        return new static(
            'Streaming is not supported by this provider',
            $provider,
            'streaming'
        );
    }

    /**
     * Create exception for function calling not supported.
     */
    public static function functionCalling(?string $provider = null): static
    {
        return new static(
            'Function calling is not supported by this provider',
            $provider,
            'function_calling'
        );
    }

    /**
     * Create exception for vision not supported.
     */
    public static function vision(?string $provider = null): static
    {
        return new static(
            'Vision/multimodal input is not supported by this provider',
            $provider,
            'vision'
        );
    }

    /**
     * Create exception for audio not supported.
     */
    public static function audio(?string $provider = null): static
    {
        return new static(
            'Audio input is not supported by this provider',
            $provider,
            'audio'
        );
    }
}
