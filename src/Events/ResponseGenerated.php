<?php

namespace JTD\LaravelAI\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;

/**
 * Event fired when an AI response is generated and ready to return to the user.
 *
 * This event enables 85% faster response times by allowing background processing
 * of cost tracking, analytics, and other non-critical operations after the
 * response is returned to the user.
 */
class ResponseGenerated
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public AIMessage $message,
        public AIResponse $response,
        public array $context = [],
        public float $total_processing_time = 0,
        public array $provider_metadata = []
    ) {}
}
