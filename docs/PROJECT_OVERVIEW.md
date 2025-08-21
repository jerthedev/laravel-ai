# JTD Laravel AI – Project Overview

Package: jerthedev/laravel-ai
Namespace: JTD\\LaravelAI
Local path: packages/jerthedev/laravel-ai

## Purpose
A comprehensive Laravel package that provides a unified, driver-based interface for multiple AI providers with enterprise-level features including conversation management, cost tracking, and Model Context Protocol integration. The package abstracts the complexity of working with different AI APIs while providing a familiar Laravel-style developer experience.

## Key Goals
- Provide a driver-based architecture similar to Laravel's database system for seamless AI provider switching
- Support major AI providers (OpenAI, xAI, Gemini) and local solutions (Ollama) with extensibility for future providers
- Implement persistent conversation management with cross-provider continuity
- Automatic cost tracking and billing analytics with token usage monitoring
- Laravel-style facades and method chaining for intuitive developer experience
- Model Context Protocol (MCP) server integration with built-in Sequential Thinking support
- Automatic model synchronization and availability tracking
- Background processing for maintenance tasks and analytics

## Supported AI Providers
- **OpenAI**: GPT models, DALL-E, Whisper, and future models
- **xAI**: Grok models and future releases
- **Google Gemini**: Gemini Pro, Gemini Vision, and upcoming models
- **Ollama**: Local LLM support for privacy-focused deployments
- **Extensible**: Plugin architecture for custom providers

## Core Features

### Driver Architecture
- Primary AI provider configured via `.env` (similar to `DB_CONNECTION`)
- Multiple provider configurations with seamless switching
- Provider-specific model lists automatically synced
- Credential management and validation

### Conversation System
- Persistent conversation threads stored in database
- Cross-provider conversation continuity
- Message history with token usage tracking
- Conversation branching and merging capabilities

### Cost Management
- Automatic token counting for requests and responses
- Real-time cost calculation based on provider pricing
- Usage analytics and trend reporting
- Budget alerts and spending limits
- Historical cost analysis and forecasting

### Developer Interface
- Laravel-style facades (`AI::conversation()`, `AI::model()`, etc.)
- Fluent method chaining for complex operations
- Closure-based response handling and processing
- Batch processing capabilities
- Event system for hooks and middleware

### Model Context Protocol Integration
- Built-in Sequential Thinking support
- Extensible MCP server architecture
- Tool calling and function execution
- Context management and memory systems

## Database Schema

### Core Tables
- `ai_providers` - Available AI service providers
- `ai_accounts` - Multiple account configurations per provider
- `ai_provider_models` - Synced model information (chat, image, etc.)
- `ai_provider_model_costs` - Token pricing and cost structures

### Conversation Tables
- `ai_conversations` - Conversation threads and metadata
- `ai_messages` - Individual messages with token usage
- `ai_conversation_participants` - Multi-user conversation support

### Analytics Tables
- `ai_usage_analytics` - Aggregated usage statistics
- `ai_cost_tracking` - Detailed cost breakdowns
- `ai_model_performance` - Response time and quality metrics

## Operating Modes

### Standard Mode
- Direct API calls to configured providers
- Real-time responses with immediate cost tracking
- Suitable for most applications

### Batch Mode
- Queue multiple requests for efficient processing
- Cost optimization through batching
- Background processing with job queues

### Offline Mode (Future)
- Local model support via Ollama
- Privacy-focused deployments
- Reduced API costs for development

## Developer Experience

### Simple Usage
```php
$response = AI::conversation('My Chat')
    ->message('Hello, world!')
    ->send();
```

### Advanced Chaining
```php
$result = AI::conversation()
    ->provider('openai')
    ->model('gpt-4')
    ->temperature(0.7)
    ->maxTokens(1000)
    ->message('Analyze this data...')
    ->onSuccess(fn($response) => $this->processResult($response))
    ->onError(fn($error) => $this->handleError($error))
    ->send();
```

### MCP Integration
```php
$response = AI::conversation()
    ->mcp('sequential-thinking')
    ->message('Solve this complex problem step by step')
    ->send();
```

## Configuration Philosophy
- Environment-driven configuration following Laravel conventions
- Sensible defaults with extensive customization options
- Provider-specific settings with global overrides
- Security-first approach to credential management

## Testing Strategy
- 100% PHP unit test coverage with provider mocking
- Integration tests against real AI APIs (rate-limited)
- Performance testing for cost optimization
- Security testing for credential handling

## Compatibility Targets
- Laravel 10.0+ with PHP 8.1+
- PSR-4 autoloading and PSR-12 coding standards
- Laravel package discovery and service provider auto-registration
- Horizon/Queue integration for background processing

## Roadmap (High-level)
1. **Foundation**: Package skeleton, service providers, configuration system
2. **Core Drivers**: OpenAI, xAI, Gemini provider implementations
3. **Conversation System**: Database schema, conversation management
4. **Cost Tracking**: Token usage, billing calculations, analytics
5. **MCP Integration**: Sequential Thinking, extensible MCP architecture
6. **Advanced Features**: Batch processing, offline mode, performance optimization
7. **Enterprise Features**: Multi-tenant support, advanced analytics, compliance tools

## Success Metrics
- Developer adoption and community feedback
- Performance benchmarks (response time, cost efficiency)
- Test coverage and code quality metrics
- Documentation completeness and clarity
- Provider compatibility and feature parity
