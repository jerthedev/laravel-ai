<?php

namespace JTD\LaravelAI\Tests\Support;

use JTD\LaravelAI\Events\FunctionCallRequested;

/**
 * Test Email Sender Listener
 */
class TestEmailSenderListener
{
    public function handle(FunctionCallRequested $event): void
    {
        // Simulate email sending
        logger()->info('Test email sent', [
            'to' => $event->parameters['to'] ?? 'unknown',
            'subject' => $event->parameters['subject'] ?? 'No subject',
        ]);
    }
}
