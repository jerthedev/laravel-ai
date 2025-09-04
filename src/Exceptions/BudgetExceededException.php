<?php

namespace JTD\LaravelAI\Exceptions;

/**
 * Budget Exceeded Exception
 *
 * Thrown when a budget limit would be exceeded by a request.
 */
class BudgetExceededException extends AIException
{
    /**
     * Create a new budget exceeded exception.
     *
     * @param  string  $message  The exception message
     * @param  int  $code  The exception code
     * @param  \Throwable|null  $previous  The previous exception
     */
    public function __construct(string $message = 'Budget limit exceeded', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
