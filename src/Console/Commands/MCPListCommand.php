<?php

namespace JTD\LaravelAI\Console\Commands;

use Illuminate\Console\Command;
use JTD\LaravelAI\Services\MCPConfigurationService;
use JTD\LaravelAI\Services\MCPManager;
use JTD\LaravelAI\Services\MCPServerInstaller;

/**
 * MCP List Command
 *
 * Lists configured MCP servers with their status and information.
 */
class MCPListCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ai:mcp:list
                            {--available : Show available servers for installation}
                            {--tools : Show tools for each server}
                            {--json : Output results as JSON}
                            {--status : Show server status}';

    /**
     * The console command description.
     */
    protected $description = 'List configured MCP servers and their status';

    /**
     * MCP Manager service.
     */
    protected MCPManager $mcpManager;

    /**
     * MCP Configuration service.
     */
    protected MCPConfigurationService $configService;

    /**
     * MCP Server Installer service.
     */
    protected MCPServerInstaller $installer;

    /**
     * Create a new command instance.
     */
    public function __construct(
        MCPManager $mcpManager,
        MCPConfigurationService $configService,
        MCPServerInstaller $installer
    ) {
        parent::__construct();

        $this->mcpManager = $mcpManager;
        $this->configService = $configService;
        $this->installer = $installer;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $showAvailable = $this->option('available');
        $showTools = $this->option('tools');
        $jsonOutput = $this->option('json');
        $showStatus = $this->option('status');

        try {
            if ($showAvailable) {
                return $this->listAvailableServers($jsonOutput);
            } else {
                return $this->listConfiguredServers($showTools, $showStatus, $jsonOutput);
            }
        } catch (\Exception $e) {
            $this->error("Failed to list servers: {$e->getMessage()}");

            return 1;
        }
    }

    /**
     * List available servers for installation.
     */
    protected function listAvailableServers(bool $jsonOutput): int
    {
        $availableServers = $this->installer->getAvailableServers();

        if ($jsonOutput) {
            $this->line(json_encode($availableServers, JSON_PRETTY_PRINT));

            return 0;
        }

        $this->info('📦 Available MCP Servers for Installation:');
        $this->line('');

        foreach ($availableServers as $key => $server) {
            $this->line("  <fg=cyan>{$key}</>");
            $serverName = $server['name'] ?? 'Unknown';
            $serverDescription = $server['description'] ?? 'No description';
            $serverPackage = $server['package'] ?? 'Unknown';
            $this->line("    Name: {$serverName}");
            $this->line("    Description: {$serverDescription}");
            $this->line("    Package: {$serverPackage}");
            $this->line('    Requires API Key: ' . ($server['requires_api_key'] ? 'Yes' : 'No'));

            if ($server['requires_api_key'] && isset($server['api_key_name'])) {
                $this->line("    API Key: {$server['api_key_name']}");
            }

            // Check if already installed
            $installStatus = $this->installer->isServerInstalled($key);
            if ($installStatus['installed']) {
                $version = $installStatus['version'] ?? 'unknown';
                $this->line("    <fg=green>Status: Installed (v{$version})</>");
            } else {
                $this->line('    <fg=yellow>Status: Not installed</>');
            }

            $this->line('');
        }

        $this->info('To install a server, run: php artisan ai:mcp:setup <server-key>');

        return 0;
    }

    /**
     * List configured servers.
     */
    protected function listConfiguredServers(bool $showTools, bool $showStatus, bool $jsonOutput): int
    {
        $config = $this->configService->loadConfiguration();
        $configuredServers = $config['servers'] ?? [];

        if (empty($configuredServers)) {
            if (! $jsonOutput) {
                $this->warn('No MCP servers configured.');
                $this->line('Run "php artisan ai:mcp:setup" to configure servers.');
            }

            return 0;
        }

        $serverData = [];

        foreach ($configuredServers as $name => $serverConfig) {
            $server = $this->mcpManager->getServer($name);

            $data = [
                'name' => $name,
                'config' => $serverConfig,
                'is_loaded' => $server !== null,
            ];

            if ($server) {
                $data['server_info'] = [
                    'display_name' => $server->getDisplayName(),
                    'description' => $server->getDescription(),
                    'type' => $server->getType(),
                    'version' => $server->getVersion(),
                    'is_configured' => $server->isConfigured(),
                    'is_enabled' => $server->isEnabled(),
                ];

                if ($showStatus) {
                    try {
                        $testResult = $server->testConnection();
                        $data['status'] = $testResult;
                    } catch (\Exception $e) {
                        $data['status'] = [
                            'status' => 'error',
                            'message' => $e->getMessage(),
                        ];
                    }
                }

                if ($showTools) {
                    try {
                        $tools = $server->getAvailableTools();
                        $data['tools'] = $tools;
                    } catch (\Exception $e) {
                        $data['tools_error'] = $e->getMessage();
                    }
                }
            }

            $serverData[$name] = $data;
        }

        if ($jsonOutput) {
            $this->line(json_encode([
                'total_servers' => count($serverData),
                'servers' => $serverData,
            ], JSON_PRETTY_PRINT));

            return 0;
        }

        $this->displayConfiguredServers($serverData, $showTools, $showStatus);

        return 0;
    }

    /**
     * Display configured servers.
     */
    protected function displayConfiguredServers(array $serverData, bool $showTools, bool $showStatus): void
    {
        $this->info('🤖 Configured MCP Servers:');
        $this->line('');

        foreach ($serverData as $name => $data) {
            $config = $data['config'];
            $isLoaded = $data['is_loaded'];
            $serverInfo = $data['server_info'] ?? null;

            // Server header
            $statusIcon = $isLoaded ? '✅' : '❌';
            $this->line("  {$statusIcon} <fg=cyan>{$name}</>");

            // Basic info
            if ($serverInfo) {
                $this->line("    Display Name: {$serverInfo['display_name']}");
                $this->line("    Description: {$serverInfo['description']}");
                $this->line("    Type: {$serverInfo['type']}");
                $this->line("    Version: {$serverInfo['version']}");
            }

            // Configuration details
            $this->line('    Enabled: ' . ($config['enabled'] ? 'Yes' : 'No'));

            if (isset($config['command'])) {
                $this->line("    Command: {$config['command']}");
            }

            if (isset($config['timeout'])) {
                $this->line("    Timeout: {$config['timeout']}s");
            }

            if (! empty($config['env'])) {
                $envVars = array_keys($config['env']);
                $this->line('    Environment Variables: ' . implode(', ', $envVars));
            }

            // Server status
            if ($showStatus && isset($data['status'])) {
                $status = $data['status'];
                $statusText = ucfirst($status['status']);
                $statusColor = $this->getStatusColor($status['status']);

                $this->line("    <fg={$statusColor}>Status: {$statusText}</>");

                if (isset($status['response_time_ms'])) {
                    $this->line("    Response Time: {$status['response_time_ms']}ms");
                }

                if ($status['status'] !== 'healthy' && isset($status['message'])) {
                    $this->line("    <fg=red>Error: {$status['message']}</>");
                }
            }

            // Tools
            if ($showTools) {
                if (isset($data['tools'])) {
                    $tools = $data['tools'];
                    $toolCount = count($tools);

                    $this->line("    Tools: {$toolCount} available");

                    if ($toolCount > 0) {
                        foreach (array_slice($tools, 0, 3) as $tool) {
                            $toolName = $tool['name'] ?? 'Unknown';
                            $this->line("      • {$toolName}");
                        }

                        if ($toolCount > 3) {
                            $remaining = $toolCount - 3;
                            $this->line("      ... and {$remaining} more");
                        }
                    }
                } elseif (isset($data['tools_error'])) {
                    $this->line("    <fg=red>Tools: Error loading - {$data['tools_error']}</>");
                } else {
                    $this->line('    Tools: Not loaded');
                }
            }

            $this->line('');
        }

        // Summary
        $totalServers = count($serverData);
        $enabledServers = count(array_filter($serverData, fn ($data) => $data['config']['enabled'] ?? false));
        $loadedServers = count(array_filter($serverData, fn ($data) => $data['is_loaded']));

        $this->info('📊 Summary:');
        $this->line("   Total servers: {$totalServers}");
        $this->line("   Enabled: {$enabledServers}");
        $this->line("   Loaded: {$loadedServers}");

        if ($loadedServers < $enabledServers) {
            $this->line('');
            $this->warn('Some enabled servers are not loaded. Check configuration and installation.');
        }
    }

    /**
     * Get status color for display.
     */
    protected function getStatusColor(string $status): string
    {
        return match ($status) {
            'healthy' => 'green',
            'disabled' => 'yellow',
            'error' => 'red',
            default => 'gray',
        };
    }
}
