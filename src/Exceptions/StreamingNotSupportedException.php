<?php

namespace JTD\LaravelAI\Exceptions;

use Exception;

/**
 * Exception thrown when streaming is requested but not supported.
 */
class StreamingNotSupportedException extends Exception
{
    /**
     * @var string|null Provider name
     */
    public ?string $provider;

    /**
     * @var string|null Model identifier
     */
    public ?string $model;

    /**
     * Create a new streaming not supported exception.
     *
     * @param  string  $message  Exception message
     * @param  string|null  $provider  Provider name
     * @param  string|null  $model  Model identifier
     * @param  int  $code  Exception code
     * @param  Exception|null  $previous  Previous exception
     */
    public function __construct(
        string $message = 'Streaming not supported',
        ?string $provider = null,
        ?string $model = null,
        int $code = 400,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);

        $this->provider = $provider;
        $this->model = $model;
    }

    /**
     * Get the provider name.
     */
    public function getProvider(): ?string
    {
        return $this->provider;
    }

    /**
     * Get the model identifier.
     */
    public function getModel(): ?string
    {
        return $this->model;
    }
}
