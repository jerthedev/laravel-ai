<?php

namespace JTD\LaravelAI\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JTD\LaravelAI\Models\AIConversation;
use JTD\LaravelAI\Models\AIMessageRecord;

/**
 * Message Added Event
 *
 * Fired when a message is added to a conversation.
 */
class MessageAdded
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public AIConversation $conversation,
        public AIMessageRecord $message
    ) {
        //
    }
}
