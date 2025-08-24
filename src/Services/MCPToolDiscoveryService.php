<?php

namespace JTD\LaravelAI\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Contracts\MCPManagerInterface;
use JTD\LaravelAI\Exceptions\MCPException;

/**
 * MCP Tool Discovery Service
 *
 * Handles discovery, caching, and validation of tools from MCP servers.
 * Generates and maintains the .mcp.tools.json file with available tools.
 */
class MCPToolDiscoveryService
{
    /**
     * MCP Manager instance.
     */
    protected MCPManagerInterface $mcpManager;

    /**
     * MCP Configuration service.
     */
    protected MCPConfigurationService $configService;

    /**
     * Cache key prefix for tool discovery.
     */
    protected string $cachePrefix = 'mcp_tool_discovery';

    /**
     * Default cache TTL in seconds.
     */
    protected int $defaultCacheTtl = 3600;

    /**
     * Create a new tool discovery service instance.
     */
    public function __construct(
        MCPManagerInterface $mcpManager,
        MCPConfigurationService $configService
    ) {
        $this->mcpManager = $mcpManager;
        $this->configService = $configService;
    }

    /**
     * Discover tools from all enabled MCP servers.
     */
    public function discoverAllTools(bool $forceRefresh = false): array
    {
        $cacheKey = "{$this->cachePrefix}_all";
        $cacheTtl = config('ai.mcp.tool_discovery_cache_ttl', $this->defaultCacheTtl);

        if (!$forceRefresh && Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            Log::debug('Retrieved tools from cache', ['servers' => array_keys($cached['tools'])]);
            return $cached;
        }

        $discoveredTools = [];
        $stats = [
            'servers_checked' => 0,
            'servers_successful' => 0,
            'servers_failed' => 0,
            'tools_found' => 0,
            'errors' => [],
        ];

        $enabledServers = $this->mcpManager->getEnabledServers();
        
        foreach ($enabledServers as $name => $server) {
            $stats['servers_checked']++;
            
            try {
                $serverTools = $this->discoverServerTools($name, $forceRefresh);
                
                if (!empty($serverTools['tools'])) {
                    $discoveredTools[$name] = $serverTools;
                    $stats['servers_successful']++;
                    $stats['tools_found'] += count($serverTools['tools']);
                    
                    Log::info('Discovered tools from MCP server', [
                        'server' => $name,
                        'tool_count' => count($serverTools['tools']),
                    ]);
                } else {
                    $stats['servers_failed']++;
                    $stats['errors'][] = "No tools found for server: {$name}";
                }
            } catch (\Exception $e) {
                $stats['servers_failed']++;
                $stats['errors'][] = "Failed to discover tools from {$name}: {$e->getMessage()}";
                
                Log::error('Tool discovery failed for MCP server', [
                    'server' => $name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $result = [
            'tools' => $discoveredTools,
            'statistics' => $stats,
            'discovered_at' => now()->toISOString(),
            'cache_ttl' => $cacheTtl,
        ];

        // Save to tools configuration file
        $this->configService->saveToolsConfiguration($discoveredTools);

        // Cache the result
        Cache::put($cacheKey, $result, $cacheTtl);

        Log::info('Tool discovery completed', [
            'servers_checked' => $stats['servers_checked'],
            'servers_successful' => $stats['servers_successful'],
            'total_tools' => $stats['tools_found'],
        ]);

        return $result;
    }

    /**
     * Discover tools from a specific MCP server.
     */
    public function discoverServerTools(string $serverName, bool $forceRefresh = false): array
    {
        $server = $this->mcpManager->getServer($serverName);
        
        if (!$server) {
            throw new MCPException("MCP server '{$serverName}' not found");
        }

        if (!$server->isEnabled() || !$server->isConfigured()) {
            throw new MCPException("MCP server '{$serverName}' is not enabled or configured");
        }

        $cacheKey = "{$this->cachePrefix}_server_{$serverName}";
        $cacheTtl = config('ai.mcp.tool_discovery_cache_ttl', $this->defaultCacheTtl);

        if (!$forceRefresh && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $startTime = microtime(true);
            $tools = $server->getAvailableTools();
            $discoveryTime = (microtime(true) - $startTime) * 1000;

            $serverInfo = [
                'tools' => $this->validateAndNormalizeTools($tools),
                'server_info' => [
                    'name' => $server->getDisplayName(),
                    'description' => $server->getDescription(),
                    'type' => $server->getType(),
                    'version' => $server->getVersion(),
                    'is_configured' => $server->isConfigured(),
                    'is_enabled' => $server->isEnabled(),
                ],
                'discovery_info' => [
                    'discovered_at' => now()->toISOString(),
                    'discovery_time_ms' => round($discoveryTime, 2),
                    'tool_count' => count($tools),
                ],
            ];

            Cache::put($cacheKey, $serverInfo, $cacheTtl);

            return $serverInfo;
        } catch (\Exception $e) {
            throw new MCPException(
                "Failed to discover tools from server '{$serverName}': {$e->getMessage()}",
                0,
                $e,
                $serverName
            );
        }
    }

    /**
     * Validate and normalize tool definitions.
     */
    protected function validateAndNormalizeTools(array $tools): array
    {
        $normalizedTools = [];

        foreach ($tools as $tool) {
            try {
                $normalizedTool = $this->validateToolDefinition($tool);
                $normalizedTools[] = $normalizedTool;
            } catch (\Exception $e) {
                Log::warning('Invalid tool definition skipped', [
                    'tool' => $tool['name'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $normalizedTools;
    }

    /**
     * Validate a single tool definition.
     */
    protected function validateToolDefinition(array $tool): array
    {
        // Required fields
        $requiredFields = ['name', 'description'];
        foreach ($requiredFields as $field) {
            if (!isset($tool[$field]) || empty($tool[$field])) {
                throw new \InvalidArgumentException("Tool missing required field: {$field}");
            }
        }

        // Normalize the tool definition
        $normalized = [
            'name' => $tool['name'],
            'description' => $tool['description'],
            'inputSchema' => $tool['inputSchema'] ?? $tool['input_schema'] ?? [],
            'outputSchema' => $tool['outputSchema'] ?? $tool['output_schema'] ?? [],
        ];

        // Add optional fields if present
        $optionalFields = [
            'category', 'version', 'examples', 'requires_auth',
            'estimated_execution_time', 'supports_batch'
        ];

        foreach ($optionalFields as $field) {
            if (isset($tool[$field])) {
                $normalized[$field] = $tool[$field];
            }
        }

        // Validate input schema if present
        if (!empty($normalized['inputSchema'])) {
            $this->validateJsonSchema($normalized['inputSchema'], "Tool '{$normalized['name']}' input schema");
        }

        return $normalized;
    }

    /**
     * Basic JSON Schema validation.
     */
    protected function validateJsonSchema(array $schema, string $context): void
    {
        if (!isset($schema['type'])) {
            throw new \InvalidArgumentException("{$context} must have a 'type' field");
        }

        $validTypes = ['object', 'array', 'string', 'number', 'integer', 'boolean', 'null'];
        if (!in_array($schema['type'], $validTypes)) {
            throw new \InvalidArgumentException("{$context} has invalid type: {$schema['type']}");
        }

        // Additional validation for object types
        if ($schema['type'] === 'object' && isset($schema['properties'])) {
            if (!is_array($schema['properties'])) {
                throw new \InvalidArgumentException("{$context} properties must be an array");
            }
        }
    }

    /**
     * Get cached tools for a specific server.
     */
    public function getCachedServerTools(string $serverName): ?array
    {
        $cacheKey = "{$this->cachePrefix}_server_{$serverName}";
        return Cache::get($cacheKey);
    }

    /**
     * Clear tool discovery cache.
     */
    public function clearCache(string $serverName = null): bool
    {
        if ($serverName) {
            $cacheKey = "{$this->cachePrefix}_server_{$serverName}";
            return Cache::forget($cacheKey);
        }

        // Clear all tool discovery cache
        $allCacheKey = "{$this->cachePrefix}_all";
        $cleared = Cache::forget($allCacheKey);

        // Clear individual server caches
        $enabledServers = $this->mcpManager->getEnabledServers();
        foreach (array_keys($enabledServers) as $name) {
            $serverCacheKey = "{$this->cachePrefix}_server_{$name}";
            Cache::forget($serverCacheKey);
        }

        return $cleared;
    }

    /**
     * Get tool discovery statistics.
     */
    public function getDiscoveryStatistics(): array
    {
        $cacheKey = "{$this->cachePrefix}_all";
        $cached = Cache::get($cacheKey);

        if (!$cached) {
            return [
                'status' => 'no_discovery_data',
                'message' => 'No tool discovery data available. Run discovery first.',
            ];
        }

        return [
            'status' => 'available',
            'last_discovery' => $cached['discovered_at'],
            'statistics' => $cached['statistics'],
            'servers' => array_keys($cached['tools']),
            'cache_expires_at' => now()->addSeconds($cached['cache_ttl'] ?? $this->defaultCacheTtl)->toISOString(),
        ];
    }

    /**
     * Search for tools by name or description.
     */
    public function searchTools(string $query, string $serverName = null): array
    {
        $allTools = $this->configService->loadToolsConfiguration();
        $results = [];

        $serversToSearch = $serverName ? [$serverName => $allTools[$serverName] ?? []] : $allTools;

        foreach ($serversToSearch as $server => $serverData) {
            $tools = $serverData['tools'] ?? [];
            
            foreach ($tools as $tool) {
                $name = $tool['name'] ?? '';
                $description = $tool['description'] ?? '';
                
                if (str_contains(strtolower($name), strtolower($query)) ||
                    str_contains(strtolower($description), strtolower($query))) {
                    $results[] = array_merge($tool, ['server' => $server]);
                }
            }
        }

        return [
            'query' => $query,
            'server_filter' => $serverName,
            'results' => $results,
            'result_count' => count($results),
        ];
    }
}
