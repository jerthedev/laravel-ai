<?php

namespace JTD\LaravelAI\Tests\Unit\Services;

use JTD\LaravelAI\Models\AIConversation;
use JTD\LaravelAI\Models\AIMessageRecord;
use JTD\LaravelAI\Services\ContextPreservationService;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ContextPreservationServiceTest extends TestCase
{
    protected ContextPreservationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ContextPreservationService;
    }

    #[Test]
    public function it_creates_preservation_markers_for_system_messages(): void
    {
        $conversation = AIConversation::factory()->create();
        $systemMessage = AIMessageRecord::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'system',
            'content' => 'You are a helpful assistant.',
            'sequence_number' => 1,
        ]);

        $messages = collect([$systemMessage]);
        $markers = $this->service->createPreservationMarkers($messages);

        $this->assertArrayHasKey($systemMessage->id, $markers);
        $this->assertContains('system_message', $markers[$systemMessage->id]['markers']);
        $this->assertGreaterThan(0, $markers[$systemMessage->id]['priority_score']);
        $this->assertStringContainsString('System instruction', $markers[$systemMessage->id]['preservation_reason']);
    }

    #[Test]
    public function it_identifies_important_content_markers(): void
    {
        $conversation = AIConversation::factory()->create();
        $importantMessage = AIMessageRecord::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'This is very important: remember my password is secret123.',
            'sequence_number' => 1,
        ]);

        $messages = collect([$importantMessage]);
        $markers = $this->service->createPreservationMarkers($messages);

        $this->assertArrayHasKey($importantMessage->id, $markers);
        $this->assertContains('important_content', $markers[$importantMessage->id]['markers']);
    }

    #[Test]
    public function it_identifies_question_markers(): void
    {
        $conversation = AIConversation::factory()->create();
        $questionMessage = AIMessageRecord::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'What is machine learning?',
            'sequence_number' => 1,
        ]);

        $messages = collect([$questionMessage]);
        $markers = $this->service->createPreservationMarkers($messages);

        $this->assertArrayHasKey($questionMessage->id, $markers);
        $this->assertContains('question', $markers[$questionMessage->id]['markers']);
    }

    #[Test]
    public function it_identifies_code_content_markers(): void
    {
        $conversation = AIConversation::factory()->create();
        $codeMessage = AIMessageRecord::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Here is a Python function: ```python\ndef hello():\n    print("Hello")\n```',
            'sequence_number' => 1,
        ]);

        $messages = collect([$codeMessage]);
        $markers = $this->service->createPreservationMarkers($messages);

        $this->assertArrayHasKey($codeMessage->id, $markers);
        $this->assertContains('code_content', $markers[$codeMessage->id]['markers']);
    }

    #[Test]
    public function it_identifies_user_preference_markers(): void
    {
        $conversation = AIConversation::factory()->create();
        $preferenceMessage = AIMessageRecord::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'My favorite programming language is Python.',
            'sequence_number' => 1,
        ]);

        $messages = collect([$preferenceMessage]);
        $markers = $this->service->createPreservationMarkers($messages);

        $this->assertArrayHasKey($preferenceMessage->id, $markers);
        $this->assertContains('user_preference', $markers[$preferenceMessage->id]['markers']);
    }

    #[Test]
    public function it_identifies_error_and_solution_markers(): void
    {
        $conversation = AIConversation::factory()->create();

        $errorMessage = AIMessageRecord::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'I\'m getting an error when running my code.',
            'sequence_number' => 1,
        ]);

        $solutionMessage = AIMessageRecord::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Here\'s the solution to fix that error.',
            'sequence_number' => 2,
        ]);

        $messages = collect([$errorMessage, $solutionMessage]);
        $markers = $this->service->createPreservationMarkers($messages);

        $this->assertContains('error_or_problem', $markers[$errorMessage->id]['markers']);
        $this->assertContains('solution', $markers[$solutionMessage->id]['markers']);
    }

    #[Test]
    public function it_analyzes_conversation_flow(): void
    {
        $conversation = AIConversation::factory()->create();

        $userQuestion = AIMessageRecord::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'What is PHP?',
            'sequence_number' => 1,
        ]);

        $assistantAnswer = AIMessageRecord::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'PHP is a programming language.',
            'sequence_number' => 2,
        ]);

        $followUpQuestion = AIMessageRecord::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Also, what about JavaScript?',
            'sequence_number' => 3,
        ]);

        $messages = collect([$userQuestion, $assistantAnswer, $followUpQuestion]);
        $markers = $this->service->createPreservationMarkers($messages);

        // Check for Q&A pair markers
        $this->assertContains('question_in_pair', $markers[$userQuestion->id]['markers']);
        $this->assertContains('answer_in_pair', $markers[$assistantAnswer->id]['markers']);

        // Check for follow-up question marker
        $this->assertContains('follow_up_question', $markers[$followUpQuestion->id]['markers']);
    }

    #[Test]
    public function it_calculates_priority_scores_correctly(): void
    {
        $conversation = AIConversation::factory()->create();

        $systemMessage = AIMessageRecord::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'system',
            'content' => 'You are a helpful assistant.',
            'sequence_number' => 1,
        ]);

        $regularMessage = AIMessageRecord::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Hello there.',
            'sequence_number' => 2,
        ]);

        $messages = collect([$systemMessage, $regularMessage]);
        $markers = $this->service->createPreservationMarkers($messages);

        // System message should have higher priority
        $systemScore = $markers[$systemMessage->id]['priority_score'];
        $regularScore = $markers[$regularMessage->id]['priority_score'] ?? 0;

        $this->assertGreaterThan($regularScore, $systemScore);
    }

    #[Test]
    public function it_filters_messages_by_preservation_markers(): void
    {
        $conversation = AIConversation::factory()->create();

        $systemMessage = AIMessageRecord::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'system',
            'content' => 'You are a helpful assistant.',
            'sequence_number' => 1,
        ]);

        $regularMessage = AIMessageRecord::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Hello.',
            'sequence_number' => 2,
        ]);

        $messages = collect([$systemMessage, $regularMessage]);
        $markers = $this->service->createPreservationMarkers($messages);

        // Filter for system messages only
        $filtered = $this->service->filterByPreservationMarkers(
            $messages,
            $markers,
            ['system_message']
        );

        $this->assertEquals(1, $filtered->count());
        $this->assertEquals($systemMessage->id, $filtered->first()->id);
    }

    #[Test]
    public function it_filters_by_minimum_priority_score(): void
    {
        $conversation = AIConversation::factory()->create();

        $highPriorityMessage = AIMessageRecord::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'system',
            'content' => 'Important system message.',
            'sequence_number' => 1,
        ]);

        $lowPriorityMessage = AIMessageRecord::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Hi.',
            'sequence_number' => 2,
        ]);

        $messages = collect([$highPriorityMessage, $lowPriorityMessage]);
        $markers = $this->service->createPreservationMarkers($messages);

        // Filter with high minimum score
        $filtered = $this->service->filterByPreservationMarkers(
            $messages,
            $markers,
            [],
            0.8 // High threshold
        );

        // Should only include high priority messages
        $this->assertLessThanOrEqual(1, $filtered->count());

        if ($filtered->count() > 0) {
            $this->assertEquals($highPriorityMessage->id, $filtered->first()->id);
        }
    }

    #[Test]
    public function it_identifies_context_reference_markers(): void
    {
        $conversation = AIConversation::factory()->create();
        $contextMessage = AIMessageRecord::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'As we discussed earlier, I need help with that problem.',
            'sequence_number' => 1,
        ]);

        $messages = collect([$contextMessage]);
        $markers = $this->service->createPreservationMarkers($messages);

        $this->assertArrayHasKey($contextMessage->id, $markers);
        $this->assertContains('context_reference', $markers[$contextMessage->id]['markers']);
    }

    #[Test]
    public function it_identifies_detailed_content_markers(): void
    {
        $conversation = AIConversation::factory()->create();
        $detailedMessage = AIMessageRecord::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => str_repeat('This is a very detailed explanation with lots of information. ', 20),
            'sequence_number' => 1,
        ]);

        $messages = collect([$detailedMessage]);
        $markers = $this->service->createPreservationMarkers($messages);

        $this->assertArrayHasKey($detailedMessage->id, $markers);
        $this->assertContains('detailed_content', $markers[$detailedMessage->id]['markers']);
    }

    #[Test]
    public function it_identifies_recent_message_markers(): void
    {
        $conversation = AIConversation::factory()->create();
        $recentMessage = AIMessageRecord::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'This is a recent message.',
            'sequence_number' => 1,
            'created_at' => now()->subMinutes(30), // Recent
        ]);

        $messages = collect([$recentMessage]);
        $markers = $this->service->createPreservationMarkers($messages);

        $this->assertArrayHasKey($recentMessage->id, $markers);
        $this->assertContains('recent', $markers[$recentMessage->id]['markers']);
    }
}
