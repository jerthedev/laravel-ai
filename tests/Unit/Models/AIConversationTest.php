<?php

namespace JTD\LaravelAI\Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use JTD\LaravelAI\Models\AIConversation;
use JTD\LaravelAI\Models\AIMessageRecord;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AIConversationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_a_conversation_with_default_values(): void
    {
        $conversation = AIConversation::create([
            'title' => 'Test Conversation',
        ]);

        $this->assertNotNull($conversation->uuid);
        $this->assertEquals('Test Conversation', $conversation->title);
        $this->assertEquals(AIConversation::STATUS_ACTIVE, $conversation->status);
        $this->assertEquals('en', $conversation->language);
        $this->assertEquals(AIConversation::TYPE_CHAT, $conversation->conversation_type);
        $this->assertTrue($conversation->auto_title);
        $this->assertEquals(0, $conversation->total_cost);
        $this->assertEquals(0, $conversation->total_messages);
        $this->assertNotNull($conversation->last_activity_at);
    }

    #[Test]
    public function it_generates_uuid_automatically(): void
    {
        $conversation = AIConversation::create(['title' => 'Test']);

        $this->assertNotNull($conversation->uuid);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $conversation->uuid);
    }

    #[Test]
    public function it_uses_uuid_as_route_key(): void
    {
        $conversation = AIConversation::create(['title' => 'Test']);

        $this->assertEquals('uuid', $conversation->getRouteKeyName());
    }

    #[Test]
    public function it_can_have_messages(): void
    {
        $conversation = AIConversation::create(['title' => 'Test']);

        $message = AIMessageRecord::create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Hello',
            'sequence_number' => 1,
        ]);

        $this->assertCount(1, $conversation->messages);
        $this->assertEquals('Hello', $conversation->messages->first()->content);
    }

    #[Test]
    public function it_can_be_archived(): void
    {
        $conversation = AIConversation::create(['title' => 'Test']);

        $result = $conversation->archive();

        $this->assertTrue($result);
        $this->assertEquals(AIConversation::STATUS_ARCHIVED, $conversation->status);
        $this->assertNotNull($conversation->archived_at);
    }

    #[Test]
    public function it_can_be_restored_from_archive(): void
    {
        $conversation = AIConversation::create(['title' => 'Test']);
        $conversation->archive();

        $result = $conversation->restore();

        $this->assertTrue($result);
        $this->assertEquals(AIConversation::STATUS_ACTIVE, $conversation->status);
        $this->assertNull($conversation->archived_at);
    }

    #[Test]
    public function it_can_manage_tags(): void
    {
        $conversation = AIConversation::create(['title' => 'Test']);

        $conversation->addTag('important');
        $conversation->addTag('work');

        $this->assertTrue($conversation->hasTag('important'));
        $this->assertTrue($conversation->hasTag('work'));
        $this->assertFalse($conversation->hasTag('personal'));

        $conversation->removeTag('work');

        $this->assertFalse($conversation->hasTag('work'));
        $this->assertTrue($conversation->hasTag('important'));
    }

    #[Test]
    public function it_prevents_duplicate_tags(): void
    {
        $conversation = AIConversation::create(['title' => 'Test']);

        $conversation->addTag('test');
        $conversation->addTag('test'); // Duplicate

        $conversation->refresh();

        $this->assertCount(1, $conversation->tags);
        $this->assertEquals(['test'], $conversation->tags);
    }

    #[Test]
    public function it_calculates_total_tokens_accessor(): void
    {
        $conversation = AIConversation::create([
            'title' => 'Test',
            'total_input_tokens' => 100,
            'total_output_tokens' => 50,
        ]);

        $this->assertEquals(150, $conversation->total_tokens);
    }

    #[Test]
    public function it_calculates_success_rate_accessor(): void
    {
        $conversation = AIConversation::create([
            'title' => 'Test',
            'total_requests' => 10,
            'successful_requests' => 8,
        ]);

        $this->assertEquals(80.0, $conversation->success_rate);
    }

    #[Test]
    public function it_handles_zero_requests_for_success_rate(): void
    {
        $conversation = AIConversation::create(['title' => 'Test']);

        $this->assertEquals(0.0, $conversation->success_rate);
    }

    #[Test]
    public function it_has_is_active_accessor(): void
    {
        $activeConversation = AIConversation::create([
            'title' => 'Active',
            'status' => AIConversation::STATUS_ACTIVE,
        ]);

        $archivedConversation = AIConversation::create([
            'title' => 'Archived',
            'status' => AIConversation::STATUS_ARCHIVED,
        ]);

        $this->assertTrue($activeConversation->is_active);
        $this->assertFalse($archivedConversation->is_active);
    }

    #[Test]
    public function it_has_is_archived_accessor(): void
    {
        $activeConversation = AIConversation::create([
            'title' => 'Active',
            'status' => AIConversation::STATUS_ACTIVE,
        ]);

        $archivedConversation = AIConversation::create([
            'title' => 'Archived',
            'status' => AIConversation::STATUS_ARCHIVED,
        ]);

        $this->assertFalse($activeConversation->is_archived);
        $this->assertTrue($archivedConversation->is_archived);
    }

    #[Test]
    public function it_can_scope_active_conversations(): void
    {
        AIConversation::create(['title' => 'Active', 'status' => AIConversation::STATUS_ACTIVE]);
        AIConversation::create(['title' => 'Archived', 'status' => AIConversation::STATUS_ARCHIVED]);

        $activeConversations = AIConversation::active()->get();

        $this->assertCount(1, $activeConversations);
        $this->assertEquals('Active', $activeConversations->first()->title);
    }

    #[Test]
    public function it_can_scope_archived_conversations(): void
    {
        AIConversation::create(['title' => 'Active', 'status' => AIConversation::STATUS_ACTIVE]);
        AIConversation::create(['title' => 'Archived', 'status' => AIConversation::STATUS_ARCHIVED]);

        $archivedConversations = AIConversation::archived()->get();

        $this->assertCount(1, $archivedConversations);
        $this->assertEquals('Archived', $archivedConversations->first()->title);
    }

    #[Test]
    public function it_can_scope_by_user(): void
    {
        AIConversation::create(['title' => 'User 1', 'user_id' => 1, 'user_type' => 'App\\Models\\User']);
        AIConversation::create(['title' => 'User 2', 'user_id' => 2, 'user_type' => 'App\\Models\\User']);

        $userConversations = AIConversation::forUser(1, 'App\\Models\\User')->get();

        $this->assertCount(1, $userConversations);
        $this->assertEquals('User 1', $userConversations->first()->title);
    }

    #[Test]
    public function it_can_scope_by_session(): void
    {
        AIConversation::create(['title' => 'Session 1', 'session_id' => 'session-123']);
        AIConversation::create(['title' => 'Session 2', 'session_id' => 'session-456']);

        $sessionConversations = AIConversation::forSession('session-123')->get();

        $this->assertCount(1, $sessionConversations);
        $this->assertEquals('Session 1', $sessionConversations->first()->title);
    }

    #[Test]
    public function it_can_scope_by_conversation_type(): void
    {
        AIConversation::create(['title' => 'Chat', 'conversation_type' => AIConversation::TYPE_CHAT]);
        AIConversation::create(['title' => 'Analysis', 'conversation_type' => AIConversation::TYPE_ANALYSIS]);

        $chatConversations = AIConversation::ofType(AIConversation::TYPE_CHAT)->get();

        $this->assertCount(1, $chatConversations);
        $this->assertEquals('Chat', $chatConversations->first()->title);
    }

    #[Test]
    public function it_can_search_conversations(): void
    {
        AIConversation::create(['title' => 'Important Meeting', 'description' => 'Quarterly review']);
        AIConversation::create(['title' => 'Casual Chat', 'description' => 'Random discussion']);

        $searchResults = AIConversation::search('meeting')->get();

        $this->assertCount(1, $searchResults);
        $this->assertEquals('Important Meeting', $searchResults->first()->title);
    }

    #[Test]
    public function it_updates_last_activity_on_update(): void
    {
        $conversation = AIConversation::create(['title' => 'Test']);
        $originalActivity = $conversation->last_activity_at;

        // Wait a moment to ensure timestamp difference
        sleep(1);

        $conversation->update(['title' => 'Updated Test']);

        $this->assertNotEquals($originalActivity, $conversation->last_activity_at);
    }

    #[Test]
    public function it_casts_attributes_correctly(): void
    {
        $conversation = AIConversation::create([
            'title' => 'Test',
            'participants' => ['user1', 'user2'],
            'system_prompt' => ['role' => 'system', 'content' => 'You are helpful'],
            'configuration' => ['temperature' => 0.7],
            'tags' => ['important', 'work'],
            'auto_title' => true,
            'total_cost' => 1.234567,
        ]);

        $this->assertIsArray($conversation->participants);
        $this->assertIsArray($conversation->system_prompt);
        $this->assertIsArray($conversation->configuration);
        $this->assertIsArray($conversation->tags);
        $this->assertIsBool($conversation->auto_title);
        $this->assertEquals(1.234567, $conversation->total_cost);
    }

    #[Test]
    public function it_hides_sensitive_attributes(): void
    {
        $conversation = AIConversation::create([
            'title' => 'Test',
            'user_id' => 123,
            'user_type' => 'App\\Models\\User',
            'session_id' => 'session-123',
        ]);

        $array = $conversation->toArray();

        $this->assertArrayNotHasKey('user_id', $array);
        $this->assertArrayNotHasKey('user_type', $array);
        $this->assertArrayNotHasKey('session_id', $array);
    }

    #[Test]
    public function it_appends_computed_attributes(): void
    {
        $conversation = AIConversation::create([
            'title' => 'Test',
            'total_input_tokens' => 100,
            'total_output_tokens' => 50,
            'total_requests' => 10,
            'successful_requests' => 8,
            'status' => AIConversation::STATUS_ACTIVE,
        ]);

        $array = $conversation->toArray();

        $this->assertArrayHasKey('total_tokens', $array);
        $this->assertArrayHasKey('success_rate', $array);
        $this->assertArrayHasKey('is_active', $array);
        $this->assertArrayHasKey('is_archived', $array);

        $this->assertEquals(150, $array['total_tokens']);
        $this->assertEquals(80.0, $array['success_rate']);
        $this->assertTrue($array['is_active']);
        $this->assertFalse($array['is_archived']);
    }

    #[Test]
    public function it_can_filter_recent_conversations(): void
    {
        // Create old conversation with explicit old timestamp
        $oldConversation = AIConversation::create([
            'title' => 'Old',
            'last_activity_at' => now()->subDays(10),
        ]);

        // Create recent conversation with explicit recent timestamp
        $recentConversation = AIConversation::create([
            'title' => 'Recent',
            'last_activity_at' => now()->subHours(1),
        ]);

        $recentConversations = AIConversation::recent(7)->get();

        $this->assertCount(1, $recentConversations);
        $this->assertEquals('Recent', $recentConversations->first()->title);
    }
}
