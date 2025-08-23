<?php

namespace JTD\LaravelAI\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when an AI function fails during execution.
 *
 * This event is fired when function execution encounters an error,
 * including the error details and execution time for monitoring.
 */
class AIFunctionFailed
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $functionName,
        public array $parameters,
        public \Throwable $error,
        public float $executionTime,
        public int $userId,
        public ?int $conversationId = null,
        public ?int $messageId = null,
        public array $context = []
    ) {}
}
