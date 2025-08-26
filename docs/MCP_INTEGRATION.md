# Model Context Protocol (MCP) Integration

## Overview

JTD Laravel AI includes built-in support for Model Context Protocol (MCP) servers, providing enhanced AI capabilities through structured thinking processes, tool integration, and context management. The package comes with Sequential Thinking support out of the box and provides an extensible architecture for custom MCP servers.

## Quick Setup

### Interactive Installation

The easiest way to set up MCP servers is using the interactive setup command:

```bash
# Start interactive setup wizard
php artisan ai:mcp:setup

# Install specific server with prompts
php artisan ai:mcp:setup sequential-thinking
```

### Non-Interactive Installation

For automation, CI/CD, or batch operations, use non-interactive mode:

```bash
# Install Sequential Thinking (no API key required)
php artisan ai:mcp:setup sequential-thinking --non-interactive

# Install GitHub MCP with API key
php artisan ai:mcp:setup github --api-key=ghp_your_token --non-interactive

# Install with custom timeout and skip testing
php artisan ai:mcp:setup sequential-thinking --timeout=60 --skip-test --non-interactive

# Force reconfigure existing server
php artisan ai:mcp:setup sequential-thinking --force --non-interactive
```

### Available Servers

```bash
# List all available MCP servers
php artisan ai:mcp:setup --list
```

**Built-in Servers:**
- **sequential-thinking**: Structured problem-solving (no API key required)
- **github**: Repository management and search (requires `GITHUB_PERSONAL_ACCESS_TOKEN`)
- **brave-search**: Web search capabilities (requires `BRAVE_API_KEY`)

## Sequential Thinking

Sequential Thinking is a built-in MCP server that helps AI models break down complex problems into structured thinking steps, leading to more accurate and well-reasoned responses.

### Basic Usage

```php
use JTD\LaravelAI\Facades\AI;

// Enable Sequential Thinking
$response = AI::conversation()
    ->mcp('sequential-thinking')
    ->message('Solve this complex math problem: If a train travels at 60 mph for 2.5 hours, then slows to 40 mph for another 1.5 hours, what is the total distance traveled?')
    ->send();

// The AI will break down the problem into steps:
// 1. Calculate distance for first segment
// 2. Calculate distance for second segment  
// 3. Add the distances together
// 4. Provide final answer
```

### Sequential Thinking Configuration

```php
// Configure Sequential Thinking parameters
$response = AI::conversation()
    ->mcp('sequential-thinking', [
        'max_thoughts' => 8,           // Maximum thinking steps
        'min_thoughts' => 3,           // Minimum thinking steps
        'require_verification' => true, // Verify each step
        'show_thinking' => true,       // Include thinking in response
    ])
    ->message('Design a database schema for an e-commerce platform')
    ->send();
```

### Advanced Sequential Thinking

```php
// Custom thinking prompts
$response = AI::conversation()
    ->mcp('sequential-thinking')
    ->thinkingPrompt('Break this down step by step, considering all edge cases:')
    ->verificationPrompt('Double-check this reasoning for any logical errors:')
    ->message('How would you implement a distributed caching system?')
    ->send();

// Access thinking steps
$thinkingSteps = $response->getThinkingSteps();
foreach ($thinkingSteps as $step) {
    echo "Step {$step->number}: {$step->content}\n";
    echo "Confidence: {$step->confidence}\n";
}
```

## MCP Server Architecture

### Built-in MCP Servers

```php
// Available built-in servers
$servers = AI::getMCPServers();

// Sequential Thinking
AI::conversation()->mcp('sequential-thinking');

// Code Analysis (future)
AI::conversation()->mcp('code-analysis');

// Research Assistant (future)
AI::conversation()->mcp('research-assistant');

// Math Solver (future)
AI::conversation()->mcp('math-solver');
```

### MCP Server Configuration

Configure MCP servers in `config/ai.php`:

```php
'mcp' => [
    'enabled' => env('AI_MCP_ENABLED', true),
    'timeout' => 30,
    'servers' => [
        'sequential-thinking' => [
            'enabled' => true,
            'class' => SequentialThinkingServer::class,
            'config' => [
                'max_thoughts' => 10,
                'default_temperature' => 0.7,
                'verification_enabled' => true,
            ],
        ],
        'custom-server' => [
            'enabled' => false,
            'class' => CustomMCPServer::class,
            'endpoint' => env('AI_MCP_CUSTOM_ENDPOINT'),
            'config' => [
                'api_key' => env('AI_MCP_CUSTOM_API_KEY'),
            ],
        ],
    ],
],
```

## Creating Custom MCP Servers

### MCP Server Interface

```php
<?php

namespace App\AI\MCP;

use JTD\LaravelAI\Contracts\MCPServerInterface;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;

class CustomMCPServer implements MCPServerInterface
{
    public function getName(): string
    {
        return 'custom-server';
    }
    
    public function getDescription(): string
    {
        return 'A custom MCP server for specialized tasks';
    }
    
    public function processMessage(AIMessage $message, array $config = []): AIMessage
    {
        // Pre-process the message
        $enhancedMessage = $this->enhanceMessage($message, $config);
        
        return $enhancedMessage;
    }
    
    public function processResponse(AIResponse $response, array $config = []): AIResponse
    {
        // Post-process the response
        $enhancedResponse = $this->enhanceResponse($response, $config);
        
        return $enhancedResponse;
    }
    
    public function getCapabilities(): array
    {
        return [
            'message_enhancement',
            'response_processing',
            'context_management',
        ];
    }
    
    private function enhanceMessage(AIMessage $message, array $config): AIMessage
    {
        // Your custom logic here
        return $message;
    }
    
    private function enhanceResponse(AIResponse $response, array $config): AIResponse
    {
        // Your custom logic here
        return $response;
    }
}
```

### Registering Custom Servers

```php
// In a service provider
use JTD\LaravelAI\Facades\AI;

public function boot()
{
    AI::registerMCPServer('custom-server', CustomMCPServer::class);
}

// Or register with configuration
AI::registerMCPServer('custom-server', CustomMCPServer::class, [
    'timeout' => 60,
    'retries' => 3,
    'config' => [
        'api_endpoint' => 'https://api.example.com',
        'api_key' => config('services.custom.api_key'),
    ],
]);
```

## MCP Tools and Functions

### Tool Integration

```php
// MCP server with tools
class ToolEnabledMCPServer implements MCPServerInterface
{
    public function getTools(): array
    {
        return [
            [
                'name' => 'web_search',
                'description' => 'Search the web for information',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string'],
                        'num_results' => ['type' => 'integer', 'default' => 5],
                    ],
                ],
            ],
            [
                'name' => 'calculate',
                'description' => 'Perform mathematical calculations',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'expression' => ['type' => 'string'],
                    ],
                ],
            ],
        ];
    }
    
    public function executeTool(string $toolName, array $parameters): array
    {
        return match ($toolName) {
            'web_search' => $this->webSearch($parameters),
            'calculate' => $this->calculate($parameters),
            default => throw new InvalidArgumentException("Unknown tool: {$toolName}"),
        };
    }
}
```

### Using MCP Tools

```php
$response = AI::conversation()
    ->mcp('tool-enabled-server')
    ->message('Search for the latest Laravel news and calculate the average of these numbers: 15, 23, 31, 42')
    ->send();

// The MCP server will automatically use tools as needed
```

## Context Management

### MCP Context Enhancement

```php
class ContextAwareMCPServer implements MCPServerInterface
{
    public function enhanceContext(array $context, AIMessage $message): array
    {
        // Add relevant context based on message content
        if (str_contains($message->content, 'Laravel')) {
            $context['framework'] = 'Laravel';
            $context['documentation'] = $this->getLaravelDocs();
        }
        
        if (str_contains($message->content, 'database')) {
            $context['database_schema'] = $this->getDatabaseSchema();
        }
        
        return $context;
    }
    
    public function processMessage(AIMessage $message, array $config = []): AIMessage
    {
        $context = $this->enhanceContext($message->context ?? [], $message);
        $message->context = $context;
        
        return $message;
    }
}
```

### Dynamic Context Loading

```php
$response = AI::conversation()
    ->mcp('context-aware-server', [
        'context_sources' => [
            'documentation',
            'code_repository',
            'previous_conversations',
        ],
        'max_context_tokens' => 2000,
    ])
    ->message('How do I implement authentication in Laravel?')
    ->send();
```

## MCP Chaining

### Sequential MCP Processing

```php
// Chain multiple MCP servers
$response = AI::conversation()
    ->mcp('research-assistant')      // First: gather information
    ->mcp('sequential-thinking')     // Second: structure thinking
    ->mcp('code-generator')          // Third: generate code
    ->message('Create a Laravel API for user management')
    ->send();
```

### Conditional MCP Usage

```php
$response = AI::conversation()
    ->when(str_contains($message, 'complex'), function ($builder) {
        return $builder->mcp('sequential-thinking');
    })
    ->when(str_contains($message, 'code'), function ($builder) {
        return $builder->mcp('code-analysis');
    })
    ->message($message)
    ->send();
```

## MCP Performance and Caching

### MCP Response Caching

```php
// Cache MCP processing results
$response = AI::conversation()
    ->mcp('sequential-thinking', [
        'cache_enabled' => true,
        'cache_ttl' => 3600,
        'cache_key_prefix' => 'mcp:sequential:',
    ])
    ->message('Solve this recurring problem...')
    ->send();
```

### Async MCP Processing

```php
// Process MCP servers asynchronously
$response = AI::conversation()
    ->mcp('research-assistant', ['async' => true])
    ->mcp('fact-checker', ['async' => true])
    ->message('Tell me about quantum computing')
    ->onMCPComplete(function ($serverName, $result) {
        Log::info("MCP server {$serverName} completed processing");
    })
    ->send();
```

## MCP Monitoring and Debugging

### MCP Server Health

```bash
# Check MCP server status
php artisan ai:mcp:status

# Test specific MCP server
php artisan ai:mcp:test sequential-thinking

# Monitor MCP performance
php artisan ai:mcp:monitor --server=sequential-thinking
```

### MCP Debugging

```php
// Enable MCP debugging
$response = AI::conversation()
    ->mcp('sequential-thinking', [
        'debug' => true,
        'log_steps' => true,
        'include_metadata' => true,
    ])
    ->message('Debug this complex problem')
    ->send();

// Access MCP debug information
$debugInfo = $response->getMCPDebugInfo();
foreach ($debugInfo as $server => $info) {
    echo "Server: {$server}\n";
    echo "Processing time: {$info['processing_time']}ms\n";
    echo "Steps taken: {$info['steps_count']}\n";
}
```

### MCP Metrics

```php
// Get MCP usage metrics
$metrics = AI::getMCPMetrics([
    'start_date' => '2024-01-01',
    'end_date' => '2024-01-31',
    'server' => 'sequential-thinking',
]);

echo "Total requests: {$metrics['total_requests']}\n";
echo "Average processing time: {$metrics['avg_processing_time']}ms\n";
echo "Success rate: {$metrics['success_rate']}%\n";
```

## MCP Configuration Examples

### Development Configuration

```php
// config/ai.php - Development
'mcp' => [
    'enabled' => true,
    'debug' => true,
    'timeout' => 60,
    'servers' => [
        'sequential-thinking' => [
            'enabled' => true,
            'config' => [
                'max_thoughts' => 5,
                'show_thinking' => true,
                'debug_mode' => true,
            ],
        ],
    ],
],
```

### Production Configuration

```php
// config/ai.php - Production
'mcp' => [
    'enabled' => true,
    'debug' => false,
    'timeout' => 30,
    'cache_enabled' => true,
    'servers' => [
        'sequential-thinking' => [
            'enabled' => true,
            'config' => [
                'max_thoughts' => 10,
                'show_thinking' => false,
                'cache_ttl' => 3600,
            ],
        ],
    ],
],
```

## Best Practices

### MCP Server Design

1. **Keep servers focused**: Each MCP server should have a specific purpose
2. **Handle errors gracefully**: Always provide fallback behavior
3. **Cache expensive operations**: Use caching for repeated computations
4. **Monitor performance**: Track processing times and success rates
5. **Validate inputs**: Always validate parameters and configurations

### Usage Guidelines

1. **Choose appropriate servers**: Select MCP servers based on task complexity
2. **Configure timeouts**: Set reasonable timeouts for MCP processing
3. **Monitor costs**: MCP processing may increase token usage
4. **Test thoroughly**: Test MCP servers with various input types
5. **Document custom servers**: Provide clear documentation for custom implementations

### Security Considerations

1. **Validate MCP inputs**: Sanitize all inputs to MCP servers
2. **Limit resource usage**: Set limits on processing time and memory
3. **Secure API endpoints**: Protect external MCP server endpoints
4. **Audit MCP usage**: Log and monitor MCP server usage
5. **Control access**: Implement proper access controls for MCP features
