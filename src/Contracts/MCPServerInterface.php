<?php

namespace JTD\LaravelAI\Contracts;

use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;

/**
 * Interface for Model Context Protocol (MCP) server implementations.
 *
 * MCP servers provide enhanced AI capabilities through structured thinking processes,
 * tool integration, and context management. This interface ensures consistent behavior
 * across different MCP server types (built-in, external, custom).
 *
 * MCP servers can process messages before they reach AI providers, add context,
 * provide tools, and enhance responses with additional capabilities.
 */
interface MCPServerInterface
{
    /**
     * Get the unique identifier for this MCP server.
     *
     * @return string Server identifier (e.g., 'sequential-thinking', 'github', 'brave-search')
     */
    public function getName(): string;

    /**
     * Get the display name for this MCP server.
     *
     * @return string Human-readable server name
     */
    public function getDisplayName(): string;

    /**
     * Get the description of what this MCP server provides.
     *
     * @return string Server description
     */
    public function getDescription(): string;

    /**
     * Check if the MCP server is properly configured and ready to use.
     *
     * @return bool True if server is configured and available
     */
    public function isConfigured(): bool;

    /**
     * Check if the MCP server is currently enabled.
     *
     * @return bool True if server is enabled
     */
    public function isEnabled(): bool;

    /**
     * Get the server configuration.
     *
     * @return array Server configuration data
     */
    public function getConfig(): array;

    /**
     * Process an AI message through this MCP server.
     *
     * This method allows the MCP server to modify, enhance, or add context
     * to messages before they are sent to AI providers.
     *
     * @param AIMessage $message The message to process
     * @return AIMessage The processed message
     *
     * @throws \JTD\LaravelAI\Exceptions\MCPException
     */
    public function processMessage(AIMessage $message): AIMessage;

    /**
     * Process an AI response through this MCP server.
     *
     * This method allows the MCP server to modify, enhance, or add metadata
     * to responses after they are received from AI providers.
     *
     * @param AIResponse $response The response to process
     * @return AIResponse The processed response
     *
     * @throws \JTD\LaravelAI\Exceptions\MCPException
     */
    public function processResponse(AIResponse $response): AIResponse;

    /**
     * Get the tools available from this MCP server.
     *
     * @return array Array of tool definitions with schemas
     */
    public function getAvailableTools(): array;

    /**
     * Execute a tool provided by this MCP server.
     *
     * @param string $toolName The name of the tool to execute
     * @param array $parameters Tool parameters
     * @return array Tool execution result
     *
     * @throws \JTD\LaravelAI\Exceptions\MCPToolException
     */
    public function executeTool(string $toolName, array $parameters = []): array;

    /**
     * Test the connectivity and functionality of this MCP server.
     *
     * @return array Test result with status and details
     */
    public function testConnection(): array;

    /**
     * Get performance metrics for this MCP server.
     *
     * @return array Performance metrics (response times, success rates, etc.)
     */
    public function getMetrics(): array;

    /**
     * Get the server type (built-in, external, custom).
     *
     * @return string Server type
     */
    public function getType(): string;

    /**
     * Get the server version.
     *
     * @return string Server version
     */
    public function getVersion(): string;
}
