<?php

namespace JTD\LaravelAI\Tests\Unit\Services;

use JTD\LaravelAI\Models\AIConversation;
use JTD\LaravelAI\Models\AIProvider;
use JTD\LaravelAI\Models\AIProviderModel;
use JTD\LaravelAI\Services\ContextConfigurationService;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ContextConfigurationServiceTest extends TestCase
{
    protected ContextConfigurationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ContextConfigurationService;
    }

    #[Test]
    public function it_returns_default_configuration(): void
    {
        $config = $this->service->getDefaultConfiguration();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('window_size', $config);
        $this->assertArrayHasKey('preservation_strategy', $config);
        $this->assertArrayHasKey('context_ratio', $config);
        $this->assertArrayHasKey('search_enhanced', $config);
        $this->assertArrayHasKey('cache_ttl', $config);
        $this->assertArrayHasKey('max_search_results', $config);
        $this->assertArrayHasKey('relevance_threshold', $config);
    }

    #[Test]
    public function it_gets_configuration_for_conversation_with_custom_settings(): void
    {
        $conversation = AIConversation::factory()->create([
            'context_data' => [
                'settings' => [
                    'window_size' => 8192,
                    'preservation_strategy' => 'search_enhanced_truncation',
                    'context_ratio' => 0.9,
                ],
            ],
        ]);

        $config = $this->service->getConfigurationForConversation($conversation);

        $this->assertEquals(8192, $config['window_size']);
        $this->assertEquals('search_enhanced_truncation', $config['preservation_strategy']);
        $this->assertEquals(0.9, $config['context_ratio']);
    }

    #[Test]
    public function it_applies_provider_specific_configuration(): void
    {
        $provider = AIProvider::factory()->create(['name' => 'OpenAI']);
        $model = AIProviderModel::factory()->create([
            'ai_provider_id' => $provider->id,
            'context_length' => 16384,
        ]);

        $conversation = AIConversation::factory()->create([
            'ai_provider_model_id' => $model->id,
        ]);

        // Mock the relationship
        $conversation->setRelation('currentModel', $model);
        $model->setRelation('provider', $provider);

        $config = $this->service->getConfigurationForConversation($conversation);

        $this->assertEquals(16384, $config['window_size']);
        $this->assertEquals(0.85, $config['context_ratio']); // OpenAI specific
    }

    #[Test]
    public function it_provides_gemini_specific_configuration(): void
    {
        $provider = AIProvider::factory()->create(['name' => 'Gemini']);
        $model = AIProviderModel::factory()->create([
            'ai_provider_id' => $provider->id,
            'context_length' => 32768,
        ]);

        $model->setRelation('provider', $provider);

        $config = $this->service->getProviderSpecificConfiguration($model);

        $this->assertEquals(32768, $config['window_size']);
        $this->assertEquals(0.8, $config['context_ratio']); // Gemini specific
        $this->assertEquals('recent_messages', $config['preservation_strategy']);
    }

    #[Test]
    public function it_provides_xai_specific_configuration(): void
    {
        $provider = AIProvider::factory()->create(['name' => 'xAI']);
        $model = AIProviderModel::factory()->create([
            'ai_provider_id' => $provider->id,
            'context_length' => 8192,
        ]);

        $model->setRelation('provider', $provider);

        $config = $this->service->getProviderSpecificConfiguration($model);

        $this->assertEquals(8192, $config['window_size']);
        $this->assertEquals(0.75, $config['context_ratio']); // xAI specific (more conservative)
    }

    #[Test]
    public function it_returns_available_strategies(): void
    {
        $strategies = $this->service->getAvailableStrategies();

        $this->assertIsArray($strategies);
        $this->assertArrayHasKey('recent_messages', $strategies);
        $this->assertArrayHasKey('important_messages', $strategies);
        $this->assertArrayHasKey('summarized_context', $strategies);
        $this->assertArrayHasKey('intelligent_truncation', $strategies);
        $this->assertArrayHasKey('search_enhanced_truncation', $strategies);

        // Check structure of strategy info
        $recentStrategy = $strategies['recent_messages'];
        $this->assertArrayHasKey('name', $recentStrategy);
        $this->assertArrayHasKey('description', $recentStrategy);
        $this->assertArrayHasKey('best_for', $recentStrategy);
    }

    #[Test]
    public function it_validates_configuration_successfully(): void
    {
        $validConfig = [
            'window_size' => 4096,
            'context_ratio' => 0.8,
            'preservation_strategy' => 'intelligent_truncation',
            'cache_ttl' => 300,
            'relevance_threshold' => 0.7,
        ];

        $errors = $this->service->validateConfiguration($validConfig);

        $this->assertEmpty($errors);
    }

    #[Test]
    public function it_validates_configuration_with_errors(): void
    {
        $invalidConfig = [
            'window_size' => 50, // Too small
            'context_ratio' => 1.5, // Too high
            'preservation_strategy' => 'invalid_strategy',
            'cache_ttl' => 30, // Too small
            'relevance_threshold' => -0.1, // Negative
        ];

        $errors = $this->service->validateConfiguration($invalidConfig);

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('window_size', $errors);
        $this->assertArrayHasKey('context_ratio', $errors);
        $this->assertArrayHasKey('preservation_strategy', $errors);
        $this->assertArrayHasKey('cache_ttl', $errors);
        $this->assertArrayHasKey('relevance_threshold', $errors);
    }

    #[Test]
    public function it_applies_configuration_to_conversation(): void
    {
        $conversation = AIConversation::factory()->create();

        $config = [
            'window_size' => 8192,
            'preservation_strategy' => 'search_enhanced_truncation',
            'context_ratio' => 0.9,
        ];

        $result = $this->service->applyConfiguration($conversation, $config);

        $this->assertTrue($result);

        // Refresh the conversation to get updated data
        $conversation->refresh();
        $contextSettings = $conversation->getContextSettings();

        $this->assertEquals(8192, $contextSettings['window_size']);
        $this->assertEquals('search_enhanced_truncation', $contextSettings['preservation_strategy']);
        $this->assertEquals(0.9, $contextSettings['context_ratio']);
    }

    #[Test]
    public function it_rejects_invalid_configuration(): void
    {
        $conversation = AIConversation::factory()->create();

        $invalidConfig = [
            'window_size' => 50, // Too small
            'context_ratio' => 2.0, // Too high
        ];

        $result = $this->service->applyConfiguration($conversation, $invalidConfig);

        $this->assertFalse($result);
    }

    #[Test]
    public function it_resets_conversation_to_defaults(): void
    {
        $conversation = AIConversation::factory()->create([
            'context_data' => [
                'settings' => [
                    'window_size' => 8192,
                    'preservation_strategy' => 'custom_strategy',
                ],
            ],
        ]);

        $this->service->resetToDefaults($conversation);

        $conversation->refresh();
        $contextSettings = $conversation->getContextSettings();

        // Should have default values
        $defaults = $this->service->getDefaultConfiguration();
        $this->assertEquals($defaults['window_size'], $contextSettings['window_size']);
        $this->assertEquals($defaults['preservation_strategy'], $contextSettings['preservation_strategy']);
    }

    #[Test]
    public function it_provides_recommended_configuration_for_chat(): void
    {
        $config = $this->service->getRecommendedConfiguration('chat');

        $this->assertEquals('recent_messages', $config['preservation_strategy']);
        $this->assertEquals(0.8, $config['context_ratio']);
        $this->assertTrue($config['search_enhanced']);
    }

    #[Test]
    public function it_provides_recommended_configuration_for_analysis(): void
    {
        $config = $this->service->getRecommendedConfiguration('analysis');

        $this->assertEquals('important_messages', $config['preservation_strategy']);
        $this->assertEquals(0.9, $config['context_ratio']);
        $this->assertFalse($config['search_enhanced']);
    }

    #[Test]
    public function it_provides_recommended_configuration_for_coding(): void
    {
        $config = $this->service->getRecommendedConfiguration('coding');

        $this->assertEquals('intelligent_truncation', $config['preservation_strategy']);
        $this->assertEquals(0.85, $config['context_ratio']);
        $this->assertTrue($config['search_enhanced']);
    }

    #[Test]
    public function it_provides_recommended_configuration_for_creative(): void
    {
        $config = $this->service->getRecommendedConfiguration('creative');

        $this->assertEquals('summarized_context', $config['preservation_strategy']);
        $this->assertEquals(0.75, $config['context_ratio']);
        $this->assertFalse($config['search_enhanced']);
    }

    #[Test]
    public function it_provides_default_configuration_for_unknown_type(): void
    {
        $config = $this->service->getRecommendedConfiguration('unknown_type');
        $defaults = $this->service->getDefaultConfiguration();

        $this->assertEquals($defaults, $config);
    }
}
