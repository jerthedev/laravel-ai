<?php

namespace JTD\LaravelAI\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JTD\LaravelAI\Models\AIMessage;

/**
 * Event fired when a message is sent to an AI provider.
 */
class MessageSent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * The message that was sent.
     */
    public AIMessage $message;

    /**
     * The provider used.
     */
    public string $provider;

    /**
     * The model used.
     */
    public string $model;

    /**
     * Request options.
     */
    public array $options;

    /**
     * Conversation ID if applicable.
     */
    public ?string $conversationId;

    /**
     * User ID if applicable.
     *
     * @var mixed
     */
    public $userId;

    /**
     * Create a new event instance.
     *
     * @param  mixed  $userId
     */
    public function __construct(
        AIMessage $message,
        string $provider,
        string $model,
        array $options = [],
        ?string $conversationId = null,
        $userId = null
    ) {
        $this->message = $message;
        $this->provider = $provider;
        $this->model = $model;
        $this->options = $options;
        $this->conversationId = $conversationId;
        $this->userId = $userId;
    }
}
