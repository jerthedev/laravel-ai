# AI Providers

## Overview

JTD Laravel AI supports multiple AI providers through a unified driver system. Each provider has its own driver that handles API communication, model management, and cost calculation while providing a consistent interface.

## Supported Providers

### OpenAI
- **Models**: GPT-4, GPT-3.5, DALL-E, Whisper, and future releases
- **Capabilities**: Text generation, image generation, speech-to-text, function calling
- **API Documentation**: https://platform.openai.com/docs

### xAI (Grok)
- **Models**: Grok-1, Grok-2, and future releases
- **Capabilities**: Text generation, reasoning, real-time information
- **API Documentation**: https://docs.x.ai/

### Google Gemini
- **Models**: Gemini Pro, Gemini Vision, Gemini Ultra
- **Capabilities**: Text generation, image analysis, multimodal processing
- **API Documentation**: https://ai.google.dev/docs

### Ollama (Local Models)
- **Models**: Llama 2, Code Llama, Mistral, and community models
- **Capabilities**: Local text generation, privacy-focused processing
- **Documentation**: https://ollama.ai/

## Provider Configuration

### OpenAI Setup

1. **Get API Key**: Visit https://platform.openai.com/api-keys
2. **Configure Environment**:

```env
AI_OPENAI_API_KEY=sk-your-openai-api-key
AI_OPENAI_ORGANIZATION=org-your-organization-id  # Optional
AI_OPENAI_PROJECT=proj-your-project-id          # Optional
```

3. **Configuration Options**:

```php
'openai' => [
    'driver' => 'openai',
    'api_key' => env('AI_OPENAI_API_KEY'),
    'organization' => env('AI_OPENAI_ORGANIZATION'),
    'project' => env('AI_OPENAI_PROJECT'),
    'base_url' => 'https://api.openai.com/v1',
    'timeout' => 30,
    'retry_attempts' => 3,
    'default_model' => 'gpt-4',
    'default_temperature' => 0.7,
    'max_tokens' => 4000,
    'stream_support' => true,
],
```

4. **Usage Example**:

```php
$response = AI::conversation()
    ->provider('openai')
    ->model('gpt-4')
    ->temperature(0.8)
    ->maxTokens(1000)
    ->message('Explain quantum computing')
    ->send();
```

### xAI Setup

1. **Get API Key**: Visit https://console.x.ai/
2. **Configure Environment**:

```env
AI_XAI_API_KEY=xai-your-api-key
```

3. **Configuration Options**:

```php
'xai' => [
    'driver' => 'xai',
    'api_key' => env('AI_XAI_API_KEY'),
    'base_url' => 'https://api.x.ai/v1',
    'timeout' => 30,
    'retry_attempts' => 3,
    'default_model' => 'grok-beta',
    'default_temperature' => 0.7,
],
```

4. **Usage Example**:

```php
$response = AI::conversation()
    ->provider('xai')
    ->model('grok-beta')
    ->message('What are the latest developments in AI?')
    ->send();
```

### Google Gemini Setup

1. **Get API Key**: Visit https://makersuite.google.com/app/apikey
2. **Configure Environment**:

```env
AI_GEMINI_API_KEY=your-gemini-api-key
```

3. **Configuration Options**:

```php
'gemini' => [
    'driver' => 'gemini',
    'api_key' => env('AI_GEMINI_API_KEY'),
    'base_url' => 'https://generativelanguage.googleapis.com/v1',
    'timeout' => 30,
    'default_model' => 'gemini-pro',
    'safety_settings' => [
        'HARM_CATEGORY_HARASSMENT' => 'BLOCK_MEDIUM_AND_ABOVE',
        'HARM_CATEGORY_HATE_SPEECH' => 'BLOCK_MEDIUM_AND_ABOVE',
        'HARM_CATEGORY_SEXUALLY_EXPLICIT' => 'BLOCK_MEDIUM_AND_ABOVE',
        'HARM_CATEGORY_DANGEROUS_CONTENT' => 'BLOCK_MEDIUM_AND_ABOVE',
    ],
],
```

4. **Usage Example**:

```php
$response = AI::conversation()
    ->provider('gemini')
    ->model('gemini-pro')
    ->message('Analyze this image and describe what you see')
    ->attachImage('path/to/image.jpg')
    ->send();
```

### Ollama Setup

1. **Install Ollama**: Visit https://ollama.ai/download
2. **Start Ollama Server**:

```bash
ollama serve
```

3. **Pull Models**:

```bash
ollama pull llama2
ollama pull codellama
ollama pull mistral
```

4. **Configure Environment**:

```env
AI_OLLAMA_BASE_URL=http://localhost:11434
AI_OLLAMA_TIMEOUT=120
```

5. **Configuration Options**:

```php
'ollama' => [
    'driver' => 'ollama',
    'base_url' => env('AI_OLLAMA_BASE_URL', 'http://localhost:11434'),
    'timeout' => 120,
    'default_model' => 'llama2',
    'keep_alive' => '5m',
    'num_ctx' => 2048,
    'temperature' => 0.7,
],
```

6. **Usage Example**:

```php
$response = AI::conversation()
    ->provider('ollama')
    ->model('llama2')
    ->message('Write a Python function to calculate fibonacci numbers')
    ->send();
```

## Provider Management

### Listing Available Providers

```php
use JTD\LaravelAI\Facades\AI;

// Get all configured providers
$providers = AI::getProviders();

// Get active providers only
$activeProviders = AI::getActiveProviders();

// Check if provider is available
$isAvailable = AI::isProviderAvailable('openai');
```

### Provider Status

```bash
# Check all provider statuses
php artisan ai:providers:status

# Test specific provider
php artisan ai:providers:test openai

# Verify credentials
php artisan ai:providers:verify
```

### Switching Providers

```php
// Switch default provider
AI::setDefaultProvider('gemini');

// Use specific provider for one request
$response = AI::conversation()
    ->provider('xai')
    ->message('Hello')
    ->send();

// Provider fallback chain
$response = AI::conversation()
    ->providers(['openai', 'gemini', 'xai']) // Try in order
    ->message('Hello')
    ->send();
```

## Model Management

### Syncing Models

```bash
# Sync models from all providers
php artisan ai:sync-models

# Sync from specific provider
php artisan ai:sync-models --provider=openai

# Force sync (ignore cache)
php artisan ai:sync-models --force
```

### Listing Models

```php
// Get all models
$models = AI::getModels();

// Get models for specific provider
$openaiModels = AI::getModels('openai');

// Get models by type
$chatModels = AI::getModels(null, 'chat');
$imageModels = AI::getModels(null, 'image');

// Check if model is available
$isAvailable = AI::isModelAvailable('gpt-4', 'openai');
```

### Model Information

```php
// Get model details
$model = AI::getModel('gpt-4', 'openai');

echo $model->name;           // "gpt-4"
echo $model->type;           // "chat"
echo $model->context_length; // 8192
echo $model->input_cost;     // Cost per input token
echo $model->output_cost;    // Cost per output token
```

## Cost Management

### Provider Costs

```php
// Get cost for specific usage
$cost = AI::calculateCost('openai', 'gpt-4', [
    'input_tokens' => 100,
    'output_tokens' => 50,
]);

// Get provider cost summary
$costs = AI::getProviderCosts('openai', '2024-01-01', '2024-01-31');

// Compare provider costs
$comparison = AI::compareProviderCosts(['openai', 'gemini'], 'gpt-4', 'gemini-pro');
```

### Cost Optimization

```php
// Find cheapest provider for task
$cheapest = AI::findCheapestProvider([
    'input_tokens' => 1000,
    'output_tokens' => 500,
    'model_type' => 'chat',
]);

// Cost-aware provider selection
$response = AI::conversation()
    ->costOptimized() // Automatically select cheapest suitable provider
    ->message('Summarize this text...')
    ->send();
```

## Provider-Specific Features

### Tool Integration

```php
// Use specific tools by name
$response = AI::conversation()
    ->provider('openai')
    ->model('gpt-4')
    ->withTools(['get_weather', 'calculator'])
    ->message('What\'s the weather in New York and calculate 15% of 250?')
    ->send();

// Use all available tools
$response = AI::conversation()
    ->provider('openai')
    ->model('gpt-4')
    ->allTools()
    ->message('Help me with any task you can')
    ->send();
```

### Gemini Safety Settings

```php
$response = AI::conversation()
    ->provider('gemini')
    ->safetySettings([
        'HARM_CATEGORY_HARASSMENT' => 'BLOCK_NONE',
        'HARM_CATEGORY_HATE_SPEECH' => 'BLOCK_LOW_AND_ABOVE',
    ])
    ->message('Discuss controversial topic...')
    ->send();
```

### Ollama Model Parameters

```php
$response = AI::conversation()
    ->provider('ollama')
    ->model('llama2')
    ->parameters([
        'num_ctx' => 4096,      // Context window
        'temperature' => 0.8,    // Creativity
        'top_p' => 0.9,         // Nucleus sampling
        'repeat_penalty' => 1.1, // Repetition penalty
    ])
    ->message('Write a creative story...')
    ->send();
```

## Custom Providers

### Creating a Custom Provider

1. **Create Driver Class**:

```php
<?php

namespace App\AI\Drivers;

use JTD\LaravelAI\Contracts\AIProviderInterface;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;

class CustomAIDriver implements AIProviderInterface
{
    public function sendMessage(AIMessage $message, array $options = []): AIResponse
    {
        // Implement your custom API logic
    }
    
    public function getAvailableModels(): Collection
    {
        // Return available models
    }
    
    // Implement other required methods...
}
```

2. **Register Provider**:

```php
// In a service provider
AI::extend('custom', function ($config) {
    return new CustomAIDriver($config);
});
```

3. **Configure Provider**:

```php
'providers' => [
    'custom' => [
        'driver' => 'custom',
        'api_key' => env('CUSTOM_AI_API_KEY'),
        'base_url' => env('CUSTOM_AI_BASE_URL'),
    ],
],
```

## Troubleshooting

### Common Issues

1. **API Key Issues**:
```bash
php artisan ai:providers:verify
```

2. **Connection Timeouts**:
```php
'timeout' => 60, // Increase timeout
'retry_attempts' => 5, // More retries
```

3. **Rate Limiting**:
```php
'rate_limiting' => [
    'requests_per_minute' => 30, // Reduce rate
],
```

4. **Model Not Found**:
```bash
php artisan ai:sync-models --provider=openai --force
```

### Debug Mode

Enable debug logging:

```env
AI_DEBUG=true
AI_LOG_LEVEL=debug
```

### Provider Health Checks

```bash
# Monitor provider health
php artisan ai:providers:health

# Set up monitoring alerts
php artisan ai:providers:monitor --alert-email=admin@example.com
```
