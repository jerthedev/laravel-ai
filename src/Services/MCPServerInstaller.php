<?php

namespace JTD\LaravelAI\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use JTD\LaravelAI\Exceptions\MCPException;

/**
 * MCP Server Installer Service
 *
 * Handles installation, validation, and management of MCP server packages.
 * Supports npm-based external servers with automatic dependency resolution.
 */
class MCPServerInstaller
{
    /**
     * Server installation templates.
     */
    protected array $serverTemplates = [
        'sequential-thinking' => [
            'package' => '@modelcontextprotocol/server-sequential-thinking',
            'install_command' => 'npm install -g @modelcontextprotocol/server-sequential-thinking',
            'test_command' => 'npx @modelcontextprotocol/server-sequential-thinking --health',
            'requires_api_key' => false,
            'dependencies' => ['node', 'npm'],
            'min_node_version' => '18.0.0',
        ],
        'github' => [
            'package' => '@modelcontextprotocol/server-github',
            'install_command' => 'npm install -g @modelcontextprotocol/server-github',
            'test_command' => 'npx @modelcontextprotocol/server-github --health',
            'requires_api_key' => true,
            'api_key_env' => 'GITHUB_PERSONAL_ACCESS_TOKEN',
            'dependencies' => ['node', 'npm'],
            'min_node_version' => '18.0.0',
        ],
        'brave-search' => [
            'package' => '@modelcontextprotocol/server-brave-search',
            'install_command' => 'npm install -g @modelcontextprotocol/server-brave-search',
            'test_command' => 'npx @modelcontextprotocol/server-brave-search --health',
            'requires_api_key' => true,
            'api_key_env' => 'BRAVE_API_KEY',
            'dependencies' => ['node', 'npm'],
            'min_node_version' => '18.0.0',
        ],
    ];

    /**
     * Default installation timeout in seconds.
     */
    protected int $defaultTimeout = 300;

    /**
     * Check system prerequisites for MCP server installation.
     */
    public function checkPrerequisites(): array
    {
        $results = [
            'node' => $this->checkNodeJs(),
            'npm' => $this->checkNpm(),
            'permissions' => $this->checkPermissions(),
        ];

        $results['all_passed'] = !in_array(false, array_column($results, 'available'));

        return $results;
    }

    /**
     * Check if Node.js is available and meets minimum version requirements.
     */
    protected function checkNodeJs(): array
    {
        try {
            $result = Process::run('node --version');
            
            if (!$result->successful()) {
                return [
                    'available' => false,
                    'error' => 'Node.js not found',
                    'suggestion' => 'Install Node.js from https://nodejs.org/',
                ];
            }

            $version = trim($result->output());
            $versionNumber = ltrim($version, 'v');

            return [
                'available' => true,
                'version' => $version,
                'meets_requirements' => version_compare($versionNumber, '18.0.0', '>='),
                'path' => $this->findExecutablePath('node'),
            ];
        } catch (\Exception $e) {
            return [
                'available' => false,
                'error' => $e->getMessage(),
                'suggestion' => 'Install Node.js from https://nodejs.org/',
            ];
        }
    }

    /**
     * Check if npm is available.
     */
    protected function checkNpm(): array
    {
        try {
            $result = Process::run('npm --version');
            
            if (!$result->successful()) {
                return [
                    'available' => false,
                    'error' => 'npm not found',
                    'suggestion' => 'npm should be installed with Node.js',
                ];
            }

            return [
                'available' => true,
                'version' => trim($result->output()),
                'path' => $this->findExecutablePath('npm'),
            ];
        } catch (\Exception $e) {
            return [
                'available' => false,
                'error' => $e->getMessage(),
                'suggestion' => 'npm should be installed with Node.js',
            ];
        }
    }

    /**
     * Check if we have permissions to install global npm packages.
     */
    protected function checkPermissions(): array
    {
        try {
            // Try to get npm global directory
            $result = Process::run('npm config get prefix');
            
            if (!$result->successful()) {
                return [
                    'available' => false,
                    'error' => 'Cannot determine npm global directory',
                ];
            }

            $globalDir = trim($result->output());
            $canWrite = is_writable($globalDir) || is_writable(dirname($globalDir));

            return [
                'available' => $canWrite,
                'global_dir' => $globalDir,
                'writable' => $canWrite,
                'suggestion' => $canWrite ? null : 'You may need to use sudo or configure npm permissions',
            ];
        } catch (\Exception $e) {
            return [
                'available' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Install a specific MCP server.
     */
    public function installServer(string $serverKey, array $options = []): array
    {
        if (!isset($this->serverTemplates[$serverKey])) {
            throw new MCPException("Unknown server: {$serverKey}");
        }

        $template = $this->serverTemplates[$serverKey];
        $timeout = $options['timeout'] ?? $this->defaultTimeout;
        $skipPrerequisites = $options['skip_prerequisites'] ?? false;

        Log::info("Starting installation of MCP server: {$serverKey}");

        // Check prerequisites unless skipped
        if (!$skipPrerequisites) {
            $prerequisites = $this->checkPrerequisites();
            if (!$prerequisites['all_passed']) {
                throw new MCPException("Prerequisites not met for server installation");
            }
        }

        try {
            $startTime = microtime(true);

            // Install the package
            $installResult = $this->executeInstallCommand($template['install_command'], $timeout);
            
            if (!$installResult['success']) {
                throw new MCPException("Installation failed: {$installResult['error']}");
            }

            $installTime = microtime(true) - $startTime;

            // Test the installation
            $testResult = $this->testServerInstallation($serverKey, $template);

            $result = [
                'server' => $serverKey,
                'package' => $template['package'],
                'success' => true,
                'install_time' => round($installTime, 2),
                'install_output' => $installResult['output'],
                'test_result' => $testResult,
                'installed_at' => now()->toISOString(),
            ];

            Log::info("Successfully installed MCP server: {$serverKey}", [
                'install_time' => $installTime,
                'test_passed' => $testResult['success'],
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error("Failed to install MCP server: {$serverKey}", [
                'error' => $e->getMessage(),
            ]);

            return [
                'server' => $serverKey,
                'package' => $template['package'],
                'success' => false,
                'error' => $e->getMessage(),
                'installed_at' => now()->toISOString(),
            ];
        }
    }

    /**
     * Execute npm install command.
     */
    protected function executeInstallCommand(string $command, int $timeout): array
    {
        try {
            $result = Process::timeout($timeout)->run($command);

            return [
                'success' => $result->successful(),
                'output' => $result->output(),
                'error' => $result->errorOutput(),
                'exit_code' => $result->exitCode(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'output' => '',
                'error' => $e->getMessage(),
                'exit_code' => -1,
            ];
        }
    }

    /**
     * Test server installation.
     */
    protected function testServerInstallation(string $serverKey, array $template): array
    {
        try {
            $testCommand = $template['test_command'] ?? null;
            
            if (!$testCommand) {
                // Fallback test - just check if the package can be executed
                $packageName = $template['package'];
                $testCommand = "npx {$packageName} --help";
            }

            $result = Process::timeout(30)->run($testCommand);

            return [
                'success' => $result->successful(),
                'output' => $result->output(),
                'error' => $result->errorOutput(),
                'command' => $testCommand,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'command' => $testCommand ?? 'unknown',
            ];
        }
    }

    /**
     * Check if a server is already installed.
     */
    public function isServerInstalled(string $serverKey): array
    {
        if (!isset($this->serverTemplates[$serverKey])) {
            return [
                'installed' => false,
                'error' => "Unknown server: {$serverKey}",
            ];
        }

        $template = $this->serverTemplates[$serverKey];
        $package = $template['package'];

        try {
            // Check if package is globally installed
            $result = Process::run("npm list -g {$package} --depth=0");
            
            $installed = $result->successful();
            $output = $result->output();

            // Extract version if installed
            $version = null;
            if ($installed && preg_match("/{$package}@([^\s]+)/", $output, $matches)) {
                $version = $matches[1];
            }

            return [
                'installed' => $installed,
                'package' => $package,
                'version' => $version,
                'output' => $output,
            ];
        } catch (\Exception $e) {
            return [
                'installed' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Uninstall a server.
     */
    public function uninstallServer(string $serverKey): array
    {
        if (!isset($this->serverTemplates[$serverKey])) {
            throw new MCPException("Unknown server: {$serverKey}");
        }

        $template = $this->serverTemplates[$serverKey];
        $package = $template['package'];

        try {
            $result = Process::timeout(120)->run("npm uninstall -g {$package}");

            return [
                'success' => $result->successful(),
                'package' => $package,
                'output' => $result->output(),
                'error' => $result->errorOutput(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'package' => $package,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get available server templates.
     */
    public function getAvailableServers(): array
    {
        return $this->serverTemplates;
    }

    /**
     * Get server template by key.
     */
    public function getServerTemplate(string $serverKey): ?array
    {
        return $this->serverTemplates[$serverKey] ?? null;
    }

    /**
     * Find executable path.
     */
    protected function findExecutablePath(string $executable): ?string
    {
        try {
            $result = Process::run("which {$executable}");
            return $result->successful() ? trim($result->output()) : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
