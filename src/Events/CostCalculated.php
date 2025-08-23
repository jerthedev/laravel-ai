<?php

namespace JTD\LaravelAI\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when cost calculation is completed for a message or conversation.
 *
 * This event enables background processing for budget monitoring, analytics,
 * and cost tracking without impacting response times.
 */
class CostCalculated
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public int $userId,
        public string $provider,
        public string $model,
        public float $cost,
        public int $inputTokens,
        public int $outputTokens,
        public ?int $conversationId = null,
        public ?int $messageId = null
    ) {}
}
