<?php

namespace JTD\LaravelAI\Exceptions;

use Exception;

/**
 * Conversation Exception
 *
 * Thrown when conversation operations fail.
 */
class ConversationException extends Exception
{
    /**
     * Create a new conversation exception.
     */
    public function __construct(
        string $message = 'Conversation operation failed',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
