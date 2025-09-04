<?php

namespace JTD\LaravelAI\Tests\E2E;

use JTD\LaravelAI\Drivers\OpenAI\OpenAIDriver;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Advanced E2E Integration Tests for OpenAI Driver
 *
 * Tests advanced scenarios including different models, conversation context,
 * parameter variations, and edge cases with real OpenAI API.
 */
#[Group('e2e')]
#[Group('openai')]
#[Group('integration')]
class OpenAIAdvancedIntegrationTest extends E2ETestCase
{
    protected OpenAIDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip if no credentials available
        if (! $this->hasE2ECredentials('openai')) {
            $this->markTestSkipped('OpenAI E2E credentials not available');
        }

        // Create OpenAI driver with real credentials
        $credentials = $this->getE2ECredentials();
        $config = [
            'api_key' => $credentials['openai']['api_key'],
            'organization' => $credentials['openai']['organization'] ?? null,
            'project' => $credentials['openai']['project'] ?? null,
            'timeout' => 30,
            'retry_attempts' => 2,
        ];

        $this->driver = new OpenAIDriver($config);
    }

    #[Test]
    public function it_can_use_different_models(): void
    {
        $this->logTestStart('Testing different OpenAI models');

        $message = AIMessage::user('Say "test" and nothing else.');
        $models = ['gpt-3.5-turbo', 'gpt-4o-mini'];

        foreach ($models as $model) {
            $this->logTestStep("Testing model: {$model}");

            try {
                $response = $this->driver->sendMessage($message, [
                    'model' => $model,
                    'max_tokens' => 10,
                    'temperature' => 0,
                ]);

                $this->assertInstanceOf(AIResponse::class, $response);
                $this->assertStringStartsWith($model, $response->model);
                $this->assertNotEmpty($response->content);
                $this->assertGreaterThan(0, $response->tokenUsage->totalTokens);

                $this->logTestStep("✅ {$model}: \"{$response->content}\" ({$response->tokenUsage->totalTokens} tokens)");
            } catch (\Exception $e) {
                $this->logTestStep("❌ {$model} failed: " . $e->getMessage());
                throw $e;
            }
        }

        $this->logTestEnd('Different models test completed');
    }

    #[Test]
    public function it_can_handle_conversation_context(): void
    {
        $this->logTestStart('Testing conversation context handling');

        // First message
        $message1 = AIMessage::user('My name is Alice. Remember this.');
        $response1 = $this->driver->sendMessage($message1, [
            'model' => 'gpt-3.5-turbo',
            'max_tokens' => 50,
        ]);

        $this->assertInstanceOf(AIResponse::class, $response1);
        $this->logTestStep('First message: "' . trim($response1->content) . '"');

        // Convert response to AIMessage for conversation history
        $assistantMessage = AIMessage::assistant($response1->content);

        // Second message with conversation history
        $message2 = AIMessage::user('What is my name?');
        $response2 = $this->driver->sendMessage($message2, [
            'model' => 'gpt-3.5-turbo',
            'max_tokens' => 50,
            'conversation_history' => [$message1, $assistantMessage],
        ]);

        $this->assertInstanceOf(AIResponse::class, $response2);
        $this->logTestStep('Second message: "' . trim($response2->content) . '"');

        // Check if the AI remembered the name
        $responseContent = strtolower($response2->content);
        $this->assertStringContainsString('alice', $responseContent, 'AI should remember the name Alice');
        $this->logTestStep('✅ AI correctly remembered the name Alice');

        $this->logTestEnd('Conversation context test completed');
    }

    #[Test]
    public function it_can_handle_different_parameters(): void
    {
        $this->logTestStart('Testing different parameter configurations');

        $message = AIMessage::user('Write a creative sentence about cats.');

        $parameterSets = [
            'creative' => ['temperature' => 0.9, 'max_tokens' => 30],
            'deterministic' => ['temperature' => 0.0, 'max_tokens' => 30],
            'concise' => ['temperature' => 0.5, 'max_tokens' => 10],
        ];

        foreach ($parameterSets as $name => $params) {
            $this->logTestStep("Testing {$name} parameters: " . json_encode($params));

            try {
                $response = $this->driver->sendMessage($message, array_merge($params, [
                    'model' => 'gpt-3.5-turbo',
                ]));

                $this->assertInstanceOf(AIResponse::class, $response);
                $this->assertNotEmpty($response->content);
                $this->assertLessThanOrEqual($params['max_tokens'] + 5, $response->tokenUsage->output_tokens); // Allow small margin

                $this->logTestStep("✅ {$name}: \"{$response->content}\" ({$response->tokenUsage->output_tokens} output tokens)");
            } catch (\Exception $e) {
                $this->logTestStep("❌ {$name} failed: " . $e->getMessage());
                throw $e;
            }
        }

        $this->logTestEnd('Parameter configuration test completed');
    }

    #[Test]
    public function it_can_handle_system_messages(): void
    {
        $this->logTestStart('Testing system message handling');

        $systemMessageText = 'You are a helpful assistant that always responds in exactly 3 words.';
        $userMessage = AIMessage::user('Hello there!');

        try {
            $response = $this->driver->sendMessage($userMessage, [
                'model' => 'gpt-3.5-turbo',
                'max_tokens' => 20,
                'system_message' => $systemMessageText,
            ]);

            $this->assertInstanceOf(AIResponse::class, $response);
            $this->assertNotEmpty($response->content);

            $wordCount = str_word_count(trim($response->content));
            $this->logTestStep("Response: \"{$response->content}\" ({$wordCount} words)");

            // The AI should try to follow the 3-word instruction (allow flexibility for real AI behavior)
            $this->assertLessThanOrEqual(10, $wordCount, 'Response should be reasonably concise as per system message');

            if ($wordCount <= 5) {
                $this->logTestStep('✅ System message followed closely');
            } else {
                $this->logTestStep('⚠️  System message partially followed (AI behavior can vary)');
            }
        } catch (\Exception $e) {
            $this->logTestStep('❌ System message test failed: ' . $e->getMessage());
            throw $e;
        }

        $this->logTestEnd('System message test completed');
    }

    #[Test]
    public function it_can_handle_edge_cases(): void
    {
        $this->logTestStart('Testing edge cases');

        // Test empty message (should handle gracefully)
        try {
            $emptyMessage = AIMessage::user('');
            $response = $this->driver->sendMessage($emptyMessage, [
                'model' => 'gpt-3.5-turbo',
                'max_tokens' => 10,
            ]);

            // Should either work or throw a meaningful exception
            if ($response) {
                $this->assertInstanceOf(AIResponse::class, $response);
                $this->logTestStep('✅ Empty message handled gracefully');
            }
        } catch (\Exception $e) {
            // This is acceptable - empty messages might be rejected
            $this->logTestStep('⚠️  Empty message rejected (acceptable): ' . $e->getMessage());
        }

        // Test very long message (should handle or fail gracefully)
        try {
            $longMessage = AIMessage::user(str_repeat('This is a very long message. ', 100));
            $response = $this->driver->sendMessage($longMessage, [
                'model' => 'gpt-3.5-turbo',
                'max_tokens' => 10,
            ]);

            if ($response) {
                $this->assertInstanceOf(AIResponse::class, $response);
                $this->logTestStep('✅ Long message handled successfully');
            }
        } catch (\Exception $e) {
            // This is acceptable - very long messages might exceed context limits
            $this->logTestStep('⚠️  Long message rejected (acceptable): ' . $e->getMessage());
        }

        // Test minimal token limit
        try {
            $message = AIMessage::user('Hi');
            $response = $this->driver->sendMessage($message, [
                'model' => 'gpt-3.5-turbo',
                'max_tokens' => 1,
            ]);

            $this->assertInstanceOf(AIResponse::class, $response);
            $this->assertLessThanOrEqual(3, $response->tokenUsage->output_tokens); // Very small response
            $this->logTestStep('✅ Minimal token limit handled: "' . $response->content . '"');
        } catch (\Exception $e) {
            $this->logTestStep('❌ Minimal token limit failed: ' . $e->getMessage());
            throw $e;
        }

        $this->logTestEnd('Edge cases test completed');
    }

    /**
     * Log test step for better visibility.
     */
    protected function logTestStep(string $message): void
    {
        echo "\n  " . $message;
    }

    /**
     * Log test start.
     */
    protected function logTestStart(string $testName): void
    {
        echo "\n🧪 " . $testName;
    }

    /**
     * Log test end.
     */
    protected function logTestEnd(string $message): void
    {
        echo "\n✅ " . $message . "\n";
    }
}
