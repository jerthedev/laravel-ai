# JTD Laravel AI

[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue.svg?style=flat-square)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/laravel-%5E10.0%7C%5E11.0%7C%5E12.0-red.svg?style=flat-square)](https://laravel.com)
[![License](https://img.shields.io/badge/license-MIT-green.svg?style=flat-square)](LICENSE.md)
[![Tests](https://github.com/jerthedev/laravel-ai/actions/workflows/tests.yml/badge.svg)](https://github.com/jerthedev/laravel-ai/actions/workflows/tests.yml)
[![codecov](https://codecov.io/gh/jerthedev/laravel-ai/branch/main/graph/badge.svg)](https://codecov.io/gh/jerthedev/laravel-ai)
[![PHPStan Level](https://img.shields.io/badge/phpstan-level%206-brightgreen.svg?style=flat-square)](https://phpstan.org)
[![Code Style](https://img.shields.io/badge/code%20style-laravel%20pint-orange.svg?style=flat-square)](https://laravel.com/docs/pint)

A comprehensive Laravel package that provides a unified, driver-based interface for multiple AI providers with conversation management, cost tracking, and enterprise-level features.

> **Note**: This package is currently in active development. Sprint 1 (Foundation) is complete with core infrastructure, testing framework, and mock provider implementation.

## Features

### ✅ Sprint 1 (Foundation) - Complete
- **Package Foundation**: Complete Laravel package structure with auto-discovery
- **Database Schema**: Full migration system for providers, conversations, messages, and analytics
- **Configuration System**: Environment-driven configuration with validation
- **Driver Architecture**: Extensible driver system with provider registry
- **Core Interfaces**: AIProviderInterface and data transfer objects (AIMessage, AIResponse, TokenUsage)
- **Service Provider & Facade**: Laravel service provider with AI facade
- **Mock Provider**: Comprehensive testing provider with configurable responses
- **Testing Framework**: 260+ tests with 81%+ code coverage using PHPUnit and Orchestra Testbench
- **CI/CD Pipeline**: GitHub Actions with multi-version testing, static analysis, and code coverage

### 🚧 Upcoming Features
- **Real Provider Drivers**: OpenAI, xAI, Gemini, Ollama implementations
- **Conversation Management**: Persistent conversation threads with cross-provider continuity
- **Cost Tracking**: Automatic token usage tracking and billing calculations
- **Model Syncing**: Automatic synchronization of available models from providers
- **MCP Integration**: Model Context Protocol server support with Sequential Thinking
- **Background Processing**: Automated model syncing and cost calculations

## Quick Start

```bash
composer require jerthedev/laravel-ai
php artisan vendor:publish --provider="JTD\LaravelAI\LaravelAIServiceProvider"
php artisan migrate
```

Configure your AI providers in `.env`:

```env
AI_DEFAULT_PROVIDER=mock
AI_COST_TRACKING_ENABLED=true
AI_MODEL_SYNC_ENABLED=true

# Future provider configurations (not yet implemented)
# AI_OPENAI_API_KEY=your-openai-key
# AI_XAI_API_KEY=your-xai-key
# AI_GEMINI_API_KEY=your-gemini-key
```

## Basic Usage (Sprint 1 - Mock Provider)

```php
use JTD\LaravelAI\Facades\AI;

// Create a conversation builder
$conversation = AI::conversation('My AI Assistant');

// Send a message using the mock provider
$response = $conversation
    ->provider('mock')
    ->model('mock-model')
    ->message('Hello, how are you?')
    ->send();

echo $response->getContent(); // "Mock response to: Hello, how are you?"

// Continue the conversation
$response = $conversation
    ->message('Tell me about Laravel')
    ->send();

// Access response metadata
echo $response->getTokenUsage()->getInputTokens();
echo $response->getTokenUsage()->getOutputTokens();
echo $response->getTokenUsage()->getTotalCost();
```

### Testing Your Integration

```php
// In your tests, use the mock provider
use JTD\LaravelAI\Facades\AI;

public function test_ai_integration()
{
    $response = AI::conversation()
        ->provider('mock')
        ->message('Test message')
        ->send();

    $this->assertInstanceOf(AIResponse::class, $response);
    $this->assertStringContains('Mock response', $response->getContent());
}
```

## Architecture Overview

### Driver System
The package uses a driver-based architecture similar to Laravel's database connections:

```php
// Register custom drivers
AI::extend('custom-provider', function ($app, $config) {
    return new CustomAIProvider($config);
});

// Use different providers
$response = AI::conversation()
    ->provider('custom-provider')
    ->message('Hello')
    ->send();
```

### Configuration Structure
```php
// config/ai.php
return [
    'default' => env('AI_DEFAULT_PROVIDER', 'mock'),

    'providers' => [
        'mock' => [
            'driver' => 'mock',
            'responses' => [
                'default' => 'Mock response to: {message}',
                'error' => 'Mock error response',
            ],
        ],

        // Future providers will be added here
        'openai' => [
            'driver' => 'openai',
            'api_key' => env('AI_OPENAI_API_KEY'),
            'organization' => env('AI_OPENAI_ORGANIZATION'),
        ],
    ],
];
```

### Database Schema
The package includes comprehensive migrations for:
- `ai_providers` - Provider configurations
- `ai_accounts` - Provider account credentials
- `ai_provider_models` - Available models per provider
- `ai_provider_model_costs` - Pricing information
- `ai_conversations` - Conversation threads
- `ai_messages` - Individual messages
- `ai_usage_analytics` - Usage statistics

## Development Status

### Sprint 1: Foundation ✅ Complete
- [x] Package structure and auto-discovery
- [x] Database migrations and schema
- [x] Configuration system with validation
- [x] Core interfaces and contracts
- [x] Service provider and facade
- [x] Driver system foundation
- [x] Mock provider for testing
- [x] Comprehensive test suite (260+ tests, 81%+ coverage)
- [x] CI/CD pipeline with GitHub Actions
- [x] Static analysis with PHPStan
- [x] Code style enforcement with Pint

### Sprint 2: Real Providers (Planned)
- [ ] OpenAI driver implementation
- [ ] xAI (Grok) driver implementation
- [ ] Google Gemini driver implementation
- [ ] Ollama driver implementation
- [ ] Provider-specific model syncing
- [ ] Real-world integration tests

### Sprint 3: Advanced Features (Planned)
- [ ] Conversation persistence and management
- [ ] Cost tracking and analytics
- [ ] Model Context Protocol (MCP) integration
- [ ] Background job processing
- [ ] Webhook integrations
- [ ] Admin dashboard

## Requirements

- PHP 8.1+
- Laravel 10.0+

## Installation

You can install the package via composer:

```bash
composer require jerthedev/laravel-ai
```

Publish the configuration file and migrations:

```bash
php artisan vendor:publish --provider="JTD\LaravelAI\LaravelAIServiceProvider"
```

Run the migrations:

```bash
php artisan migrate
```

## Configuration

The package uses a driver-based configuration system similar to Laravel's database connections. Configure your providers in `config/ai.php` and set your API keys in `.env`.

## Testing

The package includes a comprehensive test suite with 260+ tests covering:

```bash
# Run all tests
composer test

# Run tests with coverage
composer test-coverage

# Run static analysis
composer analyse

# Fix code style
composer format
```

### Test Categories
- **Unit Tests**: Individual class and method testing
- **Feature Tests**: End-to-end functionality testing
- **Integration Tests**: Component interaction testing
- **Database Tests**: Migration and model testing

### CI/CD Pipeline
- Automated testing on PHP 8.1-8.4 and Laravel 10.x-11.x
- Code coverage reporting with Codecov
- Static analysis with PHPStan (level 6)
- Code style enforcement with Laravel Pint
- Security auditing with Composer audit

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Jeremy Fall](https://github.com/jerthedev)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
