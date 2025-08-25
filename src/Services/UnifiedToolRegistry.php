<?php

namespace JTD\LaravelAI\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Exceptions\ToolNotFoundException;

/**
 * Unified Tool Registry Service
 *
 * Combines MCP tools (from .mcp.tools.json) and Function Events into a single
 * discoverable registry. Provides unified tool lookup by name with metadata
 * about tool types and execution modes.
 */
class UnifiedToolRegistry
{
    /**
     * MCP Configuration service.
     */
    protected MCPConfigurationService $mcpConfigService;

    /**
     * MCP Tool Discovery service.
     */
    protected MCPToolDiscoveryService $mcpDiscoveryService;

    /**
     * Cache key for unified tool registry.
     */
    protected string $cacheKey = 'unified_tool_registry';

    /**
     * Cache TTL in seconds.
     */
    protected int $cacheTtl = 3600;

    /**
     * Create a new unified tool registry instance.
     */
    public function __construct(
        MCPConfigurationService $mcpConfigService,
        MCPToolDiscoveryService $mcpDiscoveryService
    ) {
        $this->mcpConfigService = $mcpConfigService;
        $this->mcpDiscoveryService = $mcpDiscoveryService;
    }

    /**
     * Get all available tools (MCP + Function Events).
     */
    public function getAllTools(bool $forceRefresh = false): array
    {
        if (!$forceRefresh && Cache::has($this->cacheKey)) {
            return Cache::get($this->cacheKey);
        }

        $allTools = [];

        // Get MCP tools
        $mcpTools = $this->getMCPTools();
        foreach ($mcpTools as $toolName => $toolData) {
            $allTools[$toolName] = array_merge($toolData, [
                'type' => 'mcp_tool',
                'execution_mode' => 'immediate',
                'source' => 'mcp',
            ]);
        }

        // Get Function Events
        $functionEvents = $this->getFunctionEvents();
        foreach ($functionEvents as $functionName => $functionData) {
            $allTools[$functionName] = array_merge($functionData, [
                'type' => 'function_event',
                'execution_mode' => 'background',
                'source' => 'function_event',
            ]);
        }

        // Cache the combined tools
        Cache::put($this->cacheKey, $allTools, $this->cacheTtl);

        Log::debug('Unified tool registry refreshed', [
            'mcp_tools' => count($mcpTools),
            'function_events' => count($functionEvents),
            'total_tools' => count($allTools),
        ]);

        return $allTools;
    }

    /**
     * Get a specific tool by name.
     */
    public function getTool(string $name): ?array
    {
        $allTools = $this->getAllTools();
        
        return $allTools[$name] ?? null;
    }

    /**
     * Get tools by type (mcp_tool or function_event).
     */
    public function getToolsByType(string $type): array
    {
        $allTools = $this->getAllTools();
        
        return array_filter($allTools, function ($tool) use ($type) {
            return ($tool['type'] ?? '') === $type;
        });
    }

    /**
     * Check if a tool exists.
     */
    public function hasTool(string $name): bool
    {
        return $this->getTool($name) !== null;
    }

    /**
     * Validate that all provided tool names exist.
     */
    public function validateToolNames(array $toolNames): array
    {
        $allTools = $this->getAllTools();
        $missing = [];

        foreach ($toolNames as $toolName) {
            if (!isset($allTools[$toolName])) {
                $missing[] = $toolName;
            }
        }

        return $missing;
    }

    /**
     * Get tool names by execution mode.
     */
    public function getToolsByExecutionMode(string $mode): array
    {
        $allTools = $this->getAllTools();
        
        return array_filter($allTools, function ($tool) use ($mode) {
            return ($tool['execution_mode'] ?? '') === $mode;
        });
    }

    /**
     * Refresh the tool cache.
     */
    public function refreshCache(): void
    {
        Cache::forget($this->cacheKey);
        $this->getAllTools(true);
    }

    /**
     * Get MCP tools from configuration.
     */
    protected function getMCPTools(): array
    {
        $mcpTools = [];
        $toolsConfig = $this->mcpConfigService->loadToolsConfiguration();

        foreach ($toolsConfig as $serverName => $serverData) {
            $tools = $serverData['tools'] ?? [];
            
            foreach ($tools as $tool) {
                $toolName = $tool['name'] ?? null;
                if (!$toolName) {
                    continue;
                }

                $mcpTools[$toolName] = [
                    'name' => $toolName,
                    'description' => $tool['description'] ?? '',
                    'parameters' => $this->normalizeMCPParameters($tool),
                    'server' => $serverName,
                    'category' => $tool['category'] ?? 'general',
                    'version' => $tool['version'] ?? '1.0.0',
                ];
            }
        }

        return $mcpTools;
    }

    /**
     * Get Function Events from AIFunctionEvent service.
     */
    protected function getFunctionEvents(): array
    {
        $functionEvents = [];
        $registeredFunctions = AIFunctionEvent::getRegisteredFunctions();

        foreach ($registeredFunctions as $functionName => $definition) {
            $functionEvents[$functionName] = [
                'name' => $functionName,
                'description' => $definition['description'] ?? '',
                'parameters' => $definition['parameters'] ?? [
                    'type' => 'object',
                    'properties' => [],
                ],
                'category' => 'function_event',
                'version' => '1.0.0',
            ];
        }

        return $functionEvents;
    }

    /**
     * Normalize MCP tool parameters to standard format.
     */
    protected function normalizeMCPParameters(array $tool): array
    {
        // Handle different MCP parameter formats
        if (isset($tool['inputSchema'])) {
            return $tool['inputSchema'];
        }

        if (isset($tool['input_schema'])) {
            return $tool['input_schema'];
        }

        if (isset($tool['parameters'])) {
            return $tool['parameters'];
        }

        // Default empty schema
        return [
            'type' => 'object',
            'properties' => [],
        ];
    }

    /**
     * Search tools by name or description.
     */
    public function searchTools(string $query): array
    {
        $allTools = $this->getAllTools();
        $results = [];

        foreach ($allTools as $toolName => $tool) {
            $name = $tool['name'] ?? '';
            $description = $tool['description'] ?? '';
            
            if (str_contains(strtolower($name), strtolower($query)) ||
                str_contains(strtolower($description), strtolower($query))) {
                $results[$toolName] = $tool;
            }
        }

        return $results;
    }

    /**
     * Get tool statistics.
     */
    public function getStats(): array
    {
        $allTools = $this->getAllTools();
        
        $stats = [
            'total_tools' => count($allTools),
            'mcp_tools' => 0,
            'function_events' => 0,
            'immediate_execution' => 0,
            'background_execution' => 0,
            'categories' => [],
        ];

        foreach ($allTools as $tool) {
            // Count by type
            if (($tool['type'] ?? '') === 'mcp_tool') {
                $stats['mcp_tools']++;
            } elseif (($tool['type'] ?? '') === 'function_event') {
                $stats['function_events']++;
            }

            // Count by execution mode
            if (($tool['execution_mode'] ?? '') === 'immediate') {
                $stats['immediate_execution']++;
            } elseif (($tool['execution_mode'] ?? '') === 'background') {
                $stats['background_execution']++;
            }

            // Count by category
            $category = $tool['category'] ?? 'general';
            $stats['categories'][$category] = ($stats['categories'][$category] ?? 0) + 1;
        }

        return $stats;
    }
}
