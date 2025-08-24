# MCP Testing Patterns

This document outlines comprehensive testing patterns for MCP (Model Context Protocol) servers and integrations within the Laravel AI package.

## Table of Contents

1. [Testing Philosophy](#testing-philosophy)
2. [Test Categories](#test-categories)
3. [Unit Testing Patterns](#unit-testing-patterns)
4. [Integration Testing Patterns](#integration-testing-patterns)
5. [E2E Testing Patterns](#e2e-testing-patterns)
6. [Performance Testing Patterns](#performance-testing-patterns)
7. [Error Handling Testing](#error-handling-testing)
8. [Mock and Stub Patterns](#mock-and-stub-patterns)
9. [Test Data Management](#test-data-management)
10. [Best Practices](#best-practices)

## Testing Philosophy

### Core Principles

1. **Reliability First**: MCP servers must be thoroughly tested for reliability
2. **Performance Validation**: All tests include performance benchmarks
3. **Real-world Scenarios**: Tests simulate actual usage patterns
4. **Graceful Degradation**: Test failure scenarios and fallback mechanisms
5. **Comprehensive Coverage**: Test all integration points and edge cases

### Testing Pyramid

```
    E2E Tests (Real APIs)
         /\
        /  \
   Integration Tests
      /        \
     /          \
Unit Tests (Mocked)
```

## Test Categories

### 1. Unit Tests
- Individual MCP server components
- Tool execution logic
- Configuration management
- Error handling

### 2. Integration Tests
- MCP server communication
- Event-driven flow integration
- Middleware coordination
- Database interactions

### 3. E2E Tests
- Real API credentials
- Complete workflow testing
- Performance validation
- User experience testing

### 4. Performance Tests
- Execution time benchmarks
- Load testing
- Memory usage validation
- Concurrent request handling

## Unit Testing Patterns

### Basic MCP Tool Test

```php
#[Test]
public function it_executes_sequential_thinking_tool(): void
{
    $mcpManager = app(MCPManager::class);
    
    $result = $mcpManager->executeTool('sequential_thinking', [
        'thought' => 'Test thought',
        'nextThoughtNeeded' => false,
        'thoughtNumber' => 1,
        'totalThoughts' => 1,
    ]);
    
    $this->assertIsArray($result);
    $this->assertTrue($result['success']);
    $this->assertArrayHasKey('result', $result);
}
```

### Performance Validation Pattern

```php
#[Test]
public function it_meets_performance_targets(): void
{
    $startTime = microtime(true);
    
    $result = $this->mcpManager->executeTool('sequential_thinking', $params);
    
    $executionTime = (microtime(true) - $startTime) * 1000;
    
    $this->assertTrue($result['success']);
    $this->assertLessThan(100, $executionTime, 
        "Execution took {$executionTime}ms, exceeding 100ms target");
}
```

### Error Handling Pattern

```php
#[Test]
public function it_handles_invalid_parameters_gracefully(): void
{
    $result = $this->mcpManager->executeTool('sequential_thinking', [
        'invalid_param' => 'invalid_value',
    ]);
    
    $this->assertIsArray($result);
    $this->assertFalse($result['success']);
    $this->assertArrayHasKey('error', $result);
    $this->assertArrayHasKey('error_type', $result);
}
```

## Integration Testing Patterns

### Event-Driven Flow Testing

```php
#[Test]
public function it_integrates_with_event_driven_flow(): void
{
    Event::fake();
    
    // Execute MCP tool within event flow
    $message = new AIMessage([
        'content' => 'Test message',
        'provider' => 'openai',
    ]);
    
    $response = $this->aiManager->processMessage($message);
    
    // Verify events were fired
    Event::assertDispatched(MCPToolExecuted::class);
    Event::assertDispatched(AIResponseGenerated::class);
    
    // Verify response
    $this->assertTrue($response->success);
}
```

### Middleware Integration Pattern

```php
#[Test]
public function it_works_with_budget_middleware(): void
{
    $message = new AIMessage([
        'user_id' => 1,
        'content' => 'Test with budget enforcement',
        'provider' => 'openai',
    ]);
    
    $startTime = microtime(true);
    
    $response = $this->budgetMiddleware->handle($message, function ($msg) {
        return $this->mcpManager->executeTool('sequential_thinking', [
            'thought' => $msg->content,
            'nextThoughtNeeded' => false,
            'thoughtNumber' => 1,
            'totalThoughts' => 1,
        ]);
    });
    
    $executionTime = (microtime(true) - $startTime) * 1000;
    
    $this->assertTrue($response['success']);
    $this->assertLessThan(10, $executionTime, 
        "Budget middleware overhead exceeded 10ms target");
}
```

## E2E Testing Patterns

### Real API Testing Pattern

```php
#[Test]
public function it_executes_with_real_brave_search_api(): void
{
    if (!$this->hasCredentials('brave_search')) {
        $this->markTestSkipped('Brave Search credentials not available');
    }
    
    $result = $this->mcpManager->executeTool('brave_search', [
        'query' => 'Laravel MCP integration',
        'count' => 3,
    ]);
    
    $this->assertTrue($result['success']);
    $this->assertArrayHasKey('results', $result['result']);
    $this->assertGreaterThan(0, count($result['result']['results']));
}
```

### Credential Management Pattern

```php
protected function hasCredentials(string $service): bool
{
    $credentialsPath = __DIR__ . '/../credentials/e2e-credentials.json';
    
    if (!file_exists($credentialsPath)) {
        return false;
    }
    
    $credentials = json_decode(file_get_contents($credentialsPath), true);
    
    return isset($credentials['mcp_servers'][$service]) && 
           $credentials['mcp_servers'][$service]['enabled'] ?? false;
}
```

## Performance Testing Patterns

### Load Testing Pattern

```php
#[Test]
public function it_handles_concurrent_requests(): void
{
    $concurrentRequests = 10;
    $promises = [];
    
    $startTime = microtime(true);
    
    for ($i = 0; $i < $concurrentRequests; $i++) {
        $promises[] = function () use ($i) {
            return $this->mcpManager->executeTool('sequential_thinking', [
                'thought' => "Concurrent request {$i}",
                'nextThoughtNeeded' => false,
                'thoughtNumber' => 1,
                'totalThoughts' => 1,
            ]);
        };
    }
    
    $results = array_map(fn($promise) => $promise(), $promises);
    $totalTime = (microtime(true) - $startTime) * 1000;
    
    $successCount = count(array_filter($results, fn($r) => $r['success']));
    
    $this->assertEquals($concurrentRequests, $successCount);
    $this->assertLessThan(1000, $totalTime, 
        "Concurrent execution took {$totalTime}ms, exceeding 1000ms target");
}
```

### Memory Usage Pattern

```php
#[Test]
public function it_maintains_reasonable_memory_usage(): void
{
    $memoryBefore = memory_get_usage(true);
    
    for ($i = 0; $i < 100; $i++) {
        $result = $this->mcpManager->executeTool('sequential_thinking', [
            'thought' => "Memory test iteration {$i}",
            'nextThoughtNeeded' => false,
            'thoughtNumber' => 1,
            'totalThoughts' => 1,
        ]);
        
        $this->assertTrue($result['success']);
    }
    
    $memoryAfter = memory_get_usage(true);
    $memoryIncrease = $memoryAfter - $memoryBefore;
    
    $this->assertLessThan(10 * 1024 * 1024, $memoryIncrease, 
        "Memory usage increased by {$memoryIncrease} bytes, exceeding 10MB limit");
}
```

## Error Handling Testing

### Server Failure Pattern

```php
#[Test]
public function it_handles_server_failures_gracefully(): void
{
    // Configure failing server
    config([
        'ai.mcp.servers.failing_server' => [
            'type' => 'external',
            'command' => 'non-existent-command',
            'enabled' => true,
        ],
    ]);
    
    $result = $this->mcpManager->executeTool('failing_server', ['test' => 'param']);
    
    $this->assertIsArray($result);
    $this->assertFalse($result['success']);
    $this->assertArrayHasKey('error', $result);
    $this->assertArrayHasKey('error_type', $result);
    $this->assertEquals('connection_failed', $result['error_type']);
}
```

### Timeout Handling Pattern

```php
#[Test]
public function it_handles_timeouts_appropriately(): void
{
    Process::fake([
        'slow-server' => Process::result('', '', 1, 'timeout'),
    ]);
    
    config([
        'ai.mcp.servers.slow_server' => [
            'type' => 'external',
            'command' => 'slow-server',
            'timeout' => 1, // 1 second
            'enabled' => true,
        ],
    ]);
    
    $startTime = microtime(true);
    
    $result = $this->mcpManager->executeTool('slow_server', ['test' => 'timeout']);
    
    $executionTime = (microtime(true) - $startTime) * 1000;
    
    $this->assertFalse($result['success']);
    $this->assertEquals('timeout', $result['error_type']);
    $this->assertLessThan(1500, $executionTime, 
        "Timeout handling took too long: {$executionTime}ms");
}
```

## Mock and Stub Patterns

### Process Mocking Pattern

```php
protected function mockExternalServer(string $command, array $response): void
{
    Process::fake([
        $command => Process::result(
            json_encode($response),
            '',
            $response['success'] ? 0 : 1
        ),
    ]);
}
```

### HTTP Client Mocking Pattern

```php
protected function mockHttpResponse(string $url, array $response): void
{
    Http::fake([
        $url => Http::response($response['body'], $response['status']),
    ]);
}
```

## Test Data Management

### Credentials Management

```php
// tests/credentials/e2e-credentials.example.json
{
  "mcp_servers": {
    "brave_search": {
      "api_key": "your-api-key-here",
      "enabled": true
    },
    "github": {
      "token": "your-token-here",
      "enabled": true
    }
  }
}
```

### Test Configuration Pattern

```php
protected function setupTestConfiguration(): void
{
    config([
        'ai.mcp.enabled' => true,
        'ai.mcp.timeout' => 30,
        'ai.mcp.servers' => [
            'test_server' => [
                'type' => 'built-in',
                'enabled' => true,
            ],
        ],
    ]);
}
```

## Best Practices

### 1. Test Organization

- Group related tests with `#[Group]` attributes
- Use descriptive test method names
- Follow AAA pattern (Arrange, Act, Assert)

### 2. Performance Testing

- Always include performance assertions
- Use realistic test data sizes
- Test both single and concurrent execution

### 3. Error Handling

- Test all failure scenarios
- Verify error messages are helpful
- Ensure graceful degradation

### 4. Mocking Strategy

- Mock external dependencies in unit tests
- Use real APIs in E2E tests when possible
- Provide clear mock setup methods

### 5. Test Data

- Use factories for complex test data
- Keep test data minimal but realistic
- Clean up test data after tests

### 6. Assertions

- Use specific assertions over generic ones
- Include helpful failure messages
- Test both positive and negative cases

### 7. Test Isolation

- Each test should be independent
- Clean up state between tests
- Use database transactions when appropriate

## Example Test Suite Structure

```
tests/
├── Unit/
│   ├── Services/
│   │   ├── MCPManagerTest.php
│   │   └── EventPerformanceTrackerTest.php
│   └── Middleware/
│       └── BudgetEnforcementMiddlewareTest.php
├── Integration/
│   ├── MCPIntegrationTest.php
│   └── EventDrivenFlowTest.php
├── E2E/
│   ├── MCPRealServerTest.php
│   └── MCPSetupWorkflowTest.php
├── Performance/
│   ├── MCPPerformanceTest.php
│   └── LoadTestingTest.php
└── credentials/
    ├── e2e-credentials.json (git-ignored)
    └── e2e-credentials.example.json
```

## Advanced Testing Patterns

### Fallback Testing Pattern

```php
#[Test]
public function it_falls_back_to_alternative_servers(): void
{
    // Configure primary and fallback servers
    config([
        'ai.mcp.servers.primary_search' => [
            'type' => 'external',
            'command' => 'failing-search-server',
            'enabled' => true,
            'fallback' => 'backup_search',
        ],
        'ai.mcp.servers.backup_search' => [
            'type' => 'external',
            'command' => 'working-search-server',
            'enabled' => true,
        ],
    ]);

    Process::fake([
        'failing-search-server' => Process::result('', 'Server error', 1),
        'working-search-server' => Process::result('{"success": true, "result": "fallback_success"}', '', 0),
    ]);

    $result = $this->mcpManager->executeTool('primary_search', ['query' => 'test']);

    $this->assertTrue($result['success']);
    $this->assertEquals('fallback_success', $result['result']);
    $this->assertTrue($result['fallback_used']);
    $this->assertEquals('backup_search', $result['fallback_server']);
}
```

### Circuit Breaker Testing Pattern

```php
#[Test]
public function it_implements_circuit_breaker_pattern(): void
{
    config([
        'ai.mcp.servers.circuit_test' => [
            'type' => 'external',
            'command' => 'failing-circuit-server',
            'enabled' => true,
            'circuit_breaker' => [
                'failure_threshold' => 3,
                'timeout' => 60,
                'recovery_timeout' => 30,
            ],
        ],
    ]);

    Process::fake([
        'failing-circuit-server' => Process::result('', 'Server error', 1),
    ]);

    // Trigger circuit breaker with multiple failures
    $results = [];
    for ($i = 0; $i < 5; $i++) {
        $startTime = microtime(true);
        $result = $this->mcpManager->executeTool('circuit_test', ['attempt' => $i]);
        $executionTime = (microtime(true) - $startTime) * 1000;

        $results[] = [
            'result' => $result,
            'execution_time' => $executionTime,
        ];
    }

    // First 3 attempts should fail normally
    for ($i = 0; $i < 3; $i++) {
        $this->assertFalse($results[$i]['result']['success']);
        $this->assertEquals('server_error', $results[$i]['result']['error_type']);
    }

    // Subsequent attempts should be circuit breaker failures (fast)
    for ($i = 3; $i < 5; $i++) {
        $this->assertFalse($results[$i]['result']['success']);
        $this->assertEquals('circuit_breaker_open', $results[$i]['result']['error_type']);
        $this->assertLessThan(50, $results[$i]['execution_time']);
    }
}
```

### Retry Mechanism Testing Pattern

```php
#[Test]
public function it_implements_retry_mechanism_for_transient_failures(): void
{
    $attemptCount = 0;

    Process::fake([
        'flaky-server' => function () use (&$attemptCount) {
            $attemptCount++;
            if ($attemptCount <= 2) {
                return Process::result('', 'Connection refused', 1);
            }
            return Process::result('{"success": true, "result": "retry_success"}', '', 0);
        },
    ]);

    config([
        'ai.mcp.servers.flaky_server' => [
            'type' => 'external',
            'command' => 'flaky-server',
            'enabled' => true,
            'retry_attempts' => 3,
            'retry_delay' => 100,
        ],
    ]);

    $startTime = microtime(true);

    $result = $this->mcpManager->executeTool('flaky_server', ['test' => 'retry']);

    $executionTime = (microtime(true) - $startTime) * 1000;

    $this->assertTrue($result['success']);
    $this->assertEquals('retry_success', $result['result']);
    $this->assertEquals(3, $attemptCount);
    $this->assertGreaterThan(200, $executionTime); // At least 2 * 100ms delay
}
```

## Testing Checklist

### Pre-Test Setup
- [ ] Test environment configured
- [ ] Credentials file available (for E2E tests)
- [ ] Mock servers configured
- [ ] Database migrations run
- [ ] Cache cleared

### Unit Test Checklist
- [ ] All public methods tested
- [ ] Error conditions tested
- [ ] Performance targets validated
- [ ] Mock dependencies properly
- [ ] Test isolation maintained

### Integration Test Checklist
- [ ] Event flow integration tested
- [ ] Middleware coordination verified
- [ ] Database interactions validated
- [ ] Configuration loading tested
- [ ] Service dependencies resolved

### E2E Test Checklist
- [ ] Real API credentials configured
- [ ] Complete workflows tested
- [ ] Performance benchmarks met
- [ ] Error scenarios handled
- [ ] User experience validated

### Performance Test Checklist
- [ ] Execution time benchmarks
- [ ] Memory usage validation
- [ ] Concurrent request handling
- [ ] Load testing completed
- [ ] Resource cleanup verified

This comprehensive testing approach ensures MCP integrations are reliable, performant, and maintainable while providing clear patterns for future development.
