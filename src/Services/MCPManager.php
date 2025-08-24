<?php

namespace JTD\LaravelAI\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Contracts\MCPManagerInterface;
use JTD\LaravelAI\Contracts\MCPServerInterface;
use JTD\LaravelAI\Exceptions\MCPException;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;

/**
 * MCP Manager Service
 *
 * Manages Model Context Protocol servers, handles configuration loading,
 * coordinates server operations, and provides a unified interface for MCP functionality.
 */
class MCPManager implements MCPManagerInterface
{
    /**
     * Registered MCP servers.
     *
     * @var array<string, MCPServerInterface>
     */
    protected array $servers = [];

    /**
     * MCP configuration data.
     *
     * @var array
     */
    protected array $config = [];

    /**
     * Cached tools data.
     *
     * @var array
     */
    protected array $tools = [];

    /**
     * Configuration file path.
     *
     * @var string
     */
    protected string $configPath;

    /**
     * Tools file path.
     *
     * @var string
     */
    protected string $toolsPath;

    /**
     * Create a new MCP Manager instance.
     */
    public function __construct()
    {
        $this->configPath = base_path('.mcp.json');
        $this->toolsPath = base_path('.mcp.tools.json');

        $this->loadConfiguration();
    }

    /**
     * Load MCP configuration from files and environment.
     */
    public function loadConfiguration(): void
    {
        // Load main configuration
        if (File::exists($this->configPath)) {
            try {
                $configContent = File::get($this->configPath);
                $this->config = json_decode($configContent, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                Log::error('Failed to parse MCP configuration', [
                    'file' => $this->configPath,
                    'error' => $e->getMessage(),
                ]);
                $this->config = [];
            }
        }

        // Load tools configuration
        if (File::exists($this->toolsPath)) {
            try {
                $toolsContent = File::get($this->toolsPath);
                $this->tools = json_decode($toolsContent, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                Log::error('Failed to parse MCP tools configuration', [
                    'file' => $this->toolsPath,
                    'error' => $e->getMessage(),
                ]);
                $this->tools = [];
            }
        }

        // Register servers from configuration
        $this->registerServersFromConfig();
    }

    /**
     * Register servers from configuration.
     */
    protected function registerServersFromConfig(): void
    {
        foreach ($this->config['servers'] ?? [] as $name => $serverConfig) {
            if (!($serverConfig['enabled'] ?? false)) {
                continue;
            }

            try {
                $this->registerExternalServer($name, $serverConfig);
            } catch (\Exception $e) {
                Log::warning('Failed to register MCP server', [
                    'server' => $name,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Register an external MCP server.
     */
    protected function registerExternalServer(string $name, array $config): void
    {
        $server = new ExternalMCPServer($name, $config);
        $this->registerServer($name, $server);
    }

    /**
     * Register an MCP server with the manager.
     */
    public function registerServer(string $name, MCPServerInterface $server): void
    {
        $this->servers[$name] = $server;

        Log::info('MCP server registered', [
            'server' => $name,
            'type' => $server->getType(),
            'version' => $server->getVersion(),
        ]);
    }

    /**
     * Unregister an MCP server from the manager.
     */
    public function unregisterServer(string $name): void
    {
        if (isset($this->servers[$name])) {
            unset($this->servers[$name]);

            Log::info('MCP server unregistered', ['server' => $name]);
        }
    }

    /**
     * Get a registered MCP server by name.
     */
    public function getServer(string $name): ?MCPServerInterface
    {
        return $this->servers[$name] ?? null;
    }

    /**
     * Get all registered MCP servers.
     */
    public function getServers(): array
    {
        return $this->servers;
    }

    /**
     * Get all enabled MCP servers.
     */
    public function getEnabledServers(): array
    {
        return array_filter($this->servers, function (MCPServerInterface $server) {
            return $server->isEnabled();
        });
    }

    /**
     * Check if an MCP server is registered and enabled.
     */
    public function hasServer(string $name): bool
    {
        return isset($this->servers[$name]) && $this->servers[$name]->isEnabled();
    }

    /**
     * Process a message through specified MCP servers.
     */
    public function processMessage(AIMessage $message, array $enabledServers = []): AIMessage
    {
        $processedMessage = $message;

        foreach ($enabledServers as $serverName) {
            if (!$this->hasServer($serverName)) {
                Log::warning('Attempted to use unavailable MCP server', [
                    'server' => $serverName,
                    'available_servers' => array_keys($this->getEnabledServers()),
                ]);
                continue;
            }

            try {
                $startTime = microtime(true);
                $processedMessage = $this->servers[$serverName]->processMessage($processedMessage);
                $processingTime = (microtime(true) - $startTime) * 1000;

                Log::debug('MCP server processed message', [
                    'server' => $serverName,
                    'processing_time_ms' => round($processingTime, 2),
                ]);
            } catch (\Exception $e) {
                Log::error('MCP server failed to process message', [
                    'server' => $serverName,
                    'error' => $e->getMessage(),
                ]);

                throw new MCPException(
                    "MCP server '{$serverName}' failed to process message: {$e->getMessage()}",
                    0,
                    $e
                );
            }
        }

        return $processedMessage;
    }

    /**
     * Process a response through specified MCP servers.
     */
    public function processResponse(AIResponse $response, array $enabledServers = []): AIResponse
    {
        $processedResponse = $response;

        foreach ($enabledServers as $serverName) {
            if (!$this->hasServer($serverName)) {
                continue;
            }

            try {
                $startTime = microtime(true);
                $processedResponse = $this->servers[$serverName]->processResponse($processedResponse);
                $processingTime = (microtime(true) - $startTime) * 1000;

                Log::debug('MCP server processed response', [
                    'server' => $serverName,
                    'processing_time_ms' => round($processingTime, 2),
                ]);
            } catch (\Exception $e) {
                Log::error('MCP server failed to process response', [
                    'server' => $serverName,
                    'error' => $e->getMessage(),
                ]);

                throw new MCPException(
                    "MCP server '{$serverName}' failed to process response: {$e->getMessage()}",
                    0,
                    $e
                );
            }
        }

        return $processedResponse;
    }

    /**
     * Get all available tools from specified servers.
     */
    public function getAvailableTools(string $serverName = null): array
    {
        if ($serverName) {
            return $this->tools[$serverName]['tools'] ?? [];
        }

        $allTools = [];
        foreach ($this->tools as $server => $data) {
            $allTools[$server] = $data['tools'] ?? [];
        }

        return $allTools;
    }

    /**
     * Execute a tool from a specific server.
     */
    public function executeTool(string $serverName, string $toolName, array $parameters = []): array
    {
        if (!$this->hasServer($serverName)) {
            throw new MCPException("MCP server '{$serverName}' is not available");
        }

        try {
            $startTime = microtime(true);
            $result = $this->servers[$serverName]->executeTool($toolName, $parameters);
            $executionTime = (microtime(true) - $startTime) * 1000;

            Log::info('MCP tool executed', [
                'server' => $serverName,
                'tool' => $toolName,
                'execution_time_ms' => round($executionTime, 2),
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('MCP tool execution failed', [
                'server' => $serverName,
                'tool' => $toolName,
                'error' => $e->getMessage(),
            ]);

            throw new MCPException(
                "Failed to execute tool '{$toolName}' on server '{$serverName}': {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Discover and cache tools from all configured servers.
     */
    public function discoverTools(bool $forceRefresh = false): array
    {
        $cacheKey = 'mcp_tools_discovery';
        $cacheTtl = config('ai.mcp.tool_discovery_cache_ttl', 3600);

        if (!$forceRefresh && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $discoveredTools = [];
        $stats = ['servers_checked' => 0, 'tools_found' => 0, 'errors' => 0];

        foreach ($this->getEnabledServers() as $name => $server) {
            $stats['servers_checked']++;

            try {
                $tools = $server->getAvailableTools();
                $discoveredTools[$name] = [
                    'tools' => $tools,
                    'server_info' => [
                        'name' => $server->getDisplayName(),
                        'description' => $server->getDescription(),
                        'type' => $server->getType(),
                        'version' => $server->getVersion(),
                    ],
                    'discovered_at' => now()->toISOString(),
                ];
                $stats['tools_found'] += count($tools);
            } catch (\Exception $e) {
                $stats['errors']++;
                Log::error('Tool discovery failed for MCP server', [
                    'server' => $name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Update tools cache
        $this->tools = $discoveredTools;
        $this->saveToolsConfiguration();

        $result = [
            'tools' => $discoveredTools,
            'statistics' => $stats,
            'discovered_at' => now()->toISOString(),
        ];

        Cache::put($cacheKey, $result, $cacheTtl);

        return $result;
    }

    /**
     * Test connectivity for all or specific MCP servers.
     */
    public function testServers(string $serverName = null): array
    {
        $serversToTest = $serverName
            ? [$serverName => $this->getServer($serverName)]
            : $this->getEnabledServers();

        $results = [];

        foreach ($serversToTest as $name => $server) {
            if (!$server) {
                $results[$name] = [
                    'status' => 'error',
                    'message' => 'Server not found or not enabled',
                    'tested_at' => now()->toISOString(),
                ];
                continue;
            }

            try {
                $startTime = microtime(true);
                $testResult = $server->testConnection();
                $testTime = (microtime(true) - $startTime) * 1000;

                $results[$name] = array_merge($testResult, [
                    'test_time_ms' => round($testTime, 2),
                    'tested_at' => now()->toISOString(),
                ]);
            } catch (\Exception $e) {
                $results[$name] = [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                    'tested_at' => now()->toISOString(),
                ];
            }
        }

        return $results;
    }

    /**
     * Get performance metrics for all or specific MCP servers.
     */
    public function getMetrics(string $serverName = null): array
    {
        $serversToCheck = $serverName
            ? [$serverName => $this->getServer($serverName)]
            : $this->getEnabledServers();

        $metrics = [];

        foreach ($serversToCheck as $name => $server) {
            if (!$server) {
                continue;
            }

            try {
                $metrics[$name] = $server->getMetrics();
            } catch (\Exception $e) {
                Log::error('Failed to get metrics for MCP server', [
                    'server' => $name,
                    'error' => $e->getMessage(),
                ]);

                $metrics[$name] = [
                    'status' => 'error',
                    'message' => 'Failed to retrieve metrics',
                ];
            }
        }

        return $metrics;
    }

    /**
     * Enable an MCP server.
     */
    public function enableServer(string $name): bool
    {
        if (!isset($this->config['servers'][$name])) {
            return false;
        }

        $this->config['servers'][$name]['enabled'] = true;
        $this->saveConfiguration();

        // Re-register the server if it exists
        if (isset($this->servers[$name])) {
            $this->registerExternalServer($name, $this->config['servers'][$name]);
        }

        return true;
    }

    /**
     * Disable an MCP server.
     */
    public function disableServer(string $name): bool
    {
        if (!isset($this->config['servers'][$name])) {
            return false;
        }

        $this->config['servers'][$name]['enabled'] = false;
        $this->saveConfiguration();

        // Unregister the server
        $this->unregisterServer($name);

        return true;
    }

    /**
     * Get the current MCP configuration.
     */
    public function getConfiguration(): array
    {
        return $this->config;
    }

    /**
     * Update MCP configuration.
     */
    public function updateConfiguration(array $config): bool
    {
        $validation = $this->validateConfiguration($config);

        if (!empty($validation['errors'])) {
            return false;
        }

        $this->config = $config;
        $this->saveConfiguration();
        $this->registerServersFromConfig();

        return true;
    }

    /**
     * Validate MCP configuration.
     */
    public function validateConfiguration(array $config): array
    {
        $errors = [];
        $warnings = [];

        // Validate structure
        if (!isset($config['servers']) || !is_array($config['servers'])) {
            $errors[] = 'Configuration must contain a "servers" array';
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        // Validate each server configuration
        foreach ($config['servers'] as $name => $serverConfig) {
            if (!is_array($serverConfig)) {
                $errors[] = "Server '{$name}' configuration must be an array";
                continue;
            }

            // Required fields
            $requiredFields = ['type', 'enabled'];
            foreach ($requiredFields as $field) {
                if (!isset($serverConfig[$field])) {
                    $errors[] = "Server '{$name}' missing required field: {$field}";
                }
            }

            // Validate server type
            if (isset($serverConfig['type']) && !in_array($serverConfig['type'], ['external'])) {
                $errors[] = "Server '{$name}' has invalid type: {$serverConfig['type']}";
            }

            // Validate external server configuration
            if (($serverConfig['type'] ?? '') === 'external') {
                if (empty($serverConfig['command'])) {
                    $errors[] = "External server '{$name}' missing required 'command' field";
                }
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Save configuration to file.
     */
    protected function saveConfiguration(): void
    {
        try {
            File::put($this->configPath, json_encode($this->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } catch (\Exception $e) {
            Log::error('Failed to save MCP configuration', [
                'file' => $this->configPath,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Save tools configuration to file.
     */
    protected function saveToolsConfiguration(): void
    {
        try {
            File::put($this->toolsPath, json_encode($this->tools, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } catch (\Exception $e) {
            Log::error('Failed to save MCP tools configuration', [
                'file' => $this->toolsPath,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
