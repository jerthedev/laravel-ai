<?php

namespace JTD\LaravelAI\Tests\Unit\Services;

use JTD\LaravelAI\Models\AIConversation;
use JTD\LaravelAI\Models\AIProvider;
use JTD\LaravelAI\Models\AIProviderModel;
use JTD\LaravelAI\Models\ConversationTemplate;
use JTD\LaravelAI\Services\ConversationTemplateService;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ConversationTemplateServiceTest extends TestCase
{
    protected ConversationTemplateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ConversationTemplateService::class);
    }

    #[Test]
    public function it_creates_template_successfully(): void
    {
        $templateData = [
            'name' => 'Test Template',
            'description' => 'A test template',
            'category' => ConversationTemplate::CATEGORY_GENERAL,
            'template_data' => [
                'system_prompt' => 'You are a helpful assistant.',
                'initial_messages' => [
                    ['role' => 'user', 'content' => 'Hello!'],
                ],
            ],
            'parameters' => [
                'name' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'User name',
                ],
            ],
            'tags' => ['test', 'example'],
        ];

        $template = $this->service->createTemplate($templateData);

        $this->assertInstanceOf(ConversationTemplate::class, $template);
        $this->assertEquals('Test Template', $template->name);
        $this->assertEquals(ConversationTemplate::CATEGORY_GENERAL, $template->category);
        $this->assertNotNull($template->uuid);
        $this->assertEquals(['test', 'example'], $template->tags);
    }

    #[Test]
    public function it_validates_template_data_on_creation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Validation failed');

        $invalidData = [
            'name' => '', // Empty name should fail
            'category' => 'invalid_category',
            'template_data' => [],
        ];

        $this->service->createTemplate($invalidData);
    }

    #[Test]
    public function it_updates_template_successfully(): void
    {
        $template = ConversationTemplate::factory()->create([
            'name' => 'Original Name',
            'description' => 'Original description',
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'category' => $template->category, // Keep existing category
            'template_data' => $template->template_data, // Keep existing template data
            'tags' => ['updated', 'test'],
        ];

        $updatedTemplate = $this->service->updateTemplate($template, $updateData);

        $this->assertEquals('Updated Name', $updatedTemplate->name);
        $this->assertEquals('Updated description', $updatedTemplate->description);
        $this->assertEquals(['updated', 'test'], $updatedTemplate->tags);
    }

    #[Test]
    public function it_deletes_template_successfully(): void
    {
        $template = ConversationTemplate::factory()->create();
        $templateId = $template->id;

        $result = $this->service->deleteTemplate($template);

        $this->assertTrue($result);
        $this->assertSoftDeleted('ai_conversation_templates', ['id' => $templateId]);
    }

    #[Test]
    public function it_gets_templates_with_filters(): void
    {
        // Create test templates
        ConversationTemplate::factory()->create([
            'category' => ConversationTemplate::CATEGORY_BUSINESS,
            'is_public' => true,
            'is_active' => true,
            'tags' => ['business', 'professional'],
        ]);

        ConversationTemplate::factory()->create([
            'category' => ConversationTemplate::CATEGORY_CREATIVE,
            'is_public' => true,
            'is_active' => true,
            'tags' => ['creative', 'writing'],
        ]);

        // Test category filter
        $businessTemplates = $this->service->getTemplates(['category' => ConversationTemplate::CATEGORY_BUSINESS]);
        $this->assertEquals(1, $businessTemplates->count());

        // Test tags filter
        $businessTaggedTemplates = $this->service->getTemplates(['tags' => ['business']]);
        $this->assertEquals(1, $businessTaggedTemplates->count());

        // Test public filter
        $publicTemplates = $this->service->getTemplates(['is_public' => true]);
        $this->assertEquals(2, $publicTemplates->count());
    }

    #[Test]
    public function it_creates_conversation_from_template(): void
    {
        $provider = AIProvider::factory()->create();
        $model = AIProviderModel::factory()->create(['ai_provider_id' => $provider->id]);

        $template = ConversationTemplate::factory()->create([
            'ai_provider_id' => $provider->id,
            'ai_provider_model_id' => $model->id,
            'template_data' => [
                'system_prompt' => 'You are {{role}}.',
                'initial_messages' => [
                    ['role' => 'user', 'content' => 'Hello, my name is {{name}}!'],
                ],
            ],
            'parameters' => [
                'role' => ['type' => 'string', 'required' => true],
                'name' => ['type' => 'string', 'required' => true],
            ],
        ]);

        $parameters = [
            'role' => 'a helpful assistant',
            'name' => 'John',
        ];

        $conversation = $this->service->createConversationFromTemplate($template, $parameters);

        $this->assertInstanceOf(AIConversation::class, $conversation);
        $this->assertEquals($template->id, $conversation->template_id);
        $this->assertEquals($provider->id, $conversation->ai_provider_id);
        $this->assertEquals($model->id, $conversation->ai_provider_model_id);

        // Check that parameters were substituted
        $messages = $conversation->messages;
        $this->assertCount(1, $messages);
        $this->assertEquals('Hello, my name is John!', $messages->first()->content);
    }

    #[Test]
    public function it_validates_parameters_when_creating_conversation(): void
    {
        $template = ConversationTemplate::factory()->create([
            'parameters' => [
                'required_param' => ['type' => 'string', 'required' => true],
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid parameters');

        // Missing required parameter
        $this->service->createConversationFromTemplate($template, []);
    }

    #[Test]
    public function it_substitutes_parameters_correctly(): void
    {
        $template = ConversationTemplate::factory()->create([
            'template_data' => [
                'system_prompt' => 'You are {{role}} helping with {{task}}.',
                'initial_messages' => [
                    ['role' => 'user', 'content' => 'My name is {{name}} and I need help with {{task}}.'],
                ],
            ],
            'parameters' => [
                'role' => ['type' => 'string', 'required' => true],
                'name' => ['type' => 'string', 'required' => true],
                'task' => ['type' => 'string', 'required' => true],
            ],
        ]);

        $parameters = [
            'role' => 'an expert developer',
            'name' => 'Alice',
            'task' => 'debugging code',
        ];

        $conversation = $this->service->createConversationFromTemplate($template, $parameters);
        $messages = $conversation->messages;

        $this->assertEquals('My name is Alice and I need help with debugging code.', $messages->first()->content);
    }

    #[Test]
    public function it_duplicates_template_successfully(): void
    {
        $originalTemplate = ConversationTemplate::factory()->create([
            'name' => 'Original Template',
            'description' => 'Original description',
            'is_public' => true,
            'usage_count' => 10,
        ]);

        $duplicatedTemplate = $this->service->duplicateTemplate($originalTemplate, [
            'name' => 'Duplicated Template',
        ]);

        $this->assertEquals('Duplicated Template', $duplicatedTemplate->name);
        $this->assertEquals($originalTemplate->description, $duplicatedTemplate->description);
        $this->assertFalse($duplicatedTemplate->is_public); // Should be private
        $this->assertEquals(0, $duplicatedTemplate->usage_count); // Should reset usage
        $this->assertNotEquals($originalTemplate->uuid, $duplicatedTemplate->uuid);
    }

    #[Test]
    public function it_gets_popular_templates(): void
    {
        // Create templates with different usage counts
        ConversationTemplate::factory()->create([
            'usage_count' => 100,
            'is_public' => true,
            'is_active' => true,
        ]);

        ConversationTemplate::factory()->create([
            'usage_count' => 50,
            'is_public' => true,
            'is_active' => true,
        ]);

        ConversationTemplate::factory()->create([
            'usage_count' => 2, // Below minimum usage
            'is_public' => true,
            'is_active' => true,
        ]);

        $popularTemplates = $this->service->getPopularTemplates(10, 5);

        $this->assertEquals(2, $popularTemplates->count());
        $this->assertEquals(100, $popularTemplates->first()->usage_count);
    }

    #[Test]
    public function it_gets_highly_rated_templates(): void
    {
        ConversationTemplate::factory()->create([
            'avg_rating' => 4.8,
            'is_public' => true,
            'is_active' => true,
        ]);

        ConversationTemplate::factory()->create([
            'avg_rating' => 4.2,
            'is_public' => true,
            'is_active' => true,
        ]);

        ConversationTemplate::factory()->create([
            'avg_rating' => 3.5, // Below minimum rating
            'is_public' => true,
            'is_active' => true,
        ]);

        $highlyRatedTemplates = $this->service->getHighlyRatedTemplates(10, 4.0);

        $this->assertEquals(2, $highlyRatedTemplates->count());
        $this->assertEquals(4.8, $highlyRatedTemplates->first()->avg_rating);
    }

    #[Test]
    public function it_searches_templates(): void
    {
        ConversationTemplate::factory()->create([
            'name' => 'Code Review Assistant',
            'description' => 'Helps with code reviews',
            'is_public' => true,
            'is_active' => true,
        ]);

        ConversationTemplate::factory()->create([
            'name' => 'Writing Helper',
            'description' => 'Assists with creative writing',
            'is_public' => true,
            'is_active' => true,
        ]);

        $searchResults = $this->service->searchTemplates('code');

        $this->assertEquals(1, $searchResults->count());
        $this->assertStringContainsString('Code', $searchResults->first()->name);
    }

    #[Test]
    public function it_exports_template_data(): void
    {
        $template = ConversationTemplate::factory()->create([
            'name' => 'Export Test Template',
            'description' => 'Template for export testing',
            'category' => ConversationTemplate::CATEGORY_TECHNICAL,
            'tags' => ['export', 'test'],
        ]);

        $exportData = $this->service->exportTemplate($template);

        $this->assertIsArray($exportData);
        $this->assertEquals('Export Test Template', $exportData['name']);
        $this->assertEquals('Template for export testing', $exportData['description']);
        $this->assertEquals(ConversationTemplate::CATEGORY_TECHNICAL, $exportData['category']);
        $this->assertEquals(['export', 'test'], $exportData['tags']);
        $this->assertArrayHasKey('exported_at', $exportData);
        $this->assertArrayHasKey('export_version', $exportData);
    }

    #[Test]
    public function it_imports_template_data(): void
    {
        $importData = [
            'name' => 'Imported Template',
            'description' => 'Template imported from export',
            'category' => ConversationTemplate::CATEGORY_BUSINESS,
            'template_data' => [
                'system_prompt' => 'You are a business assistant.',
            ],
            'tags' => ['imported', 'business'],
            'exported_at' => '2024-01-01T00:00:00Z',
            'export_version' => '1.0',
        ];

        $template = $this->service->importTemplate($importData);

        $this->assertEquals('Imported Template', $template->name);
        $this->assertEquals('Template imported from export', $template->description);
        $this->assertEquals(ConversationTemplate::CATEGORY_BUSINESS, $template->category);
        $this->assertEquals(['imported', 'business'], $template->tags);
    }

    #[Test]
    public function it_gets_template_statistics(): void
    {
        // Clear existing templates to ensure clean state
        ConversationTemplate::query()->delete();

        // Create test data
        ConversationTemplate::factory()->create(['is_active' => true, 'is_public' => true]);
        ConversationTemplate::factory()->create(['is_active' => true, 'is_public' => false]);
        ConversationTemplate::factory()->create(['is_active' => false]);

        $stats = $this->service->getTemplateStatistics();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_templates', $stats);
        $this->assertArrayHasKey('active_templates', $stats);
        $this->assertArrayHasKey('public_templates', $stats);
        $this->assertEquals(3, $stats['total_templates']);
        $this->assertEquals(2, $stats['active_templates']);
        $this->assertGreaterThanOrEqual(1, $stats['public_templates']);
    }
}
