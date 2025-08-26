<?php

namespace JTD\LaravelAI\Tests\Feature\MCPSetup;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use JTD\LaravelAI\Services\MCPConfigurationService;
use JTD\LaravelAI\Services\MCPManager;
use JTD\LaravelAI\Services\MCPServerInstaller;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * MCP Installation Tests
 *
 * Tests for MCP server installation workflows including
 * Sequential Thinking, GitHub, and Brave Search servers.
 */
#[Group('mcp-setup')]
#[Group('mcp-installation')]
class MCPInstallationTest extends TestCase
{
    use RefreshDatabase;

    protected string $testConfigPath;

    protected string $testToolsPath;

    protected MCPConfigurationService $configService;

    protected MCPManager $mcpManager;

    protected MCPServerInstaller $installer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testConfigPath = base_path('.mcp.json');
        $this->testToolsPath = base_path('.mcp.tools.json');
        $this->configService = app(MCPConfigurationService::class);
        $this->mcpManager = app(MCPManager::class);
        $this->installer = app(MCPServerInstaller::class);

        $this->cleanupTestFiles();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestFiles();
        parent::tearDown();
    }

    #[Test]
    public function it_installs_sequential_thinking_server_successfully(): void
    {
        Process::fake([
            'npm install -g @modelcontextprotocol/server-sequential-thinking' => Process::result('installed successfully', '', 0),
        ]);

        $result = $this->installer->installServer('sequential-thinking');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('installed successfully', $result['output']);

        Process::assertRan('npm install -g @modelcontextprotocol/server-sequential-thinking');
    }

    #[Test]
    public function it_installs_github_server_successfully(): void
    {
        Process::fake([
            'npm install -g @modelcontextprotocol/server-github' => Process::result('installed successfully', '', 0),
        ]);

        $result = $this->installer->installServer('github');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('installed successfully', $result['output']);

        Process::assertRan('npm install -g @modelcontextprotocol/server-github');
    }

    #[Test]
    public function it_installs_brave_search_server_successfully(): void
    {
        Process::fake([
            'npm install -g @modelcontextprotocol/server-brave-search' => Process::result('installed successfully', '', 0),
        ]);

        $result = $this->installer->installServer('brave-search');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('installed successfully', $result['output']);

        Process::assertRan('npm install -g @modelcontextprotocol/server-brave-search');
    }

    #[Test]
    public function it_handles_installation_failures(): void
    {
        Process::fake([
            'npm install -g @modelcontextprotocol/server-brave-search' => Process::result('', 'Installation failed', 1),
        ]);

        $result = $this->installer->installServer('brave-search');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Installation failed', $result['error']);

        Process::assertRan('npm install -g @modelcontextprotocol/server-brave-search');
    }

    #[Test]
    public function it_checks_existing_installations(): void
    {
        Process::fake([
            'npm list -g @modelcontextprotocol/server-sequential-thinking' => Process::result('@modelcontextprotocol/server-sequential-thinking@1.0.0', '', 0),
        ]);

        $status = $this->installer->isServerInstalled('sequential-thinking');

        $this->assertTrue($status['installed']);
        $this->assertEquals('1.0.0', $status['version']);

        Process::assertRan('npm list -g @modelcontextprotocol/server-sequential-thinking');
    }

    #[Test]
    public function it_detects_missing_installations(): void
    {
        Process::fake([
            'npm list -g @modelcontextprotocol/server-github' => Process::result('', 'not found', 1),
        ]);

        $status = $this->installer->isServerInstalled('github');

        $this->assertFalse($status['installed']);
        $this->assertNull($status['version']);

        Process::assertRan('npm list -g @modelcontextprotocol/server-github');
    }

    #[Test]
    public function it_configures_sequential_thinking_server(): void
    {
        $config = [
            'type' => 'external',
            'enabled' => true,
            'command' => 'npx @modelcontextprotocol/server-sequential-thinking',
            'config' => [
                'max_thoughts' => 10,
                'min_thoughts' => 2,
                'show_thinking' => false,
            ],
        ];

        $result = $this->configService->addServer('sequential-thinking', $config);

        $this->assertTrue($result);
        $this->assertFileExists($this->testConfigPath);

        $savedConfig = json_decode(File::get($this->testConfigPath), true);
        $this->assertArrayHasKey('sequential-thinking', $savedConfig['servers']);
        $this->assertEquals($config, $savedConfig['servers']['sequential-thinking']);
    }

    #[Test]
    public function it_configures_github_server_with_environment_variables(): void
    {
        $config = [
            'type' => 'external',
            'enabled' => true,
            'command' => 'npx @modelcontextprotocol/server-github',
            'env' => [
                'GITHUB_PERSONAL_ACCESS_TOKEN' => '${GITHUB_PERSONAL_ACCESS_TOKEN}',
            ],
            'config' => [
                'timeout' => 30,
            ],
        ];

        $result = $this->configService->addServer('github', $config);

        $this->assertTrue($result);
        $this->assertFileExists($this->testConfigPath);

        $savedConfig = json_decode(File::get($this->testConfigPath), true);
        $this->assertArrayHasKey('github', $savedConfig['servers']);
        $this->assertEquals($config, $savedConfig['servers']['github']);
        $this->assertArrayHasKey('env', $savedConfig['servers']['github']);
        $this->assertEquals('${GITHUB_PERSONAL_ACCESS_TOKEN}', $savedConfig['servers']['github']['env']['GITHUB_PERSONAL_ACCESS_TOKEN']);
    }

    #[Test]
    public function it_configures_brave_search_server_with_api_key(): void
    {
        $config = [
            'type' => 'external',
            'enabled' => true,
            'command' => 'npx @modelcontextprotocol/server-brave-search',
            'env' => [
                'BRAVE_API_KEY' => '${BRAVE_API_KEY}',
            ],
        ];

        $result = $this->configService->addServer('brave-search', $config);

        $this->assertTrue($result);
        $this->assertFileExists($this->testConfigPath);

        $savedConfig = json_decode(File::get($this->testConfigPath), true);
        $this->assertArrayHasKey('brave-search', $savedConfig['servers']);
        $this->assertEquals($config, $savedConfig['servers']['brave-search']);
        $this->assertArrayHasKey('env', $savedConfig['servers']['brave-search']);
        $this->assertEquals('${BRAVE_API_KEY}', $savedConfig['servers']['brave-search']['env']['BRAVE_API_KEY']);
    }

    #[Test]
    public function it_handles_multiple_server_configurations(): void
    {
        $sequentialConfig = [
            'type' => 'external',
            'enabled' => true,
            'command' => 'npx @modelcontextprotocol/server-sequential-thinking',
        ];

        $githubConfig = [
            'type' => 'external',
            'enabled' => true,
            'command' => 'npx @modelcontextprotocol/server-github',
            'env' => ['GITHUB_PERSONAL_ACCESS_TOKEN' => '${GITHUB_PERSONAL_ACCESS_TOKEN}'],
        ];

        $this->configService->addServer('sequential-thinking', $sequentialConfig);
        $this->configService->addServer('github', $githubConfig);

        $savedConfig = json_decode(File::get($this->testConfigPath), true);
        $this->assertCount(2, $savedConfig['servers']);
        $this->assertArrayHasKey('sequential-thinking', $savedConfig['servers']);
        $this->assertArrayHasKey('github', $savedConfig['servers']);
    }

    #[Test]
    public function it_validates_server_templates(): void
    {
        $sequentialTemplate = $this->installer->getServerTemplate('sequential-thinking');
        $this->assertNotNull($sequentialTemplate);
        $this->assertEquals('Sequential Thinking', $sequentialTemplate['name']);
        $this->assertEquals('@modelcontextprotocol/server-sequential-thinking', $sequentialTemplate['package']);
        $this->assertFalse($sequentialTemplate['requires_api_key']);

        $githubTemplate = $this->installer->getServerTemplate('github');
        $this->assertNotNull($githubTemplate);
        $this->assertEquals('GitHub MCP', $githubTemplate['name']);
        $this->assertEquals('@modelcontextprotocol/server-github', $githubTemplate['package']);
        $this->assertTrue($githubTemplate['requires_api_key']);

        $braveTemplate = $this->installer->getServerTemplate('brave-search');
        $this->assertNotNull($braveTemplate);
        $this->assertEquals('Brave Search', $braveTemplate['name']);
        $this->assertEquals('@modelcontextprotocol/server-brave-search', $braveTemplate['package']);
        $this->assertTrue($braveTemplate['requires_api_key']);
    }

    #[Test]
    public function it_handles_unknown_server_templates(): void
    {
        $unknownTemplate = $this->installer->getServerTemplate('unknown-server');
        $this->assertNull($unknownTemplate);
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
