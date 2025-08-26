<?php

namespace JTD\LaravelAI\Tests\Feature\MCPSetup;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use JTD\LaravelAI\Services\MCPConfigurationService;
use JTD\LaravelAI\Services\MCPManager;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * MCP Setup Command Tests
 *
 * Comprehensive tests for MCP server setup commands including
 * installation, configuration, and validation processes.
 */
#[Group('mcp-setup')]
class MCPSetupCommandTest extends TestCase
{
    use RefreshDatabase;

    protected string $testConfigPath;

    protected string $testToolsPath;

    protected MCPConfigurationService $configService;

    protected MCPManager $mcpManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testConfigPath = base_path('.mcp.json');
        $this->testToolsPath = base_path('.mcp.tools.json');
        $this->configService = app(MCPConfigurationService::class);
        $this->mcpManager = app(MCPManager::class);

        // Clean up any existing test files
        $this->cleanupTestFiles();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestFiles();
        parent::tearDown();
    }

    #[Test]
    public function it_displays_available_mcp_servers_with_list_option(): void
    {
        $this->artisan('ai:mcp:setup --list')
            ->expectsOutput('Available MCP Servers:')
            ->expectsOutput('sequential-thinking')
            ->expectsOutput('Name: Sequential Thinking')
            ->expectsOutput('Description: Structured step-by-step problem-solving and reasoning')
            ->expectsOutput('github')
            ->expectsOutput('Name: GitHub MCP')
            ->expectsOutput('brave-search')
            ->expectsOutput('Name: Brave Search')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_installs_sequential_thinking_server_non_interactively(): void
    {
        Process::fake([
            'npm install -g @modelcontextprotocol/server-sequential-thinking' => Process::result('installed successfully', '', 0),
        ]);

        $this->artisan('ai:mcp:setup sequential-thinking --non-interactive --skip-test --skip-discovery')
            ->assertExitCode(0);

        // Verify configuration was created
        $this->assertFileExists($this->testConfigPath);

        $config = json_decode(File::get($this->testConfigPath), true);
        $this->assertArrayHasKey('sequential-thinking', $config['servers']);
        $this->assertEquals('external', $config['servers']['sequential-thinking']['type']);
        $this->assertTrue($config['servers']['sequential-thinking']['enabled']);
        $this->assertEquals('npx @modelcontextprotocol/server-sequential-thinking', $config['servers']['sequential-thinking']['command']);
    }

    #[Test]
    public function it_installs_brave_search_server_with_api_key(): void
    {
        Process::fake([
            'npm install -g @modelcontextprotocol/server-brave-search' => Process::result('installed successfully', '', 0),
        ]);

        $this->artisan('ai:mcp:setup brave-search --api-key=test-api-key-123 --non-interactive --skip-test --skip-discovery')
            ->assertExitCode(0);

        // Verify configuration
        $config = json_decode(File::get($this->testConfigPath), true);
        $this->assertArrayHasKey('brave-search', $config['servers']);
        $this->assertEquals('external', $config['servers']['brave-search']['type']);
        $this->assertArrayHasKey('env', $config['servers']['brave-search']);
        $this->assertEquals('${BRAVE_API_KEY}', $config['servers']['brave-search']['env']['BRAVE_API_KEY']);
        $this->assertEquals('npx @modelcontextprotocol/server-brave-search', $config['servers']['brave-search']['command']);
    }

    #[Test]
    public function it_installs_github_mcp_server_with_token(): void
    {
        Process::fake([
            'npm install -g @modelcontextprotocol/server-github' => Process::result('installed successfully', '', 0),
        ]);

        $this->artisan('ai:mcp:setup github --api-key=ghp_test_token_123 --non-interactive --skip-test --skip-discovery')
            ->assertExitCode(0);

        // Verify configuration
        $config = json_decode(File::get($this->testConfigPath), true);
        $this->assertArrayHasKey('github', $config['servers']);
        $this->assertEquals('external', $config['servers']['github']['type']);
        $this->assertEquals('${GITHUB_PERSONAL_ACCESS_TOKEN}', $config['servers']['github']['env']['GITHUB_PERSONAL_ACCESS_TOKEN']);
        $this->assertEquals('npx @modelcontextprotocol/server-github', $config['servers']['github']['command']);
    }

    #[Test]
    public function it_handles_installation_failures_gracefully(): void
    {
        Process::fake([
            'npm install -g @modelcontextprotocol/server-brave-search' => Process::result('', 'Installation failed', 1),
        ]);

        $this->artisan('ai:mcp:setup brave-search --api-key=test-key --non-interactive --skip-test --skip-discovery')
            ->assertExitCode(1);

        // Verify no configuration was created
        if (File::exists($this->testConfigPath)) {
            $config = json_decode(File::get($this->testConfigPath), true);
            $this->assertArrayNotHasKey('brave-search', $config['servers'] ?? []);
        }
    }

    #[Test]
    public function it_handles_force_reconfiguration(): void
    {
        // First install a server
        Process::fake([
            'npm install -g @modelcontextprotocol/server-sequential-thinking' => Process::result('installed successfully', '', 0),
        ]);

        $this->artisan('ai:mcp:setup sequential-thinking --non-interactive --skip-test --skip-discovery')
            ->assertExitCode(0);

        // Verify initial configuration
        $this->assertFileExists($this->testConfigPath);
        $config = json_decode(File::get($this->testConfigPath), true);
        $this->assertArrayHasKey('sequential-thinking', $config['servers']);

        // Try to install again without force (should skip in non-interactive mode)
        $this->artisan('ai:mcp:setup sequential-thinking --non-interactive --skip-test --skip-discovery')
            ->assertExitCode(0);

        // Try to install again with force (should reconfigure)
        $this->artisan('ai:mcp:setup sequential-thinking --force --non-interactive --skip-test --skip-discovery')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_requires_server_argument_in_non_interactive_mode(): void
    {
        $this->artisan('ai:mcp:setup --non-interactive')
            ->expectsOutput('Server argument is required in non-interactive mode. Use --list to see available servers.')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_handles_unknown_server_argument(): void
    {
        $this->artisan('ai:mcp:setup unknown-server --non-interactive')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_skips_installation_when_requested(): void
    {
        // Should not attempt npm install when --skip-install is used
        Process::fake();

        $this->artisan('ai:mcp:setup sequential-thinking --skip-install --non-interactive --skip-test --skip-discovery')
            ->assertExitCode(0);

        // Verify no npm install was attempted
        Process::assertNothingRan();

        // Verify configuration was still created
        $this->assertFileExists($this->testConfigPath);
        $config = json_decode(File::get($this->testConfigPath), true);
        $this->assertArrayHasKey('sequential-thinking', $config['servers']);
    }

    #[Test]
    public function it_handles_multiple_server_installations(): void
    {
        Process::fake([
            'npm install -g @modelcontextprotocol/server-sequential-thinking' => Process::result('installed successfully', '', 0),
            'npm install -g @modelcontextprotocol/server-brave-search' => Process::result('installed successfully', '', 0),
        ]);

        // Install Sequential Thinking first
        $this->artisan('ai:mcp:setup sequential-thinking --non-interactive --skip-test --skip-discovery')
            ->assertExitCode(0);

        // Install Brave Search second
        $this->artisan('ai:mcp:setup brave-search --api-key=test-key --non-interactive --skip-test --skip-discovery')
            ->assertExitCode(0);

        // Verify both servers are configured
        $config = json_decode(File::get($this->testConfigPath), true);
        $this->assertArrayHasKey('sequential-thinking', $config['servers']);
        $this->assertArrayHasKey('brave-search', $config['servers']);
        $this->assertCount(2, $config['servers']);
    }

    #[Test]
    public function it_validates_configuration_service_integration(): void
    {
        Process::fake([
            'npm install -g @modelcontextprotocol/server-sequential-thinking' => Process::result('installed successfully', '', 0),
        ]);

        $this->artisan('ai:mcp:setup sequential-thinking --non-interactive --skip-test --skip-discovery')
            ->assertExitCode(0);

        // Verify the configuration service can load the configuration
        $config = $this->configService->loadConfiguration();
        $this->assertArrayHasKey('servers', $config);
        $this->assertArrayHasKey('sequential-thinking', $config['servers']);

        $serverConfig = $config['servers']['sequential-thinking'];
        $this->assertEquals('external', $serverConfig['type']);
        $this->assertTrue($serverConfig['enabled']);
        $this->assertEquals('npx @modelcontextprotocol/server-sequential-thinking', $serverConfig['command']);
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
