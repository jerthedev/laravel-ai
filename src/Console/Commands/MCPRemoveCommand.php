<?php

namespace JTD\LaravelAI\Console\Commands;

use Illuminate\Console\Command;
use JTD\LaravelAI\Services\MCPConfigurationService;
use JTD\LaravelAI\Services\MCPManager;
use JTD\LaravelAI\Services\MCPServerInstaller;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\warning;

/**
 * MCP Remove Command
 *
 * Removes MCP server configuration and optionally uninstalls packages.
 */
class MCPRemoveCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ai:mcp:remove 
                            {server? : Server to remove}
                            {--uninstall : Also uninstall the npm package}
                            {--force : Skip confirmation prompts}
                            {--keep-config : Keep configuration but disable server}';

    /**
     * The console command description.
     */
    protected $description = 'Remove MCP server configuration and optionally uninstall packages';

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
        $serverName = $this->argument('server');
        $uninstall = $this->option('uninstall');
        $force = $this->option('force');
        $keepConfig = $this->option('keep-config');

        try {
            if ($serverName) {
                return $this->removeServer($serverName, $uninstall, $force, $keepConfig);
            } else {
                return $this->interactiveRemove($uninstall, $force, $keepConfig);
            }
        } catch (\Exception $e) {
            error("Failed to remove server: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Interactive server removal.
     */
    protected function interactiveRemove(bool $uninstall, bool $force, bool $keepConfig): int
    {
        $config = $this->configService->loadConfiguration();
        $servers = $config['servers'] ?? [];

        if (empty($servers)) {
            warning('No MCP servers configured.');
            return 0;
        }

        info('🗑️  MCP Server Removal');
        info('Select a server to remove:');

        $serverOptions = [];
        foreach ($servers as $name => $serverConfig) {
            $enabled = $serverConfig['enabled'] ? 'enabled' : 'disabled';
            $type = $serverConfig['type'] ?? 'unknown';
            $serverOptions[$name] = "{$name} ({$type}, {$enabled})";
        }

        $selectedServer = select(
            'Which server would you like to remove?',
            $serverOptions
        );

        return $this->removeServer($selectedServer, $uninstall, $force, $keepConfig);
    }

    /**
     * Remove a specific server.
     */
    protected function removeServer(string $serverName, bool $uninstall, bool $force, bool $keepConfig): int
    {
        $config = $this->configService->loadConfiguration();
        $serverConfig = $config['servers'][$serverName] ?? null;

        if (!$serverConfig) {
            error("Server '{$serverName}' not found in configuration.");
            return 1;
        }

        info("Removing server '{$serverName}'...");

        // Show server information
        $this->displayServerInfo($serverName, $serverConfig);

        // Confirmation
        if (!$force) {
            $action = $keepConfig ? 'disable' : 'remove';
            if (!confirm("Are you sure you want to {$action} this server?")) {
                info('Operation cancelled.');
                return 0;
            }
        }

        // Remove or disable configuration
        if ($keepConfig) {
            $success = $this->disableServer($serverName);
            $action = 'disabled';
        } else {
            $success = $this->removeServerConfiguration($serverName);
            $action = 'removed';
        }

        if (!$success) {
            error("Failed to {$action} server configuration.");
            return 1;
        }

        info("✅ Server configuration {$action} successfully.");

        // Uninstall package if requested
        if ($uninstall) {
            return $this->uninstallServerPackage($serverName, $force);
        }

        // Show next steps
        $this->showNextSteps($serverName, $keepConfig, $uninstall);

        return 0;
    }

    /**
     * Display server information.
     */
    protected function displayServerInfo(string $serverName, array $serverConfig): void
    {
        info('');
        info('📋 Server Information:');
        info("   Name: {$serverName}");
        info("   Type: " . ($serverConfig['type'] ?? 'unknown'));
        info("   Enabled: " . ($serverConfig['enabled'] ? 'Yes' : 'No'));
        
        if (isset($serverConfig['command'])) {
            info("   Command: {$serverConfig['command']}");
        }
        
        if (!empty($serverConfig['env'])) {
            $envVars = array_keys($serverConfig['env']);
            info("   Environment Variables: " . implode(', ', $envVars));
        }

        // Check if package is installed
        $template = $this->installer->getServerTemplate($serverName);
        if ($template) {
            $installStatus = $this->installer->isServerInstalled($serverName);
            $packageStatus = $installStatus['installed'] ? 'Installed' : 'Not installed';
            info("   Package Status: {$packageStatus}");
            
            if ($installStatus['installed'] && isset($installStatus['version'])) {
                info("   Package Version: {$installStatus['version']}");
            }
        }

        info('');
    }

    /**
     * Disable server in configuration.
     */
    protected function disableServer(string $serverName): bool
    {
        return $this->configService->updateServer($serverName, ['enabled' => false]);
    }

    /**
     * Remove server from configuration.
     */
    protected function removeServerConfiguration(string $serverName): bool
    {
        return $this->configService->removeServer($serverName);
    }

    /**
     * Uninstall server package.
     */
    protected function uninstallServerPackage(string $serverName, bool $force): int
    {
        $template = $this->installer->getServerTemplate($serverName);
        
        if (!$template) {
            warning("No installation template found for '{$serverName}'. Cannot uninstall package.");
            return 0;
        }

        $installStatus = $this->installer->isServerInstalled($serverName);
        
        if (!$installStatus['installed']) {
            info("Package for '{$serverName}' is not installed.");
            return 0;
        }

        info("Uninstalling package '{$template['package']}'...");

        // Confirmation for package uninstall
        if (!$force) {
            if (!confirm("This will uninstall the npm package '{$template['package']}'. Continue?")) {
                info('Package uninstall cancelled.');
                return 0;
            }
        }

        $result = $this->installer->uninstallServer($serverName);

        if ($result['success']) {
            info("✅ Package '{$template['package']}' uninstalled successfully.");
            return 0;
        } else {
            error("❌ Failed to uninstall package: {$result['error']}");
            return 1;
        }
    }

    /**
     * Show next steps after removal.
     */
    protected function showNextSteps(string $serverName, bool $keepConfig, bool $uninstall): void
    {
        info('');
        info('📝 Next Steps:');

        if ($keepConfig) {
            info("• Server '{$serverName}' has been disabled but configuration is preserved");
            info("• To re-enable: Update .mcp.json or run setup again");
        } else {
            info("• Server '{$serverName}' has been completely removed from configuration");
            info("• Configuration file .mcp.json has been updated");
        }

        if (!$uninstall) {
            $template = $this->installer->getServerTemplate($serverName);
            if ($template) {
                $installStatus = $this->installer->isServerInstalled($serverName);
                if ($installStatus['installed']) {
                    info("• The npm package '{$template['package']}' is still installed");
                    info("• To uninstall: php artisan ai:mcp:remove {$serverName} --uninstall");
                }
            }
        }

        info("• Run 'php artisan ai:mcp:list' to see remaining servers");
        info("• Run 'php artisan ai:mcp:discover --force' to refresh tool cache");
        info('');
    }

    /**
     * List servers for removal selection.
     */
    protected function listServersForRemoval(): array
    {
        $config = $this->configService->loadConfiguration();
        $servers = $config['servers'] ?? [];

        $serverList = [];
        foreach ($servers as $name => $serverConfig) {
            $enabled = $serverConfig['enabled'] ? 'enabled' : 'disabled';
            $type = $serverConfig['type'] ?? 'unknown';
            
            $serverList[] = [
                'name' => $name,
                'display' => "{$name} ({$type}, {$enabled})",
                'config' => $serverConfig,
            ];
        }

        return $serverList;
    }
}
