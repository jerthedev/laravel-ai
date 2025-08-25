<?php

namespace JTD\LaravelAI\Tests\Support;

use JTD\LaravelAI\Events\FunctionCallRequested;

/**
 * Test Background Notification Listener
 */
class TestBackgroundNotificationListener
{
    public function handle(FunctionCallRequested $event): void
    {
        logger()->info('Test background notification sent', [
            'type' => $event->parameters['type'] ?? 'info',
            'message' => $event->parameters['message'] ?? 'Default notification',
            'sent_at' => now()->toISOString(),
        ]);
    }
}
