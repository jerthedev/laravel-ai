# Unified Tool System Integration for Driver Templates

## Overview

The driver templates in `docs/templates/drivers/` have been updated to include full support for the **Unified Tool System**. This system provides a consistent API for both MCP (Model Context Protocol) tools and Function Events across all AI providers.

## Key Methods Added to Templates

### 1. `formatToolsForAPI(array $resolvedTools): array`

**Purpose**: Converts unified tool definitions from the `UnifiedToolRegistry` to your provider's specific API format.

**Implementation Required**: 
```php
protected function formatToolsForAPI(array $resolvedTools): array
{
    $formattedTools = [];

    foreach ($resolvedTools as $toolName => $tool) {
        // Convert to your provider's format
        $formattedTools[] = [
            // Provider-specific structure
            // Example for OpenAI-compatible:
            'type' => 'function',
            'function' => [
                'name' => $tool['name'] ?? $toolName,
                'description' => $tool['description'] ?? '',
                'parameters' => $tool['parameters'] ?? [
                    'type' => 'object',
                    'properties' => [],
                ],
            ],
        ];
    }

    return $formattedTools;
}
```

**Provider-Specific Examples**:
- **OpenAI/XAI Format**: `{'type': 'function', 'function': {...}}`
- **Gemini Format**: `{'function_declarations': [...]}`
- **Anthropic Format**: `{'name': '...', 'description': '...', 'input_schema': {...}}`

### 2. `supportsFunctionCalling(): bool`

**Purpose**: Indicates whether your provider supports function/tool calling.

**Implementation Required**:
```php
public function supportsFunctionCalling(): bool
{
    // Return true if your provider supports function calling
    return true; // or check model capabilities
}
```

### 3. `hasToolCalls($response): bool`

**Purpose**: Checks if an AI response contains tool calls that need to be executed.

**Implementation Required**:
```php
protected function hasToolCalls($response): bool
{
    // Check your provider's response format for tool calls
    return !empty($response->toolCalls);
}
```

### 4. `extractToolCalls($response): array`

**Purpose**: Extracts tool calls from your provider's response format into a unified format.

**Implementation Required**:
```php
protected function extractToolCalls($response): array
{
    // Convert your provider's tool call format to unified format
    $toolCalls = [];
    
    // Parse your provider's response and extract tool calls
    // Return in unified format expected by UnifiedToolExecutor
    
    return $toolCalls;
}
```

## Automatic Inheritance

Your driver template automatically inherits these methods from `AbstractAIProvider`:

- ✅ `processToolOptions(array $options): array` - Validates and resolves tools
- ✅ `processToolCallsInResponse($response): void` - Routes tool calls for execution
- ✅ Integration with `UnifiedToolRegistry` and `UnifiedToolExecutor`

## Usage Patterns Supported

Your new driver will automatically support these patterns:

### ConversationBuilder Pattern
```php
$response = AI::conversation()
    ->provider('your-provider')
    ->withTools(['calculator', 'weather_service'])
    ->message('Calculate 15% of $120 and check weather')
    ->send();
```

### Direct SendMessage Pattern
```php
$response = AI::provider('your-provider')->sendMessage(
    AIMessage::user('Help me with calculations'),
    [
        'model' => 'your-model',
        'allTools' => true,
    ]
);
```

## Implementation Checklist

When creating a new driver from the templates:

### ✅ **Required Implementations**
1. **`formatToolsForAPI()`** - Convert unified tools to your provider's format
2. **`supportsFunctionCalling()`** - Return capability status
3. **`hasToolCalls()`** - Detect tool calls in responses
4. **`extractToolCalls()`** - Parse tool calls from responses

### ✅ **Provider-Specific Considerations**
1. **API Format**: Study your provider's function calling documentation
2. **Parameter Schema**: Understand how your provider expects tool parameters
3. **Response Format**: Learn how your provider returns tool calls
4. **Error Handling**: Implement provider-specific error scenarios

### ✅ **Testing Requirements**
1. **Unit Tests**: Test tool formatting and parsing methods
2. **Integration Tests**: Test with `UnifiedToolRegistry` and `UnifiedToolExecutor`
3. **E2E Tests**: Test full tool execution flow with real API calls

## Example Implementation

Here's how the OpenAI driver implements these methods:

```php
// OpenAI-specific tool formatting
protected function formatToolsForAPI(array $resolvedTools): array
{
    $formattedTools = [];

    foreach ($resolvedTools as $toolName => $tool) {
        $formattedTools[] = [
            'type' => 'function',
            'function' => [
                'name' => $tool['name'] ?? $toolName,
                'description' => $tool['description'] ?? '',
                'parameters' => $tool['parameters'] ?? [
                    'type' => 'object',
                    'properties' => [],
                ],
            ],
        ];
    }

    return $formattedTools;
}

public function supportsFunctionCalling(): bool
{
    return true; // OpenAI supports function calling
}
```

## Benefits of Using Templates

1. **Automatic Compatibility**: Your driver works with the unified tool system immediately
2. **Consistent API**: Same tool patterns work across all providers
3. **Robust Infrastructure**: Built-in validation, error handling, and routing
4. **Future-Proof**: Automatically supports new tool types and features
5. **Testing Coverage**: Comprehensive test templates included

## Getting Started

1. Copy the template directory: `docs/templates/drivers/`
2. Replace `DriverTemplate` with your provider name
3. Implement the four required methods above
4. Update API endpoints and authentication
5. Run the test suite to validate implementation

Your new driver will be fully compatible with the unified tool system and ready for production use!
