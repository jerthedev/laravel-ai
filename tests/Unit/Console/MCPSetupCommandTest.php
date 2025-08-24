<?php

namespace JTD\LaravelAI\Tests\Unit\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;

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

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->testConfigPath = base_path('.mcp.test.json');
        $this->testToolsPath = base_path('.mcp.tools.test.json');
        
        // Clean up any existing test files
        $this->cleanupTestFiles();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestFiles();
        parent::tearDown();
    }

    #[Test]
    public function it_displays_available_mcp_servers(): void
    {
        $this->artisan('ai:mcp:setup')
            ->expectsOutput('Available MCP Servers:')
            ->expectsOutput('1. Sequential Thinking - Built-in reasoning tool')
            ->expectsOutput('2. Brave Search - Web search capabilities')
            ->expectsOutput('3. GitHub MCP - GitHub repository integration')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_installs_sequential_thinking_server(): void
    {
        $this->artisan('ai:mcp:setup')
            ->expectsChoice('Which MCP server would you like to install?', 'Sequential Thinking', [
                'Sequential Thinking',
                'Brave Search',
                'GitHub MCP',
                'Custom Server',
            ])
            ->expectsOutput('Installing Sequential Thinking MCP server...')
            ->expectsOutput('✓ Sequential Thinking server configured successfully')
            ->assertExitCode(0);

        // Verify configuration was created
        $this->assertFileExists($this->testConfigPath);
        
        $config = json_decode(File::get($this->testConfigPath), true);
        $this->assertArrayHasKey('sequential_thinking', $config['servers']);
        $this->assertEquals('built-in', $config['servers']['sequential_thinking']['type']);
        $this->assertTrue($config['servers']['sequential_thinking']['enabled']);
    }

    #[Test]
    public function it_installs_brave_search_server_with_api_key(): void
    {
        Process::fake([
            'npm list -g @modelcontextprotocol/server-brave-search' => Process::result('', '', 1),
            'npm install -g @modelcontextprotocol/server-brave-search' => Process::result('installed successfully', '', 0),
            'npx @modelcontextprotocol/server-brave-search --version' => Process::result('1.0.0', '', 0),
        ]);

        $this->artisan('ai:mcp:setup')
            ->expectsChoice('Which MCP server would you like to install?', 'Brave Search', [
                'Sequential Thinking',
                'Brave Search',
                'GitHub MCP',
                'Custom Server',
            ])
            ->expectsOutput('Installing Brave Search MCP server...')
            ->expectsOutput('Installing npm package: @modelcontextprotocol/server-brave-search')
            ->expectsQuestion('Enter your Brave Search API key', 'test-api-key-123')
            ->expectsConfirmation('Test the API key?', 'yes')
            ->expectsOutput('✓ API key validated successfully')
            ->expectsOutput('✓ Brave Search server installed and configured successfully')
            ->assertExitCode(0);

        // Verify configuration
        $config = json_decode(File::get($this->testConfigPath), true);
        $this->assertArrayHasKey('brave_search', $config['servers']);
        $this->assertEquals('external', $config['servers']['brave_search']['type']);
        $this->assertArrayHasKey('env', $config['servers']['brave_search']);
        $this->assertEquals('test-api-key-123', $config['servers']['brave_search']['env']['BRAVE_API_KEY']);
    }

    #[Test]
    public function it_installs_github_mcp_server_with_token(): void
    {
        Process::fake([
            'npm list -g @modelcontextprotocol/server-github' => Process::result('', '', 1),
            'npm install -g @modelcontextprotocol/server-github' => Process::result('installed successfully', '', 0),
            'npx @modelcontextprotocol/server-github --version' => Process::result('1.0.0', '', 0),
        ]);

        $this->artisan('ai:mcp:setup')
            ->expectsChoice('Which MCP server would you like to install?', 'GitHub MCP', [
                'Sequential Thinking',
                'Brave Search',
                'GitHub MCP',
                'Custom Server',
            ])
            ->expectsOutput('Installing GitHub MCP server...')
            ->expectsOutput('Installing npm package: @modelcontextprotocol/server-github')
            ->expectsQuestion('Enter your GitHub personal access token', 'ghp_test_token_123')
            ->expectsConfirmation('Test the GitHub token?', 'yes')
            ->expectsOutput('✓ GitHub token validated successfully')
            ->expectsOutput('✓ GitHub MCP server installed and configured successfully')
            ->assertExitCode(0);

        // Verify configuration
        $config = json_decode(File::get($this->testConfigPath), true);
        $this->assertArrayHasKey('github', $config['servers']);
        $this->assertEquals('external', $config['servers']['github']['type']);
        $this->assertEquals('ghp_test_token_123', $config['servers']['github']['env']['GITHUB_TOKEN']);
    }

    #[Test]
    public function it_handles_custom_server_installation(): void
    {
        $this->artisan('ai:mcp:setup')
            ->expectsChoice('Which MCP server would you like to install?', 'Custom Server', [
                'Sequential Thinking',
                'Brave Search',
                'GitHub MCP',
                'Custom Server',
            ])
            ->expectsQuestion('Enter server name', 'custom-server')
            ->expectsQuestion('Enter server command', 'node /path/to/custom-server.js')
            ->expectsConfirmation('Is this an external server?', 'yes')
            ->expectsConfirmation('Does this server require environment variables?', 'yes')
            ->expectsQuestion('Enter environment variable name (or press enter to finish)', 'CUSTOM_API_KEY')
            ->expectsQuestion('Enter value for CUSTOM_API_KEY', 'custom-key-123')
            ->expectsQuestion('Enter environment variable name (or press enter to finish)', '')
            ->expectsOutput('✓ Custom server configured successfully')
            ->assertExitCode(0);

        // Verify configuration
        $config = json_decode(File::get($this->testConfigPath), true);
        $this->assertArrayHasKey('custom-server', $config['servers']);
        $this->assertEquals('external', $config['servers']['custom-server']['type']);
        $this->assertEquals('node /path/to/custom-server.js', $config['servers']['custom-server']['command']);
        $this->assertEquals('custom-key-123', $config['servers']['custom-server']['env']['CUSTOM_API_KEY']);
    }

    #[Test]
    public function it_validates_existing_installations(): void
    {
        Process::fake([
            'npm list -g @modelcontextprotocol/server-brave-search' => Process::result('@modelcontextprotocol/server-brave-search@1.0.0', '', 0),
        ]);

        $this->artisan('ai:mcp:setup')
            ->expectsChoice('Which MCP server would you like to install?', 'Brave Search', [
                'Sequential Thinking',
                'Brave Search',
                'GitHub MCP',
                'Custom Server',
            ])
            ->expectsOutput('✓ @modelcontextprotocol/server-brave-search is already installed')
            ->expectsQuestion('Enter your Brave Search API key', 'test-api-key-123')
            ->expectsConfirmation('Test the API key?', 'yes')
            ->expectsOutput('✓ API key validated successfully')
            ->expectsOutput('✓ Brave Search server configured successfully')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_handles_installation_failures_gracefully(): void
    {
        Process::fake([
            'npm list -g @modelcontextprotocol/server-brave-search' => Process::result('', '', 1),
            'npm install -g @modelcontextprotocol/server-brave-search' => Process::result('', 'Installation failed', 1),
        ]);

        $this->artisan('ai:mcp:setup')
            ->expectsChoice('Which MCP server would you like to install?', 'Brave Search', [
                'Sequential Thinking',
                'Brave Search',
                'GitHub MCP',
                'Custom Server',
            ])
            ->expectsOutput('Installing Brave Search MCP server...')
            ->expectsOutput('Installing npm package: @modelcontextprotocol/server-brave-search')
            ->expectsOutput('✗ Failed to install @modelcontextprotocol/server-brave-search')
            ->expectsOutput('Error: Installation failed')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_validates_api_keys_and_tokens(): void
    {
        Process::fake([
            'npm list -g @modelcontextprotocol/server-brave-search' => Process::result('@modelcontextprotocol/server-brave-search@1.0.0', '', 0),
        ]);

        // Mock HTTP client for API validation
        $this->mockHttpClient([
            'https://api.search.brave.com/res/v1/web/search?q=test&count=1' => [
                'status' => 401,
                'body' => json_encode(['error' => 'Invalid API key']),
            ],
        ]);

        $this->artisan('ai:mcp:setup')
            ->expectsChoice('Which MCP server would you like to install?', 'Brave Search', [
                'Sequential Thinking',
                'Brave Search',
                'GitHub MCP',
                'Custom Server',
            ])
            ->expectsQuestion('Enter your Brave Search API key', 'invalid-api-key')
            ->expectsConfirmation('Test the API key?', 'yes')
            ->expectsOutput('✗ API key validation failed: Invalid API key')
            ->expectsConfirmation('Continue with invalid API key?', 'no')
            ->expectsOutput('Setup cancelled')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_creates_mcp_tools_discovery_file(): void
    {
        $this->artisan('ai:mcp:setup')
            ->expectsChoice('Which MCP server would you like to install?', 'Sequential Thinking', [
                'Sequential Thinking',
                'Brave Search',
                'GitHub MCP',
                'Custom Server',
            ])
            ->expectsOutput('✓ Sequential Thinking server configured successfully')
            ->expectsOutput('✓ Tool discovery file updated')
            ->assertExitCode(0);

        // Verify tools file was created
        $this->assertFileExists($this->testToolsPath);
        
        $tools = json_decode(File::get($this->testToolsPath), true);
        $this->assertArrayHasKey('sequential_thinking', $tools['tools']);
        $this->assertArrayHasKey('description', $tools['tools']['sequential_thinking']);
        $this->assertArrayHasKey('parameters', $tools['tools']['sequential_thinking']);
    }

    #[Test]
    public function it_handles_multiple_server_installations(): void
    {
        // Install Sequential Thinking first
        $this->artisan('ai:mcp:setup')
            ->expectsChoice('Which MCP server would you like to install?', 'Sequential Thinking', [
                'Sequential Thinking',
                'Brave Search',
                'GitHub MCP',
                'Custom Server',
            ])
            ->expectsOutput('✓ Sequential Thinking server configured successfully')
            ->assertExitCode(0);

        // Install Brave Search second
        Process::fake([
            'npm list -g @modelcontextprotocol/server-brave-search' => Process::result('', '', 1),
            'npm install -g @modelcontextprotocol/server-brave-search' => Process::result('installed successfully', '', 0),
        ]);

        $this->artisan('ai:mcp:setup')
            ->expectsChoice('Which MCP server would you like to install?', 'Brave Search', [
                'Sequential Thinking',
                'Brave Search',
                'GitHub MCP',
                'Custom Server',
            ])
            ->expectsQuestion('Enter your Brave Search API key', 'test-api-key-123')
            ->expectsConfirmation('Test the API key?', 'no')
            ->expectsOutput('✓ Brave Search server installed and configured successfully')
            ->assertExitCode(0);

        // Verify both servers are configured
        $config = json_decode(File::get($this->testConfigPath), true);
        $this->assertArrayHasKey('sequential_thinking', $config['servers']);
        $this->assertArrayHasKey('brave_search', $config['servers']);
        $this->assertCount(2, $config['servers']);
    }

    #[Test]
    public function it_shows_installation_status(): void
    {
        // Create existing configuration
        $existingConfig = [
            'servers' => [
                'sequential_thinking' => [
                    'type' => 'built-in',
                    'enabled' => true,
                ],
                'brave_search' => [
                    'type' => 'external',
                    'command' => 'npx @modelcontextprotocol/server-brave-search',
                    'enabled' => false,
                ],
            ],
        ];
        File::put($this->testConfigPath, json_encode($existingConfig, JSON_PRETTY_PRINT));

        $this->artisan('ai:mcp:status')
            ->expectsOutput('MCP Server Status:')
            ->expectsOutput('✓ sequential_thinking - Built-in (Enabled)')
            ->expectsOutput('✗ brave_search - External (Disabled)')
            ->expectsOutput('')
            ->expectsOutput('Total servers: 2')
            ->expectsOutput('Enabled: 1')
            ->expectsOutput('Disabled: 1')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_enables_and_disables_servers(): void
    {
        // Create existing configuration
        $existingConfig = [
            'servers' => [
                'sequential_thinking' => [
                    'type' => 'built-in',
                    'enabled' => true,
                ],
                'brave_search' => [
                    'type' => 'external',
                    'command' => 'npx @modelcontextprotocol/server-brave-search',
                    'enabled' => false,
                ],
            ],
        ];
        File::put($this->testConfigPath, json_encode($existingConfig, JSON_PRETTY_PRINT));

        // Enable brave_search
        $this->artisan('ai:mcp:enable brave_search')
            ->expectsOutput('✓ Enabled brave_search server')
            ->assertExitCode(0);

        $config = json_decode(File::get($this->testConfigPath), true);
        $this->assertTrue($config['servers']['brave_search']['enabled']);

        // Disable sequential_thinking
        $this->artisan('ai:mcp:disable sequential_thinking')
            ->expectsOutput('✓ Disabled sequential_thinking server')
            ->assertExitCode(0);

        $config = json_decode(File::get($this->testConfigPath), true);
        $this->assertFalse($config['servers']['sequential_thinking']['enabled']);
    }

    #[Test]
    public function it_tests_server_connectivity(): void
    {
        // Create configuration with servers
        $config = [
            'servers' => [
                'sequential_thinking' => [
                    'type' => 'built-in',
                    'enabled' => true,
                ],
                'brave_search' => [
                    'type' => 'external',
                    'command' => 'npx @modelcontextprotocol/server-brave-search',
                    'enabled' => true,
                    'env' => ['BRAVE_API_KEY' => 'test-key'],
                ],
            ],
        ];
        File::put($this->testConfigPath, json_encode($config, JSON_PRETTY_PRINT));

        Process::fake([
            'npx @modelcontextprotocol/server-brave-search' => Process::result('{"tools": []}', '', 0),
        ]);

        $this->artisan('ai:mcp:test')
            ->expectsOutput('Testing MCP servers...')
            ->expectsOutput('✓ sequential_thinking - OK')
            ->expectsOutput('✓ brave_search - OK')
            ->expectsOutput('')
            ->expectsOutput('All servers tested successfully')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_removes_servers(): void
    {
        // Create configuration with servers
        $config = [
            'servers' => [
                'sequential_thinking' => [
                    'type' => 'built-in',
                    'enabled' => true,
                ],
                'brave_search' => [
                    'type' => 'external',
                    'command' => 'npx @modelcontextprotocol/server-brave-search',
                    'enabled' => true,
                ],
            ],
        ];
        File::put($this->testConfigPath, json_encode($config, JSON_PRETTY_PRINT));

        $this->artisan('ai:mcp:remove brave_search')
            ->expectsConfirmation('Are you sure you want to remove the brave_search server?', 'yes')
            ->expectsOutput('✓ Removed brave_search server')
            ->assertExitCode(0);

        $config = json_decode(File::get($this->testConfigPath), true);
        $this->assertArrayNotHasKey('brave_search', $config['servers']);
        $this->assertArrayHasKey('sequential_thinking', $config['servers']);
    }

    /**
     * Clean up test files.
     */
    protected function cleanupTestFiles(): void
    {
        if (File::exists($this->testConfigPath)) {
            File::delete($this->testConfigPath);
        }
        
        if (File::exists($this->testToolsPath)) {
            File::delete($this->testToolsPath);
        }
    }

    /**
     * Mock HTTP client responses.
     */
    protected function mockHttpClient(array $responses): void
    {
        // This would mock HTTP client responses in a real implementation
        // For now, we'll just log the expected responses
        foreach ($responses as $url => $response) {
            \Log::info("Mocked HTTP response for {$url}", $response);
        }
    }
}
