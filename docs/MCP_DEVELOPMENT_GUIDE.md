# MCP Server Development Guide

This guide covers the development patterns, configuration, and integration guidelines for Model Context Protocol (MCP) servers in JTD Laravel AI.

## Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Server Types](#server-types)
4. [Configuration Patterns](#configuration-patterns)
5. [Development Workflow](#development-workflow)
6. [Testing Strategies](#testing-strategies)
7. [Performance Guidelines](#performance-guidelines)
8. [Troubleshooting](#troubleshooting)

## Overview

MCP servers provide enhanced AI capabilities through structured thinking processes, tool integration, and context management. The JTD Laravel AI package supports external MCP servers installed via npm packages.

### Key Features

- **Easy Setup**: Interactive installation via `php artisan ai:mcp:setup`
- **Configuration Management**: JSON-based configuration with validation
- **Tool Discovery**: Automatic discovery and caching of available tools
- **Performance Monitoring**: Built-in metrics and performance tracking
- **Error Handling**: Graceful degradation and comprehensive error reporting

## Architecture

### Core Components

```
MCPManager
├── MCPConfigurationService    # Configuration loading and validation
├── MCPToolDiscoveryService   # Tool discovery and caching
├── MCPServerInstaller        # Package installation management
├── MCPServerValidator        # Server validation and testing
└── ExternalMCPServer         # External server communication
```

### Data Flow

1. **Configuration Loading**: `.mcp.json` → MCPConfigurationService → MCPManager
2. **Server Registration**: MCPManager registers ExternalMCPServer instances
3. **Tool Discovery**: MCPToolDiscoveryService discovers tools → `.mcp.tools.json`
4. **Request Processing**: AIMessage → MCP servers → Enhanced AIMessage
5. **Response Processing**: AIResponse → MCP servers → Enhanced AIResponse

## Server Types

### External Servers

External servers run as separate Node.js processes and communicate via command execution.

**Supported Servers:**
- **Sequential Thinking**: Structured problem-solving and reasoning
- **GitHub MCP**: Repository management and search
- **Brave Search**: Web search capabilities

**Installation Pattern:**
```bash
npm install -g @modelcontextprotocol/server-{name}
```

## Configuration Patterns

### .mcp.json Structure

```json
{
  "servers": {
    "server-name": {
      "type": "external",
      "enabled": true,
      "command": "npx @modelcontextprotocol/server-name",
      "args": ["--verbose"],
      "env": {
        "API_KEY": "${API_KEY_ENV_VAR}"
      },
      "timeout": 30,
      "config": {
        "custom_option": "value"
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

### Environment Variables

Use placeholder syntax for environment variables:
```json
{
  "env": {
    "GITHUB_TOKEN": "${GITHUB_PERSONAL_ACCESS_TOKEN}",
    "BRAVE_API_KEY": "${BRAVE_API_KEY}"
  }
}
```

### Laravel Configuration

Update `config/ai.php` for application-wide MCP settings:
```php
'mcp' => [
    'enabled' => env('AI_MCP_ENABLED', true),
    'timeout' => env('AI_MCP_TIMEOUT', 30),
    'max_concurrent' => env('AI_MCP_MAX_CONCURRENT', 3),
    'tool_discovery_cache_ttl' => env('AI_MCP_TOOL_CACHE_TTL', 3600),
],
```

## Development Workflow

### 1. Server Installation

```bash
# Interactive setup with prompts
php artisan ai:mcp:setup

# Install specific server interactively
php artisan ai:mcp:setup sequential-thinking

# List available servers
php artisan ai:mcp:setup --list

# Non-interactive installation (for automation/CI)
php artisan ai:mcp:setup sequential-thinking --non-interactive

# Install with API key in non-interactive mode
php artisan ai:mcp:setup github --api-key=your-token --non-interactive

# Skip installation steps for testing
php artisan ai:mcp:setup sequential-thinking --skip-install --skip-test --non-interactive

# Force reconfiguration of existing server
php artisan ai:mcp:setup sequential-thinking --force --non-interactive
```

### 2. Configuration Management

```bash
# List configured servers
php artisan ai:mcp:list

# Test server connectivity
php artisan ai:mcp:test

# Discover tools
php artisan ai:mcp:discover
```

### 3. Server Removal

```bash
# Remove server configuration
php artisan ai:mcp:remove server-name

# Remove and uninstall package
php artisan ai:mcp:remove server-name --uninstall
```

### 4. Development Testing

```php
// Test server integration
$mcpManager = app(MCPManager::class);
$message = new AIMessage('Test message');
$processedMessage = $mcpManager->processMessage($message, ['sequential-thinking']);

// Execute tools
$result = $mcpManager->executeTool('github', 'search_repositories', [
    'query' => 'laravel ai',
    'limit' => 10
]);
```

## Testing Strategies

### Unit Testing

Use PHPUnit 12 with `#[Test]` attributes:

```php
use PHPUnit\Framework\Attributes\Test;

class MCPServerTest extends TestCase
{
    #[Test]
    public function it_processes_messages_correctly(): void
    {
        $server = new ExternalMCPServer('test', $config);
        $message = new AIMessage('test');
        
        $result = $server->processMessage($message);
        
        $this->assertInstanceOf(AIMessage::class, $result);
    }
}
```

### Integration Testing

Test complete MCP workflows:

```php
#[Test]
public function it_integrates_with_real_mcp_server(): void
{
    $this->artisan('ai:mcp:setup sequential-thinking --skip-install')
        ->expectsOutput('✅ Sequential Thinking has been configured successfully!');
    
    $mcpManager = app(MCPManager::class);
    $tools = $mcpManager->getAvailableTools('sequential-thinking');
    
    $this->assertNotEmpty($tools);
}
```

### Performance Testing

Monitor response times and resource usage:

```php
#[Test]
public function it_meets_performance_benchmarks(): void
{
    $startTime = microtime(true);
    
    $mcpManager->processMessage($message, ['sequential-thinking']);
    
    $executionTime = (microtime(true) - $startTime) * 1000;
    $this->assertLessThan(100, $executionTime); // <100ms target
}
```

## Performance Guidelines

### Response Time Targets

- **Built-in servers**: <100ms processing overhead
- **External servers**: <500ms processing overhead
- **Tool discovery**: <2s for external servers
- **Tool execution**: Varies by tool complexity

### Optimization Strategies

1. **Caching**: Tool definitions cached for 1 hour by default
2. **Concurrent Processing**: Max 3 concurrent server operations
3. **Timeout Management**: 30s default timeout with configurable overrides
4. **Error Handling**: Graceful degradation when servers unavailable

### Monitoring

```php
// Get server metrics
$metrics = $mcpManager->getMetrics('server-name');

// Performance data includes:
// - Response times
// - Success/error rates
// - Tool execution statistics
// - Cache hit rates
```

## Troubleshooting

### Common Issues

#### Server Not Found
```bash
# Check if server is installed
npm list -g @modelcontextprotocol/server-name

# Reinstall if needed
php artisan ai:mcp:setup server-name
```

#### Configuration Errors
```bash
# Validate configuration
php artisan ai:mcp:test server-name --comprehensive

# Check logs
tail -f storage/logs/laravel.log | grep MCP
```

#### Environment Variables
```bash
# Check required environment variables
php artisan ai:mcp:list --status

# Verify .env file contains required keys
grep -E "(GITHUB_|BRAVE_)" .env
```

#### Performance Issues
```bash
# Check server performance
php artisan ai:mcp:test --comprehensive

# Clear tool cache
php artisan ai:mcp:discover --force
```

### Debug Mode

Enable detailed logging in `config/ai.php`:
```php
'mcp' => [
    'performance' => [
        'track_metrics' => true,
        'log_slow_operations' => true,
        'slow_operation_threshold_ms' => 1000,
    ],
],
```

### Health Checks

Regular server health monitoring:
```php
// Automated health check
$results = $mcpManager->testServers();

foreach ($results as $server => $result) {
    if ($result['status'] !== 'healthy') {
        Log::warning("MCP server {$server} is unhealthy", $result);
    }
}
```

## Best Practices

1. **Configuration**: Always validate configuration before deployment
2. **Error Handling**: Implement graceful degradation for server failures
3. **Performance**: Monitor response times and optimize slow operations
4. **Security**: Validate all inputs and sanitize outputs
5. **Testing**: Write comprehensive tests for all MCP integrations
6. **Documentation**: Document custom server configurations and usage patterns

## Advanced Topics

### Custom Server Development

For custom MCP servers, follow the MCP specification and implement:
- Health check endpoints
- Tool discovery endpoints
- Proper error handling
- Performance monitoring

### Load Balancing

For high-traffic applications, consider:
- Multiple server instances
- Request queuing
- Circuit breaker patterns
- Fallback mechanisms

### Monitoring and Alerting

Implement monitoring for:
- Server availability
- Response times
- Error rates
- Resource usage

This guide provides the foundation for developing, configuring, and maintaining MCP servers in JTD Laravel AI applications.
