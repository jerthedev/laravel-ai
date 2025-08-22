<?php

namespace JTD\LaravelAI\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;

/**
 * Event fired when a conversation is updated with new messages.
 *
 * This event is fired after a message exchange is completed and the
 * conversation state is updated. It enables background processing for
 * conversation analytics, memory updates, and context management.
 */
class ConversationUpdated
{
    use Dispatchable;
    use SerializesModels;

    /**
     * The conversation ID.
     */
    public string $conversationId;

    /**
     * The new message added to the conversation.
     */
    public AIMessage $newMessage;

    /**
     * The response generated for the message.
     */
    public AIResponse $response;

    /**
     * The provider used.
     */
    public string $provider;

    /**
     * The model used.
     */
    public string $model;

    /**
     * Total messages in conversation after update.
     */
    public int $totalMessages;

    /**
     * Total cost of conversation after update.
     */
    public float $totalCost;

    /**
     * Conversation context/metadata.
     */
    public array $context;

    /**
     * User ID if applicable.
     *
     * @var mixed
     */
    public $userId;

    /**
     * Timestamp when conversation was updated.
     */
    public \DateTime $updatedAt;

    /**
     * Whether this is a new conversation.
     */
    public bool $isNewConversation;

    /**
     * Create a new event instance.
     *
     * @param  mixed  $userId
     */
    public function __construct(
        string $conversationId,
        AIMessage $newMessage,
        AIResponse $response,
        string $provider,
        string $model,
        int $totalMessages,
        float $totalCost,
        array $context = [],
        $userId = null,
        ?\DateTime $updatedAt = null,
        bool $isNewConversation = false
    ) {
        $this->conversationId = $conversationId;
        $this->newMessage = $newMessage;
        $this->response = $response;
        $this->provider = $provider;
        $this->model = $model;
        $this->totalMessages = $totalMessages;
        $this->totalCost = $totalCost;
        $this->context = $context;
        $this->userId = $userId;
        $this->updatedAt = $updatedAt ?? new \DateTime;
        $this->isNewConversation = $isNewConversation;
    }

    /**
     * Get the conversation length in messages.
     */
    public function getConversationLength(): int
    {
        return $this->totalMessages;
    }

    /**
     * Get formatted total cost.
     */
    public function getFormattedTotalCost(): string
    {
        return '$' . number_format($this->totalCost, 6);
    }

    /**
     * Check if conversation is getting long.
     */
    public function isLongConversation(int $threshold = 20): bool
    {
        return $this->totalMessages > $threshold;
    }

    /**
     * Check if conversation cost is high.
     */
    public function isExpensiveConversation(float $threshold = 1.0): bool
    {
        return $this->totalCost > $threshold;
    }

    /**
     * Get context value by key.
     */
    public function getContextValue(string $key, $default = null)
    {
        return $this->context[$key] ?? $default;
    }

    /**
     * Check if context has a specific key.
     */
    public function hasContextKey(string $key): bool
    {
        return array_key_exists($key, $this->context);
    }
}
