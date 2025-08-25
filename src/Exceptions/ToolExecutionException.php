<?php

namespace JTD\LaravelAI\Exceptions;

/**
 * Tool Execution Exception
 *
 * Thrown when tool execution fails in the unified tool system.
 */
class ToolExecutionException extends \Exception
{
    /**
     * Create a new tool execution exception.
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        public ?string $toolName = null,
        public ?array $parameters = null,
        public ?string $toolType = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the tool name that failed.
     */
    public function getToolName(): ?string
    {
        return $this->toolName;
    }

    /**
     * Get the parameters that were passed to the tool.
     */
    public function getParameters(): ?array
    {
        return $this->parameters;
    }

    /**
     * Get the tool type (mcp_tool or function_event).
     */
    public function getToolType(): ?string
    {
        return $this->toolType;
    }
}
