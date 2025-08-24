<?php

namespace JTD\LaravelAI\Console\Commands;

use Illuminate\Console\Command;
use JTD\LaravelAI\Services\MCPManager;
use JTD\LaravelAI\Services\MCPToolDiscoveryService;

/**
 * MCP Discover Command
 *
 * Discovers and caches tools from configured MCP servers.
 */
class MCPDiscoverCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ai:mcp:discover
                            {server? : Specific server to discover tools from}
                            {--force : Force refresh of cached tools}
                            {--show-tools : Display discovered tools}
                            {--json : Output results as JSON}';

    /**
     * The console command description.
     */
    protected $description = 'Discover tools from configured MCP servers';

    /**
     * MCP Manager service.
     */
    protected MCPManager $mcpManager;

    /**
     * MCP Tool Discovery service.
     */
    protected MCPToolDiscoveryService $discoveryService;

    /**
     * Create a new command instance.
     */
    public function __construct(MCPManager $mcpManager, MCPToolDiscoveryService $discoveryService)
    {
        parent::__construct();

        $this->mcpManager = $mcpManager;
        $this->discoveryService = $discoveryService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $serverName = $this->argument('server');
        $forceRefresh = $this->option('force');
        $showTools = $this->option('show-tools');
        $jsonOutput = $this->option('json');

        try {
            if ($serverName) {
                return $this->discoverServerTools($serverName, $forceRefresh, $showTools, $jsonOutput);
            } else {
                return $this->discoverAllTools($forceRefresh, $showTools, $jsonOutput);
            }
        } catch (\Exception $e) {
            $this->error("Tool discovery failed: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Discover tools from all servers.
     */
    protected function discoverAllTools(bool $forceRefresh, bool $showTools, bool $jsonOutput): int
    {
        if (!$jsonOutput) {
            $this->info('🔍 Discovering tools from all MCP servers...');
            if ($forceRefresh) {
                $this->line('   Force refresh enabled - ignoring cache');
            }
        }

        $result = $this->discoveryService->discoverAllTools($forceRefresh);

        if ($jsonOutput) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));
            return 0;
        }

        $stats = $result['statistics'];

        $this->info("✅ Tool discovery completed!");
        $this->line("   Servers checked: {$stats['servers_checked']}");
        $this->line("   Servers successful: {$stats['servers_successful']}");
        $this->line("   Total tools found: {$stats['tools_found']}");

        if ($stats['servers_failed'] > 0) {
            $this->warn("   Servers failed: {$stats['servers_failed']}");

            if (!empty($stats['errors'])) {
                $this->line('');
                $this->warn('Errors encountered:');
                foreach ($stats['errors'] as $error) {
                    $this->line("   • {$error}");
                }
            }
        }

        if ($showTools && !empty($result['tools'])) {
            $this->displayDiscoveredTools($result['tools']);
        }

        $this->line('');
        $this->info('Tools have been cached in .mcp.tools.json');

        return 0;
    }

    /**
     * Discover tools from a specific server.
     */
    protected function discoverServerTools(string $serverName, bool $forceRefresh, bool $showTools, bool $jsonOutput): int
    {
        if (!$jsonOutput) {
            $this->info("🔍 Discovering tools from server '{$serverName}'...");
        }

        $result = $this->discoveryService->discoverServerTools($serverName, $forceRefresh);

        if ($jsonOutput) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));
            return 0;
        }

        $tools = $result['tools'] ?? [];
        $toolCount = count($tools);
        $discoveryTime = $result['discovery_info']['discovery_time_ms'] ?? 0;

        $this->info("✅ Discovered {$toolCount} tool(s) from '{$serverName}'");
        $this->line("   Discovery time: {$discoveryTime}ms");

        if ($showTools && !empty($tools)) {
            $this->displayServerTools($serverName, $tools);
        }

        return 0;
    }

    /**
     * Display discovered tools from all servers.
     */
    protected function displayDiscoveredTools(array $serversTools): void
    {
        $this->line('');
        $this->info('📋 Discovered Tools:');

        foreach ($serversTools as $serverName => $serverData) {
            $tools = $serverData['tools'] ?? [];
            $serverInfo = $serverData['server_info'] ?? [];

            $this->line('');
            $serverDisplayName = $serverInfo['name'] ?? 'Unknown';
            $this->line("  <fg=cyan>{$serverName}</> ({$serverDisplayName})");
            $serverType = $serverInfo['type'] ?? 'unknown';
            $serverVersion = $serverInfo['version'] ?? 'unknown';
            $this->line("    Type: {$serverType}");
            $this->line("    Version: {$serverVersion}");

            if (empty($tools)) {
                $this->line("    <fg=yellow>No tools available</>");
                continue;
            }

            foreach ($tools as $tool) {
                $name = $tool['name'] ?? 'Unknown';
                $description = $tool['description'] ?? 'No description';
                $this->line("    • <fg=green>{$name}</> - {$description}");
            }
        }
    }

    /**
     * Display tools from a specific server.
     */
    protected function displayServerTools(string $serverName, array $tools): void
    {
        $this->line('');
        $this->info("📋 Tools from '{$serverName}':");

        if (empty($tools)) {
            $this->line("   <fg=yellow>No tools available</>");
            return;
        }

        foreach ($tools as $tool) {
            $name = $tool['name'] ?? 'Unknown';
            $description = $tool['description'] ?? 'No description';
            $category = $tool['category'] ?? null;

            $this->line("   • <fg=green>{$name}</> - {$description}");

            if ($category) {
                $this->line("     Category: {$category}");
            }

            if (isset($tool['inputSchema']) && !empty($tool['inputSchema'])) {
                $this->line("     Has input schema: Yes");
            }

            if (isset($tool['requires_auth']) && $tool['requires_auth']) {
                $this->line("     Requires authentication: Yes");
            }
        }
    }
}
