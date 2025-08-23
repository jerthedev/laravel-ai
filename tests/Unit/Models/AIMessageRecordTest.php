<?php

namespace JTD\LaravelAI\Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use JTD\LaravelAI\Models\AIConversation;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIMessageRecord;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AIMessageRecordTest extends TestCase
{
    use RefreshDatabase;

    protected AIConversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conversation = AIConversation::create(['title' => 'Test Conversation']);
    }

    #[Test]
    public function it_can_create_a_message_with_default_values(): void
    {
        $message = AIMessageRecord::create([
            'ai_conversation_id' => $this->conversation->id,
            'role' => 'user',
            'content' => 'Hello, world!',
        ]);

        $this->assertNotNull($message->uuid);
        $this->assertEquals(1, $message->sequence_number);
        $this->assertEquals('user', $message->role);
        $this->assertEquals('Hello, world!', $message->content);
        $this->assertEquals(AIMessageRecord::CONTENT_TYPE_TEXT, $message->content_type);
        $this->assertEquals('USD', $message->cost_currency);
        $this->assertFalse($message->is_streaming);
        $this->assertFalse($message->is_edited);
    }

    #[Test]
    public function it_generates_uuid_automatically(): void
    {
        $message = AIMessageRecord::create([
            'ai_conversation_id' => $this->conversation->id,
            'role' => 'user',
            'content' => 'Test',
        ]);

        $this->assertNotNull($message->uuid);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $message->uuid);
    }

    #[Test]
    public function it_auto_increments_sequence_number(): void
    {
        $message1 = AIMessageRecord::create([
            'ai_conversation_id' => $this->conversation->id,
            'role' => 'user',
            'content' => 'First message',
        ]);

        $message2 = AIMessageRecord::create([
            'ai_conversation_id' => $this->conversation->id,
            'role' => 'assistant',
            'content' => 'Second message',
        ]);

        $this->assertEquals(1, $message1->sequence_number);
        $this->assertEquals(2, $message2->sequence_number);
    }

    #[Test]
    public function it_uses_uuid_as_route_key(): void
    {
        $message = AIMessageRecord::create([
            'ai_conversation_id' => $this->conversation->id,
            'role' => 'user',
            'content' => 'Test',
        ]);

        $this->assertEquals('uuid', $message->getRouteKeyName());
    }

    #[Test]
    public function it_belongs_to_conversation(): void
    {
        $message = AIMessageRecord::create([
            'ai_conversation_id' => $this->conversation->id,
            'role' => 'user',
            'content' => 'Test',
        ]);

        $this->assertInstanceOf(AIConversation::class, $message->conversation);
        $this->assertEquals($this->conversation->id, $message->conversation->id);
    }

    #[Test]
    public function it_has_correct_accessors(): void
    {
        $message = AIMessageRecord::create([
            'ai_conversation_id' => $this->conversation->id,
            'role' => 'user',
            'content' => 'Test',
            'total_tokens' => 10,
            'cost' => 0.001,
        ]);

        $this->assertTrue($message->has_tokens);
        $this->assertTrue($message->has_cost);
        $this->assertTrue($message->is_user_message);
        $this->assertFalse($message->is_assistant_message);
        $this->assertFalse($message->is_system_message);
    }

    #[Test]
    public function it_identifies_message_roles_correctly(): void
    {
        $userMessage = AIMessageRecord::create([
            'ai_conversation_id' => $this->conversation->id,
            'role' => AIMessageRecord::ROLE_USER,
            'content' => 'User message',
        ]);

        $assistantMessage = AIMessageRecord::create([
            'ai_conversation_id' => $this->conversation->id,
            'role' => AIMessageRecord::ROLE_ASSISTANT,
            'content' => 'Assistant message',
        ]);

        $systemMessage = AIMessageRecord::create([
            'ai_conversation_id' => $this->conversation->id,
            'role' => AIMessageRecord::ROLE_SYSTEM,
            'content' => 'System message',
        ]);

        $this->assertTrue($userMessage->is_user_message);
        $this->assertFalse($userMessage->is_assistant_message);
        $this->assertFalse($userMessage->is_system_message);

        $this->assertFalse($assistantMessage->is_user_message);
        $this->assertTrue($assistantMessage->is_assistant_message);
        $this->assertFalse($assistantMessage->is_system_message);

        $this->assertFalse($systemMessage->is_user_message);
        $this->assertFalse($systemMessage->is_assistant_message);
        $this->assertTrue($systemMessage->is_system_message);
    }

    #[Test]
    public function it_can_scope_by_role(): void
    {
        AIMessageRecord::create([
            'ai_conversation_id' => $this->conversation->id,
            'role' => 'user',
            'content' => 'User message',
        ]);

        AIMessageRecord::create([
            'ai_conversation_id' => $this->conversation->id,
            'role' => 'assistant',
            'content' => 'Assistant message',
        ]);

        $userMessages = AIMessageRecord::byRole('user')->get();
        $assistantMessages = AIMessageRecord::assistantMessages()->get();

        $this->assertCount(1, $userMessages);
        $this->assertCount(1, $assistantMessages);
        $this->assertEquals('User message', $userMessages->first()->content);
        $this->assertEquals('Assistant message', $assistantMessages->first()->content);
    }

    #[Test]
    public function it_can_scope_by_content_type(): void
    {
        AIMessageRecord::create([
            'ai_conversation_id' => $this->conversation->id,
            'role' => 'user',
            'content' => 'Text message',
            'content_type' => AIMessageRecord::CONTENT_TYPE_TEXT,
        ]);

        AIMessageRecord::create([
            'ai_conversation_id' => $this->conversation->id,
            'role' => 'user',
            'content' => 'Image message',
            'content_type' => AIMessageRecord::CONTENT_TYPE_IMAGE,
        ]);

        $textMessages = AIMessageRecord::byContentType(AIMessageRecord::CONTENT_TYPE_TEXT)->get();
        $imageMessages = AIMessageRecord::byContentType(AIMessageRecord::CONTENT_TYPE_IMAGE)->get();

        $this->assertCount(1, $textMessages);
        $this->assertCount(1, $imageMessages);
    }

    #[Test]
    public function it_can_scope_messages_with_tokens(): void
    {
        AIMessageRecord::create([
            'ai_conversation_id' => $this->conversation->id,
            'role' => 'user',
            'content' => 'With tokens',
            'total_tokens' => 10,
        ]);

        AIMessageRecord::create([
            'ai_conversation_id' => $this->conversation->id,
            'role' => 'user',
            'content' => 'Without tokens',
        ]);

        $messagesWithTokens = AIMessageRecord::withTokens()->get();

        $this->assertCount(1, $messagesWithTokens);
        $this->assertEquals('With tokens', $messagesWithTokens->first()->content);
    }

    #[Test]
    public function it_can_scope_messages_with_cost(): void
    {
        AIMessageRecord::create([
            'ai_conversation_id' => $this->conversation->id,
            'role' => 'user',
            'content' => 'With cost',
            'cost' => 0.001,
        ]);

        AIMessageRecord::create([
            'ai_conversation_id' => $this->conversation->id,
            'role' => 'user',
            'content' => 'Without cost',
        ]);

        $messagesWithCost = AIMessageRecord::withCost()->get();

        $this->assertCount(1, $messagesWithCost);
        $this->assertEquals('With cost', $messagesWithCost->first()->content);
    }

    #[Test]
    public function it_can_scope_streaming_messages(): void
    {
        AIMessageRecord::create([
            'ai_conversation_id' => $this->conversation->id,
            'role' => 'assistant',
            'content' => 'Streaming message',
            'is_streaming' => true,
        ]);

        AIMessageRecord::create([
            'ai_conversation_id' => $this->conversation->id,
            'role' => 'assistant',
            'content' => 'Regular message',
            'is_streaming' => false,
        ]);

        $streamingMessages = AIMessageRecord::streaming()->get();

        $this->assertCount(1, $streamingMessages);
        $this->assertEquals('Streaming message', $streamingMessages->first()->content);
    }

    #[Test]
    public function it_can_scope_messages_with_errors(): void
    {
        AIMessageRecord::create([
            'ai_conversation_id' => $this->conversation->id,
            'role' => 'assistant',
            'content' => 'Error message',
            'error_code' => 'rate_limit',
        ]);

        AIMessageRecord::create([
            'ai_conversation_id' => $this->conversation->id,
            'role' => 'assistant',
            'content' => 'Success message',
        ]);

        $messagesWithErrors = AIMessageRecord::withErrors()->get();

        $this->assertCount(1, $messagesWithErrors);
        $this->assertEquals('Error message', $messagesWithErrors->first()->content);
    }

    #[Test]
    public function it_can_convert_to_ai_message_dto(): void
    {
        $messageRecord = AIMessageRecord::create([
            'ai_conversation_id' => $this->conversation->id,
            'role' => 'user',
            'content' => 'Test message',
            'content_type' => AIMessageRecord::CONTENT_TYPE_TEXT,
            'content_metadata' => ['key' => 'value'],
        ]);

        $aiMessage = $messageRecord->toAIMessage();

        $this->assertInstanceOf(AIMessage::class, $aiMessage);
        $this->assertEquals('Test message', $aiMessage->content);
        $this->assertEquals('user', $aiMessage->role);
        $this->assertEquals(AIMessageRecord::CONTENT_TYPE_TEXT, $aiMessage->contentType);
        $this->assertArrayHasKey('uuid', $aiMessage->metadata);
        $this->assertArrayHasKey('sequence_number', $aiMessage->metadata);
    }

    #[Test]
    public function it_can_create_from_ai_message_dto(): void
    {
        $aiMessage = AIMessage::user('Test message');
        $aiMessage->contentType = AIMessageRecord::CONTENT_TYPE_TEXT;
        $aiMessage->metadata = ['test' => 'data'];

        $messageRecord = AIMessageRecord::fromAIMessage($aiMessage, $this->conversation->id);

        $this->assertEquals($this->conversation->id, $messageRecord->ai_conversation_id);
        $this->assertEquals('user', $messageRecord->role);
        $this->assertEquals('Test message', $messageRecord->content);
        $this->assertEquals(AIMessageRecord::CONTENT_TYPE_TEXT, $messageRecord->content_type);
        $this->assertEquals(['test' => 'data'], $messageRecord->content_metadata);
    }

    #[Test]
    public function it_casts_attributes_correctly(): void
    {
        $message = AIMessageRecord::create([
            'ai_conversation_id' => $this->conversation->id,
            'role' => 'user',
            'content' => 'Test',
            'content_metadata' => ['key' => 'value'],
            'attachments' => ['file1.jpg', 'file2.pdf'],
            'is_streaming' => true,
            'cost' => 1.234567,
            'tool_calls' => [['name' => 'test_function']],
        ]);

        $this->assertIsArray($message->content_metadata);
        $this->assertIsArray($message->attachments);
        $this->assertIsBool($message->is_streaming);
        $this->assertEquals(1.234567, $message->cost);
        $this->assertIsArray($message->tool_calls);
    }

    #[Test]
    public function it_hides_sensitive_attributes(): void
    {
        $message = AIMessageRecord::create([
            'ai_conversation_id' => $this->conversation->id,
            'role' => 'user',
            'content' => 'Test',
            'ai_provider_id' => 1,
            'provider_message_id' => 'msg-123',
            'error_details' => ['error' => 'test'],
        ]);

        $array = $message->toArray();

        $this->assertArrayNotHasKey('ai_provider_id', $array);
        $this->assertArrayNotHasKey('provider_message_id', $array);
        $this->assertArrayNotHasKey('error_details', $array);
    }
}
