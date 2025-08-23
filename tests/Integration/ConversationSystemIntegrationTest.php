<?php

namespace JTD\LaravelAI\Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use JTD\LaravelAI\Contracts\AIProviderInterface;
use JTD\LaravelAI\Events\ConversationCreated;
use JTD\LaravelAI\Models\AIConversation;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIMessageRecord;
use JTD\LaravelAI\Models\AIProvider;
use JTD\LaravelAI\Models\AIProviderModel;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Models\ConversationTemplate;
use JTD\LaravelAI\Models\TokenUsage;
use JTD\LaravelAI\Services\ConversationSearchService;
use JTD\LaravelAI\Services\ConversationService;
use JTD\LaravelAI\Services\ConversationStatisticsService;
use JTD\LaravelAI\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class ConversationSystemIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected ConversationService $conversationService;

    protected ConversationSearchService $searchService;

    protected ConversationStatisticsService $statisticsService;

    protected AIProvider $provider;

    protected AIProviderModel $model;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conversationService = new ConversationService;
        $this->searchService = new ConversationSearchService;
        $this->statisticsService = new ConversationStatisticsService;

        // Clear statistics cache before each test
        $this->statisticsService->clearCache();

        // Create test provider and model
        $this->provider = AIProvider::create([
            'name' => 'test',
            'slug' => 'test',
            'driver' => 'TestDriver',
            'status' => 'active',
        ]);

        $this->model = AIProviderModel::create([
            'ai_provider_id' => $this->provider->id,
            'model_id' => 'test-model',
            'name' => 'Test Model',
            'status' => 'active',
        ]);

        Event::fake();
    }

    #[Test]
    public function it_can_create_and_manage_complete_conversation_lifecycle(): void
    {
        // 1. Create conversation
        $conversation = $this->conversationService->createConversation([
            'title' => 'Integration Test Conversation',
            'description' => 'Testing full conversation lifecycle',
            'user_id' => 1,
            'user_type' => 'App\\Models\\User',
            'ai_provider_id' => $this->provider->id,
            'ai_provider_model_id' => $this->model->id,
            'provider_name' => $this->provider->name,
            'model_name' => $this->model->name,
            'tags' => ['integration', 'test'],
            'conversation_type' => AIConversation::TYPE_CHAT,
        ]);

        $this->assertDatabaseHas('ai_conversations', [
            'title' => 'Integration Test Conversation',
            'user_id' => 1,
            'ai_provider_id' => $this->provider->id,
        ]);

        Event::assertDispatched(ConversationCreated::class);

        // 2. Prepare user message for AI interaction
        $userMessage = AIMessage::user('Hello, can you help me with testing?');

        // 3. Simulate AI response
        $mockProvider = Mockery::mock(AIProviderInterface::class);
        $mockResponse = new AIResponse(
            content: 'Of course! I\'d be happy to help you with testing.',
            tokenUsage: new TokenUsage(15, 12, 27, 0.0015, 0.0012, 0.0027),
            model: 'test-model',
            provider: 'test',
            finishReason: 'stop',
            responseTimeMs: 1250
        );

        $mockProvider->shouldReceive('sendMessage')
            ->once()
            ->andReturn($mockResponse);

        $response = $this->conversationService->sendMessage($conversation, $userMessage, $mockProvider);

        // Verify both user and AI messages were stored
        $this->assertDatabaseHas('ai_messages', [
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Hello, can you help me with testing?',
            'sequence_number' => 1,
        ]);

        $this->assertDatabaseHas('ai_messages', [
            'ai_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Of course! I\'d be happy to help you with testing.',
            'sequence_number' => 2,
            'input_tokens' => 15,
            'output_tokens' => 12,
            'total_tokens' => 27,
            'cost' => 0.0027,
        ]);

        // Verify conversation stats were updated
        $conversation->refresh();
        $this->assertEquals(2, $conversation->total_messages);
        $this->assertEquals(1, $conversation->total_requests);
        $this->assertEquals(1, $conversation->successful_requests);
        $this->assertEquals(15, $conversation->total_input_tokens);
        $this->assertEquals(12, $conversation->total_output_tokens);
        $this->assertEquals(0.0027, $conversation->total_cost);
        $this->assertNotNull($conversation->avg_response_time_ms);

        // 4. Test search functionality
        $searchResults = $this->searchService->search(['search' => 'testing']);
        $this->assertCount(1, $searchResults->items());
        $this->assertEquals($conversation->id, $searchResults->items()[0]->id);

        // 5. Test statistics
        $stats = $this->statisticsService->getOverallStatistics();
        $this->assertEquals(1, $stats['conversations']['total']);
        $this->assertEquals(2, $stats['messages']['total']);
        $this->assertEquals(27, $stats['tokens']['total']);
        $this->assertEquals(0.0027, $stats['costs']['total']);

        // 6. Archive conversation
        $this->conversationService->archiveConversation($conversation);
        $this->assertDatabaseHas('ai_conversations', [
            'id' => $conversation->id,
            'status' => AIConversation::STATUS_ARCHIVED,
        ]);
    }

    #[Test]
    public function it_can_create_conversation_from_template_with_full_workflow(): void
    {
        // 1. Create template
        $template = ConversationTemplate::create([
            'name' => 'Customer Support for {{product}}',
            'description' => 'Template for {{product}} customer support',
            'category' => 'support',
            'template_data' => [
                'system_prompt' => 'You are a helpful customer support agent for {{product}}. Be friendly and professional.',
                'initial_messages' => [
                    ['role' => 'assistant', 'content' => 'Hello! How can I help you with {{product}} today?'],
                ],
            ],
            'parameters' => [
                'product' => ['type' => 'string', 'required' => true],
            ],
            'default_configuration' => ['temperature' => 0.3, 'max_tokens' => 500],
            'ai_provider_id' => $this->provider->id,
            'ai_provider_model_id' => $this->model->id,
            'provider_name' => $this->provider->name,
            'model_name' => $this->model->name,
            'is_public' => true,
            'is_active' => true,
            'tags' => ['support', 'template'],
        ]);

        $this->assertDatabaseHas('ai_conversation_templates', [
            'name' => 'Customer Support for {{product}}',
            'category' => 'support',
            'is_public' => true,
        ]);

        // 2. Create conversation from template
        $parameters = ['product' => 'Laravel AI Package'];
        $conversation = $this->conversationService->createFromTemplate($template, $parameters);

        $this->assertDatabaseHas('ai_conversations', [
            'title' => 'Customer Support for Laravel AI Package',
            'description' => 'Template for Laravel AI Package customer support',
            'template_id' => $template->id,
            'ai_provider_id' => $this->provider->id,
        ]);

        // Verify system prompt was processed
        $this->assertEquals([
            'role' => 'system',
            'content' => 'You are a helpful customer support agent for Laravel AI Package. Be friendly and professional.',
        ], $conversation->system_prompt);

        // Verify initial messages were added (system + assistant)
        $this->assertCount(2, $conversation->messages);

        $systemMessage = $conversation->messages->where('role', 'system')->first();
        $this->assertNotNull($systemMessage);
        $this->assertEquals('You are a helpful customer support agent for Laravel AI Package. Be friendly and professional.', $systemMessage->content);

        $assistantMessage = $conversation->messages->where('role', 'assistant')->first();
        $this->assertNotNull($assistantMessage);
        $this->assertEquals('Hello! How can I help you with Laravel AI Package today?', $assistantMessage->content);

        // 3. Verify template usage was incremented
        $template->refresh();
        $this->assertEquals(1, $template->usage_count);

        // 4. Test template search
        $templateSearch = ConversationTemplate::search('support')->get();
        $this->assertCount(1, $templateSearch);
        $this->assertEquals($template->id, $templateSearch->first()->id);
    }

    #[Test]
    public function it_can_handle_multi_user_conversation_scenarios(): void
    {
        // Create conversations for different users
        $user1Conversations = [];
        $user2Conversations = [];

        for ($i = 1; $i <= 3; $i++) {
            $user1Conversations[] = $this->conversationService->createConversation([
                'title' => "User 1 Conversation {$i}",
                'user_id' => 1,
                'user_type' => 'App\\Models\\User',
                'provider_name' => 'test',
                'model_name' => 'test-model',
                'tags' => ['user1', "conv{$i}"],
            ]);

            $user2Conversations[] = $this->conversationService->createConversation([
                'title' => "User 2 Conversation {$i}",
                'user_id' => 2,
                'user_type' => 'App\\Models\\User',
                'provider_name' => 'test',
                'model_name' => 'test-model',
                'tags' => ['user2', "conv{$i}"],
            ]);
        }

        // Add messages to conversations
        foreach ($user1Conversations as $conv) {
            $this->conversationService->addMessage($conv, AIMessage::user('User 1 message'));
            $this->conversationService->addMessage($conv, AIMessage::assistant('Assistant response'));
        }

        foreach ($user2Conversations as $conv) {
            $this->conversationService->addMessage($conv, AIMessage::user('User 2 message'));
            $this->conversationService->addMessage($conv, AIMessage::assistant('Assistant response'));
        }

        // Test user-specific searches
        $user1Results = $this->searchService->searchForUser(1, 'App\\Models\\User');
        $user2Results = $this->searchService->searchForUser(2, 'App\\Models\\User');

        $this->assertCount(3, $user1Results->items());
        $this->assertCount(3, $user2Results->items());

        // Verify user isolation
        foreach ($user1Results->items() as $conv) {
            $this->assertEquals(1, $conv->user_id);
        }

        foreach ($user2Results->items() as $conv) {
            $this->assertEquals(2, $conv->user_id);
        }

        // Test statistics by user
        $user1Stats = $this->statisticsService->getOverallStatistics(['user_id' => 1]);
        $user2Stats = $this->statisticsService->getOverallStatistics(['user_id' => 2]);

        $this->assertEquals(3, $user1Stats['conversations']['total']);
        $this->assertEquals(6, $user1Stats['messages']['total']); // 2 messages per conversation
        $this->assertEquals(3, $user2Stats['conversations']['total']);
        $this->assertEquals(6, $user2Stats['messages']['total']);
    }

    #[Test]
    public function it_can_handle_conversation_search_with_complex_filters(): void
    {
        // Create diverse conversations
        $conversations = [
            $this->conversationService->createConversation([
                'title' => 'Technical Discussion',
                'description' => 'Deep dive into Laravel architecture',
                'conversation_type' => AIConversation::TYPE_ANALYSIS,
                'provider_name' => 'test',
                'status' => AIConversation::STATUS_ACTIVE,
                'tags' => ['technical', 'laravel'],
                'total_cost' => 0.05,
                'total_messages' => 10,
            ]),
            $this->conversationService->createConversation([
                'title' => 'Creative Writing',
                'description' => 'Story generation session',
                'conversation_type' => AIConversation::TYPE_CREATIVE,
                'provider_name' => 'test',
                'status' => AIConversation::STATUS_ACTIVE,
                'tags' => ['creative', 'writing'],
                'total_cost' => 0.15,
                'total_messages' => 25,
            ]),
            $this->conversationService->createConversation([
                'title' => 'Quick Chat',
                'description' => 'Brief conversation',
                'conversation_type' => AIConversation::TYPE_CHAT,
                'provider_name' => 'test',
                'status' => AIConversation::STATUS_ACTIVE,
                'tags' => ['quick', 'chat'],
                'total_cost' => 0.01,
                'total_messages' => 3,
            ]),
        ];

        // Test search by conversation type
        $analysisResults = $this->searchService->search(['conversation_type' => AIConversation::TYPE_ANALYSIS]);
        $this->assertCount(1, $analysisResults->items());
        $this->assertEquals('Technical Discussion', $analysisResults->items()[0]->title);

        // Test search by cost range
        $expensiveResults = $this->searchService->search(['min_cost' => 0.10]);
        $this->assertCount(1, $expensiveResults->items());
        $this->assertEquals('Creative Writing', $expensiveResults->items()[0]->title);

        // Test search by message count
        $longResults = $this->searchService->search(['min_messages' => 20]);
        $this->assertCount(1, $longResults->items());
        $this->assertEquals('Creative Writing', $longResults->items()[0]->title);

        // Test search by tags
        $technicalResults = $this->searchService->search(['tags' => ['technical']]);
        $this->assertCount(1, $technicalResults->items());
        $this->assertEquals('Technical Discussion', $technicalResults->items()[0]->title);

        // Test text search
        $laravelResults = $this->searchService->search(['search' => 'Laravel']);
        $this->assertCount(1, $laravelResults->items());
        $this->assertEquals('Technical Discussion', $laravelResults->items()[0]->title);
    }

    #[Test]
    public function it_can_generate_comprehensive_statistics(): void
    {
        // Clear cache to ensure fresh statistics
        $this->statisticsService->clearCache();

        // Create test data with known values
        $conversations = [];
        $totalCost = 0;
        $totalMessages = 0;

        for ($i = 1; $i <= 5; $i++) {
            $cost = $i * 0.01; // 0.01, 0.02, 0.03, 0.04, 0.05
            $messages = $i * 2; // 2, 4, 6, 8, 10

            $conv = $this->conversationService->createConversation([
                'title' => "Test Conversation {$i}",
                'provider_name' => 'test',
                'model_name' => 'test-model',
                'conversation_type' => $i <= 3 ? AIConversation::TYPE_CHAT : AIConversation::TYPE_ANALYSIS,
                'total_cost' => $cost,
                'total_messages' => $messages,
                'total_input_tokens' => $messages * 10,
                'total_output_tokens' => $messages * 8,
                'successful_requests' => $i,
                'failed_requests' => $i > 3 ? 1 : 0,
                'avg_response_time_ms' => 1000 + ($i * 100),
            ]);

            $conversations[] = $conv;
            $totalCost += $cost;
            $totalMessages += $messages;
        }

        // Test overall statistics
        $overallStats = $this->statisticsService->getOverallStatistics();

        $this->assertEquals(5, $overallStats['conversations']['total']);
        $this->assertEquals($totalMessages, $overallStats['messages']['total']);
        $this->assertEqualsWithDelta($totalCost, $overallStats['costs']['total'], 0.000001);

        // Test provider statistics
        $providerStats = $this->statisticsService->getProviderStatistics();
        $this->assertCount(1, $providerStats);
        $this->assertEquals('test', $providerStats[0]['provider']);
        $this->assertEquals(5, $providerStats[0]['conversations']);

        // Test conversation type statistics
        $typeStats = $this->statisticsService->getConversationTypeStatistics();
        $this->assertCount(2, $typeStats);

        $chatStats = collect($typeStats)->firstWhere('type', AIConversation::TYPE_CHAT);
        $analysisStats = collect($typeStats)->firstWhere('type', AIConversation::TYPE_ANALYSIS);

        $this->assertEquals(3, $chatStats['count']);
        $this->assertEquals(2, $analysisStats['count']);

        // Test cost breakdown
        $costBreakdown = $this->statisticsService->getCostBreakdown();
        $this->assertEqualsWithDelta($totalCost, $costBreakdown['total_cost'], 0.000001);
        $this->assertCount(1, $costBreakdown['by_provider']);
        $this->assertEquals('test', $costBreakdown['by_provider'][0]['provider']);

        // Test performance metrics
        $performance = $this->statisticsService->getPerformanceMetrics();
        $this->assertGreaterThan(0, $performance['response_time']['average_ms']);
        $this->assertGreaterThan(0, $performance['reliability']['success_rate']);
    }

    #[Test]
    public function it_maintains_data_consistency_across_operations(): void
    {
        $conversation = $this->conversationService->createConversation([
            'title' => 'Consistency Test',
            'user_id' => 1,
            'user_type' => 'App\\Models\\User',
        ]);

        // Add multiple messages and verify sequence numbers
        $messages = [];
        for ($i = 1; $i <= 5; $i++) {
            $message = AIMessage::user("Message {$i}");
            $messageRecord = $this->conversationService->addMessage($conversation, $message);
            $messages[] = $messageRecord;

            $this->assertEquals($i, $messageRecord->sequence_number);
        }

        // Verify conversation stats are consistent
        $conversation->refresh();
        $this->assertEquals(5, $conversation->total_messages);
        $this->assertEquals(5, $conversation->messages()->count());

        // Verify message ordering
        $retrievedMessages = $this->conversationService->getConversationMessages($conversation);
        $this->assertCount(5, $retrievedMessages);

        for ($i = 0; $i < 5; $i++) {
            $this->assertEquals('Message ' . ($i + 1), $retrievedMessages[$i]->content);
        }

        // Test soft delete consistency
        $this->conversationService->deleteConversation($conversation);

        $this->assertSoftDeleted($conversation);
        $this->assertEquals(0, AIMessageRecord::where('ai_conversation_id', $conversation->id)->count());
        $this->assertEquals(5, AIMessageRecord::withTrashed()->where('ai_conversation_id', $conversation->id)->count());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
