<?php

namespace JTD\LaravelAI\Exceptions;

/**
 * Exception thrown when MCP tool operations fail.
 *
 * This exception is used specifically for tool execution errors,
 * parameter validation failures, and tool-specific issues.
 */
class MCPToolException extends MCPException
{
    /**
     * The tool name that caused the exception.
     */
    protected ?string $toolName = null;

    /**
     * The tool parameters that were used.
     */
    protected array $parameters = [];

    /**
     * Create a new MCP tool exception instance.
     *
     * @param string $message Exception message
     * @param int $code Exception code
     * @param \Throwable|null $previous Previous exception
     * @param string|null $serverName MCP server name
     * @param string|null $toolName Tool name
     * @param array $parameters Tool parameters
     * @param array $context Additional context data
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        ?string $serverName = null,
        ?string $toolName = null,
        array $parameters = [],
        array $context = []
    ) {
        parent::__construct($message, $code, $previous, $serverName, $context);
        
        $this->toolName = $toolName;
        $this->parameters = $parameters;
    }

    /**
     * Get the tool name that caused the exception.
     */
    public function getToolName(): ?string
    {
        return $this->toolName;
    }

    /**
     * Get the tool parameters that were used.
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Set the tool name.
     */
    public function setToolName(string $toolName): self
    {
        $this->toolName = $toolName;
        return $this;
    }

    /**
     * Set the tool parameters.
     */
    public function setParameters(array $parameters): self
    {
        $this->parameters = $parameters;
        return $this;
    }

    /**
     * Get a formatted error message with tool context.
     */
    public function getFormattedMessage(): string
    {
        $message = $this->getMessage();
        
        if ($this->serverName && $this->toolName) {
            $message = "[{$this->serverName}:{$this->toolName}] {$message}";
        } elseif ($this->serverName) {
            $message = "[{$this->serverName}] {$message}";
        } elseif ($this->toolName) {
            $message = "[{$this->toolName}] {$message}";
        }
        
        $contextData = $this->context;
        if (!empty($this->parameters)) {
            $contextData['parameters'] = $this->parameters;
        }
        
        if (!empty($contextData)) {
            $contextString = json_encode($contextData, JSON_UNESCAPED_SLASHES);
            $message .= " Context: {$contextString}";
        }
        
        return $message;
    }
}
