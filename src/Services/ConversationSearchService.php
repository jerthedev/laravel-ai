<?php

namespace JTD\LaravelAI\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use JTD\LaravelAI\Models\AIConversation;
use JTD\LaravelAI\Models\AIMessageRecord;

/**
 * Conversation Search Service
 *
 * Provides advanced search and filtering capabilities for conversations
 * with support for full-text search, faceted filtering, and sorting.
 */
class ConversationSearchService
{
    /**
     * Search conversations with advanced filtering.
     */
    public function search(array $criteria = [], int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        $query = AIConversation::query();

        // Apply search filters
        $this->applySearchFilters($query, $criteria);

        // Apply sorting
        $this->applySorting($query, $criteria['sort'] ?? 'recent');

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Search conversations for a specific user.
     */
    public function searchForUser($userId, ?string $userType = null, array $criteria = [], int $perPage = 15): LengthAwarePaginator
    {
        $criteria['user_id'] = $userId;
        if ($userType) {
            $criteria['user_type'] = $userType;
        }

        return $this->search($criteria, $perPage);
    }

    /**
     * Search conversations by session.
     */
    public function searchBySession(string $sessionId, array $criteria = [], int $perPage = 15): LengthAwarePaginator
    {
        $criteria['session_id'] = $sessionId;

        return $this->search($criteria, $perPage);
    }

    /**
     * Get conversation suggestions based on content similarity.
     */
    public function getSimilarConversations(AIConversation $conversation, int $limit = 5): Collection
    {
        $query = AIConversation::query()
            ->where('id', '!=', $conversation->id)
            ->where('status', AIConversation::STATUS_ACTIVE);

        // Match by tags
        if (! empty($conversation->tags)) {
            $query->where(function ($q) use ($conversation) {
                foreach ($conversation->tags as $tag) {
                    $q->orWhereJsonContains('tags', $tag);
                }
            });
        }

        // Match by conversation type
        $query->where('conversation_type', $conversation->conversation_type);

        // Match by provider if available
        if ($conversation->ai_provider_id) {
            $query->where('ai_provider_id', $conversation->ai_provider_id);
        }

        // Prefer conversations from same user
        if ($conversation->user_id) {
            $query->orderByRaw('CASE WHEN user_id = ? THEN 0 ELSE 1 END', [$conversation->user_id]);
        }

        return $query->orderByDesc('last_activity_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Search within conversation messages.
     */
    public function searchMessages(array $criteria = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = AIMessageRecord::query()
            ->with(['conversation']);

        // Apply message search filters
        $this->applyMessageSearchFilters($query, $criteria);

        // Sort by relevance and recency
        $query->orderByDesc('created_at');

        return $query->paginate($perPage);
    }

    /**
     * Get conversation statistics for search results.
     */
    public function getSearchStatistics(array $criteria = []): array
    {
        $query = AIConversation::query();
        $this->applySearchFilters($query, $criteria);

        return [
            'total_conversations' => $query->count(),
            'total_messages' => $query->sum('total_messages'),
            'total_cost' => $query->sum('total_cost'),
            'avg_messages_per_conversation' => $query->avg('total_messages'),
            'avg_cost_per_conversation' => $query->avg('total_cost'),
            'date_range' => [
                'earliest' => $query->min('created_at'),
                'latest' => $query->max('last_activity_at'),
            ],
            'providers' => $this->getProviderBreakdown($query),
            'conversation_types' => $this->getConversationTypeBreakdown($query),
        ];
    }

    /**
     * Get popular tags from search results.
     */
    public function getPopularTags(array $criteria = [], int $limit = 20): array
    {
        $query = AIConversation::query();
        $this->applySearchFilters($query, $criteria);

        $conversations = $query->whereNotNull('tags')->get(['tags']);

        $tagCounts = [];
        foreach ($conversations as $conversation) {
            foreach ($conversation->tags ?? [] as $tag) {
                $tagCounts[$tag] = ($tagCounts[$tag] ?? 0) + 1;
            }
        }

        arsort($tagCounts);

        return array_slice($tagCounts, 0, $limit, true);
    }

    /**
     * Apply search filters to query.
     */
    protected function applySearchFilters(Builder $query, array $criteria): void
    {
        // Text search
        if (! empty($criteria['search'])) {
            $search = $criteria['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('messages', function ($mq) use ($search) {
                        $mq->where('content', 'like', "%{$search}%");
                    });
            });
        }

        // User filter
        if (! empty($criteria['user_id'])) {
            $query->where('user_id', $criteria['user_id']);

            if (! empty($criteria['user_type'])) {
                $query->where('user_type', $criteria['user_type']);
            }
        }

        // Session filter
        if (! empty($criteria['session_id'])) {
            $query->where('session_id', $criteria['session_id']);
        }

        // Status filter
        if (! empty($criteria['status'])) {
            if (is_array($criteria['status'])) {
                $query->whereIn('status', $criteria['status']);
            } else {
                $query->where('status', $criteria['status']);
            }
        } else {
            // Default to active conversations
            $query->where('status', AIConversation::STATUS_ACTIVE);
        }

        // Provider filter
        if (! empty($criteria['provider_id'])) {
            $query->where('ai_provider_id', $criteria['provider_id']);
        }

        if (! empty($criteria['provider_name'])) {
            $query->where('provider_name', $criteria['provider_name']);
        }

        // Model filter
        if (! empty($criteria['model_id'])) {
            $query->where('ai_provider_model_id', $criteria['model_id']);
        }

        if (! empty($criteria['model_name'])) {
            $query->where('model_name', $criteria['model_name']);
        }

        // Conversation type filter
        if (! empty($criteria['conversation_type'])) {
            if (is_array($criteria['conversation_type'])) {
                $query->whereIn('conversation_type', $criteria['conversation_type']);
            } else {
                $query->where('conversation_type', $criteria['conversation_type']);
            }
        }

        // Language filter
        if (! empty($criteria['language'])) {
            $query->where('language', $criteria['language']);
        }

        // Tags filter
        if (! empty($criteria['tags'])) {
            $tags = is_array($criteria['tags']) ? $criteria['tags'] : [$criteria['tags']];

            $query->where(function ($q) use ($tags) {
                foreach ($tags as $tag) {
                    $q->whereJsonContains('tags', $tag);
                }
            });
        }

        // Date range filters
        if (! empty($criteria['created_after'])) {
            $query->where('created_at', '>=', Carbon::parse($criteria['created_after']));
        }

        if (! empty($criteria['created_before'])) {
            $query->where('created_at', '<=', Carbon::parse($criteria['created_before']));
        }

        if (! empty($criteria['active_after'])) {
            $query->where('last_activity_at', '>=', Carbon::parse($criteria['active_after']));
        }

        if (! empty($criteria['active_before'])) {
            $query->where('last_activity_at', '<=', Carbon::parse($criteria['active_before']));
        }

        // Cost range filters
        if (! empty($criteria['min_cost'])) {
            $query->where('total_cost', '>=', $criteria['min_cost']);
        }

        if (! empty($criteria['max_cost'])) {
            $query->where('total_cost', '<=', $criteria['max_cost']);
        }

        // Message count filters
        if (! empty($criteria['min_messages'])) {
            $query->where('total_messages', '>=', $criteria['min_messages']);
        }

        if (! empty($criteria['max_messages'])) {
            $query->where('total_messages', '<=', $criteria['max_messages']);
        }

        // Quality rating filter
        if (! empty($criteria['min_rating'])) {
            $query->where('avg_quality_rating', '>=', $criteria['min_rating']);
        }
    }

    /**
     * Apply message search filters.
     */
    protected function applyMessageSearchFilters(Builder $query, array $criteria): void
    {
        // Text search in message content
        if (! empty($criteria['search'])) {
            $query->where('content', 'like', "%{$criteria['search']}%");
        }

        // Role filter
        if (! empty($criteria['role'])) {
            $query->where('role', $criteria['role']);
        }

        // Conversation filter
        if (! empty($criteria['conversation_id'])) {
            $query->where('ai_conversation_id', $criteria['conversation_id']);
        }

        // Date range
        if (! empty($criteria['created_after'])) {
            $query->where('created_at', '>=', Carbon::parse($criteria['created_after']));
        }

        if (! empty($criteria['created_before'])) {
            $query->where('created_at', '<=', Carbon::parse($criteria['created_before']));
        }

        // Content type filter
        if (! empty($criteria['content_type'])) {
            $query->where('content_type', $criteria['content_type']);
        }

        // Has tokens/cost filters
        if (! empty($criteria['has_tokens'])) {
            $query->whereNotNull('total_tokens')->where('total_tokens', '>', 0);
        }

        if (! empty($criteria['has_cost'])) {
            $query->whereNotNull('cost')->where('cost', '>', 0);
        }
    }

    /**
     * Apply sorting to query.
     */
    protected function applySorting(Builder $query, string $sort): void
    {
        switch ($sort) {
            case 'recent':
                $query->orderByDesc('last_activity_at');
                break;
            case 'oldest':
                $query->orderBy('created_at');
                break;
            case 'title':
                $query->orderBy('title');
                break;
            case 'messages':
                $query->orderByDesc('total_messages');
                break;
            case 'cost':
                $query->orderByDesc('total_cost');
                break;
            case 'rating':
                $query->orderByDesc('avg_quality_rating');
                break;
            default:
                $query->orderByDesc('last_activity_at');
        }
    }

    /**
     * Get provider breakdown for statistics.
     */
    protected function getProviderBreakdown(Builder $query): array
    {
        return $query->select('provider_name', DB::raw('count(*) as count'))
            ->whereNotNull('provider_name')
            ->groupBy('provider_name')
            ->orderByDesc('count')
            ->pluck('count', 'provider_name')
            ->toArray();
    }

    /**
     * Get conversation type breakdown for statistics.
     */
    protected function getConversationTypeBreakdown(Builder $query): array
    {
        return $query->select('conversation_type', DB::raw('count(*) as count'))
            ->groupBy('conversation_type')
            ->orderByDesc('count')
            ->pluck('count', 'conversation_type')
            ->toArray();
    }
}
