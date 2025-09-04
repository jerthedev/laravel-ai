<?php

namespace JTD\LaravelAI\Tests\Support;

use JTD\LaravelAI\Events\FunctionCallRequested;

/**
 * Test Background Email Listener
 */
class TestBackgroundEmailListener
{
    public function handle(FunctionCallRequested $event): void
    {
        // Simulate background email processing
        sleep(1); // Simulate processing time

        logger()->info('Test background email processed', [
            'to' => $event->parameters['to'] ?? 'unknown',
            'subject' => $event->parameters['subject'] ?? 'No subject',
            'processed_at' => now()->toISOString(),
        ]);
    }
}
