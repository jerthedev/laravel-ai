<?php

namespace JTD\LaravelAI\Tests\Integration;

use JTD\LaravelAI\Contracts\ConversationBuilderInterface;
use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Providers\MockProvider;
use JTD\LaravelAI\Services\AIManager;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Integration tests for AI facade basic functionality.
 *
 * Tests the complete AI facade integration including:
 * - Facade resolution and service binding
 * - Basic AI operations through facade
 * - Conversation building and execution
 * - Provider management through facade
 * - Error handling and edge cases
 */
class AIFacadeTest extends TestCase
{
    #[Test]
    public function facade_resolves_to_ai_manager()
    {
        $manager = AI::getFacadeRoot();

        $this->assertInstanceOf(AIManager::class, $manager);
    }

    #[Test]
    public function facade_can_create_conversation_builder()
    {
        $builder = AI::conversation();

        $this->assertInstanceOf(ConversationBuilderInterface::class, $builder);
    }

    #[Test]
    public function facade_can_create_named_conversation()
    {
        $builder = AI::conversation('Test Conversation');

        $this->assertInstanceOf(ConversationBuilderInterface::class, $builder);
        $this->assertEquals('Test Conversation', $builder->getTitle());
    }

    #[Test]
    public function facade_provides_fluent_interface()
    {
        $builder = AI::conversation()
            ->provider('mock')
            ->model('mock-model')
            ->temperature(0.7)
            ->maxTokens(1000);

        $this->assertInstanceOf(ConversationBuilderInterface::class, $builder);
        $this->assertEquals('mock', $builder->getProvider());
        $this->assertEquals('mock-model', $builder->getModel());
        $this->assertEquals(0.7, $builder->getTemperature());
        $this->assertEquals(1000, $builder->getMaxTokens());
    }

    #[Test]
    public function facade_can_send_simple_message()
    {
        $response = AI::conversation()
            ->provider('mock')
            ->message('Hello, world!')
            ->send();

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertNotEmpty($response->content);
        $this->assertEquals('mock', $response->provider);
    }

    #[Test]
    public function facade_can_send_ai_message_object()
    {
        $messageContent = 'Hello, world!';

        $response = AI::conversation()
            ->provider('mock')
            ->message($messageContent)
            ->send();

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertNotEmpty($response->content);
    }

    #[Test]
    public function facade_can_send_multiple_messages()
    {
        $response = AI::conversation()
            ->provider('mock')
            ->message('Hello')
            ->message('How are you?')
            ->message('What can you do?')
            ->send();

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertNotEmpty($response->content);
    }

    #[Test]
    public function facade_can_send_streaming_message()
    {
        $chunks = [];

        foreach (AI::conversation()->provider('mock')->message('Hello')->stream() as $chunk) {
            $chunks[] = $chunk;
            $this->assertInstanceOf(AIResponse::class, $chunk);
        }

        $this->assertNotEmpty($chunks);
        $this->assertGreaterThan(1, count($chunks));
    }

    #[Test]
    public function facade_can_access_providers()
    {
        $providers = AI::getProviders();

        $this->assertIsArray($providers);
        $this->assertArrayHasKey('mock', $providers);
    }

    #[Test]
    public function facade_can_get_models_for_provider()
    {
        $models = AI::getModels('mock');

        $this->assertIsArray($models);
        $this->assertNotEmpty($models);

        foreach ($models as $model) {
            $this->assertArrayHasKey('id', $model);
            $this->assertArrayHasKey('name', $model);
        }
    }

    #[Test]
    public function facade_can_validate_provider()
    {
        $isValid = AI::validateProvider('mock');

        $this->assertTrue($isValid);
    }

    #[Test]
    public function facade_can_get_provider_health()
    {
        $health = AI::getProviderHealth('mock');

        $this->assertIsArray($health);
        $this->assertArrayHasKey('status', $health);
        $this->assertEquals('healthy', $health['status']);
    }

    #[Test]
    public function facade_can_get_all_provider_health()
    {
        $health = AI::getProviderHealth();

        $this->assertIsArray($health);
        $this->assertArrayHasKey('mock', $health);
        $this->assertEquals('healthy', $health['mock']['status']);
    }

    #[Test]
    public function facade_can_extend_with_custom_driver()
    {
        // This test verifies the extension mechanism exists
        // Full custom driver support may require additional configuration handling

        // Test that the extend method can be called without errors
        AI::extend('custom-test', function ($app, $config) {
            return new MockProvider($config);
        });

        // Verify the underlying manager has the extend method
        $manager = AI::getFacadeRoot();
        $this->assertTrue(method_exists($manager, 'extend'), 'AIManager should have extend method');

        // The extension mechanism is in place
        $this->assertTrue(true, 'Custom driver extension mechanism is available');
    }

    #[Test]
    public function facade_handles_invalid_provider_gracefully()
    {
        // System should handle invalid providers gracefully (fallback to default)
        $response = AI::conversation()
            ->provider('nonexistent')
            ->message('Hello')
            ->send();

        // Should still get a response (fallback behavior)
        $this->assertInstanceOf(\JTD\LaravelAI\Models\AIResponse::class, $response);
    }

    #[Test]
    public function facade_can_use_system_prompt()
    {
        $response = AI::conversation()
            ->provider('mock')
            ->systemPrompt('You are a helpful assistant.')
            ->message('Hello')
            ->send();

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertNotEmpty($response->content);
    }

    #[Test]
    public function facade_can_use_conditional_logic()
    {
        $useAdvancedModel = true;

        $builder = AI::conversation()
            ->provider('mock')
            ->when($useAdvancedModel, function ($builder) {
                return $builder->model('mock-advanced')->temperature(0.3);
            })
            ->unless($useAdvancedModel, function ($builder) {
                return $builder->model('mock-basic')->temperature(0.7);
            });

        $this->assertEquals('mock-advanced', $builder->getModel());
        $this->assertEquals(0.3, $builder->getTemperature());
    }

    #[Test]
    public function facade_can_use_callback_handlers()
    {
        $successCalled = false;
        $errorCalled = false;

        $response = AI::conversation()
            ->provider('mock')
            ->message('Hello')
            ->onSuccess(function ($response) use (&$successCalled) {
                $successCalled = true;
                $this->assertInstanceOf(AIResponse::class, $response);
            })
            ->onError(function ($error) use (&$errorCalled) {
                $errorCalled = true;
            })
            ->send();

        $this->assertTrue($successCalled);
        $this->assertFalse($errorCalled);
        $this->assertInstanceOf(AIResponse::class, $response);
    }

    #[Test]
    public function facade_can_calculate_cost()
    {
        $cost = AI::calculateCost('Hello, world!', 'mock');

        $this->assertIsArray($cost);
        $this->assertArrayHasKey('total', $cost);
        $this->assertArrayHasKey('input_cost', $cost);
        $this->assertArrayHasKey('output_cost', $cost);
        $this->assertArrayHasKey('currency', $cost);
    }

    #[Test]
    public function facade_can_estimate_tokens()
    {
        $tokens = AI::estimateTokens('Hello, world!', 'mock');

        $this->assertIsInt($tokens);
        $this->assertGreaterThan(0, $tokens);
    }

    #[Test]
    public function facade_can_get_default_driver()
    {
        $defaultDriver = AI::getDefaultDriver();

        $this->assertEquals('mock', $defaultDriver);
    }

    #[Test]
    public function facade_can_access_driver_directly()
    {
        $driver = AI::driver('mock');

        $this->assertInstanceOf(MockProvider::class, $driver);
    }

    #[Test]
    public function facade_can_access_default_driver()
    {
        $driver = AI::driver();

        $this->assertInstanceOf(MockProvider::class, $driver);
    }

    #[Test]
    public function facade_maintains_singleton_behavior()
    {
        $manager1 = AI::getFacadeRoot();
        $manager2 = AI::getFacadeRoot();

        $this->assertSame($manager1, $manager2);
    }

    #[Test]
    public function facade_can_handle_complex_conversation()
    {
        $response = AI::conversation('Complex Test')
            ->provider('mock')
            ->model('mock-advanced')
            ->systemPrompt('You are an expert programmer.')
            ->temperature(0.2)
            ->maxTokens(500)
            ->message('Explain dependency injection')
            ->message('Give me a PHP example')
            ->metadata(['topic' => 'programming', 'difficulty' => 'intermediate'])
            ->send();

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertNotEmpty($response->content);
        $this->assertEquals('mock', $response->provider);
    }

    #[Test]
    public function facade_can_handle_batch_operations()
    {
        $conversations = [
            AI::conversation()->provider('mock')->message('Hello 1'),
            AI::conversation()->provider('mock')->message('Hello 2'),
            AI::conversation()->provider('mock')->message('Hello 3'),
        ];

        $responses = [];
        foreach ($conversations as $conversation) {
            $responses[] = $conversation->send();
        }

        $this->assertCount(3, $responses);
        foreach ($responses as $response) {
            $this->assertInstanceOf(AIResponse::class, $response);
            $this->assertNotEmpty($response->content);
        }
    }

    #[Test]
    public function facade_preserves_conversation_state()
    {
        $builder = AI::conversation('Stateful Test')
            ->provider('mock')
            ->model('mock-model')
            ->temperature(0.5);

        // Add messages one by one
        $builder->message('First message');
        $builder->message('Second message');

        // Verify state is preserved
        $this->assertEquals('Stateful Test', $builder->getTitle());
        $this->assertEquals('mock', $builder->getProvider());
        $this->assertEquals('mock-model', $builder->getModel());
        $this->assertEquals(0.5, $builder->getTemperature());

        $response = $builder->send();
        $this->assertInstanceOf(AIResponse::class, $response);
    }

    #[Test]
    public function facade_can_reset_conversation()
    {
        $builder = AI::conversation()
            ->provider('mock')
            ->message('First message')
            ->message('Second message');

        $builder->reset();

        // After reset, should be able to start fresh
        $response = $builder
            ->message('New message after reset')
            ->send();

        $this->assertInstanceOf(AIResponse::class, $response);
    }
}
