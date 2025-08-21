<?php

namespace JTD\LaravelAI\Exceptions;

use Exception;

class InvalidConfigurationException extends Exception
{
    /**
     * Create a new invalid configuration exception.
     */
    public function __construct(string $message = 'Invalid AI configuration', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
