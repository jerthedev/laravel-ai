<?php

namespace JTD\LaravelAI\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Models\AIConversation;
use JTD\LaravelAI\Models\AIMessage;

/**
 * Search-Enhanced Context Service
 *
 * Specialized service for retrieving contextually relevant historical messages
 * using conversation search capabilities. This implements the core feature where
 * asking about "favorite color" will find and preserve the relevant historical message.
 */
class SearchEnhancedContextService
{
    /**
     * The conversation search service instance.
     */
    protected ConversationSearchService $searchService;

    /**
     * Create a new search-enhanced context service instance.
     */
    public function __construct(ConversationSearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * Find contextually relevant messages for the current query.
     *
     * This is the main method that implements the "favorite color" use case.
     */
    public function findRelevantContext(
        AIConversation $conversation,
        AIMessage $currentMessage,
        array $options = []
    ): array {
        $searchTerms = $this->extractContextualSearchTerms($currentMessage->content);

        if (empty($searchTerms)) {
            return [
                'relevant_messages' => collect(),
                'search_terms' => [],
                'search_performed' => false,
                'relevance_scores' => [],
            ];
        }

        $relevantMessages = collect();
        $relevanceScores = [];
        $allSearchTerms = [];

        // Search for each term and combine results
        foreach ($searchTerms as $termGroup) {
            $searchResults = $this->searchForTerm($conversation, $termGroup, $options);

            foreach ($searchResults as $message) {
                $messageId = $message->id;
                $score = $this->calculateContextualRelevance($message, $currentMessage, $termGroup);

                if ($score >= ($options['relevance_threshold'] ?? 0.7)) {
                    if (! $relevantMessages->contains('id', $messageId)) {
                        $relevantMessages->push($message);
                    }

                    // Keep the highest score for each message
                    if (! isset($relevanceScores[$messageId]) || $relevanceScores[$messageId] < $score) {
                        $relevanceScores[$messageId] = $score;
                    }
                }
            }

            $allSearchTerms = array_merge($allSearchTerms, $termGroup);
        }

        // Sort by relevance score
        $sortedMessages = $relevantMessages->sortBy(function ($message) use ($relevanceScores) {
            return -$relevanceScores[$message->id]; // Negative for descending sort
        });

        Log::info('Search-enhanced context retrieval completed', [
            'conversation_id' => $conversation->id,
            'current_message_preview' => substr($currentMessage->content, 0, 100),
            'search_terms' => $allSearchTerms,
            'relevant_messages_found' => $sortedMessages->count(),
            'avg_relevance_score' => ! empty($relevanceScores) ? array_sum($relevanceScores) / count($relevanceScores) : 0,
        ]);

        return [
            'relevant_messages' => $sortedMessages,
            'search_terms' => array_unique($allSearchTerms),
            'search_performed' => true,
            'relevance_scores' => $relevanceScores,
            'total_found' => $sortedMessages->count(),
        ];
    }

    /**
     * Extract contextual search terms from the current message.
     *
     * This method identifies when a user is referencing previous conversation topics.
     */
    protected function extractContextualSearchTerms(string $content): array
    {
        $content = strtolower($content);
        $searchTermGroups = [];

        // Pattern 1: Direct references to previous topics
        // "What was my favorite color?" -> ["favorite", "color"]
        if (preg_match('/what.*was.*my.*(\w+).*(\w+)?/', $content, $matches)) {
            $terms = array_filter([$matches[1], $matches[2] ?? null]);
            if (! empty($terms)) {
                $searchTermGroups[] = $terms;
            }
        }

        // Pattern 2: Remember/recall patterns
        // "Remember when we talked about dogs?" -> ["dogs"]
        if (preg_match('/remember.*(?:when|we|about).*?(\w+)/', $content, $matches)) {
            $searchTermGroups[] = [$matches[1]];
        }

        // Pattern 3: Previous discussion references
        // "We discussed programming languages" -> ["programming", "languages"]
        if (preg_match('/we.*(?:discussed|talked about|mentioned).*?(\w+)(?:\s+(\w+))?/', $content, $matches)) {
            $terms = array_filter([$matches[1], $matches[2] ?? null]);
            if (! empty($terms)) {
                $searchTermGroups[] = $terms;
            }
        }

        // Pattern 4: "You said" references
        // "You said something about databases" -> ["databases"]
        if (preg_match('/you.*said.*(?:about|regarding).*?(\w+)/', $content, $matches)) {
            $searchTermGroups[] = [$matches[1]];
        }

        // Pattern 5: Earlier mention patterns
        // "Earlier you mentioned React" -> ["React"]
        if (preg_match('/earlier.*(?:mentioned|said).*?(\w+)/', $content, $matches)) {
            $searchTermGroups[] = [$matches[1]];
        }

        // Pattern 6: Topic continuation
        // "Tell me more about machine learning" -> ["machine", "learning"]
        if (preg_match('/(?:tell me more|more about|continue|expand on).*?(\w+)(?:\s+(\w+))?/', $content, $matches)) {
            $terms = array_filter([$matches[1], $matches[2] ?? null]);
            if (! empty($terms)) {
                $searchTermGroups[] = $terms;
            }
        }

        // Pattern 7: Specific entity references
        // Extract quoted terms, capitalized words, or technical terms
        if (preg_match_all('/"([^"]+)"|\'([^\']+)\'/', $content, $matches)) {
            foreach ($matches[1] as $quoted) {
                if (! empty($quoted)) {
                    $searchTermGroups[] = explode(' ', $quoted);
                }
            }
        }

        // Pattern 8: Important nouns (fallback)
        if (empty($searchTermGroups)) {
            $words = str_word_count($content, 1);
            $importantWords = array_filter($words, function ($word) {
                return strlen($word) > 3 &&
                       ! in_array($word, ['what', 'when', 'where', 'how', 'why', 'this', 'that', 'they', 'them', 'with', 'from', 'about']);
            });

            if (! empty($importantWords)) {
                $searchTermGroups[] = array_slice($importantWords, 0, 3);
            }
        }

        return $searchTermGroups;
    }

    /**
     * Search for messages containing specific terms.
     */
    protected function searchForTerm(AIConversation $conversation, array $terms, array $options = []): Collection
    {
        $searchQuery = implode(' ', $terms);
        $limit = $options['search_limit'] ?? 10;

        try {
            $searchCriteria = [
                'conversation_id' => $conversation->id,
                'search' => $searchQuery,
            ];

            $searchResults = $this->searchService->searchMessages($searchCriteria, $limit);

            return collect($searchResults->items());
        } catch (\Exception $e) {
            Log::warning('Failed to search for contextual terms', [
                'conversation_id' => $conversation->id,
                'terms' => $terms,
                'error' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * Calculate contextual relevance between messages.
     */
    protected function calculateContextualRelevance($historicalMessage, AIMessage $currentMessage, array $searchTerms): float
    {
        $score = 0.0;
        $historicalContent = strtolower($historicalMessage->content);
        $currentContent = strtolower($currentMessage->content);

        // Base score for containing search terms
        $termMatches = 0;
        foreach ($searchTerms as $term) {
            if (str_contains($historicalContent, strtolower($term))) {
                $termMatches++;
            }
        }

        if (! empty($searchTerms)) {
            $score += ($termMatches / count($searchTerms)) * 0.5; // Up to 0.5 points
        }

        // Boost for exact phrase matches
        $searchPhrase = implode(' ', $searchTerms);
        if (str_contains($historicalContent, $searchPhrase)) {
            $score += 0.3;
        }

        // Role-based scoring
        if ($historicalMessage->role === 'user') {
            $score += 0.2; // User messages often contain the original context
        } elseif ($historicalMessage->role === 'assistant') {
            $score += 0.1; // Assistant responses provide context too
        }

        // Content length factor (longer messages often have more context)
        $contentLength = strlen($historicalMessage->content);
        if ($contentLength > 200) {
            $score += 0.1;
        }

        // Recency factor (more recent is more relevant, but not too much weight)
        if ($historicalMessage->created_at) {
            $daysOld = now()->diffInDays($historicalMessage->created_at);
            if ($daysOld < 1) {
                $score += 0.1;
            } elseif ($daysOld < 7) {
                $score += 0.05;
            }
        }

        // Question-answer pair bonus
        if ($currentMessage->role === 'user' && str_contains($currentContent, '?') &&
            $historicalMessage->role === 'user' && str_contains($historicalContent, '?')) {
            $score += 0.1; // Both are questions, likely related
        }

        return min($score, 1.0);
    }

    /**
     * Get search statistics for debugging and optimization.
     */
    public function getSearchStatistics(array $searchResult): array
    {
        $relevanceScores = $searchResult['relevance_scores'] ?? [];

        return [
            'search_performed' => $searchResult['search_performed'] ?? false,
            'search_terms_count' => count($searchResult['search_terms'] ?? []),
            'relevant_messages_found' => $searchResult['total_found'] ?? 0,
            'avg_relevance_score' => ! empty($relevanceScores) ? array_sum($relevanceScores) / count($relevanceScores) : 0,
            'max_relevance_score' => ! empty($relevanceScores) ? max($relevanceScores) : 0,
            'min_relevance_score' => ! empty($relevanceScores) ? min($relevanceScores) : 0,
            'search_terms' => $searchResult['search_terms'] ?? [],
        ];
    }
}
