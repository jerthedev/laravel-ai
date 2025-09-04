<?php

namespace JTD\LaravelAI\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use JTD\LaravelAI\Services\MCPConfigurationService;
use JTD\LaravelAI\Services\MCPManager;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

/**
 * MCP Setup Command
 *
 * Interactive command for setting up MCP servers with automatic installation,
 * configuration, and validation using Laravel Prompts.
 */
class MCPSetupCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ai:mcp:setup
                            {server? : Specific server to install}
                            {--list : List available servers}
                            {--skip-install : Skip npm package installation}
                            {--skip-test : Skip server testing}
                            {--skip-discovery : Skip tool discovery}
                            {--api-key= : API key for servers that require it}
                            {--timeout=30 : Server timeout in seconds}
                            {--force : Force reconfiguration of existing servers}
                            {--non-interactive : Run in non-interactive mode with defaults}';

    /**
     * The console command description.
     */
    protected $description = 'Interactive setup for MCP servers with automatic installation and configuration. Supports both interactive and non-interactive modes.';

    /**
     * MCP Configuration service.
     */
    protected MCPConfigurationService $configService;

    /**
     * MCP Manager service.
     */
    protected MCPManager $mcpManager;

    /**
     * Available MCP servers for installation.
     */
    protected array $availableServers = [
        'sequential-thinking' => [
            'name' => 'Sequential Thinking',
            'description' => 'Structured step-by-step problem-solving and reasoning',
            'type' => 'external',
            'package' => '@modelcontextprotocol/server-sequential-thinking',
            'requires_api_key' => false,
            'command' => 'npx @modelcontextprotocol/server-sequential-thinking',
            'config' => [
                'max_thoughts' => 10,
                'min_thoughts' => 2,
                'show_thinking' => false,
            ],
        ],
        'github' => [
            'name' => 'GitHub MCP',
            'description' => 'GitHub repository management and search',
            'type' => 'external',
            'package' => '@modelcontextprotocol/server-github',
            'requires_api_key' => true,
            'api_key_name' => 'GITHUB_PERSONAL_ACCESS_TOKEN',
            'command' => 'npx @modelcontextprotocol/server-github',
            'env_vars' => ['GITHUB_PERSONAL_ACCESS_TOKEN'],
            'config' => [
                'timeout' => 30,
            ],
        ],
        'brave-search' => [
            'name' => 'Brave Search',
            'description' => 'Web search using Brave Search API',
            'type' => 'external',
            'package' => '@modelcontextprotocol/server-brave-search',
            'requires_api_key' => true,
            'api_key_name' => 'BRAVE_API_KEY',
            'command' => 'npx @modelcontextprotocol/server-brave-search',
            'env_vars' => ['BRAVE_API_KEY'],
        ],
    ];

    /**
     * Create a new command instance.
     */
    public function __construct(MCPConfigurationService $configService, MCPManager $mcpManager)
    {
        parent::__construct();

        $this->configService = $configService;
        $this->mcpManager = $mcpManager;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->option('non-interactive')) {
            info('🤖 JTD Laravel AI - MCP Server Setup');
            info('This wizard will help you install and configure MCP servers.');
        }

        if ($this->option('list')) {
            return $this->listAvailableServers();
        }

        $serverKey = $this->argument('server');

        if ($serverKey) {
            if (! isset($this->availableServers[$serverKey])) {
                if (! $this->option('non-interactive')) {
                    error("Unknown server: {$serverKey}");
                }

                return 1;
            }

            return $this->installServer($serverKey, $this->availableServers[$serverKey]);
        }

        // Non-interactive mode requires server argument
        if ($this->option('non-interactive')) {
            $this->error('Server argument is required in non-interactive mode. Use --list to see available servers.');

            return 1;
        }

        // Interactive server selection
        $serverOptions = [];
        foreach ($this->availableServers as $key => $server) {
            $serverOptions["{$server['name']} - {$server['description']}"] = $key;
        }

        $selectedDisplay = select(
            'Which MCP server would you like to install?',
            array_keys($serverOptions)
        );

        $selectedKey = $serverOptions[$selectedDisplay];

        return $this->installServer($selectedKey, $this->availableServers[$selectedKey]);
    }

    /**
     * List available MCP servers.
     */
    protected function listAvailableServers(): int
    {
        info('Available MCP Servers:');
        info('');

        foreach ($this->availableServers as $key => $server) {
            $this->line("  <fg=cyan>{$key}</>");
            $this->line("    Name: {$server['name']}");
            $this->line("    Description: {$server['description']}");
            $this->line("    Package: {$server['package']}");
            $this->line('    Requires API Key: ' . ($server['requires_api_key'] ? 'Yes' : 'No'));

            if ($server['requires_api_key']) {
                $this->line("    API Key: {$server['api_key_name']}");
            }

            $this->line('');
        }

        info('To install a server, run: php artisan ai:mcp:setup <server-key>');

        return 0;
    }

    /**
     * Install and configure a specific MCP server.
     */
    protected function installServer(string $key, array $server): int
    {
        if (! $this->option('non-interactive')) {
            info("Installing {$server['name']}...");
        }

        // Check if server is already configured
        $existingConfig = $this->configService->loadConfiguration();
        if (isset($existingConfig['servers'][$key])) {
            if ($this->option('force')) {
                // Force reconfiguration - continue
            } elseif ($this->option('non-interactive')) {
                // In non-interactive mode, skip if already configured and no force flag
                return 0;
            } elseif (! confirm("Server '{$key}' is already configured. Do you want to reconfigure it?")) {
                info('Installation cancelled.');

                return 0;
            }
        }

        // Install npm package
        if (! $this->option('skip-install')) {
            if (! $this->installNpmPackage($server['package'])) {
                if (! $this->option('non-interactive')) {
                    error("Failed to install {$server['package']}");
                }

                return 1;
            }
        }

        // Collect configuration
        $config = $this->collectServerConfiguration($key, $server);

        if (! $config) {
            if (! $this->option('non-interactive')) {
                error('Configuration collection failed.');
            }

            return 1;
        }

        // Save configuration
        if (! $this->configService->addServer($key, $config)) {
            if (! $this->option('non-interactive')) {
                error('Failed to save server configuration.');
            }

            return 1;
        }

        if (! $this->option('non-interactive')) {
            info("✅ {$server['name']} has been configured successfully!");
        }

        // Test server if not skipped
        if (! $this->option('skip-test')) {
            if ($this->option('non-interactive') || confirm('Would you like to test the server configuration?', true)) {
                $this->testServer($key);
            }
        }

        // Discover tools
        if (! $this->option('skip-discovery')) {
            if ($this->option('non-interactive') || confirm('Would you like to discover available tools?', true)) {
                $this->discoverTools($key);
            }
        }

        return 0;
    }

    /**
     * Install npm package globally.
     */
    protected function installNpmPackage(string $package): bool
    {
        if (! $this->option('non-interactive')) {
            info("Installing npm package: {$package}");
        }

        try {
            $result = Process::timeout(120)->run("npm install -g {$package}");

            if ($result->successful()) {
                if (! $this->option('non-interactive')) {
                    info("✅ Package {$package} installed successfully");
                }

                return true;
            } else {
                if (! $this->option('non-interactive')) {
                    error("❌ Failed to install {$package}");
                    error('Error: ' . $result->errorOutput());
                }

                return false;
            }
        } catch (\Exception $e) {
            if (! $this->option('non-interactive')) {
                error("❌ Exception during npm install: {$e->getMessage()}");
            }

            return false;
        }
    }

    /**
     * Collect server configuration from user input.
     */
    protected function collectServerConfiguration(string $key, array $server): ?array
    {
        $config = [
            'type' => 'external',
            'enabled' => true,
            'command' => $server['command'],
            'args' => $server['args'] ?? [],
        ];

        // Add default config if specified
        if (isset($server['config'])) {
            $config['config'] = $server['config'];
        }

        // Collect API keys if required
        if ($server['requires_api_key']) {
            $env = [];

            foreach ($server['env_vars'] ?? [$server['api_key_name']] as $envVar) {
                $currentValue = env($envVar);

                if ($this->option('non-interactive')) {
                    // In non-interactive mode, use provided API key or existing env var
                    if ($this->option('api-key')) {
                        $env[$envVar] = "\${{{$envVar}}}";
                        if (! $this->option('non-interactive')) {
                            warning("Don't forget to add {$envVar}={$this->option('api-key')} to your .env file!");
                        }
                    } elseif ($currentValue) {
                        $env[$envVar] = "\${{{$envVar}}}";
                    } else {
                        // API key required but not provided
                        return null;
                    }
                } else {
                    // Interactive mode
                    if ($currentValue) {
                        info("Environment variable {$envVar} is already set.");
                        if (! confirm('Do you want to update it?')) {
                            $env[$envVar] = "\${{{$envVar}}}";

                            continue;
                        }
                    }

                    $apiKey = password("Enter your {$envVar}:");

                    if (empty($apiKey)) {
                        error('API key is required for this server.');

                        return null;
                    }

                    // Store in environment variable format
                    $env[$envVar] = "\${{{$envVar}}}";

                    // Suggest adding to .env file
                    warning("Don't forget to add {$envVar}={$apiKey} to your .env file!");
                }
            }

            if (! empty($env)) {
                $config['env'] = $env;
            }
        }

        // Collect timeout if not set
        if (! isset($config['config']['timeout'])) {
            if ($this->option('non-interactive')) {
                $config['timeout'] = (int) $this->option('timeout');
            } else {
                $timeout = text(
                    'Server timeout in seconds (default: 30):',
                    default: (string) $this->option('timeout'),
                    validate: fn (string $value) => is_numeric($value) && (int) $value > 0 ? null : 'Timeout must be a positive number'
                );

                $config['timeout'] = (int) $timeout;
            }
        }

        return $config;
    }

    /**
     * Test server connectivity and functionality.
     */
    protected function testServer(string $serverName): void
    {
        if (! $this->option('non-interactive')) {
            info("Testing server '{$serverName}'...");
        }

        try {
            // Reload MCP manager to pick up new configuration
            $this->mcpManager->loadConfiguration();

            $results = $this->mcpManager->testServers($serverName);
            $result = $results[$serverName] ?? null;

            if (! $result) {
                if (! $this->option('non-interactive')) {
                    error("❌ Server '{$serverName}' not found or not loaded");
                }

                return;
            }

            switch ($result['status']) {
                case 'healthy':
                    if (! $this->option('non-interactive')) {
                        info('✅ Server is healthy');
                        info("   Response time: {$result['response_time_ms']}ms");
                        if (isset($result['version'])) {
                            info("   Version: {$result['version']}");
                        }
                    }
                    break;

                case 'error':
                    if (! $this->option('non-interactive')) {
                        error("❌ Server test failed: {$result['message']}");
                    }
                    break;

                case 'disabled':
                    if (! $this->option('non-interactive')) {
                        warning('⚠️  Server is disabled');
                    }
                    break;

                default:
                    if (! $this->option('non-interactive')) {
                        warning("⚠️  Unknown server status: {$result['status']}");
                    }
                    break;
            }
        } catch (\Exception $e) {
            if (! $this->option('non-interactive')) {
                error("❌ Server test failed: {$e->getMessage()}");
            }
        }
    }

    /**
     * Discover tools from the server.
     */
    protected function discoverTools(string $serverName): void
    {
        if (! $this->option('non-interactive')) {
            info("Discovering tools from '{$serverName}'...");
        }

        try {
            // Reload MCP manager to pick up new configuration
            $this->mcpManager->loadConfiguration();

            $discovery = $this->mcpManager->discoverTools(true);
            $serverTools = $discovery['tools'][$serverName] ?? null;

            if (! $serverTools) {
                if (! $this->option('non-interactive')) {
                    warning("⚠️  No tools discovered from server '{$serverName}'");
                }

                return;
            }

            $tools = $serverTools['tools'] ?? [];
            $toolCount = count($tools);

            if ($toolCount === 0) {
                if (! $this->option('non-interactive')) {
                    warning("⚠️  Server '{$serverName}' has no available tools");
                }

                return;
            }

            if (! $this->option('non-interactive')) {
                info("✅ Discovered {$toolCount} tool(s) from '{$serverName}':");

                foreach ($tools as $tool) {
                    $name = $tool['name'] ?? 'Unknown';
                    $description = $tool['description'] ?? 'No description';
                    $this->line("   • <fg=cyan>{$name}</> - {$description}");
                }

                info('Tools have been cached in .mcp.tools.json');
            }
        } catch (\Exception $e) {
            if (! $this->option('non-interactive')) {
                error("❌ Tool discovery failed: {$e->getMessage()}");
            }
        }
    }

    /**
     * Validate server configuration.
     */
    protected function validateServerConfiguration(array $config): array
    {
        $validation = $this->configService->validateConfiguration(['servers' => ['test' => $config]]);

        return [
            'valid' => empty($validation['errors']),
            'errors' => $validation['errors'],
            'warnings' => $validation['warnings'],
        ];
    }

    /**
     * Show installation summary.
     */
    protected function showInstallationSummary(string $serverName, array $server, array $config): void
    {
        info('');
        info('📋 Installation Summary:');
        info("   Server: {$server['name']}");
        info("   Key: {$serverName}");
        info("   Package: {$server['package']}");
        info("   Type: {$config['type']}");
        info('   Enabled: ' . ($config['enabled'] ? 'Yes' : 'No'));

        if (isset($config['env'])) {
            info('   Environment Variables: ' . implode(', ', array_keys($config['env'])));
        }

        if (isset($config['timeout'])) {
            info("   Timeout: {$config['timeout']}s");
        }

        info('');
        info('Next steps:');
        info('• Run "php artisan ai:mcp:test" to test all servers');
        info('• Run "php artisan ai:mcp:discover" to discover tools');
        info('• Check .mcp.json for configuration details');
        info('');
    }
}
