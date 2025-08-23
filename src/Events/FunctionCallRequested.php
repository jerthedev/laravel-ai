<?php

namespace JTD\LaravelAI\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when an AI function call is requested for background processing.
 * 
 * This event enables AI function calls to trigger background actions without
 * returning values to the AI, allowing for agent-like capabilities.
 */
class FunctionCallRequested
{
    use Dispatchable, SerializesModels;

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
