<?php

namespace JTD\LaravelAI\Exceptions;

use Exception;

/**
 * Exception thrown when a requested model is not found or not available.
 */
class ModelNotFoundException extends Exception
{
    /**
     * @var string|null Model identifier
     */
    public ?string $model;

    /**
     * @var string|null Provider name
     */
    public ?string $provider;

    /**
     * @var array Available models
     */
    public array $availableModels;

    /**
     * Create a new model not found exception.
     *
     * @param  string  $message  Exception message
     * @param  string|null  $model  Model identifier
     * @param  string|null  $provider  Provider name
     * @param  array  $availableModels  Available models
     * @param  int  $code  Exception code
     * @param  Exception|null  $previous  Previous exception
     */
    public function __construct(
        string $message = 'Model not found',
        ?string $model = null,
        ?string $provider = null,
        array $availableModels = [],
        int $code = 404,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);

        $this->model = $model;
        $this->provider = $provider;
        $this->availableModels = $availableModels;
    }

    /**
     * Get the model identifier.
     */
    public function getModel(): ?string
    {
        return $this->model;
    }

    /**
     * Get the provider name.
     */
    public function getProvider(): ?string
    {
        return $this->provider;
    }

    /**
     * Get available models.
     */
    public function getAvailableModels(): array
    {
        return $this->availableModels;
    }
}
