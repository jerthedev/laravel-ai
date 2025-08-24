<?php

namespace JTD\LaravelAI\Tests\Unit\Console\Commands;

use Illuminate\Support\Facades\Process;
use JTD\LaravelAI\Console\Commands\MCPSetupCommand;
use JTD\LaravelAI\Services\MCPConfigurationService;
use JTD\LaravelAI\Services\MCPManager;
use JTD\LaravelAI\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class MCPSetupCommandTest extends TestCase
{
    protected MCPConfigurationService $configService;
    protected MCPManager $mcpManager;
    protected MCPSetupCommand $command;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->configService = Mockery::mock(MCPConfigurationService::class);
        $this->mcpManager = Mockery::mock(MCPManager::class);
        
        $this->command = new MCPSetupCommand($this->configService, $this->mcpManager);
        $this->command->setLaravel($this->app);
    }

    #[Test]
    public function it_lists_available_servers(): void
    {
        $this->artisan('ai:mcp:setup --list')
            ->expectsOutput('Available MCP Servers:')
            ->expectsOutput('  sequential-thinking')
            ->expectsOutput('    Name: Sequential Thinking')
            ->expectsOutput('    Description: Structured step-by-step problem-solving and reasoning')
            ->expectsOutput('    Package: @modelcontextprotocol/server-sequential-thinking')
            ->expectsOutput('    Requires API Key: No')
            ->expectsOutput('  github')
            ->expectsOutput('    Name: GitHub MCP')
            ->expectsOutput('    Requires API Key: Yes')
            ->expectsOutput('  brave-search')
            ->expectsOutput('    Name: Brave Search')
            ->expectsOutput('    Requires API Key: Yes')
            ->expectsOutput('To install a server, run: php artisan ai:mcp:setup <server-key>')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_fails_with_unknown_server(): void
    {
        $this->artisan('ai:mcp:setup unknown-server')
            ->expectsOutput('Unknown server: unknown-server')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_installs_sequential_thinking_server_successfully(): void
    {
        // Mock npm install success
        Process::shouldReceive('timeout')
            ->with(120)
            ->andReturnSelf()
            ->shouldReceive('run')
            ->with('npm install -g @modelcontextprotocol/server-sequential-thinking')
            ->andReturn(Mockery::mock([
                'successful' => true,
                'output' => 'Package installed successfully',
                'errorOutput' => '',
            ]));

        // Mock configuration service
        $this->configService->shouldReceive('loadConfiguration')
            ->andReturn(['servers' => []]);

        $this->configService->shouldReceive('addServer')
            ->with('sequential-thinking', Mockery::type('array'))
            ->andReturn(true);

        // Mock MCP manager for testing
        $this->mcpManager->shouldReceive('loadConfiguration')->once();
        $this->mcpManager->shouldReceive('testServers')
            ->with('sequential-thinking')
            ->andReturn([
                'sequential-thinking' => [
                    'status' => 'healthy',
                    'message' => 'Server is responding normally',
                    'response_time_ms' => 150.5,
                ],
            ]);

        $this->mcpManager->shouldReceive('discoverTools')
            ->with(true)
            ->andReturn([
                'tools' => [
                    'sequential-thinking' => [
                        'tools' => [
                            ['name' => 'sequential_thinking', 'description' => 'Structured thinking process'],
                        ],
                    ],
                ],
            ]);

        $this->artisan('ai:mcp:setup sequential-thinking --skip-test --skip-install')
            ->expectsQuestion('Would you like to discover available tools?', true)
            ->expectsOutput('Installing Sequential Thinking...')
            ->expectsOutput('✅ Sequential Thinking has been configured successfully!')
            ->expectsOutput('✅ Discovered 1 tool(s) from \'sequential-thinking\':')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_handles_npm_install_failure(): void
    {
        Process::shouldReceive('timeout')
            ->with(120)
            ->andReturnSelf()
            ->shouldReceive('run')
            ->with('npm install -g @modelcontextprotocol/server-sequential-thinking')
            ->andReturn(Mockery::mock([
                'successful' => false,
                'errorOutput' => 'npm install failed',
            ]));

        $this->artisan('ai:mcp:setup sequential-thinking')
            ->expectsOutput('Installing Sequential Thinking...')
            ->expectsOutput('Installing npm package: @modelcontextprotocol/server-sequential-thinking')
            ->expectsOutput('❌ Failed to install @modelcontextprotocol/server-sequential-thinking')
            ->expectsOutput('Failed to install @modelcontextprotocol/server-sequential-thinking')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_skips_installation_when_requested(): void
    {
        $this->configService->shouldReceive('loadConfiguration')
            ->andReturn(['servers' => []]);

        $this->configService->shouldReceive('addServer')
            ->with('sequential-thinking', Mockery::type('array'))
            ->andReturn(true);

        $this->artisan('ai:mcp:setup sequential-thinking --skip-install --skip-test')
            ->expectsQuestion('Would you like to discover available tools?', false)
            ->expectsOutput('Installing Sequential Thinking...')
            ->expectsOutput('✅ Sequential Thinking has been configured successfully!')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_prompts_for_reconfiguration_of_existing_server(): void
    {
        $this->configService->shouldReceive('loadConfiguration')
            ->andReturn([
                'servers' => [
                    'sequential-thinking' => [
                        'type' => 'external',
                        'enabled' => true,
                        'command' => 'npx @modelcontextprotocol/server-sequential-thinking',
                    ],
                ],
            ]);

        $this->artisan('ai:mcp:setup sequential-thinking --skip-install --skip-test')
            ->expectsQuestion("Server 'sequential-thinking' is already configured. Do you want to reconfigure it?", false)
            ->expectsOutput('Installation cancelled.')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_handles_configuration_save_failure(): void
    {
        $this->configService->shouldReceive('loadConfiguration')
            ->andReturn(['servers' => []]);

        $this->configService->shouldReceive('addServer')
            ->with('sequential-thinking', Mockery::type('array'))
            ->andReturn(false);

        $this->artisan('ai:mcp:setup sequential-thinking --skip-install --skip-test')
            ->expectsOutput('Installing Sequential Thinking...')
            ->expectsOutput('Failed to save server configuration.')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_tests_server_after_installation(): void
    {
        $this->configService->shouldReceive('loadConfiguration')
            ->andReturn(['servers' => []]);

        $this->configService->shouldReceive('addServer')
            ->with('sequential-thinking', Mockery::type('array'))
            ->andReturn(true);

        $this->mcpManager->shouldReceive('loadConfiguration')->once();
        $this->mcpManager->shouldReceive('testServers')
            ->with('sequential-thinking')
            ->andReturn([
                'sequential-thinking' => [
                    'status' => 'healthy',
                    'message' => 'Server is responding normally',
                    'response_time_ms' => 150.5,
                    'version' => '1.0.0',
                ],
            ]);

        $this->artisan('ai:mcp:setup sequential-thinking --skip-install')
            ->expectsQuestion('Would you like to test the server configuration?', true)
            ->expectsQuestion('Would you like to discover available tools?', false)
            ->expectsOutput('Installing Sequential Thinking...')
            ->expectsOutput('✅ Sequential Thinking has been configured successfully!')
            ->expectsOutput("Testing server 'sequential-thinking'...")
            ->expectsOutput('✅ Server is healthy')
            ->expectsOutput('   Response time: 150.5ms')
            ->expectsOutput('   Version: 1.0.0')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_handles_server_test_failure(): void
    {
        $this->configService->shouldReceive('loadConfiguration')
            ->andReturn(['servers' => []]);

        $this->configService->shouldReceive('addServer')
            ->with('sequential-thinking', Mockery::type('array'))
            ->andReturn(true);

        $this->mcpManager->shouldReceive('loadConfiguration')->once();
        $this->mcpManager->shouldReceive('testServers')
            ->with('sequential-thinking')
            ->andReturn([
                'sequential-thinking' => [
                    'status' => 'error',
                    'message' => 'Connection failed',
                ],
            ]);

        $this->artisan('ai:mcp:setup sequential-thinking --skip-install')
            ->expectsQuestion('Would you like to test the server configuration?', true)
            ->expectsQuestion('Would you like to discover available tools?', false)
            ->expectsOutput('Installing Sequential Thinking...')
            ->expectsOutput('✅ Sequential Thinking has been configured successfully!')
            ->expectsOutput("Testing server 'sequential-thinking'...")
            ->expectsOutput('❌ Server test failed: Connection failed')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_discovers_tools_after_installation(): void
    {
        $this->configService->shouldReceive('loadConfiguration')
            ->andReturn(['servers' => []]);

        $this->configService->shouldReceive('addServer')
            ->with('sequential-thinking', Mockery::type('array'))
            ->andReturn(true);

        $this->mcpManager->shouldReceive('loadConfiguration')->once();
        $this->mcpManager->shouldReceive('discoverTools')
            ->with(true)
            ->andReturn([
                'tools' => [
                    'sequential-thinking' => [
                        'tools' => [
                            ['name' => 'sequential_thinking', 'description' => 'Structured thinking process'],
                            ['name' => 'analyze_problem', 'description' => 'Problem analysis tool'],
                        ],
                    ],
                ],
            ]);

        $this->artisan('ai:mcp:setup sequential-thinking --skip-install --skip-test')
            ->expectsQuestion('Would you like to discover available tools?', true)
            ->expectsOutput('Installing Sequential Thinking...')
            ->expectsOutput('✅ Sequential Thinking has been configured successfully!')
            ->expectsOutput("Discovering tools from 'sequential-thinking'...")
            ->expectsOutput("✅ Discovered 2 tool(s) from 'sequential-thinking':")
            ->expectsOutput('   • sequential_thinking - Structured thinking process')
            ->expectsOutput('   • analyze_problem - Problem analysis tool')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_handles_tool_discovery_failure(): void
    {
        $this->configService->shouldReceive('loadConfiguration')
            ->andReturn(['servers' => []]);

        $this->configService->shouldReceive('addServer')
            ->with('sequential-thinking', Mockery::type('array'))
            ->andReturn(true);

        $this->mcpManager->shouldReceive('loadConfiguration')->once();
        $this->mcpManager->shouldReceive('discoverTools')
            ->with(true)
            ->andThrow(new \Exception('Discovery failed'));

        $this->artisan('ai:mcp:setup sequential-thinking --skip-install --skip-test')
            ->expectsQuestion('Would you like to discover available tools?', true)
            ->expectsOutput('Installing Sequential Thinking...')
            ->expectsOutput('✅ Sequential Thinking has been configured successfully!')
            ->expectsOutput("Discovering tools from 'sequential-thinking'...")
            ->expectsOutput('❌ Tool discovery failed: Discovery failed')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_collects_timeout_configuration(): void
    {
        $this->configService->shouldReceive('loadConfiguration')
            ->andReturn(['servers' => []]);

        $this->configService->shouldReceive('addServer')
            ->with('sequential-thinking', Mockery::on(function ($config) {
                return $config['timeout'] === 45;
            }))
            ->andReturn(true);

        $this->artisan('ai:mcp:setup sequential-thinking --skip-install --skip-test')
            ->expectsQuestion('Server timeout in seconds (default: 30):', '45')
            ->expectsQuestion('Would you like to discover available tools?', false)
            ->expectsOutput('Installing Sequential Thinking...')
            ->expectsOutput('✅ Sequential Thinking has been configured successfully!')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_validates_timeout_input(): void
    {
        $this->configService->shouldReceive('loadConfiguration')
            ->andReturn(['servers' => []]);

        $this->configService->shouldReceive('addServer')
            ->with('sequential-thinking', Mockery::on(function ($config) {
                return $config['timeout'] === 30; // Should use default after invalid input
            }))
            ->andReturn(true);

        $this->artisan('ai:mcp:setup sequential-thinking --skip-install --skip-test')
            ->expectsQuestion('Server timeout in seconds (default: 30):', 'invalid')
            ->expectsQuestion('Server timeout in seconds (default: 30):', '30')
            ->expectsQuestion('Would you like to discover available tools?', false)
            ->expectsOutput('Installing Sequential Thinking...')
            ->expectsOutput('✅ Sequential Thinking has been configured successfully!')
            ->assertExitCode(0);
    }
}
