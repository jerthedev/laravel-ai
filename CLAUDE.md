# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**JTD Laravel AI** is a Laravel package that provides a unified, driver-based interface for multiple AI providers (OpenAI, xAI, Gemini, Ollama) with enterprise features including conversation management, cost tracking, and Model Context Protocol (MCP) integration.

## Commands

### Composer Scripts
```bash
composer test           # Run PHPUnit tests
composer test-coverage  # Run tests with HTML coverage report  
composer format         # Format code with Laravel Pint
composer analyse        # Run PHPStan static analysis
```

### Artisan Commands
```bash
php artisan ai:sync-models        # Sync AI models and pricing from providers
php artisan ai:setup-e2e          # Setup end-to-end testing
php artisan ai:mcp-setup          # Setup MCP servers
php artisan ai:mcp-discover       # Discover MCP tools
php artisan ai:run-performance-tests  # Execute performance benchmarks
```

### Running Tests
```bash
# All tests
composer test

# Specific test suites
vendor/bin/phpunit --testsuite=Unit
vendor/bin/phpunit --testsuite=Feature
vendor/bin/phpunit --testsuite=Integration

# End-to-end tests (requires setup)
vendor/bin/phpunit --testsuite=E2E
```

## Architecture

### Core Patterns
- **Driver-based system**: Similar to Laravel's database drivers, managed by `AIManager` → `DriverManager`
- **Service Provider Pattern**: Main registration in `LaravelAIServiceProvider`
- **Facade Pattern**: `AI` facade provides fluent API (`AI::conversation()`, `AI::provider()`)
- **Event-driven**: Background processing via Laravel events and queues for 85% performance improvement

### Key Components
- **Providers**: `AbstractAIProvider` base class, drivers in `/src/Drivers/`
- **Conversation System**: Persistent cross-provider conversations via `AIConversation` model
- **MCP Integration**: Model Context Protocol servers with Sequential Thinking support
- **Middleware System**: Laravel-style request interception for AI operations
- **Cost Tracking**: Automatic token usage analytics and budget enforcement

### Directory Structure
```
/src/
├── Console/Commands/     # Artisan commands
├── Drivers/             # AI provider implementations 
├── Services/            # Core business logic (AIManager, DriverManager, etc.)
├── Models/              # Eloquent models (AIConversation, AIMessage, etc.)
├── Events/              # Event classes for background processing
├── Middleware/          # AI request middleware
├── Testing/             # Package testing utilities
└── ...

/tests/
├── Unit/                # Unit tests
├── Feature/             # Feature tests  
├── Integration/         # Integration tests
├── E2E/                 # End-to-end tests (require credentials)
└── Performance/         # Performance benchmarks
```

### Configuration
Main config in `/config/ai.php` with sections for:
- Provider settings (API keys, models, endpoints)
- Cost tracking and analytics  
- MCP server configurations
- Event system and queue settings
- Rate limiting and caching

### Testing Setup
- **PHPUnit 12** with Orchestra Testbench for Laravel package testing
- **Mock provider** configured as default for testing (no external API calls)
- **Test database**: SQLite in-memory
- **E2E tests**: Require real provider credentials in `/tests/credentials/`

### PHPUnit Testing Standards
- **MANDATORY**: All test files MUST use PHPUnit attributes, not docblock annotations
- **Deprecated**: `@test`, `@group`, `@dataProvider`, etc. annotations are forbidden
- **Required**: Use modern PHP attributes: `#[Test]`, `#[Group('name')]`, `#[DataProvider('method')]`
- **Imports**: Always include required attribute imports:
  ```php
  use PHPUnit\Framework\Attributes\Test;
  use PHPUnit\Framework\Attributes\Group;
  use PHPUnit\Framework\Attributes\DataProvider;
  ```
- **Validation**: Run `TestAttributeEnforcementTest` to ensure compliance
- **Enforcement**: All new test files will be rejected if using deprecated docblock annotations

### API Usage Patterns
```php
// Basic usage
AI::sendMessage(AIMessage::user('Hello world'));

// Provider-specific  
AI::provider('openai')->sendMessage($message, ['model' => 'gpt-4']);

// Conversation builder
AI::conversation()
    ->provider('openai')
    ->model('gpt-4')
    ->temperature(0.7)
    ->message('Analyze this data...')
    ->send();

// Streaming
foreach (AI::sendStreamingMessage($message) as $chunk) {
    echo $chunk->content;
}
```

## Important Notes

- Run `composer format` before committing (Laravel Pint with Laravel preset)
- All new features require comprehensive tests across Unit/Feature/Integration levels
- Use the mock provider for testing to avoid external API calls
- E2E tests are separate and require credential setup via `ai:setup-e2e`
- The event system processes analytics/notifications in background queues for performance
- Use agents laravel-package-developer to complete all functionality requests
- use agent laravel-test-fixer when working on issues with tests not passing