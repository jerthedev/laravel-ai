<?php

namespace JTD\LaravelAI\Exceptions;

/**
 * Exception thrown when MCP server operations fail.
 *
 * This exception is used for general MCP server errors including
 * configuration issues, communication failures, and processing errors.
 */
class MCPException extends \Exception
{
    /**
     * The MCP server name that caused the exception.
     */
    protected ?string $serverName = null;

    /**
     * Additional context data for the exception.
     */
    protected array $context = [];

    /**
     * Create a new MCP exception instance.
     *
     * @param  string  $message  Exception message
     * @param  int  $code  Exception code
     * @param  \Throwable|null  $previous  Previous exception
     * @param  string|null  $serverName  MCP server name
     * @param  array  $context  Additional context data
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        ?string $serverName = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);

        $this->serverName = $serverName;
        $this->context = $context;
    }

    /**
     * Get the MCP server name that caused the exception.
     */
    public function getServerName(): ?string
    {
        return $this->serverName;
    }

    /**
     * Get additional context data for the exception.
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Set the MCP server name.
     */
    public function setServerName(string $serverName): self
    {
        $this->serverName = $serverName;

        return $this;
    }

    /**
     * Set additional context data.
     */
    public function setContext(array $context): self
    {
        $this->context = $context;

        return $this;
    }

    /**
     * Add context data to the existing context.
     */
    public function addContext(string $key, mixed $value): self
    {
        $this->context[$key] = $value;

        return $this;
    }

    /**
     * Get a formatted error message with context.
     */
    public function getFormattedMessage(): string
    {
        $message = $this->getMessage();

        if ($this->serverName) {
            $message = "[{$this->serverName}] {$message}";
        }

        if (! empty($this->context)) {
            $contextString = json_encode($this->context, JSON_UNESCAPED_SLASHES);
            $message .= " Context: {$contextString}";
        }

        return $message;
    }
}
