<?php

namespace JTD\LaravelAI\Contracts;

use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;

/**
 * Interface for MCP Manager implementations.
 *
 * The MCP Manager coordinates multiple MCP servers, handles configuration,
 * manages server lifecycle, and provides a unified interface for MCP operations.
 */
interface MCPManagerInterface
{
    /**
     * Load MCP configuration from files and environment.
     */
    public function loadConfiguration(): void;

    /**
     * Register an MCP server with the manager.
     *
     * @param  string  $name  Server identifier
     * @param  MCPServerInterface  $server  Server instance
     */
    public function registerServer(string $name, MCPServerInterface $server): void;

    /**
     * Unregister an MCP server from the manager.
     *
     * @param  string  $name  Server identifier
     */
    public function unregisterServer(string $name): void;

    /**
     * Get a registered MCP server by name.
     *
     * @param  string  $name  Server identifier
     * @return MCPServerInterface|null Server instance or null if not found
     */
    public function getServer(string $name): ?MCPServerInterface;

    /**
     * Get all registered MCP servers.
     *
     * @return array Array of server instances keyed by name
     */
    public function getServers(): array;

    /**
     * Get all enabled MCP servers.
     *
     * @return array Array of enabled server instances keyed by name
     */
    public function getEnabledServers(): array;

    /**
     * Check if an MCP server is registered and enabled.
     *
     * @param  string  $name  Server identifier
     * @return bool True if server is registered and enabled
     */
    public function hasServer(string $name): bool;

    /**
     * Process a message through specified MCP servers.
     *
     * @param  AIMessage  $message  Message to process
     * @param  array  $enabledServers  Array of server names to use
     * @return AIMessage Processed message
     *
     * @throws \JTD\LaravelAI\Exceptions\MCPException
     */
    public function processMessage(AIMessage $message, array $enabledServers = []): AIMessage;

    /**
     * Process a response through specified MCP servers.
     *
     * @param  AIResponse  $response  Response to process
     * @param  array  $enabledServers  Array of server names to use
     * @return AIResponse Processed response
     *
     * @throws \JTD\LaravelAI\Exceptions\MCPException
     */
    public function processResponse(AIResponse $response, array $enabledServers = []): AIResponse;

    /**
     * Get all available tools from specified servers.
     *
     * @param  string|null  $serverName  Specific server name or null for all servers
     * @return array Array of tools organized by server
     */
    public function getAvailableTools(?string $serverName = null): array;

    /**
     * Execute a tool from a specific server.
     *
     * @param  string  $serverName  Server identifier
     * @param  string  $toolName  Tool identifier
     * @param  array  $parameters  Tool parameters
     * @return array Tool execution result
     *
     * @throws \JTD\LaravelAI\Exceptions\MCPException
     * @throws \JTD\LaravelAI\Exceptions\MCPToolException
     */
    public function executeTool(string $serverName, string $toolName, array $parameters = []): array;

    /**
     * Discover and cache tools from all configured servers.
     *
     * @param  bool  $forceRefresh  Force refresh of tool cache
     * @return array Discovery results with statistics
     */
    public function discoverTools(bool $forceRefresh = false): array;

    /**
     * Test connectivity for all or specific MCP servers.
     *
     * @param  string|null  $serverName  Specific server name or null for all servers
     * @return array Test results organized by server
     */
    public function testServers(?string $serverName = null): array;

    /**
     * Get performance metrics for all or specific MCP servers.
     *
     * @param  string|null  $serverName  Specific server name or null for all servers
     * @return array Performance metrics organized by server
     */
    public function getMetrics(?string $serverName = null): array;

    /**
     * Enable an MCP server.
     *
     * @param  string  $name  Server identifier
     * @return bool True if server was enabled successfully
     */
    public function enableServer(string $name): bool;

    /**
     * Disable an MCP server.
     *
     * @param  string  $name  Server identifier
     * @return bool True if server was disabled successfully
     */
    public function disableServer(string $name): bool;

    /**
     * Get the current MCP configuration.
     *
     * @return array Current configuration
     */
    public function getConfiguration(): array;

    /**
     * Update MCP configuration.
     *
     * @param  array  $config  New configuration
     * @return bool True if configuration was updated successfully
     */
    public function updateConfiguration(array $config): bool;

    /**
     * Validate MCP configuration.
     *
     * @param  array  $config  Configuration to validate
     * @return array Validation results with errors if any
     */
    public function validateConfiguration(array $config): array;
}
