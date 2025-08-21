# Changelog

All notable changes to `jerthedev/laravel-ai` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2025-08-21

### Added - Sprint 1: Foundation

#### Package Foundation
- Complete Laravel package structure with PSR-4 autoloading
- Composer.json with auto-discovery support for Laravel 10.0+ and PHP 8.1+
- MIT license and comprehensive README documentation
- Package service provider with auto-registration

#### Database Schema
- `ai_providers` migration for provider configurations
- `ai_accounts` migration for provider account credentials with encryption
- `ai_provider_models` migration for available models per provider
- `ai_provider_model_costs` migration for pricing information with effective dates
- `ai_conversations` migration for conversation threads with user relationships
- `ai_messages` migration for individual messages with token usage tracking
- `ai_usage_analytics` migration for aggregated usage statistics
- Comprehensive foreign key constraints and database indexes
- Full rollback support for all migrations

#### Configuration System
- Environment-driven configuration following Laravel conventions
- `config/ai.php` with provider configurations and feature toggles
- Configuration validation with custom validation rules
- Support for multiple accounts per provider
- Secure credential management with Laravel encryption
- Configuration publishing via `artisan vendor:publish`

#### Core Architecture
- `AIProviderInterface` contract defining standard provider methods
- `AIMessage` model for structured message handling with validation
- `AIResponse` model for standardized response format
- `TokenUsage` model for cost calculation and tracking
- `ConversationBuilderInterface` for fluent conversation building
- Custom exception classes for different error scenarios

#### Service Layer
- `LaravelAIServiceProvider` with comprehensive service bindings
- `AI` facade providing static access to package functionality
- `AIManager` service for coordinating AI operations
- `ConversationBuilder` with fluent method chaining interface
- `DriverManager` for provider registration and resolution
- `ConfigurationValidator` for runtime configuration validation

#### Driver System
- Extensible driver architecture following Laravel patterns
- Provider registry system for managing available providers
- `AI::extend()` method for registering custom drivers at runtime
- `AbstractAIProvider` base class with common functionality
- Support for retry logic, error handling, and caching

#### Mock Provider
- Comprehensive `MockAIProvider` implementing full interface
- Configurable mock responses for different scenarios
- Support for simulating rate limits, timeouts, and API errors
- Response fixtures for realistic testing scenarios
- Streaming response simulation capabilities

#### Testing Framework
- 260+ comprehensive tests with 81%+ code coverage
- Orchestra Testbench integration for Laravel package testing
- In-memory SQLite database for fast test execution
- Comprehensive test environment configuration
- Unit, Feature, and Integration test categories
- Mock provider integration for reliable testing

#### CI/CD Pipeline
- GitHub Actions workflow with multi-matrix testing
- Automated testing on PHP 8.1-8.4 and Laravel 10.x-11.x versions
- Code coverage reporting with Codecov integration
- Static analysis with PHPStan (level 6)
- Code style enforcement with Laravel Pint
- Security auditing with Composer audit
- Automated dependency management and vulnerability scanning

#### Development Tools
- PHPStan configuration for static analysis
- Laravel Pint configuration for consistent code style
- Codecov configuration for coverage reporting
- Comprehensive composer scripts for development workflow
- Project guidelines documentation for consistency

### Technical Details

#### Dependencies
- `illuminate/contracts`: ^10.0|^11.0
- `illuminate/support`: ^10.0|^11.0
- `illuminate/database`: ^10.0|^11.0
- `illuminate/http`: ^10.0|^11.0
- `illuminate/validation`: ^10.0|^11.0
- `illuminate/events`: ^10.0|^11.0
- `illuminate/queue`: ^10.0|^11.0
- `guzzlehttp/guzzle`: ^7.0

#### Development Dependencies
- `orchestra/testbench`: ^8.0|^9.0
- `phpunit/phpunit`: ^10.0
- `mockery/mockery`: ^1.4
- `phpstan/phpstan`: ^1.0
- `laravel/pint`: ^1.0

#### Code Quality
- PSR-12 coding standards compliance
- Comprehensive docblocks for all public methods
- Strict typing where appropriate
- 81%+ test coverage with comprehensive edge case testing
- Static analysis passing at PHPStan level 6

### Notes

This release establishes the foundational architecture for the JTD Laravel AI package. While real AI provider implementations are not yet included, the complete infrastructure is in place for rapid development of OpenAI, xAI, Gemini, and Ollama drivers in Sprint 2.

The mock provider allows developers to begin integrating the package into their applications and writing tests against the stable API, ensuring a smooth transition when real providers are implemented.

All database migrations, configuration systems, and core interfaces are production-ready and will remain stable through future releases.
