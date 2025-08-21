<?php

namespace JTD\LaravelAI\Exceptions;

use Exception;

/**
 * Exception thrown when cost calculation fails.
 */
class CostCalculationException extends Exception
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
     * @var array Calculation details
     */
    public array $details;

    /**
     * Create a new cost calculation exception.
     *
     * @param  string  $message  Exception message
     * @param  string|null  $provider  Provider name
     * @param  string|null  $model  Model identifier
     * @param  array  $details  Calculation details
     * @param  int  $code  Exception code
     * @param  Exception|null  $previous  Previous exception
     */
    public function __construct(
        string $message = 'Cost calculation failed',
        ?string $provider = null,
        ?string $model = null,
        array $details = [],
        int $code = 500,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);

        $this->provider = $provider;
        $this->model = $model;
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
     * Get the model identifier.
     */
    public function getModel(): ?string
    {
        return $this->model;
    }

    /**
     * Get calculation details.
     */
    public function getDetails(): array
    {
        return $this->details;
    }
}
