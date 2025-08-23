<?php

namespace JTD\LaravelAI\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when an AI function is called.
 *
 * This event is fired at the start of function execution for tracking
 * and monitoring purposes.
 */
class AIFunctionCalled
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $functionName,
        public array $parameters,
        public int $userId,
        public ?int $conversationId = null,
        public ?int $messageId = null,
        public array $context = []
    ) {}
}
