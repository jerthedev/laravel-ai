<?php

namespace JTD\LaravelAI\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when an MCP tool is executed.
 *
 * This event enables tracking and monitoring of MCP tool usage,
 * performance metrics, and integration with analytics systems.
 */
class MCPToolExecuted
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $serverName,
        public string $toolName,
        public array $parameters,
        public array $result,
        public float $executionTime,
        public ?int $userId = null,
        public bool $success = true,
        public ?string $error = null
    ) {}
}
