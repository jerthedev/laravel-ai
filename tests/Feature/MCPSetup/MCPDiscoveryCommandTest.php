<?php

namespace JTD\LaravelAI\Tests\Feature\MCPSetup;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use JTD\LaravelAI\Services\MCPConfigurationService;
use JTD\LaravelAI\Services\MCPManager;
use JTD\LaravelAI\Services\MCPToolDiscoveryService;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * MCP Discovery Command Tests
 *
 * Tests for ai:mcp:discover command and automatic tool discovery functionality.
 */
#[Group('mcp-setup')]
#[Group('mcp-discovery')]
class MCPDiscoveryCommandTest extends TestCase
{
    use RefreshDatabase;

    protected string $testConfigPath;

    protected string $testToolsPath;

    protected MCPConfigurationService $configService;

    protected MCPManager $mcpManager;

    protected MCPToolDiscoveryService $discoveryService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testConfigPath = base_path('.mcp.json');
        $this->testToolsPath = base_path('.mcp.tools.json');
        $this->configService = app(MCPConfigurationService::class);
        $this->mcpManager = app(MCPManager::class);
        $this->discoveryService = app(MCPToolDiscoveryService::class);

        $this->cleanupTestFiles();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestFiles();
        Cache::flush();
        parent::tearDown();
    }

    #[Test]
    public function it_discovers_tools_from_all_servers(): void
    {
        // Create test server configurations
        $this->setupTestServers();

        $this->artisan('ai:mcp:discover')
            ->expectsOutput('🔍 Discovering tools from all MCP servers...')
            ->expectsOutput('✅ Tool discovery completed!')
            ->expectsOutput('Tools have been cached in .mcp.tools.json')
            ->assertExitCode(0);

        // Verify tools file was created
        $this->assertFileExists($this->testToolsPath);
    }

    #[Test]
    public function it_discovers_tools_from_specific_server(): void
    {
        // Create test server configuration
        $this->setupTestServers();

        $this->artisan('ai:mcp:discover sequential-thinking')
            ->expectsOutput("🔍 Discovering tools from server 'sequential-thinking'...")
            ->expectsOutput('✅ Discovered')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_handles_force_refresh_option(): void
    {
        // Create test server configurations
        $this->setupTestServers();

        // First discovery to populate cache
        $this->artisan('ai:mcp:discover')
            ->assertExitCode(0);

        // Force refresh should ignore cache
        $this->artisan('ai:mcp:discover --force')
            ->expectsOutput('Force refresh enabled - ignoring cache')
            ->expectsOutput('✅ Tool discovery completed!')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_shows_tools_when_requested(): void
    {
        // Create test server configurations
        $this->setupTestServers();

        $this->artisan('ai:mcp:discover --show-tools')
            ->expectsOutput('🔍 Discovering tools from all MCP servers...')
            ->expectsOutput('📋 Discovered Tools:')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_outputs_json_when_requested(): void
    {
        // Create test server configurations
        $this->setupTestServers();

        $output = $this->artisan('ai:mcp:discover --json')
            ->assertExitCode(0)
            ->getOutput();

        // Verify output is valid JSON
        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('tools', $decoded);
        $this->assertArrayHasKey('statistics', $decoded);
    }

    #[Test]
    public function it_handles_server_discovery_failures_gracefully(): void
    {
        // Create configuration with non-existent server
        $config = [
            'servers' => [
                'non-existent' => [
                    'type' => 'external',
                    'enabled' => true,
                    'command' => 'non-existent-command',
                ],
            ],
        ];
        File::put($this->testConfigPath, json_encode($config, JSON_PRETTY_PRINT));

        $this->artisan('ai:mcp:discover')
            ->expectsOutput('🔍 Discovering tools from all MCP servers...')
            ->expectsOutput('✅ Tool discovery completed!')
            ->expectsOutput('Servers failed:')
            ->expectsOutput('Errors encountered:')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_handles_missing_server_error(): void
    {
        $this->artisan('ai:mcp:discover non-existent-server')
            ->expectsOutput('Tool discovery failed:')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_caches_discovery_results(): void
    {
        // Create test server configurations
        $this->setupTestServers();

        // First discovery should populate cache
        $this->artisan('ai:mcp:discover')
            ->assertExitCode(0);

        // Verify cache was populated
        $this->assertTrue(Cache::has('mcp_tool_discovery_all'));

        // Second discovery should use cache (faster)
        $startTime = microtime(true);
        $this->artisan('ai:mcp:discover')
            ->assertExitCode(0);
        $endTime = microtime(true);

        // Cached version should be very fast
        $this->assertLessThan(1.0, $endTime - $startTime);
    }

    #[Test]
    public function it_validates_tool_definitions(): void
    {
        // Create test server configurations
        $this->setupTestServers();

        $this->artisan('ai:mcp:discover')
            ->assertExitCode(0);

        // Verify tools file contains valid tool definitions
        if (File::exists($this->testToolsPath)) {
            $toolsData = json_decode(File::get($this->testToolsPath), true);
            $this->assertIsArray($toolsData);

            if (isset($toolsData['tools'])) {
                foreach ($toolsData['tools'] as $serverName => $serverData) {
                    $this->assertArrayHasKey('tools', $serverData);
                    $this->assertArrayHasKey('server_info', $serverData);

                    foreach ($serverData['tools'] as $tool) {
                        $this->assertArrayHasKey('name', $tool);
                        $this->assertArrayHasKey('description', $tool);
                    }
                }
            }
        }
    }

    #[Test]
    public function it_handles_discovery_service_exceptions(): void
    {
        // Create invalid configuration that will cause exceptions
        $config = [
            'servers' => [
                'invalid-server' => [
                    'type' => 'external',
                    'enabled' => true,
                    // Missing required command field
                ],
            ],
        ];
        File::put($this->testConfigPath, json_encode($config, JSON_PRETTY_PRINT));

        $this->artisan('ai:mcp:discover')
            ->expectsOutput('🔍 Discovering tools from all MCP servers...')
            ->expectsOutput('✅ Tool discovery completed!')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_shows_discovery_statistics(): void
    {
        // Create test server configurations
        $this->setupTestServers();

        $this->artisan('ai:mcp:discover')
            ->expectsOutput('Servers checked:')
            ->expectsOutput('Servers successful:')
            ->expectsOutput('Total tools found:')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_handles_empty_server_configuration(): void
    {
        // Create empty configuration
        $config = ['servers' => []];
        File::put($this->testConfigPath, json_encode($config, JSON_PRETTY_PRINT));

        $this->artisan('ai:mcp:discover')
            ->expectsOutput('🔍 Discovering tools from all MCP servers...')
            ->expectsOutput('✅ Tool discovery completed!')
            ->expectsOutput('Servers checked: 0')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_discovers_tools_with_comprehensive_server_info(): void
    {
        // Create test server configurations
        $this->setupTestServers();

        $this->artisan('ai:mcp:discover sequential-thinking --show-tools')
            ->expectsOutput("🔍 Discovering tools from server 'sequential-thinking'...")
            ->expectsOutput("📋 Tools from 'sequential-thinking':")
            ->assertExitCode(0);
    }

    /**
     * Setup test server configurations for testing.
     */
    protected function setupTestServers(): void
    {
        $config = [
            'servers' => [
                'sequential-thinking' => [
                    'type' => 'external',
                    'enabled' => true,
                    'command' => 'npx @modelcontextprotocol/server-sequential-thinking',
                    'config' => [
                        'max_thoughts' => 10,
                        'min_thoughts' => 2,
                    ],
                ],
                'github' => [
                    'type' => 'external',
                    'enabled' => true,
                    'command' => 'npx @modelcontextprotocol/server-github',
                    'env' => [
                        'GITHUB_PERSONAL_ACCESS_TOKEN' => '${GITHUB_PERSONAL_ACCESS_TOKEN}',
                    ],
                ],
            ],
        ];

        File::put($this->testConfigPath, json_encode($config, JSON_PRETTY_PRINT));
    }

    /**
     * Clean up test files.
     */
    protected function cleanupTestFiles(): void
    {
        $filesToClean = [
            $this->testConfigPath,
            $this->testToolsPath,
        ];

        foreach ($filesToClean as $file) {
            if (File::exists($file)) {
                File::delete($file);
            }
        }
    }
}
