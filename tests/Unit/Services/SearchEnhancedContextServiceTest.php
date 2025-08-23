<?php

namespace JTD\LaravelAI\Tests\Unit\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use JTD\LaravelAI\Models\AIConversation;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIMessageRecord;
use JTD\LaravelAI\Services\ConversationSearchService;
use JTD\LaravelAI\Services\SearchEnhancedContextService;
use JTD\LaravelAI\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class SearchEnhancedContextServiceTest extends TestCase
{
    protected SearchEnhancedContextService $service;

    protected $mockSearchService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockSearchService = Mockery::mock(ConversationSearchService::class);
        $this->service = new SearchEnhancedContextService($this->mockSearchService);
    }

    #[Test]
    public function it_finds_relevant_context_for_favorite_color_question(): void
    {
        $conversation = AIConversation::factory()->create();
        $currentMessage = new AIMessage([
            'role' => 'user',
            'content' => 'What was my favorite color?',
        ]);

        // Mock historical message about favorite color
        $historicalMessage = AIMessageRecord::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'My favorite color is blue.',
            'created_at' => now()->subHours(2),
        ]);

        $searchResults = new LengthAwarePaginator(
            [$historicalMessage],
            1,
            10,
            1
        );

        $this->mockSearchService
            ->shouldReceive('searchMessages')
            ->with(Mockery::on(function ($criteria) {
                return isset($criteria['conversation_id']) &&
                       isset($criteria['search']) &&
                       str_contains($criteria['search'], 'favorite');
            }), 10)
            ->andReturn($searchResults);

        $result = $this->service->findRelevantContext($conversation, $currentMessage);

        $this->assertTrue($result['search_performed']);
        $this->assertGreaterThan(0, count($result['search_terms']));
        $this->assertContains('favorite', $result['search_terms']);
        $this->assertEquals(1, $result['total_found']);
        $this->assertNotEmpty($result['relevant_messages']);
    }

    #[Test]
    public function it_extracts_contextual_search_terms_from_various_patterns(): void
    {
        $testCases = [
            'What was my favorite color?' => ['favorite', 'color'],
            'Remember when we talked about dogs?' => ['dogs'],
            'We discussed programming languages' => ['programming', 'languages'],
            'You said something about databases' => ['databases'],
            'Earlier you mentioned React' => ['React'],
            'Tell me more about machine learning' => ['machine', 'learning'],
        ];

        foreach ($testCases as $content => $expectedTerms) {
            $message = new AIMessage(['role' => 'user', 'content' => $content]);
            $conversation = AIConversation::factory()->create();

            // Mock empty search results for this test
            $this->mockSearchService
                ->shouldReceive('searchMessages')
                ->andReturn(new LengthAwarePaginator([], 0, 10, 1));

            $result = $this->service->findRelevantContext($conversation, $message);

            $this->assertTrue($result['search_performed']);

            foreach ($expectedTerms as $term) {
                $this->assertContains(strtolower($term), array_map('strtolower', $result['search_terms']));
            }
        }
    }

    #[Test]
    public function it_calculates_contextual_relevance_scores(): void
    {
        $conversation = AIConversation::factory()->create();
        $currentMessage = new AIMessage([
            'role' => 'user',
            'content' => 'What was my favorite color?',
        ]);

        // High relevance message
        $highRelevanceMessage = AIMessageRecord::factory()->create([
            'role' => 'user',
            'content' => 'My favorite color is definitely blue.',
            'created_at' => now()->subHours(1),
        ]);

        // Low relevance message
        $lowRelevanceMessage = AIMessageRecord::factory()->create([
            'role' => 'assistant',
            'content' => 'The weather is nice today.',
            'created_at' => now()->subDays(7),
        ]);

        $searchResults = new LengthAwarePaginator(
            [$highRelevanceMessage, $lowRelevanceMessage],
            2,
            10,
            1
        );

        $this->mockSearchService
            ->shouldReceive('searchMessages')
            ->andReturn($searchResults);

        $result = $this->service->findRelevantContext($conversation, $currentMessage);

        $this->assertGreaterThan(0, count($result['relevance_scores']));

        // High relevance message should have higher score
        $highScore = $result['relevance_scores'][$highRelevanceMessage->id] ?? 0;
        $lowScore = $result['relevance_scores'][$lowRelevanceMessage->id] ?? 0;

        $this->assertGreaterThan($lowScore, $highScore);
    }

    #[Test]
    public function it_handles_no_search_terms_gracefully(): void
    {
        $conversation = AIConversation::factory()->create();
        $currentMessage = new AIMessage([
            'role' => 'user',
            'content' => 'Hello there!', // No contextual references
        ]);

        $result = $this->service->findRelevantContext($conversation, $currentMessage);

        $this->assertFalse($result['search_performed']);
        $this->assertEmpty($result['search_terms']);
        $this->assertEquals(0, $result['total_found']);
        $this->assertEmpty($result['relevant_messages']);
    }

    #[Test]
    public function it_provides_search_statistics(): void
    {
        $searchResult = [
            'search_performed' => true,
            'search_terms' => ['favorite', 'color'],
            'total_found' => 2,
            'relevance_scores' => [1 => 0.8, 2 => 0.6],
        ];

        $stats = $this->service->getSearchStatistics($searchResult);

        $this->assertTrue($stats['search_performed']);
        $this->assertEquals(2, $stats['search_terms_count']);
        $this->assertEquals(2, $stats['relevant_messages_found']);
        $this->assertEquals(0.7, $stats['avg_relevance_score']); // (0.8 + 0.6) / 2
        $this->assertEquals(0.8, $stats['max_relevance_score']);
        $this->assertEquals(0.6, $stats['min_relevance_score']);
        $this->assertEquals(['favorite', 'color'], $stats['search_terms']);
    }

    #[Test]
    public function it_handles_search_service_exceptions(): void
    {
        $conversation = AIConversation::factory()->create();
        $currentMessage = new AIMessage([
            'role' => 'user',
            'content' => 'What was my favorite color?',
        ]);

        $this->mockSearchService
            ->shouldReceive('searchMessages')
            ->andThrow(new \Exception('Search service error'));

        $result = $this->service->findRelevantContext($conversation, $currentMessage);

        $this->assertTrue($result['search_performed']);
        $this->assertNotEmpty($result['search_terms']);
        $this->assertEquals(0, $result['total_found']);
        $this->assertEmpty($result['relevant_messages']);
    }

    #[Test]
    public function it_filters_by_relevance_threshold(): void
    {
        $conversation = AIConversation::factory()->create();
        $currentMessage = new AIMessage([
            'role' => 'user',
            'content' => 'What was my favorite color?',
        ]);

        // Create messages with different relevance levels
        $highRelevanceMessage = AIMessageRecord::factory()->create([
            'role' => 'user',
            'content' => 'My favorite color is blue.',
        ]);

        $lowRelevanceMessage = AIMessageRecord::factory()->create([
            'role' => 'assistant',
            'content' => 'Color is interesting.',
        ]);

        $searchResults = new LengthAwarePaginator(
            [$highRelevanceMessage, $lowRelevanceMessage],
            2,
            10,
            1
        );

        $this->mockSearchService
            ->shouldReceive('searchMessages')
            ->andReturn($searchResults);

        // Test with high threshold
        $result = $this->service->findRelevantContext($conversation, $currentMessage, [
            'relevance_threshold' => 0.9,
        ]);

        // Should filter out low relevance messages
        $this->assertLessThanOrEqual(1, $result['total_found']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
