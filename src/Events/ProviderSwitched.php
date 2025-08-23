<?php

namespace JTD\LaravelAI\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JTD\LaravelAI\Models\AIConversation;

/**
 * Provider Switched Event
 *
 * Fired when a conversation switches from one AI provider to another.
 */
class ProviderSwitched
{
    use Dispatchable;
    use SerializesModels;

    public AIConversation $conversation;

    public ?string $fromProvider;

    public string $toProvider;

    public ?string $fromModel;

    public ?string $toModel;

    public array $options;

    /**
     * Create a new event instance.
     */
    public function __construct(
        AIConversation $conversation,
        ?string $fromProvider,
        string $toProvider,
        ?string $fromModel,
        ?string $toModel,
        array $options = []
    ) {
        $this->conversation = $conversation;
        $this->fromProvider = $fromProvider;
        $this->toProvider = $toProvider;
        $this->fromModel = $fromModel;
        $this->toModel = $toModel;
        $this->options = $options;
    }
}
