# Sprint 4b: Cost Tracking and MCP Integration Implementation

**Duration**: 1.5-2 weeks
**Epic**: Cost Tracking and Analytics + MCP Integration
**Goal**: Implement comprehensive cost tracking, analytics, and robust MCP system with Easy MCP Setup using the middleware and event system foundation from Sprint 4a
**Dependencies**: Sprint 4a (Middleware and Event System Foundation)

## Sprint Objectives

1. Implement real-time cost tracking using event-driven architecture
2. Build budget management with middleware enforcement and event-driven alerts
3. Create usage analytics with background event processing
4. Develop robust MCP server framework with .mcp.json configuration
5. Create Easy MCP Setup system with interactive artisan commands
6. Add comprehensive MCP testing within event-driven architecture

## User Stories

### Story 1: Real-time Cost Tracking with Events
**As a finance manager, I want real-time cost tracking so I can monitor AI spending with 85% faster response times**

**Acceptance Criteria:**
- Costs are calculated automatically via ResponseGenerated events
- Real-time cost updates processed in background queues
- Cost breakdown by provider, model, and user
- Historical cost data is preserved
- Cost calculations are accurate to provider billing
- Response times are 85% faster than synchronous processing

**Tasks:**
- [ ] Implement cost calculation engine using events
- [ ] Create CostTrackingListener for background processing
- [ ] Add cost breakdown analytics via events
- [ ] Create cost history storage with event sourcing
- [ ] Validate cost accuracy against provider APIs
- [ ] Add comprehensive cost tracking tests
- [ ] Benchmark performance improvements

**Estimated Effort:** 2 days

### Story 2: Budget Management with Middleware and Events
**As a user, I want budget limits enforced via middleware so I don't exceed spending with real-time alerts**

**Acceptance Criteria:**
- Budget enforcement happens at middleware level (pre-request)
- Monthly, daily, and per-request budgets are supported
- Budget alerts sent via BudgetThresholdReached events
- Budget status is easily accessible
- Supports different budget types (user, project, organization)
- Real-time notifications through event system

**Tasks:**
- [ ] Enhance BudgetEnforcementMiddleware from Sprint 4a
- [ ] Implement budget alert system via events
- [ ] Create budget status dashboard
- [ ] Support multiple budget types and hierarchies
- [ ] Add budget notification listeners
- [ ] Write comprehensive budget management tests
- [ ] Create budget management API endpoints

**Estimated Effort:** 2 days

### Story 3: Usage Analytics with Background Processing
**As an administrator, I want usage analytics processed in background so I can optimize AI usage without impacting performance**

**Acceptance Criteria:**
- Analytics generated via AnalyticsListener processing events
- Comprehensive usage reports and dashboards
- Trend analysis and forecasting
- Provider and model performance comparison
- Cost optimization recommendations
- Exportable reports in multiple formats
- All processing happens in background queues

**Tasks:**
- [ ] Enhance AnalyticsListener from Sprint 4a
- [ ] Implement usage reporting with event data
- [ ] Add trend analysis and forecasting
- [ ] Create optimization recommendations engine
- [ ] Build report export functionality
- [ ] Add analytics dashboard components
- [ ] Write analytics processing tests

**Estimated Effort:** 2 days

### Story 4: MCP Server Framework and Configuration System
**As a developer, I want a robust MCP framework with .mcp.json configuration so I can easily manage and extend MCP servers**

**Acceptance Criteria:**
- MCP servers are configured via .mcp.json in project root
- MCP tools are discovered and stored in .mcp.tools.json
- MCP server registry supports built-in and external servers
- MCP processing integrates with event-driven architecture
- Performance monitoring and error handling for MCP servers
- Support for MCP server chaining and composition

**Tasks:**
- [ ] Create MCP server interface and registry
- [ ] Implement .mcp.json configuration system
- [ ] Add MCP tool discovery and .mcp.tools.json generation
- [ ] Integrate MCP processing with event-driven flow
- [ ] Add MCP performance monitoring and error handling
- [ ] Create MCP server chaining capabilities
- [ ] Write comprehensive MCP framework tests
- [ ] Document MCP server development patterns

**Estimated Effort:** 3 days

### Story 5: Easy MCP Setup System
**As a developer, I want interactive artisan commands so I can easily install and configure popular MCP servers**

**Acceptance Criteria:**
- Interactive artisan command lists available MCP installations
- Supports Sequential Thinking (@modelcontextprotocol/server-sequential-thinking), GitHub MCP (https://github.com/github/github-mcp-server), Brave Search (@modelcontextprotocol/server-brave-search), and extensible for more
- Prompts for API keys and configuration details using laravel/prompts
- Automatically installs global packages and updates .mcp.json
- Validates configurations and tests MCP server connectivity
- Provides clear error messages and troubleshooting guidance

**Tasks:**
- [ ] Create ai:mcp:setup interactive command using laravel/prompts
- [ ] Implement MCP installation scripts for popular servers
- [ ] Add API key and configuration prompting system
- [ ] Create automatic .mcp.json configuration updates
- [ ] Add MCP server validation and testing
- [ ] Implement ai:mcp:discover command for tool discovery
- [ ] Write Easy MCP Setup documentation and examples
- [ ] Create MCP setup tests

**Estimated Effort:** 3 days

### Story 6: MCP Testing and Event Integration
**As a developer, I want comprehensive MCP testing so I can ensure MCP servers work correctly within the event-driven architecture**

**Acceptance Criteria:**
- MCP servers are tested within the complete event-driven request flow
- Integration tests verify MCP + middleware + events work together
- Performance tests ensure MCP processing doesn't impact response times
- Error handling tests verify graceful degradation
- Load tests validate MCP scalability
- E2E tests with real MCP servers and external APIs

**Tasks:**
- [ ] Create MCP integration tests with event-driven flow
- [ ] Add MCP performance and load testing
- [ ] Implement MCP error handling and fallback tests
- [ ] Create E2E tests with real MCP servers
- [ ] Add MCP + middleware integration tests
- [ ] Write MCP scalability and stress tests
- [ ] Document MCP testing patterns and best practices

**Estimated Effort:** 2 days

### Story 7: Performance Optimization and Monitoring
**As a developer, I want performance monitoring so I can optimize the event-driven system**

**Acceptance Criteria:**
- Event processing performance is tracked
- Middleware execution times are monitored
- Queue performance metrics are available
- Slow operations are identified automatically
- Performance benchmarks are established
- Optimization recommendations are provided

**Tasks:**
- [ ] Implement event processing performance tracking
- [ ] Add middleware execution time monitoring
- [ ] Create queue performance metrics
- [ ] Build performance monitoring dashboard
- [ ] Add automated performance alerts
- [ ] Create optimization recommendation system
- [ ] Write performance monitoring tests

**Estimated Effort:** 1 day

## Technical Implementation

### MCP Configuration System (.mcp.json)

```json
{
  "servers": {
    "sequential-thinking": {
      "type": "external",
      "enabled": true,
      "command": "npx @modelcontextprotocol/server-sequential-thinking",
      "args": [],
      "config": {
        "max_thoughts": 10,
        "min_thoughts": 2,
        "show_thinking": false
      }
    },
    "github": {
      "type": "external",
      "enabled": true,
      "command": "npx @modelcontextprotocol/server-github",
      "args": [],
      "env": {
        "GITHUB_PERSONAL_ACCESS_TOKEN": "${GITHUB_PERSONAL_ACCESS_TOKEN}"
      },
      "config": {
        "timeout": 30
      }
    },
    "brave-search": {
      "type": "external",
      "enabled": true,
      "command": "npx @modelcontextprotocol/server-brave-search",
      "args": [],
      "env": {
        "BRAVE_API_KEY": "${BRAVE_API_KEY}"
      }
    }
  },
  "global_config": {
    "timeout": 30,
    "max_concurrent": 3,
    "retry_attempts": 2
  }
}
```

### MCP Tools Discovery (.mcp.tools.json)

```json
{
  "github": {
    "tools": [
      {
        "name": "create_repository",
        "description": "Create a new GitHub repository",
        "inputSchema": {
          "type": "object",
          "properties": {
            "name": {"type": "string"},
            "description": {"type": "string"},
            "private": {"type": "boolean"}
          }
        }
      },
      {
        "name": "search_repositories",
        "description": "Search GitHub repositories",
        "inputSchema": {
          "type": "object",
          "properties": {
            "query": {"type": "string"},
            "sort": {"type": "string", "enum": ["stars", "forks", "updated"]}
          }
        }
      }
    ]
  },
  "brave-search": {
    "tools": [
      {
        "name": "web_search",
        "description": "Search the web using Brave Search",
        "inputSchema": {
          "type": "object",
          "properties": {
            "query": {"type": "string"},
            "count": {"type": "integer", "default": 10}
          }
        }
      }
    ]
  }
}
```

### MCP Server Framework

```php
<?php

namespace JTD\LaravelAI\Services;

use JTD\LaravelAI\Contracts\MCPServerInterface;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;

class MCPManager
{
    protected array $servers = [];
    protected array $config = [];

    public function __construct()
    {
        $this->loadConfiguration();
    }

    public function loadConfiguration(): void
    {
        $mcpConfigPath = base_path('.mcp.json');
        $toolsConfigPath = base_path('.mcp.tools.json');

        if (file_exists($mcpConfigPath)) {
            $this->config = json_decode(file_get_contents($mcpConfigPath), true);
        }

        if (file_exists($toolsConfigPath)) {
            $this->tools = json_decode(file_get_contents($toolsConfigPath), true);
        }

        $this->registerServers();
    }

    protected function registerServers(): void
    {
        foreach ($this->config['servers'] ?? [] as $name => $serverConfig) {
            if (!$serverConfig['enabled']) {
                continue;
            }

            // All servers are now external
            $this->registerExternalServer($name, $serverConfig);
        }
    }

    protected function registerExternalServer(string $name, array $config): void
    {
        $this->servers[$name] = new ExternalMCPServer($name, $config);
    }

    public function processMessage(AIMessage $message, array $enabledServers = []): AIMessage
    {
        foreach ($enabledServers as $serverName) {
            if (isset($this->servers[$serverName])) {
                $message = $this->servers[$serverName]->processMessage($message);
            }
        }

        return $message;
    }

    public function processResponse(AIResponse $response, array $enabledServers = []): AIResponse
    {
        foreach ($enabledServers as $serverName) {
            if (isset($this->servers[$serverName])) {
                $response = $this->servers[$serverName]->processResponse($response);
            }
        }

        return $response;
    }

    public function getAvailableTools(string $serverName = null): array
    {
        if ($serverName) {
            return $this->tools[$serverName]['tools'] ?? [];
        }

        $allTools = [];
        foreach ($this->tools as $server => $data) {
            $allTools[$server] = $data['tools'] ?? [];
        }

        return $allTools;
    }
}
```

### Easy MCP Setup Command

```php
<?php

namespace JTD\LaravelAI\Console\Commands;

use Illuminate\Console\Command;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\password;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\error;

class MCPSetupCommand extends Command
{
    protected $signature = 'ai:mcp:setup';
    protected $description = 'Interactive setup for MCP servers';

    protected array $availableServers = [
        'sequential-thinking' => [
            'name' => 'Sequential Thinking',
            'description' => 'Structured step-by-step problem-solving and reasoning',
            'type' => 'external',
            'package' => '@modelcontextprotocol/server-sequential-thinking',
            'requires_api_key' => false,
        ],
        'github' => [
            'name' => 'GitHub MCP',
            'description' => 'GitHub repository management and search',
            'type' => 'external',
            'package' => '@modelcontextprotocol/server-github',
            'requires_api_key' => true,
            'api_key_name' => 'GITHUB_PERSONAL_ACCESS_TOKEN',
        ],
        'brave-search' => [
            'name' => 'Brave Search',
            'description' => 'Web search using Brave Search API',
            'type' => 'external',
            'package' => '@modelcontextprotocol/server-brave-search',
            'requires_api_key' => true,
            'api_key_name' => 'BRAVE_API_KEY',
        ],
    ];

    public function handle(): int
    {
        info('🤖 JTD Laravel AI - MCP Server Setup');
        info('This wizard will help you install and configure MCP servers.');

        $serverKey = select(
            'Which MCP server would you like to install?',
            array_map(fn($server) => $server['name'] . ' - ' . $server['description'], $this->availableServers)
        );

        $serverKey = array_search($serverKey, array_map(fn($server) => $server['name'] . ' - ' . $server['description'], $this->availableServers));
        $server = $this->availableServers[$serverKey];

        return $this->installServer($serverKey, $server);
    }

    protected function installServer(string $key, array $server): int
    {
        info("Installing {$server['name']}...");

        // Install external package (all servers are now external)
        if (!$this->installExternalPackage($server['package'])) {
            error("Failed to install {$server['package']}");
            return 1;
        }

        // Get API key if required
        $apiKey = null;
        if ($server['requires_api_key']) {
            $apiKey = password("Enter your {$server['api_key_name']}:");

            if (empty($apiKey)) {
                error('API key is required for this server.');
                return 1;
            }
        }

        // Configure server
        $config = $this->configureServer($key, $server, $apiKey);

        // Update .mcp.json
        $this->updateMCPConfig($key, $config);

        // Test server
        if (confirm('Would you like to test the server configuration?', true)) {
            $this->testServer($key);
        }

        // Discover tools
        if (confirm('Would you like to discover available tools?', true)) {
            $this->discoverTools($key);
        }

        info("✅ {$server['name']} has been successfully configured!");

        return 0;
    }

    protected function installExternalPackage(string $package): bool
    {
        info("Installing npm package: {$package}");

        $result = shell_exec("npm install -g {$package} 2>&1");

        return $result !== null && !str_contains($result, 'error');
    }

    protected function updateMCPConfig(string $key, array $config): void
    {
        $configPath = base_path('.mcp.json');
        $currentConfig = [];

        if (file_exists($configPath)) {
            $currentConfig = json_decode(file_get_contents($configPath), true) ?? [];
        }

        $currentConfig['servers'][$key] = $config;

        file_put_contents($configPath, json_encode($currentConfig, JSON_PRETTY_PRINT));
    }
}
```



## Configuration Updates

```php
'mcp' => [
    'enabled' => env('AI_MCP_ENABLED', true),
    'config_file' => base_path('.mcp.json'),
    'tools_file' => base_path('.mcp.tools.json'),
    'timeout' => env('AI_MCP_TIMEOUT', 30),
    'max_concurrent' => env('AI_MCP_MAX_CONCURRENT', 3),
    'retry_attempts' => env('AI_MCP_RETRY_ATTEMPTS', 2),

    'built_in_servers' => [
        // All servers are now external and installed via Easy MCP Setup
    ],

    'external_server_timeout' => 30,
    'tool_discovery_cache_ttl' => 3600,
],
```

## Testing Strategy

### MCP Integration Tests

```php
<?php

namespace Tests\Feature\MCP;

use Tests\TestCase;
use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Services\MCPManager;

class MCPIntegrationTest extends TestCase
{
    public function test_mcp_servers_integrate_with_event_driven_flow(): void
    {
        Event::fake();

        // Configure Sequential Thinking MCP
        $this->createMCPConfig([
            'sequential-thinking' => [
                'type' => 'external',
                'enabled' => true,
                'command' => 'npx @modelcontextprotocol/server-sequential-thinking',
                'config' => ['max_thoughts' => 5]
            ]
        ]);

        $response = AI::conversation()
            ->mcp(['sequential-thinking'])
            ->message('Solve this complex problem: Design a distributed system')
            ->send();

        // Verify MCP processing happened
        $this->assertTrue($response->metadata['mcp_sequential_thinking'] ?? false);
        $this->assertNotEmpty($response->metadata['thinking_steps'] ?? []);

        // Verify events were still fired
        Event::assertDispatched(ResponseGenerated::class);
    }

    public function test_external_mcp_server_configuration(): void
    {
        $this->createMCPConfig([
            'github' => [
                'type' => 'external',
                'enabled' => true,
                'command' => 'npx @modelcontextprotocol/server-github',
                'env' => ['GITHUB_PERSONAL_ACCESS_TOKEN' => 'test-token']
            ]
        ]);

        $mcpManager = app(MCPManager::class);
        $tools = $mcpManager->getAvailableTools('github');

        $this->assertNotEmpty($tools);
        $this->assertArrayHasKey('create_repository', array_column($tools, 'name'));
    }

    public function test_mcp_performance_within_event_flow(): void
    {
        $this->createMCPConfig([
            'sequential-thinking' => [
                'type' => 'external',
                'enabled' => true,
                'command' => 'npx @modelcontextprotocol/server-sequential-thinking',
            ]
        ]);

        $startTime = microtime(true);

        $response = AI::conversation()
            ->mcp(['sequential-thinking'])
            ->message('Simple question')
            ->send();

        $totalTime = microtime(true) - $startTime;

        // MCP processing should not significantly impact response time
        $this->assertLessThan(3.0, $totalTime); // 3 seconds max
        $this->assertNotNull($response->content);
    }

    protected function createMCPConfig(array $servers): void
    {
        $config = [
            'servers' => $servers,
            'global_config' => [
                'timeout' => 30,
                'max_concurrent' => 3,
                'retry_attempts' => 2
            ]
        ];

        file_put_contents(base_path('.mcp.json'), json_encode($config, JSON_PRETTY_PRINT));
    }
}
```

### MCP Setup Command Tests

```php
<?php

namespace Tests\Feature\Console;

use Tests\TestCase;
use Illuminate\Support\Facades\File;

class MCPSetupCommandTest extends TestCase
{
    public function test_mcp_setup_creates_configuration(): void
    {
        $this->artisan('ai:mcp:setup')
            ->expectsChoice('Which MCP server would you like to install?', 'Sequential Thinking - Built-in server for structured problem-solving')
            ->expectsConfirmation('Would you like to test the server configuration?', 'no')
            ->expectsConfirmation('Would you like to discover available tools?', 'no')
            ->assertExitCode(0);

        $this->assertTrue(File::exists(base_path('.mcp.json')));

        $config = json_decode(File::get(base_path('.mcp.json')), true);
        $this->assertArrayHasKey('sequential-thinking', $config['servers']);
        $this->assertTrue($config['servers']['sequential-thinking']['enabled']);
    }

    public function test_mcp_discover_command_updates_tools_file(): void
    {
        $this->createMCPConfig([
            'github' => [
                'type' => 'external',
                'enabled' => true,
                'command' => 'npx @modelcontextprotocol/server-github',
            ]
        ]);

        $this->artisan('ai:mcp:discover github')
            ->assertExitCode(0);

        $this->assertTrue(File::exists(base_path('.mcp.tools.json')));

        $tools = json_decode(File::get(base_path('.mcp.tools.json')), true);
        $this->assertArrayHasKey('github', $tools);
        $this->assertNotEmpty($tools['github']['tools']);
    }
}
```

## Definition of Done

- [ ] Real-time cost tracking works via events with 85% performance improvement
- [ ] Budget management enforces limits via middleware and sends alerts via events
- [ ] Usage analytics are processed in background with comprehensive reporting
- [ ] MCP server framework supports .mcp.json configuration and tool discovery
- [ ] Easy MCP Setup commands work with interactive prompts and automatic configuration
- [ ] Sequential Thinking, GitHub, and Brave Search MCP servers can be installed and configured
- [ ] MCP servers integrate seamlessly with event-driven architecture
- [ ] All MCP servers are external and installed via Easy MCP Setup system
- [ ] Comprehensive MCP testing covers integration, performance, and error handling
- [ ] All tests pass with 90%+ coverage including E2E MCP tests
- [ ] Documentation covers MCP setup, configuration, and development patterns

## Performance Benchmarks

- **Response Time**: <200ms for API responses (85% improvement from ~1200ms)
- **MCP Processing**: <100ms additional overhead for built-in servers
- **External MCP**: <500ms timeout for external server communication
- **Cost Calculation**: <50ms background processing
- **Analytics Processing**: <100ms per event
- **Budget Checking**: <10ms middleware overhead
- **Queue Processing**: <500ms average job completion
- **Tool Discovery**: <2s for external server tool discovery

## Artisan Commands Added

```bash
# Interactive MCP server setup
php artisan ai:mcp:setup

# Discover tools from configured MCP servers
php artisan ai:mcp:discover [server-name]

# Test MCP server connectivity
php artisan ai:mcp:test [server-name]

# List configured MCP servers
php artisan ai:mcp:list

# Remove MCP server configuration
php artisan ai:mcp:remove [server-name]
```

## Next Sprint Preview

Sprint 5 will focus on:
- Advanced middleware features (Context Injection, Smart Routing)
- Memory and learning systems with MCP integration
- Middleware chaining and conditional execution
- Advanced MCP server features and custom server development
- Performance optimization and caching for MCP operations
