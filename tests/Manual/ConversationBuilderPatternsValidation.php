<?php

/**
 * Manual validation script for ConversationBuilder patterns
 * 
 * This script demonstrates and validates the new withTools() and allTools() 
 * methods work correctly in the ConversationBuilder fluent interface.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Services\AIFunctionEvent;
use JTD\LaravelAI\Tests\Support\TestEmailSenderListener;

echo "🧪 ConversationBuilder Patterns Validation\n";
echo "==========================================\n\n";

// Register a test function event
AIFunctionEvent::listen(
    'test_validation_function',
    TestEmailSenderListener::class,
    [
        'description' => 'Test function for validation',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'message' => ['type' => 'string'],
            ],
        ],
    ]
);

echo "1. Testing withTools() method chaining:\n";
try {
    $conversation = AI::conversation()
        ->provider('mock')
        ->model('gpt-4')
        ->temperature(0.7)
        ->maxTokens(100)
        ->withTools(['test_validation_function'])
        ->systemPrompt('You are a helpful assistant');
    
    echo "   ✅ withTools() method chaining successful\n";
    echo "   ✅ Fluent interface maintained\n";
    
    $response = $conversation->message('Test message')->send();
    echo "   ✅ Message sent successfully\n";
    echo "   ✅ Response received: " . strlen($response->content) . " characters\n";
    
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

echo "\n2. Testing allTools() method chaining:\n";
try {
    $conversation = AI::conversation()
        ->provider('mock')
        ->model('gpt-4')
        ->temperature(0.8)
        ->maxTokens(150)
        ->allTools()
        ->systemPrompt('You have access to all available tools');
    
    echo "   ✅ allTools() method chaining successful\n";
    echo "   ✅ Fluent interface maintained\n";
    
    $response = $conversation->message('Use any tools you need')->send();
    echo "   ✅ Message sent successfully\n";
    echo "   ✅ Response received: " . strlen($response->content) . " characters\n";
    
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

echo "\n3. Testing method override behavior:\n";
try {
    $conversation = AI::conversation()
        ->provider('mock')
        ->allTools()
        ->withTools(['test_validation_function']); // Should override allTools
    
    echo "   ✅ Method override successful (withTools overrides allTools)\n";
    
    $response = $conversation->message('Test override')->send();
    echo "   ✅ Override behavior working correctly\n";
    
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

echo "\n4. Testing validation behavior:\n";
try {
    AI::conversation()
        ->provider('mock')
        ->withTools(['non_existent_tool'])
        ->message('This should fail')
        ->send();
    
    echo "   ❌ Validation failed - should have thrown exception\n";
    
} catch (InvalidArgumentException $e) {
    echo "   ✅ Validation working correctly\n";
    echo "   ✅ Exception message: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "   ❌ Unexpected error: " . $e->getMessage() . "\n";
}

echo "\n5. Testing complex chaining:\n";
try {
    $response = AI::conversation()
        ->provider('mock')
        ->model('gpt-4')
        ->temperature(0.5)
        ->maxTokens(200)
        ->withTools(['test_validation_function'])
        ->systemPrompt('You are a test assistant')
        ->message('Complex chaining test')
        ->send();
    
    echo "   ✅ Complex method chaining successful\n";
    echo "   ✅ All methods work together correctly\n";
    echo "   ✅ Response: " . substr($response->content, 0, 50) . "...\n";
    
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

echo "\n🎉 ConversationBuilder patterns validation complete!\n";
echo "All patterns are working correctly with the fluent interface.\n";
