<?php

namespace JTD\LaravelAI\Tests\Feature;

use JTD\LaravelAI\Contracts\ConversationBuilderInterface;
use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Services\AIManager;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class FacadeTest extends TestCase
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
        $builder = AI::conversation('Test Conversation');

        $this->assertInstanceOf(ConversationBuilderInterface::class, $builder);
    }

    #[Test]
    public function facade_can_get_providers()
    {
        $providers = AI::getProviders();

        $this->assertIsArray($providers);
    }

    #[Test]
    public function facade_can_get_models()
    {
        $models = AI::getModels();

        $this->assertIsArray($models);
    }

    #[Test]
    public function facade_can_calculate_cost()
    {
        $cost = AI::calculateCost('Hello, world!');

        $this->assertIsArray($cost);
        $this->assertArrayHasKey('total', $cost);
        $this->assertArrayHasKey('currency', $cost);
    }

    #[Test]
    public function facade_can_validate_provider()
    {
        $isValid = AI::validateProvider('mock');

        $this->assertIsBool($isValid);
    }

    #[Test]
    public function facade_can_get_provider_health()
    {
        $health = AI::getProviderHealth('mock');

        $this->assertIsArray($health);
        $this->assertArrayHasKey('status', $health);
    }

    #[Test]
    public function facade_can_get_usage_stats()
    {
        $stats = AI::getUsageStats('day');

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('period', $stats);
        $this->assertArrayHasKey('total_requests', $stats);
    }

    #[Test]
    public function facade_can_get_analytics()
    {
        $analytics = AI::getAnalytics(['provider' => 'mock']);

        $this->assertIsArray($analytics);
        $this->assertArrayHasKey('filters', $analytics);
        $this->assertArrayHasKey('data', $analytics);
    }

    #[Test]
    public function facade_can_extend_with_custom_driver()
    {
        AI::extend('custom-test', function ($app, $config) {
            return new \stdClass;
        });

        // If we reach here, extend worked without throwing an exception
        $this->assertTrue(true);
    }

    #[Test]
    public function facade_provides_fluent_conversation_interface()
    {
        $builder = AI::conversation('Fluent Test')
            ->provider('mock')
            ->model('test-model')
            ->temperature(0.7)
            ->message('Hello, how are you?');

        $this->assertInstanceOf(ConversationBuilderInterface::class, $builder);
        $this->assertEquals('mock', $builder->getProvider());
        $this->assertEquals('test-model', $builder->getModel());
        $this->assertCount(1, $builder->getMessages());
    }

    #[Test]
    public function facade_conversation_supports_method_chaining()
    {
        $builder = AI::conversation()
            ->provider('mock')
            ->model('test-model')
            ->temperature(0.8)
            ->maxTokens(1000)
            ->systemPrompt('You are a helpful assistant.')
            ->message('What is Laravel?')
            ->debug(true);

        $this->assertInstanceOf(ConversationBuilderInterface::class, $builder);

        $options = $builder->getOptions();
        $this->assertEquals(0.8, $options['temperature']);
        $this->assertEquals(1000, $options['max_tokens']);

        $messages = $builder->getMessages();
        $this->assertCount(2, $messages); // system prompt + user message
    }

    #[Test]
    public function facade_conversation_supports_conditional_logic()
    {
        $useAdvancedModel = true;

        $builder = AI::conversation()
            ->when($useAdvancedModel, function ($builder) {
                return $builder->model('advanced-model')->temperature(0.3);
            }, function ($builder) {
                return $builder->model('basic-model')->temperature(0.7);
            })
            ->message('Test message');

        $this->assertEquals('advanced-model', $builder->getModel());
        $this->assertEquals(0.3, $builder->getOptions()['temperature']);
    }

    #[Test]
    public function facade_conversation_supports_callbacks()
    {
        $successCalled = false;
        $errorCalled = false;

        $builder = AI::conversation()
            ->provider('mock')
            ->message('Test message')
            ->onSuccess(function ($response) use (&$successCalled) {
                $successCalled = true;
            })
            ->onError(function ($exception) use (&$errorCalled) {
                $errorCalled = true;
            });

        $this->assertInstanceOf(ConversationBuilderInterface::class, $builder);

        // Callbacks are registered but not executed until send() is called
        // This test just verifies the fluent interface works
    }

    #[Test]
    public function facade_conversation_can_be_cloned()
    {
        $original = AI::conversation()
            ->provider('mock')
            ->model('test-model')
            ->message('Original message');

        $cloned = $original->clone()
            ->message('Cloned message');

        $this->assertNotSame($original, $cloned);
        $this->assertCount(1, $original->getMessages());
        $this->assertCount(2, $cloned->getMessages());
    }

    #[Test]
    public function facade_conversation_can_be_reset()
    {
        $builder = AI::conversation()
            ->provider('mock')
            ->model('test-model')
            ->temperature(0.8)
            ->message('Test message');

        $this->assertCount(1, $builder->getMessages());
        $this->assertEquals('mock', $builder->getProvider());

        $builder->reset();

        $this->assertCount(0, $builder->getMessages());
        $this->assertNull($builder->getProvider());
        $this->assertEmpty($builder->getOptions());
    }

    #[Test]
    public function facade_handles_method_calls_gracefully()
    {
        // Test that facade doesn't break when calling methods that don't exist yet
        try {
            $manager = AI::getFacadeRoot();
            $this->assertInstanceOf(AIManager::class, $manager);
        } catch (\Exception $e) {
            $this->fail('Facade should handle method calls gracefully: ' . $e->getMessage());
        }
    }
}
