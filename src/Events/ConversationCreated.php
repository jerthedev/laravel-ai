<?php

namespace JTD\LaravelAI\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JTD\LaravelAI\Models\AIConversation;

/**
 * Conversation Created Event
 *
 * Fired when a new conversation is created.
 */
class ConversationCreated
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public AIConversation $conversation
    ) {
        //
    }
}
