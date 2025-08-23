<?php

namespace JTD\LaravelAI\Tests\Unit\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use JTD\LaravelAI\Contracts\AIProviderInterface;
use JTD\LaravelAI\Events\ConversationCreated;
use JTD\LaravelAI\Events\MessageAdded;
use JTD\LaravelAI\Exceptions\ConversationException;
use JTD\LaravelAI\Models\AIConversation;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIMessageRecord;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Models\ConversationTemplate;
use JTD\LaravelAI\Models\TokenUsage;
use JTD\LaravelAI\Services\ConversationService;
use JTD\LaravelAI\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class ConversationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ConversationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ConversationService;
        Event::fake();
    }

    #[Test]
    public function it_can_create_a_conversation(): void
    {
        $data = [
            'title' => 'Test Conversation',
            'description' => 'A test conversation',
            'user_id' => 1,
            'user_type' => 'App\\Models\\User',
        ];

        $conversation = $this->service->createConversation($data);

        $this->assertInstanceOf(AIConversation::class, $conversation);
        $this->assertEquals('Test Conversation', $conversation->title);
        $this->assertEquals('A test conversation', $conversation->description);
        $this->assertEquals(1, $conversation->user_id);
        $this->assertEquals('App\\Models\\User', $conversation->user_type);

        Event::assertDispatched(ConversationCreated::class);
    }

    #[Test]
    public function it_creates_conversation_with_default_values(): void
    {
        $conversation = $this->service->createConversation();

        $this->assertEquals('New Conversation', $conversation->title);
        $this->assertEquals(AIConversation::STATUS_ACTIVE, $conversation->status);
        $this->assertTrue($conversation->auto_title);
        $this->assertEquals('en', $conversation->language);
        $this->assertEquals(AIConversation::TYPE_CHAT, $conversation->conversation_type);
    }

    #[Test]
    public function it_adds_system_message_when_creating_conversation(): void
    {
        $data = [
            'title' => 'Test',
            'system_prompt' => 'You are a helpful assistant.',
        ];

        $conversation = $this->service->createConversation($data);

        $this->assertCount(1, $conversation->messages);
        $this->assertEquals('system', $conversation->messages->first()->role);
        $this->assertEquals('You are a helpful assistant.', $conversation->messages->first()->content);
    }

    #[Test]
    public function it_can_create_conversation_from_template(): void
    {
        $template = ConversationTemplate::create([
            'name' => 'Test Template {{name}}',
            'description' => 'Template for {{purpose}}',
            'template_data' => [
                'system_prompt' => 'You are a {{role}} assistant.',
                'initial_messages' => [
                    ['role' => 'user', 'content' => 'Hello {{name}}!'],
                ],
            ],
            'parameters' => [
                'name' => ['type' => 'string', 'required' => true],
                'role' => ['type' => 'string', 'required' => true],
                'purpose' => ['type' => 'string', 'required' => false],
            ],
            'default_configuration' => ['temperature' => 0.7],
        ]);

        $parameters = [
            'name' => 'John',
            'role' => 'helpful',
            'purpose' => 'testing',
        ];

        $conversation = $this->service->createFromTemplate($template, $parameters);

        $this->assertEquals('Test Template John', $conversation->title);
        $this->assertEquals('Template for testing', $conversation->description);
        $this->assertEquals($template->id, $conversation->template_id);

        // Should have system message and initial user message
        $this->assertCount(2, $conversation->messages);
        $this->assertEquals('You are a helpful assistant.', $conversation->messages->first()->content);
        $this->assertEquals('Hello John!', $conversation->messages->last()->content);
    }

    #[Test]
    public function it_validates_template_parameters(): void
    {
        $template = ConversationTemplate::create([
            'name' => 'Test Template',
            'template_data' => [],
            'parameters' => [
                'required_param' => ['type' => 'string', 'required' => true],
            ],
        ]);

        $this->expectException(ConversationException::class);
        $this->expectExceptionMessage('Invalid template parameters');

        $this->service->createFromTemplate($template, []); // Missing required parameter
    }

    #[Test]
    public function it_increments_template_usage(): void
    {
        $template = ConversationTemplate::create([
            'name' => 'Test Template',
            'template_data' => [],
            'usage_count' => 5,
        ]);

        $this->service->createFromTemplate($template, []);

        $template->refresh();
        $this->assertEquals(6, $template->usage_count);
    }

    #[Test]
    public function it_can_add_message_to_conversation(): void
    {
        $conversation = AIConversation::create(['title' => 'Test']);
        $message = AIMessage::user('Hello, world!');

        $messageRecord = $this->service->addMessage($conversation, $message);

        $this->assertInstanceOf(AIMessageRecord::class, $messageRecord);
        $this->assertEquals('Hello, world!', $messageRecord->content);
        $this->assertEquals('user', $messageRecord->role);
        $this->assertEquals($conversation->id, $messageRecord->ai_conversation_id);

        Event::assertDispatched(MessageAdded::class);
    }

    #[Test]
    public function it_updates_conversation_stats_when_adding_message(): void
    {
        $conversation = AIConversation::create(['title' => 'Test']);
        $message = AIMessage::user('Hello');

        $this->service->addMessage($conversation, $message);

        $conversation->refresh();
        $this->assertEquals(1, $conversation->total_messages);
        $this->assertNotNull($conversation->last_message_at);
        $this->assertNotNull($conversation->last_activity_at);
    }

    #[Test]
    public function it_can_add_system_message(): void
    {
        $conversation = AIConversation::create(['title' => 'Test']);

        $messageRecord = $this->service->addSystemMessage($conversation, 'You are helpful.');

        $this->assertEquals('system', $messageRecord->role);
        $this->assertEquals('You are helpful.', $messageRecord->content);
    }

    #[Test]
    public function it_can_send_message_and_get_response(): void
    {
        $conversation = AIConversation::create(['title' => 'Test']);
        $message = AIMessage::user('Hello');

        $mockProvider = Mockery::mock(AIProviderInterface::class);
        $mockResponse = new AIResponse(
            content: 'Hello! How can I help you?',
            tokenUsage: new TokenUsage(10, 8, 18, 0.0005, 0.0003, 0.001),
            model: 'test-model',
            provider: 'test',
            finishReason: 'stop'
        );

        $mockProvider->shouldReceive('sendMessage')
            ->once()
            ->andReturn($mockResponse);

        $response = $this->service->sendMessage($conversation, $message, $mockProvider);

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertEquals('Hello! How can I help you?', $response->content);

        // Check that both user and assistant messages were added
        $conversation->refresh();
        $this->assertEquals(2, $conversation->total_messages);
        $this->assertEquals(1, $conversation->total_requests);
        $this->assertEquals(1, $conversation->successful_requests);
        $this->assertEquals(18, $conversation->total_input_tokens + $conversation->total_output_tokens);
        $this->assertEquals(0.001, $conversation->total_cost);
    }

    #[Test]
    public function it_can_get_conversation_messages(): void
    {
        $conversation = AIConversation::create(['title' => 'Test']);

        AIMessageRecord::create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'First message',
            'sequence_number' => 1,
        ]);

        AIMessageRecord::create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Second message',
            'sequence_number' => 2,
        ]);

        $messages = $this->service->getConversationMessages($conversation);

        $this->assertCount(2, $messages);
        $this->assertInstanceOf(AIMessage::class, $messages[0]);
        $this->assertInstanceOf(AIMessage::class, $messages[1]);
        $this->assertEquals('First message', $messages[0]->content);
        $this->assertEquals('Second message', $messages[1]->content);
    }

    #[Test]
    public function it_can_archive_conversation(): void
    {
        $conversation = AIConversation::create(['title' => 'Test']);

        $result = $this->service->archiveConversation($conversation);

        $this->assertTrue($result);
        $this->assertEquals(AIConversation::STATUS_ARCHIVED, $conversation->status);
        $this->assertNotNull($conversation->archived_at);
    }

    #[Test]
    public function it_can_delete_conversation(): void
    {
        $conversation = AIConversation::create(['title' => 'Test']);

        // Add a message
        AIMessageRecord::create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Test message',
            'sequence_number' => 1,
        ]);

        $result = $this->service->deleteConversation($conversation);

        $this->assertTrue($result);
        $this->assertSoftDeleted($conversation);

        // Messages should also be soft deleted
        $this->assertEquals(0, AIMessageRecord::where('ai_conversation_id', $conversation->id)->count());
        $this->assertEquals(1, AIMessageRecord::withTrashed()->where('ai_conversation_id', $conversation->id)->count());
    }

    #[Test]
    public function it_can_get_conversation_statistics(): void
    {
        $conversation = AIConversation::create([
            'title' => 'Test',
            'total_messages' => 10,
            'total_input_tokens' => 100,
            'total_output_tokens' => 50,
            'total_cost' => 0.05,
            'avg_response_time_ms' => 1500,
            'total_requests' => 10,
            'successful_requests' => 8,
            'failed_requests' => 2,
            'created_at' => now()->subHours(2),
            'last_activity_at' => now()->subMinutes(30),
        ]);

        $stats = $this->service->getConversationStats($conversation);

        $this->assertEquals(10, $stats['total_messages']);
        $this->assertEquals(150, $stats['total_tokens']);
        $this->assertEquals(0.05, $stats['total_cost']);
        $this->assertEquals(1500, $stats['avg_response_time_ms']);
        $this->assertEquals(80.0, $stats['success_rate']);
        $this->assertTrue($stats['duration_minutes'] > 0); // Duration should be positive
        $this->assertTrue($stats['messages_per_hour'] > 0); // Messages per hour should be positive
    }

    #[Test]
    public function it_processes_template_strings_with_parameters(): void
    {
        $template = ConversationTemplate::create([
            'name' => 'Hello {{name}}!',
            'description' => 'Template for {{purpose}} with {{name}}',
            'template_data' => [
                'system_prompt' => 'You are {{role}}.',
            ],
            'parameters' => [
                'name' => ['type' => 'string', 'required' => true],
                'role' => ['type' => 'string', 'required' => true],
                'purpose' => ['type' => 'string', 'required' => true],
            ],
        ]);

        $parameters = [
            'name' => 'Alice',
            'role' => 'helpful',
            'purpose' => 'testing',
        ];

        $conversation = $this->service->createFromTemplate($template, $parameters);

        $this->assertEquals('Hello Alice!', $conversation->title);
        $this->assertEquals('Template for testing with Alice', $conversation->description);
        $this->assertEquals(['role' => 'system', 'content' => 'You are helpful.'], $conversation->system_prompt);
    }

    #[Test]
    public function it_handles_missing_template_parameters_gracefully(): void
    {
        $template = ConversationTemplate::create([
            'name' => 'Hello {{name}}! Welcome to {{missing_param}}.',
            'template_data' => [],
            'parameters' => [
                'name' => ['type' => 'string', 'required' => true],
            ],
        ]);

        $parameters = ['name' => 'Bob'];

        $conversation = $this->service->createFromTemplate($template, $parameters);

        // Missing parameters should remain as placeholders
        $this->assertEquals('Hello Bob! Welcome to {{missing_param}}.', $conversation->title);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
