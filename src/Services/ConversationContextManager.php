<?php

namespace JTD\LaravelAI\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JTD\LaravelAI\Models\AIConversation;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIProviderModel;

/**
 * Conversation Context Manager
 *
 * Manages conversation context preservation, truncation, and optimization
 * for provider switching and context window management.
 *
 * Enhanced with search-based context retrieval and middleware integration hooks.
 */
class ConversationContextManager
{
    /**
     * The conversation search service instance.
     */
    protected ConversationSearchService $searchService;

    /**
     * The search-enhanced context service instance.
     */
    protected SearchEnhancedContextService $searchEnhancedService;

    /**
     * Create a new conversation context manager instance.
     */
    public function __construct(
        ?ConversationSearchService $searchService = null,
        ?SearchEnhancedContextService $searchEnhancedService = null
    ) {
        $this->searchService = $searchService ?? app(ConversationSearchService::class);
        $this->searchEnhancedService = $searchEnhancedService ?? app(SearchEnhancedContextService::class);
    }

    /**
     * Preserve context for provider switching.
     */
    public function preserveContextForSwitch(
        AIConversation $conversation,
        AIProviderModel $newModel,
        array $options = []
    ): array {
        $contextWindow = $newModel->context_length ?? $newModel->context_window ?? 4096;
        $maxContextTokens = (int) ($contextWindow * ($options['context_ratio'] ?? 0.8));

        // Get conversation messages
        $messages = $this->getConversationMessages($conversation, $options);

        // Calculate current token usage
        $currentTokens = $this->calculateTokenUsage($messages);

        if ($currentTokens <= $maxContextTokens) {
            return [
                'messages' => $messages->toArray(),
                'total_tokens' => $currentTokens,
                'truncated' => false,
                'preservation_strategy' => 'full_context',
                'original_count' => $messages->count(),
                'preserved_count' => $messages->count(),
            ];
        }

        // Apply context preservation strategy
        $strategy = $options['preservation_strategy'] ?? 'intelligent_truncation';

        return match ($strategy) {
            'recent_messages' => $this->preserveRecentMessages($messages, $maxContextTokens),
            'important_messages' => $this->preserveImportantMessages($messages, $maxContextTokens),
            'summarized_context' => $this->preserveWithSummary($messages, $maxContextTokens),
            default => $this->intelligentTruncation($messages, $maxContextTokens),
        };
    }

    /**
     * Get conversation messages for context preservation.
     */
    protected function getConversationMessages(
        AIConversation $conversation,
        array $options = []
    ): Collection {
        $limit = $options['message_limit'] ?? 50;
        $includeSystem = $options['include_system'] ?? true;

        $query = $conversation->messages()
            ->orderBy('sequence_number', 'asc');

        if (! $includeSystem) {
            $query->where('role', '!=', 'system');
        }

        return $query->limit($limit)->get();
    }

    /**
     * Calculate token usage for messages.
     */
    protected function calculateTokenUsage(Collection $messages): int
    {
        return $messages->sum(function ($message) {
            // Use stored token count if available, otherwise estimate
            return $message->total_tokens ?? $this->estimateTokens($message->content);
        });
    }

    /**
     * Estimate token count for text (rough approximation).
     */
    protected function estimateTokens(string $text): int
    {
        // Rough estimation: ~4 characters per token for English text
        return (int) ceil(strlen($text) / 4);
    }

    /**
     * Preserve recent messages strategy.
     */
    protected function preserveRecentMessages(Collection $messages, int $maxTokens): array
    {
        $preservedMessages = collect();
        $totalTokens = 0;

        // Always preserve system messages first
        $systemMessages = $messages->where('role', 'system');
        foreach ($systemMessages as $message) {
            $tokens = $message->total_tokens ?? $this->estimateTokens($message->content);
            if ($totalTokens + $tokens <= $maxTokens) {
                $preservedMessages->push($message);
                $totalTokens += $tokens;
            }
        }

        // Add recent messages in reverse order
        $nonSystemMessages = $messages->where('role', '!=', 'system')->reverse();
        foreach ($nonSystemMessages as $message) {
            $tokens = $message->total_tokens ?? $this->estimateTokens($message->content);
            if ($totalTokens + $tokens <= $maxTokens) {
                $preservedMessages->push($message);
                $totalTokens += $tokens;
            } else {
                break;
            }
        }

        return [
            'messages' => $preservedMessages->sortBy('sequence_number')->values()->toArray(),
            'total_tokens' => $totalTokens,
            'truncated' => $preservedMessages->count() < $messages->count(),
            'preservation_strategy' => 'recent_messages',
            'original_count' => $messages->count(),
            'preserved_count' => $preservedMessages->count(),
        ];
    }

    /**
     * Preserve important messages strategy.
     */
    protected function preserveImportantMessages(Collection $messages, int $maxTokens): array
    {
        $preservedMessages = collect();
        $totalTokens = 0;

        // Priority order: system, user questions, assistant responses, other
        $prioritizedMessages = $messages->sortBy(function ($message) {
            return match ($message->role) {
                'system' => 1,
                'user' => 2,
                'assistant' => 3,
                default => 4,
            };
        });

        foreach ($prioritizedMessages as $message) {
            $tokens = $message->total_tokens ?? $this->estimateTokens($message->content);
            if ($totalTokens + $tokens <= $maxTokens) {
                $preservedMessages->push($message);
                $totalTokens += $tokens;
            }
        }

        $result = [
            'messages' => $preservedMessages->sortBy('sequence_number')->values()->toArray(),
            'total_tokens' => $totalTokens,
            'truncated' => $preservedMessages->count() < $messages->count(),
            'preservation_strategy' => 'important_messages',
            'original_count' => $messages->count(),
            'preserved_count' => $preservedMessages->count(),
        ];

        return $result;
    }

    /**
     * Preserve with summary strategy.
     */
    protected function preserveWithSummary(Collection $messages, int $maxTokens): array
    {
        // Reserve tokens for summary
        $summaryTokens = (int) ($maxTokens * 0.3);
        $availableTokens = $maxTokens - $summaryTokens;

        // Get recent messages that fit in available tokens
        $recentContext = $this->preserveRecentMessages($messages, $availableTokens);

        // Create summary of older messages
        $olderMessages = $messages->take($messages->count() - $recentContext['preserved_count']);
        $summary = $this->createContextSummary($olderMessages);

        // Add summary as a system message
        if (! empty($summary) && $olderMessages->isNotEmpty()) {
            $summaryMessage = [
                'role' => 'system',
                'content' => 'Previous conversation summary: ' . $summary,
                'sequence_number' => 0,
                'total_tokens' => $this->estimateTokens($summary),
            ];

            array_unshift($recentContext['messages'], $summaryMessage);
            $recentContext['total_tokens'] += $summaryMessage['total_tokens'];
        }

        return [
            'messages' => $recentContext['messages'],
            'total_tokens' => $recentContext['total_tokens'],
            'truncated' => true,
            'preservation_strategy' => 'summarized_context',
            'original_count' => $messages->count(),
            'preserved_count' => count($recentContext['messages']),
            'summary_created' => ! empty($summary),
        ];
    }

    /**
     * Intelligent truncation strategy.
     */
    protected function intelligentTruncation(Collection $messages, int $maxTokens): array
    {
        // Combine multiple strategies for optimal context preservation

        // 1. Always preserve system messages
        $systemMessages = $messages->where('role', 'system');
        $systemTokens = $this->calculateTokenUsage($systemMessages);

        // 2. Preserve recent conversation pairs (user + assistant)
        $availableTokens = $maxTokens - $systemTokens;
        $conversationPairs = $this->extractConversationPairs($messages->where('role', '!=', 'system'));

        $preservedPairs = collect();
        $pairTokens = 0;

        // Add pairs from most recent backwards
        foreach ($conversationPairs->reverse() as $pair) {
            $pairTokenCount = $this->calculateTokenUsage(collect($pair));
            if ($pairTokens + $pairTokenCount <= $availableTokens) {
                $preservedPairs->prepend($pair);
                $pairTokens += $pairTokenCount;
            } else {
                break;
            }
        }

        // Combine system messages with preserved pairs
        $allPreservedMessages = $systemMessages->concat($preservedPairs->flatten())->sortBy('sequence_number');

        return [
            'messages' => $allPreservedMessages->values()->toArray(),
            'total_tokens' => $systemTokens + $pairTokens,
            'truncated' => $allPreservedMessages->count() < $messages->count(),
            'preservation_strategy' => 'intelligent_truncation',
            'original_count' => $messages->count(),
            'preserved_count' => $allPreservedMessages->count(),
            'conversation_pairs_preserved' => $preservedPairs->count(),
        ];
    }

    /**
     * Extract conversation pairs (user message + assistant response).
     */
    protected function extractConversationPairs(Collection $messages): Collection
    {
        $pairs = collect();
        $currentPair = [];

        foreach ($messages as $message) {
            if ($message->role === 'user') {
                // Start new pair
                if (! empty($currentPair)) {
                    $pairs->push($currentPair);
                }
                $currentPair = [$message];
            } elseif ($message->role === 'assistant' && ! empty($currentPair)) {
                // Complete current pair
                $currentPair[] = $message;
                $pairs->push($currentPair);
                $currentPair = [];
            }
        }

        // Add incomplete pair if exists
        if (! empty($currentPair)) {
            $pairs->push($currentPair);
        }

        return $pairs;
    }

    /**
     * Create a summary of conversation context.
     */
    protected function createContextSummary(Collection $messages): string
    {
        if ($messages->isEmpty()) {
            return '';
        }

        // Simple summary creation - in production, you might use AI to generate better summaries
        $userMessages = $messages->where('role', 'user')->pluck('content');
        $topics = $userMessages->take(3)->implode('; ');

        return 'User discussed: ' . $topics . ' (and ' . ($messages->count() - 3) . ' more messages)';
    }

    /**
     * Validate context preservation result.
     */
    public function validateContextPreservation(array $contextResult, int $maxTokens): bool
    {
        return $contextResult['total_tokens'] <= $maxTokens &&
               ! empty($contextResult['messages']);
    }

    /**
     * Build intelligent context for a new message using search-enhanced retrieval.
     *
     * This method analyzes the current message content and uses conversation search
     * to find relevant historical messages that should be preserved in context.
     */
    public function buildIntelligentContext(
        AIConversation $conversation,
        AIMessage $currentMessage,
        array $options = []
    ): array {
        $maxTokens = $options['max_tokens'] ?? 4096;
        $contextRatio = $options['context_ratio'] ?? 0.8;
        $maxContextTokens = (int) ($maxTokens * $contextRatio);

        // Get base conversation messages
        $messages = $this->getConversationMessages($conversation, $options);

        // Find relevant historical messages using search
        $relevantMessages = $this->findRelevantMessages($conversation, $currentMessage, $options);

        // Merge and prioritize messages
        $prioritizedMessages = $this->prioritizeMessages($messages, $relevantMessages, $currentMessage);

        // Apply intelligent truncation with search-enhanced context
        return $this->applySearchEnhancedTruncation($prioritizedMessages, $maxContextTokens, $options);
    }

    /**
     * Find relevant historical messages using conversation search.
     *
     * This implements the core feature where asking about "favorite color"
     * will search and find the relevant historical message.
     */
    protected function findRelevantMessages(
        AIConversation $conversation,
        AIMessage $currentMessage,
        array $options = []
    ): Collection {
        // Use the dedicated search-enhanced context service
        $searchResult = $this->searchEnhancedService->findRelevantContext(
            $conversation,
            $currentMessage,
            $options
        );

        Log::debug('Search-enhanced context retrieval completed', [
            'conversation_id' => $conversation->id,
            'search_performed' => $searchResult['search_performed'],
            'relevant_messages_found' => $searchResult['total_found'] ?? 0,
            'search_terms' => $searchResult['search_terms'] ?? [],
        ]);

        return $searchResult['relevant_messages'] ?? collect();
    }

    /**
     * Extract search terms from message content.
     */
    protected function extractSearchTerms(string $content): array
    {
        // Simple keyword extraction - in production, use NLP libraries
        $content = strtolower($content);

        // Look for question patterns that might reference previous topics
        $patterns = [
            '/what.*was.*my.*(\w+)/',           // "what was my favorite color"
            '/remember.*when.*(\w+)/',          // "remember when we talked about"
            '/you.*said.*(\w+)/',               // "you said something about"
            '/we.*discussed.*(\w+)/',           // "we discussed"
            '/earlier.*mentioned.*(\w+)/',      // "earlier you mentioned"
        ];

        $terms = [];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                $terms = array_merge($terms, $matches[1]);
            }
        }

        // Also extract important nouns (simple approach)
        $words = str_word_count($content, 1);
        $importantWords = array_filter($words, function ($word) {
            return strlen($word) > 3 &&
                   ! in_array($word, ['what', 'when', 'where', 'how', 'why', 'this', 'that', 'they', 'them']);
        });

        return array_unique(array_merge($terms, array_slice($importantWords, 0, 5)));
    }

    /**
     * Calculate relevance score for a message.
     */
    protected function calculateRelevanceScore($message): float
    {
        // Simple scoring - in production, use more sophisticated algorithms
        $score = 0.5; // Base score

        // Boost score for user messages (they contain the original context)
        if ($message->role === 'user') {
            $score += 0.2;
        }

        // Boost score for longer messages (more context)
        $contentLength = strlen($message->content);
        if ($contentLength > 100) {
            $score += 0.1;
        }

        // Boost score for recent messages
        $daysOld = now()->diffInDays($message->created_at);
        if ($daysOld < 7) {
            $score += 0.2;
        } elseif ($daysOld < 30) {
            $score += 0.1;
        }

        return min($score, 1.0);
    }

    /**
     * Prioritize messages by combining conversation flow with search relevance.
     */
    protected function prioritizeMessages(
        Collection $conversationMessages,
        Collection $relevantMessages,
        AIMessage $currentMessage
    ): Collection {
        // Create a priority map
        $priorityMap = [];

        // High priority: System messages
        foreach ($conversationMessages->where('role', 'system') as $message) {
            $priorityMap[$message->id] = 1;
        }

        // High priority: Search-relevant messages
        foreach ($relevantMessages as $message) {
            $priorityMap[$message->id] = 1;
        }

        // Medium priority: Recent conversation pairs
        $recentMessages = $conversationMessages->sortByDesc('sequence_number')->take(10);
        foreach ($recentMessages as $message) {
            if (! isset($priorityMap[$message->id])) {
                $priorityMap[$message->id] = 2;
            }
        }

        // Low priority: Other messages
        foreach ($conversationMessages as $message) {
            if (! isset($priorityMap[$message->id])) {
                $priorityMap[$message->id] = 3;
            }
        }

        // Sort by priority, then by sequence number
        return $conversationMessages->sortBy(function ($message) use ($priorityMap) {
            return [$priorityMap[$message->id] ?? 3, $message->sequence_number];
        });
    }

    /**
     * Apply search-enhanced truncation that preserves relevant context.
     */
    protected function applySearchEnhancedTruncation(
        Collection $prioritizedMessages,
        int $maxTokens,
        array $options = []
    ): array {
        $preservedMessages = collect();
        $totalTokens = 0;

        // Always preserve system messages first
        $systemMessages = $prioritizedMessages->where('role', 'system');
        foreach ($systemMessages as $message) {
            $tokens = $message->total_tokens ?? $this->estimateTokens($message->content);
            if ($totalTokens + $tokens <= $maxTokens) {
                $preservedMessages->push($message);
                $totalTokens += $tokens;
            }
        }

        // Preserve high-priority messages (search-relevant)
        $highPriorityMessages = $prioritizedMessages->where('role', '!=', 'system')
            ->filter(function ($message) use ($options) {
                // Messages found via search are high priority
                return isset($options['relevant_message_ids']) &&
                       in_array($message->id, $options['relevant_message_ids']);
            });

        foreach ($highPriorityMessages as $message) {
            $tokens = $message->total_tokens ?? $this->estimateTokens($message->content);
            if ($totalTokens + $tokens <= $maxTokens) {
                $preservedMessages->push($message);
                $totalTokens += $tokens;
            }
        }

        // Fill remaining space with recent conversation pairs
        $remainingMessages = $prioritizedMessages->where('role', '!=', 'system')
            ->filter(function ($message) use ($preservedMessages) {
                return ! $preservedMessages->contains('id', $message->id);
            })
            ->sortByDesc('sequence_number');

        foreach ($remainingMessages as $message) {
            $tokens = $message->total_tokens ?? $this->estimateTokens($message->content);
            if ($totalTokens + $tokens <= $maxTokens) {
                $preservedMessages->push($message);
                $totalTokens += $tokens;
            } else {
                break;
            }
        }

        $finalMessages = $preservedMessages->sortBy('sequence_number');

        return [
            'messages' => $finalMessages->values()->toArray(),
            'total_tokens' => $totalTokens,
            'truncated' => $finalMessages->count() < $prioritizedMessages->count(),
            'preservation_strategy' => 'search_enhanced_truncation',
            'original_count' => $prioritizedMessages->count(),
            'preserved_count' => $finalMessages->count(),
            'search_relevant_preserved' => $highPriorityMessages->count(),
        ];
    }

    /**
     * Get configurable context window for conversation.
     */
    public function getContextWindow(AIConversation $conversation, array $options = []): int
    {
        // Check conversation-specific settings
        if ($conversation->context_settings && isset($conversation->context_settings['window_size'])) {
            return $conversation->context_settings['window_size'];
        }

        // Check provider model limits
        if ($conversation->currentModel) {
            return $conversation->currentModel->context_length ??
                   $conversation->currentModel->context_window ?? 4096;
        }

        // Default from options or config
        return $options['context_window'] ?? config('ai.conversation.default_context_window', 4096);
    }

    /**
     * Cache context for performance optimization.
     */
    public function getCachedContext(string $cacheKey, \Closure $callback, int $ttl = 300): array
    {
        return Cache::remember($cacheKey, $ttl, $callback);
    }

    /**
     * Generate cache key for context.
     */
    public function generateContextCacheKey(
        AIConversation $conversation,
        AIMessage $currentMessage,
        array $options = []
    ): string {
        $optionsHash = md5(serialize($options));
        $messageHash = md5($currentMessage->content);

        return "ai_context:{$conversation->id}:{$messageHash}:{$optionsHash}";
    }

    // ========================================================================
    // MIDDLEWARE INTEGRATION HOOKS
    // ========================================================================
    // TODO: These methods will be called by ContextInjectionMiddleware when
    // the middleware system is implemented in a future sprint.

    /**
     * TODO: MIDDLEWARE HOOK - Build context for middleware injection.
     *
     * This method will be called by ContextInjectionMiddleware to get
     * intelligent context for message processing.
     */
    public function buildContextForMiddleware(
        AIConversation $conversation,
        AIMessage $message,
        array $middlewareOptions = []
    ): array {
        // TODO: This will be the main entry point for ContextInjectionMiddleware
        // when the middleware system is implemented.

        $cacheKey = $this->generateContextCacheKey($conversation, $message, $middlewareOptions);

        return $this->getCachedContext($cacheKey, function () use ($conversation, $message, $middlewareOptions) {
            return $this->buildIntelligentContext($conversation, $message, $middlewareOptions);
        }, $middlewareOptions['cache_ttl'] ?? 300);
    }

    /**
     * TODO: MIDDLEWARE HOOK - Format context for injection into message content.
     *
     * This method will be called by ContextInjectionMiddleware to format
     * the context for injection into the message.
     */
    public function formatContextForInjection(array $contextResult, array $options = []): string
    {
        // TODO: This will format context for injection by ContextInjectionMiddleware

        if (empty($contextResult['messages'])) {
            return '';
        }

        $contextString = "Relevant conversation context:\n";

        foreach ($contextResult['messages'] as $message) {
            if ($message['role'] === 'system') {
                continue; // Skip system messages in context injection
            }

            $role = ucfirst($message['role']);
            $content = substr($message['content'], 0, 200); // Truncate for brevity
            if (strlen($message['content']) > 200) {
                $content .= '...';
            }

            $contextString .= "- {$role}: {$content}\n";
        }

        return $contextString . "\n";
    }

    /**
     * TODO: MIDDLEWARE HOOK - Check if context injection should be applied.
     *
     * This method will be called by ContextInjectionMiddleware to determine
     * if context should be injected for this message.
     */
    public function shouldInjectContext(AIMessage $message, array $options = []): bool
    {
        // TODO: This will be used by ContextInjectionMiddleware to decide
        // whether to inject context for a given message.

        // Don't inject context for system messages
        if ($message->role === 'system') {
            return false;
        }

        // Don't inject if explicitly disabled
        if (isset($options['inject_context']) && ! $options['inject_context']) {
            return false;
        }

        // Inject context for questions that might reference previous conversation
        $content = strtolower($message->content);
        $referencePatterns = [
            'what.*was', 'remember', 'you.*said', 'we.*discussed',
            'earlier', 'before', 'previous', 'my.*favorite', 'tell.*me.*about',
        ];

        foreach ($referencePatterns as $pattern) {
            if (preg_match("/{$pattern}/", $content)) {
                return true;
            }
        }

        // Default to injecting context for longer messages
        return strlen($message->content) > 50;
    }

    // ========================================================================
    // ENHANCED INTELLIGENT TRUNCATION METHODS
    // ========================================================================

    /**
     * Advanced intelligent truncation with message importance scoring.
     */
    public function advancedIntelligentTruncation(Collection $messages, int $maxTokens, array $options = []): array
    {
        // Score all messages by importance
        $scoredMessages = $messages->map(function ($message) use ($options) {
            return [
                'message' => $message,
                'score' => $this->calculateMessageImportanceScore($message, $options),
                'tokens' => $message->total_tokens ?? $this->estimateTokens($message->content),
            ];
        })->sortByDesc('score');

        $preservedMessages = collect();
        $totalTokens = 0;

        // Always preserve system messages first (highest priority)
        $systemMessages = $scoredMessages->where('message.role', 'system');
        foreach ($systemMessages as $item) {
            if ($totalTokens + $item['tokens'] <= $maxTokens) {
                $preservedMessages->push($item['message']);
                $totalTokens += $item['tokens'];
            }
        }

        // Preserve high-scoring messages
        $nonSystemMessages = $scoredMessages->where('message.role', '!=', 'system');
        foreach ($nonSystemMessages as $item) {
            if ($totalTokens + $item['tokens'] <= $maxTokens) {
                $preservedMessages->push($item['message']);
                $totalTokens += $item['tokens'];
            } else {
                break;
            }
        }

        $finalMessages = $preservedMessages->sortBy('sequence_number');

        return [
            'messages' => $finalMessages->values()->toArray(),
            'total_tokens' => $totalTokens,
            'truncated' => $finalMessages->count() < $messages->count(),
            'preservation_strategy' => 'advanced_intelligent_truncation',
            'original_count' => $messages->count(),
            'preserved_count' => $finalMessages->count(),
            'avg_importance_score' => $scoredMessages->avg('score'),
            'preserved_avg_score' => $preservedMessages->isEmpty() ? 0 :
                $scoredMessages->whereIn('message.id', $preservedMessages->pluck('id'))->avg('score'),
        ];
    }

    /**
     * Calculate importance score for a message.
     */
    protected function calculateMessageImportanceScore($message, array $options = []): float
    {
        $score = 0.0;

        // Role-based scoring
        switch ($message->role) {
            case 'system':
                $score += 1.0; // Highest priority
                break;
            case 'user':
                $score += 0.7; // High priority - user questions are important
                break;
            case 'assistant':
                $score += 0.5; // Medium priority
                break;
            default:
                $score += 0.3; // Low priority
        }

        // Content-based scoring
        $content = strtolower($message->content);
        $contentLength = strlen($content);

        // Length factor (longer messages often contain more context)
        if ($contentLength > 500) {
            $score += 0.3;
        } elseif ($contentLength > 200) {
            $score += 0.2;
        } elseif ($contentLength > 100) {
            $score += 0.1;
        }

        // Question indicators (questions are often important)
        if (str_contains($content, '?') ||
            preg_match('/\b(what|how|why|when|where|who|which)\b/', $content)) {
            $score += 0.2;
        }

        // Important keywords
        $importantKeywords = [
            'error', 'problem', 'issue', 'bug', 'fix', 'solution',
            'important', 'critical', 'urgent', 'help', 'please',
            'remember', 'note', 'warning', 'caution', 'attention',
        ];

        foreach ($importantKeywords as $keyword) {
            if (str_contains($content, $keyword)) {
                $score += 0.1;
                break; // Only add bonus once
            }
        }

        // Code indicators (code discussions are often important)
        if (preg_match('/```|`[^`]+`|\b(function|class|method|variable|array|object)\b/', $content)) {
            $score += 0.15;
        }

        // Recency factor (more recent messages are more relevant)
        if ($message->created_at) {
            $hoursOld = now()->diffInHours($message->created_at);
            if ($hoursOld < 1) {
                $score += 0.3;
            } elseif ($hoursOld < 24) {
                $score += 0.2;
            } elseif ($hoursOld < 168) { // 1 week
                $score += 0.1;
            }
        }

        // Conversation flow factor (messages that are part of ongoing exchanges)
        if (isset($options['conversation_flow_scores'][$message->id])) {
            $score += $options['conversation_flow_scores'][$message->id];
        }

        return min($score, 2.0); // Cap at 2.0
    }

    /**
     * Analyze conversation flow to identify important message sequences.
     */
    public function analyzeConversationFlow(Collection $messages): array
    {
        $flowScores = [];
        $messageArray = $messages->sortBy('sequence_number')->values()->all();

        for ($i = 0; $i < count($messageArray); $i++) {
            $message = $messageArray[$i];
            $score = 0.0;

            // Check if this message is part of a question-answer pair
            if ($message->role === 'user' && isset($messageArray[$i + 1]) &&
                $messageArray[$i + 1]->role === 'assistant') {
                $score += 0.2; // User question in Q&A pair
            } elseif ($message->role === 'assistant' && isset($messageArray[$i - 1]) &&
                     $messageArray[$i - 1]->role === 'user') {
                $score += 0.15; // Assistant answer in Q&A pair
            }

            // Check for follow-up questions
            if ($message->role === 'user' && $i > 0) {
                $prevContent = strtolower($messageArray[$i - 1]->content);
                $currentContent = strtolower($message->content);

                // Look for follow-up indicators
                $followUpIndicators = ['also', 'additionally', 'furthermore', 'moreover', 'and', 'but'];
                foreach ($followUpIndicators as $indicator) {
                    if (str_starts_with(trim($currentContent), $indicator)) {
                        $score += 0.1;
                        break;
                    }
                }
            }

            $flowScores[$message->id] = $score;
        }

        return $flowScores;
    }

    /**
     * Create context preservation markers for important elements.
     */
    public function createPreservationMarkers(Collection $messages, array $options = []): array
    {
        $markers = [];

        foreach ($messages as $message) {
            $messageMarkers = [];

            // Mark system messages as always preserve
            if ($message->role === 'system') {
                $messageMarkers[] = 'system_message';
            }

            // Mark messages with important keywords
            $content = strtolower($message->content);
            if (preg_match('/\b(remember|important|note|warning|error|critical)\b/', $content)) {
                $messageMarkers[] = 'important_content';
            }

            // Mark questions
            if (str_contains($content, '?') ||
                preg_match('/\b(what|how|why|when|where|who|which)\b/', $content)) {
                $messageMarkers[] = 'question';
            }

            // Mark code-related content
            if (preg_match('/```|`[^`]+`/', $content)) {
                $messageMarkers[] = 'code_content';
            }

            // Mark long messages (likely contain detailed explanations)
            if (strlen($message->content) > 500) {
                $messageMarkers[] = 'detailed_content';
            }

            // Mark recent messages
            if ($message->created_at && now()->diffInHours($message->created_at) < 24) {
                $messageMarkers[] = 'recent';
            }

            if (! empty($messageMarkers)) {
                $markers[$message->id] = $messageMarkers;
            }
        }

        return $markers;
    }

    // ========================================================================
    // CONTEXT OPTIMIZATION METHODS
    // ========================================================================

    /**
     * Optimize context for token efficiency while maintaining quality.
     */
    public function optimizeContext(array $contextResult, array $options = []): array
    {
        if (empty($contextResult['messages'])) {
            return $contextResult;
        }

        $messages = collect($contextResult['messages']);
        $optimizationLevel = $options['optimization_level'] ?? 'balanced'; // 'light', 'balanced', 'aggressive'

        $optimizedMessages = $messages->map(function ($message) use ($optimizationLevel) {
            return $this->optimizeMessage($message, $optimizationLevel);
        });

        // Recalculate token count after optimization
        $newTokenCount = $optimizedMessages->sum(function ($message) {
            return $this->estimateTokens($message['content']);
        });

        $originalTokenCount = $contextResult['total_tokens'];
        $tokensSaved = $originalTokenCount - $newTokenCount;

        return array_merge($contextResult, [
            'messages' => $optimizedMessages->toArray(),
            'total_tokens' => $newTokenCount,
            'optimization_applied' => true,
            'optimization_level' => $optimizationLevel,
            'tokens_saved' => $tokensSaved,
            'optimization_ratio' => $originalTokenCount > 0 ? $tokensSaved / $originalTokenCount : 0,
        ]);
    }

    /**
     * Optimize individual message content.
     */
    protected function optimizeMessage(array $message, string $level): array
    {
        $content = $message['content'];
        $originalLength = strlen($content);

        // Don't optimize system messages heavily
        if ($message['role'] === 'system') {
            $level = 'light';
        }

        switch ($level) {
            case 'light':
                $content = $this->lightOptimization($content);
                break;
            case 'balanced':
                $content = $this->balancedOptimization($content);
                break;
            case 'aggressive':
                $content = $this->aggressiveOptimization($content);
                break;
        }

        $message['content'] = $content;
        $message['optimization_applied'] = true;
        $message['original_length'] = $originalLength;
        $message['optimized_length'] = strlen($content);
        $message['compression_ratio'] = $originalLength > 0 ? (strlen($content) / $originalLength) : 1;

        return $message;
    }

    /**
     * Light optimization - minimal changes.
     */
    protected function lightOptimization(string $content): string
    {
        // Remove excessive whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);

        // Remove redundant punctuation
        $content = preg_replace('/[.]{3,}/', '...', $content);
        $content = preg_replace('/[!]{2,}/', '!', $content);
        $content = preg_replace('/[?]{2,}/', '?', $content);

        return $content;
    }

    /**
     * Balanced optimization - moderate compression.
     */
    protected function balancedOptimization(string $content): string
    {
        // Apply light optimization first
        $content = $this->lightOptimization($content);

        // Remove filler words and phrases
        $fillerPatterns = [
            '/\b(um|uh|er|ah|well|you know|like|actually|basically|literally)\b/i',
            '/\b(I think|I believe|I guess|I suppose|it seems|perhaps|maybe)\b/i',
            '/\b(sort of|kind of|more or less|pretty much)\b/i',
        ];

        foreach ($fillerPatterns as $pattern) {
            $content = preg_replace($pattern, '', $content);
        }

        // Simplify redundant phrases
        $redundantPhrases = [
            '/\bin order to\b/i' => 'to',
            '/\bdue to the fact that\b/i' => 'because',
            '/\bat this point in time\b/i' => 'now',
            '/\bfor the purpose of\b/i' => 'to',
            '/\bin the event that\b/i' => 'if',
        ];

        foreach ($redundantPhrases as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }

        // Clean up extra spaces created by removals
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);

        return $content;
    }

    /**
     * Aggressive optimization - maximum compression.
     */
    protected function aggressiveOptimization(string $content): string
    {
        // Apply balanced optimization first
        $content = $this->balancedOptimization($content);

        // Remove articles where not essential
        $content = preg_replace('/\b(a|an|the)\s+/i', '', $content);

        // Simplify contractions
        $contractions = [
            '/\bdo not\b/i' => "don't",
            '/\bcannot\b/i' => "can't",
            '/\bwill not\b/i' => "won't",
            '/\bshould not\b/i' => "shouldn't",
            '/\bwould not\b/i' => "wouldn't",
            '/\bis not\b/i' => "isn't",
            '/\bare not\b/i' => "aren't",
            '/\bwas not\b/i' => "wasn't",
            '/\bwere not\b/i' => "weren't",
        ];

        foreach ($contractions as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }

        // Remove excessive adjectives and adverbs
        $excessiveModifiers = [
            '/\b(very|really|quite|rather|extremely|incredibly|absolutely|totally|completely)\s+/i',
            '/\b(pretty|fairly|somewhat|relatively|moderately)\s+/i',
        ];

        foreach ($excessiveModifiers as $pattern) {
            $content = preg_replace($pattern, '', $content);
        }

        // Abbreviate common phrases
        $abbreviations = [
            '/\bfor example\b/i' => 'e.g.',
            '/\bthat is\b/i' => 'i.e.',
            '/\band so on\b/i' => 'etc.',
            '/\band so forth\b/i' => 'etc.',
        ];

        foreach ($abbreviations as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }

        // Final cleanup
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);

        return $content;
    }

    /**
     * Optimize context based on token budget.
     */
    public function optimizeForTokenBudget(array $contextResult, int $targetTokens, array $options = []): array
    {
        if ($contextResult['total_tokens'] <= $targetTokens) {
            return $contextResult; // Already within budget
        }

        $messages = collect($contextResult['messages']);
        $currentTokens = $contextResult['total_tokens'];
        $tokensToSave = $currentTokens - $targetTokens;

        // Try different optimization levels
        $optimizationLevels = ['light', 'balanced', 'aggressive'];

        foreach ($optimizationLevels as $level) {
            $optimized = $this->optimizeContext($contextResult, ['optimization_level' => $level]);

            if ($optimized['total_tokens'] <= $targetTokens) {
                return $optimized;
            }
        }

        // If optimization isn't enough, remove messages starting from least important
        $optimized = $this->optimizeContext($contextResult, ['optimization_level' => 'aggressive']);

        if ($optimized['total_tokens'] > $targetTokens) {
            // Score messages by importance and remove least important ones
            $messages = collect($optimized['messages']);
            $scoredMessages = $messages->map(function ($message) {
                return [
                    'message' => $message,
                    'score' => $this->calculateMessageImportanceScore((object) $message, []),
                    'tokens' => $this->estimateTokens($message['content']),
                ];
            })->sortByDesc('score');

            $preservedMessages = collect();
            $totalTokens = 0;

            foreach ($scoredMessages as $item) {
                if ($totalTokens + $item['tokens'] <= $targetTokens) {
                    $preservedMessages->push($item['message']);
                    $totalTokens += $item['tokens'];
                }
            }

            $optimized['messages'] = $preservedMessages->sortBy('sequence_number')->values()->toArray();
            $optimized['total_tokens'] = $totalTokens;
            $optimized['truncated'] = true;
            $optimized['budget_optimization_applied'] = true;
        }

        return $optimized;
    }

    /**
     * Get optimization statistics.
     */
    public function getOptimizationStats(array $originalContext, array $optimizedContext): array
    {
        $originalTokens = $originalContext['total_tokens'];
        $optimizedTokens = $optimizedContext['total_tokens'];
        $tokensSaved = $originalTokens - $optimizedTokens;

        return [
            'original_tokens' => $originalTokens,
            'optimized_tokens' => $optimizedTokens,
            'tokens_saved' => $tokensSaved,
            'compression_ratio' => $originalTokens > 0 ? $optimizedTokens / $originalTokens : 1,
            'space_saved_percentage' => $originalTokens > 0 ? ($tokensSaved / $originalTokens) * 100 : 0,
            'messages_count' => count($optimizedContext['messages']),
            'optimization_level' => $optimizedContext['optimization_level'] ?? 'none',
        ];
    }
}
