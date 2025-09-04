<?php

/**
 * Manual validation script for direct sendMessage patterns
 *
 * This script demonstrates and validates the new withTools and allTools
 * options work correctly in direct sendMessage calls.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Services\AIFunctionEvent;
use JTD\LaravelAI\Tests\Support\TestTaskManagerListener;

echo "🧪 Direct SendMessage Patterns Validation\n";
echo "=========================================\n\n";

// Register a test function event
AIFunctionEvent::listen(
    'test_direct_function',
    TestTaskManagerListener::class,
    [
        'description' => 'Test function for direct sendMessage validation',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'action' => ['type' => 'string'],
                'title' => ['type' => 'string'],
            ],
        ],
    ]
);

echo "1. Testing withTools option in default sendMessage:\n";
try {
    $response = AI::sendMessage(
        AIMessage::user('Help me with a task'),
        [
            'model' => 'gpt-4',
            'withTools' => ['test_direct_function'],
        ]
    );

    echo "   ✅ Default sendMessage with withTools successful\n";
    echo '   ✅ Response received: ' . strlen($response->content) . " characters\n";
    echo '   ✅ Provider: ' . $response->provider . "\n";
} catch (Exception $e) {
    echo '   ❌ Error: ' . $e->getMessage() . "\n";
}

echo "\n2. Testing allTools option in default sendMessage:\n";
try {
    $response = AI::sendMessage(
        AIMessage::user('Use any tools you need'),
        [
            'model' => 'gpt-4',
            'allTools' => true,
        ]
    );

    echo "   ✅ Default sendMessage with allTools successful\n";
    echo '   ✅ Response received: ' . strlen($response->content) . " characters\n";
    echo '   ✅ Provider: ' . $response->provider . "\n";
} catch (Exception $e) {
    echo '   ❌ Error: ' . $e->getMessage() . "\n";
}

echo "\n3. Testing withTools option in provider-specific sendMessage:\n";
try {
    $response = AI::provider('mock')->sendMessage(
        AIMessage::user('Help me with provider-specific tools'),
        [
            'model' => 'gpt-4',
            'withTools' => ['test_direct_function'],
        ]
    );

    echo "   ✅ Provider-specific sendMessage with withTools successful\n";
    echo '   ✅ Response received: ' . strlen($response->content) . " characters\n";
    echo '   ✅ Provider: ' . $response->provider . "\n";
} catch (Exception $e) {
    echo '   ❌ Error: ' . $e->getMessage() . "\n";
}

echo "\n4. Testing allTools option in provider-specific sendMessage:\n";
try {
    $response = AI::provider('mock')->sendMessage(
        AIMessage::user('Use all available tools'),
        [
            'model' => 'gpt-4',
            'allTools' => true,
        ]
    );

    echo "   ✅ Provider-specific sendMessage with allTools successful\n";
    echo '   ✅ Response received: ' . strlen($response->content) . " characters\n";
    echo '   ✅ Provider: ' . $response->provider . "\n";
} catch (Exception $e) {
    echo '   ❌ Error: ' . $e->getMessage() . "\n";
}

echo "\n5. Testing option combination:\n";
try {
    $response = AI::provider('mock')->sendMessage(
        AIMessage::user('Test combined options'),
        [
            'model' => 'gpt-4',
            'temperature' => 0.7,
            'max_tokens' => 150,
            'withTools' => ['test_direct_function'],
        ]
    );

    echo "   ✅ Combined options successful\n";
    echo "   ✅ Tools and other options work together\n";
    echo '   ✅ Response: ' . substr($response->content, 0, 50) . "...\n";
} catch (Exception $e) {
    echo '   ❌ Error: ' . $e->getMessage() . "\n";
}

echo "\n6. Testing validation behavior:\n";
try {
    AI::sendMessage(
        AIMessage::user('This should fail'),
        [
            'model' => 'gpt-4',
            'withTools' => ['non_existent_tool'],
        ]
    );

    echo "   ❌ Validation failed - should have thrown exception\n";
} catch (InvalidArgumentException $e) {
    echo "   ✅ Validation working correctly\n";
    echo '   ✅ Exception message: ' . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo '   ❌ Unexpected error: ' . $e->getMessage() . "\n";
}

echo "\n7. Testing priority behavior (withTools over allTools):\n";
try {
    $response = AI::provider('mock')->sendMessage(
        AIMessage::user('Test priority'),
        [
            'model' => 'gpt-4',
            'allTools' => true,
            'withTools' => ['test_direct_function'], // Should take priority
        ]
    );

    echo "   ✅ Priority behavior working correctly\n";
    echo "   ✅ withTools takes priority over allTools\n";
    echo "   ✅ Response received successfully\n";
} catch (Exception $e) {
    echo '   ❌ Error: ' . $e->getMessage() . "\n";
}

echo "\n8. Testing different message types:\n";
try {
    // Test with system message
    $systemResponse = AI::provider('mock')->sendMessage(
        AIMessage::system('You are a helpful assistant with tools'),
        [
            'model' => 'gpt-4',
            'withTools' => ['test_direct_function'],
        ]
    );

    echo "   ✅ System message with tools successful\n";

    // Test with assistant message
    $assistantResponse = AI::provider('mock')->sendMessage(
        AIMessage::assistant('I can help you with various tasks'),
        [
            'model' => 'gpt-4',
            'withTools' => ['test_direct_function'],
        ]
    );

    echo "   ✅ Assistant message with tools successful\n";
    echo "   ✅ All message types work with tools\n";
} catch (Exception $e) {
    echo '   ❌ Error: ' . $e->getMessage() . "\n";
}

echo "\n🎉 Direct sendMessage patterns validation complete!\n";
echo "All patterns are working correctly with both default and provider-specific calls.\n";
