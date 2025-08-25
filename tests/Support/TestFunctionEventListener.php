<?php

namespace JTD\LaravelAI\Tests\Support;

use JTD\LaravelAI\Events\FunctionCallRequested;

/**
 * Test Function Event Listener
 *
 * Simple listener for testing function events in E2E tests.
 */
class TestFunctionEventListener
{
    /**
     * Handle the function call event.
     */
    public function handle(FunctionCallRequested $event): void
    {
        // Simple test implementation
        logger()->info('Test function event handled', [
            'function_name' => $event->functionName,
            'parameters' => $event->parameters,
            'user_id' => $event->userId,
        ]);
    }
}
