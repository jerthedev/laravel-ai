<?php

namespace JTD\LaravelAI\Tests\Unit\Services;

use JTD\LaravelAI\Models\AIConversation;
use JTD\LaravelAI\Models\AIMessageRecord;
use JTD\LaravelAI\Models\AIProviderModel;
use JTD\LaravelAI\Services\ConversationContextManager;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ConversationContextManagerTest extends TestCase
{
    protected ConversationContextManager $contextManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->contextManager = app(ConversationContextManager::class);
    }

    #[Test]
    public function it_preserves_full_context_when_within_limits(): void
    {
        $conversation = AIConversation::factory()->create();
        $model = AIProviderModel::factory()->create(['context_length' => 4096]);

        // Create messages that fit within context window
        AIMessageRecord::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'system',
            'content' => 'You are a helpful assistant.',
            'sequence_number' => 1,
            'total_tokens' => 10,
        ]);

        AIMessageRecord::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Hello!',
            'sequence_number' => 2,
            'total_tokens' => 5,
        ]);

        AIMessageRecord::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Hi there! How can I help you?',
            'sequence_number' => 3,
            'total_tokens' => 15,
        ]);

        $result = $this->contextManager->preserveContextForSwitch($conversation, $model);

        $this->assertFalse($result['truncated']);
        $this->assertEquals(30, $result['total_tokens']);
        $this->assertCount(3, $result['messages']);
        $this->assertEquals('full_context', $result['preservation_strategy']);
    }

    #[Test]
    public function it_truncates_context_when_exceeding_limits(): void
    {
        $conversation = AIConversation::factory()->create();
        $model = AIProviderModel::factory()->create(['context_length' => 100]); // Small context window

        // Create messages that exceed context window
        for ($i = 1; $i <= 10; $i++) {
            AIMessageRecord::factory()->create([
                'ai_conversation_id' => $conversation->id,
                'role' => $i % 2 === 1 ? 'user' : 'assistant',
                'content' => "Message {$i} with some content",
                'sequence_number' => $i,
                'total_tokens' => 20, // Total: 200 tokens, exceeds 80% of 100
            ]);
        }

        $result = $this->contextManager->preserveContextForSwitch($conversation, $model, [
            'preservation_strategy' => 'recent_messages',
        ]);

        $this->assertTrue($result['truncated']);
        $this->assertLessThanOrEqual(80, $result['total_tokens']); // 80% of context window
        $this->assertLessThan(10, $result['preserved_count']);
        $this->assertEquals('recent_messages', $result['preservation_strategy']);
    }

    #[Test]
    public function it_preserves_system_messages_with_recent_strategy(): void
    {
        $conversation = AIConversation::factory()->create();
        $model = AIProviderModel::factory()->create(['context_length' => 100]);

        // Create system message
        AIMessageRecord::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'system',
            'content' => 'You are a helpful assistant.',
            'sequence_number' => 1,
            'total_tokens' => 10,
        ]);

        // Create many user/assistant messages
        for ($i = 2; $i <= 20; $i++) {
            AIMessageRecord::factory()->create([
                'ai_conversation_id' => $conversation->id,
                'role' => $i % 2 === 0 ? 'user' : 'assistant',
                'content' => "Message {$i}",
                'sequence_number' => $i,
                'total_tokens' => 10,
            ]);
        }

        $result = $this->contextManager->preserveContextForSwitch($conversation, $model, [
            'preservation_strategy' => 'recent_messages',
        ]);

        // System message should be preserved
        $systemMessages = array_filter($result['messages'], function ($msg) {
            return $msg['role'] === 'system';
        });

        $this->assertCount(1, $systemMessages);
        $this->assertTrue($result['truncated']);
    }

    #[Test]
    public function it_prioritizes_important_messages(): void
    {
        $conversation = AIConversation::factory()->create();
        $model = AIProviderModel::factory()->create(['context_length' => 60]); // Smaller to force truncation

        // Create mixed message types
        AIMessageRecord::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'system',
            'content' => 'System message',
            'sequence_number' => 1,
            'total_tokens' => 20,
        ]);

        AIMessageRecord::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'User question',
            'sequence_number' => 2,
            'total_tokens' => 20,
        ]);

        AIMessageRecord::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Assistant response',
            'sequence_number' => 3,
            'total_tokens' => 20,
        ]);

        AIMessageRecord::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'function',
            'content' => 'Function call result',
            'sequence_number' => 4,
            'total_tokens' => 20,
        ]);

        $result = $this->contextManager->preserveContextForSwitch($conversation, $model, [
            'preservation_strategy' => 'important_messages',
        ]);

        // Should preserve system and user messages first
        $roles = array_column($result['messages'], 'role');
        $this->assertContains('system', $roles);
        $this->assertContains('user', $roles);
        $this->assertTrue($result['truncated']);
    }

    #[Test]
    public function it_creates_summary_for_older_messages(): void
    {
        $conversation = AIConversation::factory()->create();
        $model = AIProviderModel::factory()->create(['context_length' => 200]);

        // Create many messages
        for ($i = 1; $i <= 15; $i++) {
            AIMessageRecord::factory()->create([
                'ai_conversation_id' => $conversation->id,
                'role' => $i % 2 === 1 ? 'user' : 'assistant',
                'content' => "Message {$i} about topic {$i}",
                'sequence_number' => $i,
                'total_tokens' => 20,
            ]);
        }

        $result = $this->contextManager->preserveContextForSwitch($conversation, $model, [
            'preservation_strategy' => 'summarized_context',
        ]);

        // Should have a summary message
        $systemMessages = array_filter($result['messages'], function ($msg) {
            return $msg['role'] === 'system' && strpos($msg['content'], 'Previous conversation summary') !== false;
        });

        $this->assertCount(1, $systemMessages);
        $this->assertTrue($result['summary_created']);
        $this->assertEquals('summarized_context', $result['preservation_strategy']);
    }

    #[Test]
    public function it_uses_intelligent_truncation_by_default(): void
    {
        $conversation = AIConversation::factory()->create();
        $model = AIProviderModel::factory()->create(['context_length' => 150]);

        // Create system message
        AIMessageRecord::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'system',
            'content' => 'System prompt',
            'sequence_number' => 1,
            'total_tokens' => 10,
        ]);

        // Create conversation pairs
        for ($i = 1; $i <= 10; $i++) {
            AIMessageRecord::factory()->create([
                'ai_conversation_id' => $conversation->id,
                'role' => 'user',
                'content' => "User message {$i}",
                'sequence_number' => $i * 2,
                'total_tokens' => 15,
            ]);

            AIMessageRecord::factory()->create([
                'ai_conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => "Assistant response {$i}",
                'sequence_number' => $i * 2 + 1,
                'total_tokens' => 15,
            ]);
        }

        $result = $this->contextManager->preserveContextForSwitch($conversation, $model);

        $this->assertEquals('intelligent_truncation', $result['preservation_strategy']);
        $this->assertTrue($result['truncated']);
        $this->assertArrayHasKey('conversation_pairs_preserved', $result);

        // System message should be preserved
        $systemMessages = array_filter($result['messages'], function ($msg) {
            return $msg['role'] === 'system';
        });
        $this->assertCount(1, $systemMessages);
    }

    #[Test]
    public function it_validates_context_preservation_result(): void
    {
        $validResult = [
            'messages' => [
                ['role' => 'user', 'content' => 'Hello'],
            ],
            'total_tokens' => 50,
            'truncated' => false,
        ];

        $this->assertTrue($this->contextManager->validateContextPreservation($validResult, 100));

        $invalidResult = [
            'messages' => [],
            'total_tokens' => 150,
            'truncated' => false,
        ];

        $this->assertFalse($this->contextManager->validateContextPreservation($invalidResult, 100));
    }

    public static function preservationStrategyProvider(): array
    {
        return [
            'recent_messages' => ['recent_messages'],
            'important_messages' => ['important_messages'],
            'summarized_context' => ['summarized_context'],
            'intelligent_truncation' => ['intelligent_truncation'],
        ];
    }

    #[Test]
    #[DataProvider('preservationStrategyProvider')]
    public function it_handles_different_preservation_strategies(string $strategy): void
    {
        $conversation = AIConversation::factory()->create();
        $model = AIProviderModel::factory()->create(['context_length' => 100]);

        // Create messages that will require truncation
        for ($i = 1; $i <= 10; $i++) {
            AIMessageRecord::factory()->create([
                'ai_conversation_id' => $conversation->id,
                'role' => $i % 2 === 1 ? 'user' : 'assistant',
                'content' => "Message {$i}",
                'sequence_number' => $i,
                'total_tokens' => 20,
            ]);
        }

        $result = $this->contextManager->preserveContextForSwitch($conversation, $model, [
            'preservation_strategy' => $strategy,
        ]);

        $this->assertEquals($strategy, $result['preservation_strategy']);
        $this->assertIsArray($result['messages']);
        $this->assertIsInt($result['total_tokens']);
        $this->assertIsBool($result['truncated']);
        $this->assertLessThanOrEqual(80, $result['total_tokens']); // Within 80% of context window
    }

    #[Test]
    public function it_handles_empty_conversation(): void
    {
        $conversation = AIConversation::factory()->create();
        $model = AIProviderModel::factory()->create(['context_length' => 4096]);

        $result = $this->contextManager->preserveContextForSwitch($conversation, $model);

        $this->assertEmpty($result['messages']);
        $this->assertEquals(0, $result['total_tokens']);
        $this->assertFalse($result['truncated']);
        $this->assertEquals('full_context', $result['preservation_strategy']);
    }

    #[Test]
    public function it_respects_context_ratio_option(): void
    {
        $conversation = AIConversation::factory()->create();
        $model = AIProviderModel::factory()->create(['context_length' => 1000]);

        // Create messages
        for ($i = 1; $i <= 10; $i++) {
            AIMessageRecord::factory()->create([
                'ai_conversation_id' => $conversation->id,
                'role' => $i % 2 === 1 ? 'user' : 'assistant',
                'content' => "Message {$i}",
                'sequence_number' => $i,
                'total_tokens' => 100, // Total: 1000 tokens
            ]);
        }

        // Use 50% context ratio instead of default 80%
        $result = $this->contextManager->preserveContextForSwitch($conversation, $model, [
            'context_ratio' => 0.5,
        ]);

        $this->assertLessThanOrEqual(500, $result['total_tokens']); // 50% of context window
        $this->assertTrue($result['truncated']);
    }

    #[Test]
    public function it_builds_intelligent_context_with_search_enhancement(): void
    {
        $conversation = AIConversation::factory()->create();

        // Create historical messages
        AIMessageRecord::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'My favorite color is blue.',
            'sequence_number' => 1,
            'total_tokens' => 10,
        ]);

        AIMessageRecord::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'That\'s a nice color choice!',
            'sequence_number' => 2,
            'total_tokens' => 8,
        ]);

        // Create current message that references previous context
        $currentMessage = new \JTD\LaravelAI\Models\AIMessage([
            'role' => 'user',
            'content' => 'What was my favorite color?',
        ]);

        $result = $this->contextManager->buildIntelligentContext($conversation, $currentMessage);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('messages', $result);
        $this->assertArrayHasKey('total_tokens', $result);
        $this->assertArrayHasKey('preservation_strategy', $result);
    }

    #[Test]
    public function it_optimizes_context_for_token_efficiency(): void
    {
        $contextResult = [
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'This  is   a    test   message   with   excessive   whitespace and very redundant words.',
                    'sequence_number' => 1,
                ],
            ],
            'total_tokens' => 100,
        ];

        $optimized = $this->contextManager->optimizeContext($contextResult, [
            'optimization_level' => 'balanced',
        ]);

        $this->assertTrue($optimized['optimization_applied']);
        $this->assertEquals('balanced', $optimized['optimization_level']);
        $this->assertLessThan(100, $optimized['total_tokens']);
        $this->assertGreaterThan(0, $optimized['tokens_saved']);
    }

    #[Test]
    public function it_creates_preservation_markers_for_important_content(): void
    {
        $conversation = AIConversation::factory()->create();

        $systemMessage = AIMessageRecord::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'system',
            'content' => 'You are a helpful assistant.',
            'sequence_number' => 1,
        ]);

        $questionMessage = AIMessageRecord::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'What is machine learning?',
            'sequence_number' => 2,
        ]);

        $codeMessage = AIMessageRecord::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Here is a code example: ```python\nprint("hello")\n```',
            'sequence_number' => 3,
        ]);

        $messages = collect([$systemMessage, $questionMessage, $codeMessage]);
        $markers = $this->contextManager->createPreservationMarkers($messages);

        $this->assertIsArray($markers);
        $this->assertArrayHasKey($systemMessage->id, $markers);
        $this->assertContains('system_message', $markers[$systemMessage->id]);

        $this->assertArrayHasKey($questionMessage->id, $markers);
        $this->assertContains('question', $markers[$questionMessage->id]);

        $this->assertArrayHasKey($codeMessage->id, $markers);
        $this->assertContains('code_content', $markers[$codeMessage->id]);
    }

    #[Test]
    public function it_provides_middleware_integration_hooks(): void
    {
        $conversation = AIConversation::factory()->create();
        $message = new \JTD\LaravelAI\Models\AIMessage([
            'role' => 'user',
            'content' => 'What was my favorite color?',
        ]);

        // Test buildContextForMiddleware
        $result = $this->contextManager->buildContextForMiddleware($conversation, $message);
        $this->assertIsArray($result);

        // Test shouldInjectContext
        $shouldInject = $this->contextManager->shouldInjectContext($message);
        $this->assertTrue($shouldInject); // Should inject for questions referencing context

        // Test formatContextForInjection
        $contextResult = [
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'My favorite color is blue.',
                    'sequence_number' => 1,
                ],
            ],
        ];
        $formatted = $this->contextManager->formatContextForInjection($contextResult);
        $this->assertIsString($formatted);
        $this->assertStringContainsString('Relevant conversation context:', $formatted);
    }

    #[Test]
    public function it_handles_configurable_context_windows(): void
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

        $windowSize = $this->contextManager->getContextWindow($conversation);
        $this->assertEquals(8192, $windowSize);
    }

    #[Test]
    public function it_performs_advanced_intelligent_truncation(): void
    {
        $conversation = AIConversation::factory()->create();

        // Create mix of message types
        $messages = collect([
            AIMessageRecord::factory()->create([
                'ai_conversation_id' => $conversation->id,
                'role' => 'system',
                'content' => 'You are a helpful assistant.',
                'sequence_number' => 1,
                'total_tokens' => 10,
            ]),
            AIMessageRecord::factory()->create([
                'ai_conversation_id' => $conversation->id,
                'role' => 'user',
                'content' => 'This is an important question about machine learning.',
                'sequence_number' => 2,
                'total_tokens' => 15,
            ]),
            AIMessageRecord::factory()->create([
                'ai_conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => 'Here is a detailed explanation...',
                'sequence_number' => 3,
                'total_tokens' => 20,
            ]),
        ]);

        $result = $this->contextManager->advancedIntelligentTruncation($messages, 30);

        $this->assertIsArray($result);
        $this->assertEquals('advanced_intelligent_truncation', $result['preservation_strategy']);
        $this->assertLessThanOrEqual(30, $result['total_tokens']);
        $this->assertArrayHasKey('avg_importance_score', $result);
    }
}
