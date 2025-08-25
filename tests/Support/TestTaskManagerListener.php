<?php

namespace JTD\LaravelAI\Tests\Support;

use JTD\LaravelAI\Events\FunctionCallRequested;

/**
 * Test Task Manager Listener
 */
class TestTaskManagerListener
{
    public function handle(FunctionCallRequested $event): void
    {
        logger()->info('Test task manager called', [
            'action' => $event->parameters['action'] ?? 'create',
            'title' => $event->parameters['title'] ?? 'New Task',
        ]);
    }
}
