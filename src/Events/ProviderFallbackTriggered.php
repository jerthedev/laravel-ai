<?php

namespace JTD\LaravelAI\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JTD\LaravelAI\Models\AIConversation;

/**
 * Provider Fallback Triggered Event
 *
 * Fired when a provider fallback is triggered due to failure.
 */
class ProviderFallbackTriggered
{
    use Dispatchable;
    use SerializesModels;

    public AIConversation $conversation;

    public ?string $failedProvider;

    public string $fallbackProvider;

    public \Exception $originalException;

    public int $attemptNumber;

    /**
     * Create a new event instance.
     */
    public function __construct(
        AIConversation $conversation,
        ?string $failedProvider,
        string $fallbackProvider,
        \Exception $originalException,
        int $attemptNumber
    ) {
        $this->conversation = $conversation;
        $this->failedProvider = $failedProvider;
        $this->fallbackProvider = $fallbackProvider;
        $this->originalException = $originalException;
        $this->attemptNumber = $attemptNumber;
    }
}
