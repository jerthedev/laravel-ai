<?php

namespace JTD\LaravelAI\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when an AI function completes successfully.
 *
 * This event is fired after successful function execution with
 * the result and execution time for monitoring and analytics.
 */
class AIFunctionCompleted
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $functionName,
        public array $parameters,
        public mixed $result,
        public float $executionTime,
        public int $userId,
        public ?int $conversationId = null,
        public ?int $messageId = null,
        public array $context = []
    ) {}
}
