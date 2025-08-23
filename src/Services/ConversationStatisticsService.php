<?php

namespace JTD\LaravelAI\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use JTD\LaravelAI\Models\AIConversation;
use JTD\LaravelAI\Models\AIMessageRecord;

/**
 * Conversation Statistics Service
 *
 * Provides comprehensive statistics and analytics for conversations,
 * messages, costs, and performance metrics with caching support.
 */
class ConversationStatisticsService
{
    /**
     * Cache duration for statistics (in minutes).
     */
    protected int $cacheDuration = 60;

    /**
     * Get overall conversation statistics.
     */
    public function getOverallStatistics(array $filters = []): array
    {
        $cacheKey = 'conversation_stats_overall_' . md5(serialize($filters));

        return Cache::remember($cacheKey, $this->cacheDuration, function () use ($filters) {
            $query = AIConversation::query();
            $this->applyFilters($query, $filters);

            $stats = $query->selectRaw('
                COUNT(*) as total_conversations,
                SUM(total_messages) as total_messages,
                SUM(total_input_tokens) as total_input_tokens,
                SUM(total_output_tokens) as total_output_tokens,
                SUM(total_cost) as total_cost,
                AVG(total_messages) as avg_messages_per_conversation,
                AVG(total_cost) as avg_cost_per_conversation,
                AVG(avg_response_time_ms) as avg_response_time,
                SUM(successful_requests) as total_successful_requests,
                SUM(failed_requests) as total_failed_requests
            ')->first();

            return [
                'conversations' => [
                    'total' => (int) $stats->total_conversations,
                    'active' => $this->getActiveConversationsCount($filters),
                    'archived' => $this->getArchivedConversationsCount($filters),
                ],
                'messages' => [
                    'total' => (int) $stats->total_messages,
                    'avg_per_conversation' => round($stats->avg_messages_per_conversation ?? 0, 2),
                    'user_messages' => $this->getUserMessagesCount($filters),
                    'assistant_messages' => $this->getAssistantMessagesCount($filters),
                ],
                'tokens' => [
                    'total_input' => (int) $stats->total_input_tokens,
                    'total_output' => (int) $stats->total_output_tokens,
                    'total' => (int) ($stats->total_input_tokens + $stats->total_output_tokens),
                    'avg_per_conversation' => $this->getAverageTokensPerConversation($filters),
                ],
                'costs' => [
                    'total' => round($stats->total_cost ?? 0, 6),
                    'avg_per_conversation' => round($stats->avg_cost_per_conversation ?? 0, 6),
                    'avg_per_message' => $this->getAverageCostPerMessage($filters),
                    'currency' => 'USD',
                ],
                'performance' => [
                    'avg_response_time_ms' => round($stats->avg_response_time ?? 0, 2),
                    'success_rate' => $this->calculateSuccessRate($stats),
                    'total_requests' => (int) ($stats->total_successful_requests + $stats->total_failed_requests),
                ],
                'period' => $this->getPeriodInfo($filters),
            ];
        });
    }

    /**
     * Get statistics by provider.
     */
    public function getProviderStatistics(array $filters = []): array
    {
        $cacheKey = 'conversation_stats_providers_' . md5(serialize($filters));

        return Cache::remember($cacheKey, $this->cacheDuration, function () use ($filters) {
            $query = AIConversation::query();
            $this->applyFilters($query, $filters);

            return $query->selectRaw('
                provider_name,
                COUNT(*) as conversation_count,
                SUM(total_messages) as total_messages,
                SUM(total_cost) as total_cost,
                AVG(avg_response_time_ms) as avg_response_time,
                SUM(successful_requests) as successful_requests,
                SUM(failed_requests) as failed_requests
            ')
                ->whereNotNull('provider_name')
                ->groupBy('provider_name')
                ->orderByDesc('conversation_count')
                ->get()
                ->map(function ($stat) {
                    return [
                        'provider' => $stat->provider_name,
                        'conversations' => (int) $stat->conversation_count,
                        'messages' => (int) $stat->total_messages,
                        'cost' => round($stat->total_cost ?? 0, 6),
                        'avg_response_time_ms' => round($stat->avg_response_time ?? 0, 2),
                        'success_rate' => $this->calculateSuccessRate($stat),
                    ];
                })
                ->toArray();
        });
    }

    /**
     * Get statistics by model.
     */
    public function getModelStatistics(array $filters = []): array
    {
        $cacheKey = 'conversation_stats_models_' . md5(serialize($filters));

        return Cache::remember($cacheKey, $this->cacheDuration, function () use ($filters) {
            $query = AIConversation::query();
            $this->applyFilters($query, $filters);

            return $query->selectRaw('
                provider_name,
                model_name,
                COUNT(*) as conversation_count,
                SUM(total_messages) as total_messages,
                SUM(total_cost) as total_cost,
                AVG(avg_response_time_ms) as avg_response_time,
                SUM(successful_requests) as successful_requests,
                SUM(failed_requests) as failed_requests
            ')
                ->whereNotNull('model_name')
                ->groupBy('provider_name', 'model_name')
                ->orderByDesc('conversation_count')
                ->get()
                ->map(function ($stat) {
                    return [
                        'provider' => $stat->provider_name,
                        'model' => $stat->model_name,
                        'conversations' => (int) $stat->conversation_count,
                        'messages' => (int) $stat->total_messages,
                        'cost' => round($stat->total_cost ?? 0, 6),
                        'avg_response_time_ms' => round($stat->avg_response_time ?? 0, 2),
                        'success_rate' => $this->calculateSuccessRate($stat),
                    ];
                })
                ->toArray();
        });
    }

    /**
     * Get time-series statistics.
     */
    public function getTimeSeriesStatistics(string $period = 'day', array $filters = []): array
    {
        $cacheKey = "conversation_stats_timeseries_{$period}_" . md5(serialize($filters));

        return Cache::remember($cacheKey, $this->cacheDuration, function () use ($period, $filters) {
            $query = AIConversation::query();
            $this->applyFilters($query, $filters);

            $dateFormat = $this->getDateFormat($period);
            $groupBy = $this->getGroupByClause($period);

            return $query->selectRaw("
                {$groupBy} as period,
                COUNT(*) as conversations,
                SUM(total_messages) as messages,
                SUM(total_cost) as cost,
                AVG(avg_response_time_ms) as avg_response_time
            ")
                ->groupBy(DB::raw($groupBy))
                ->orderBy(DB::raw($groupBy))
                ->get()
                ->map(function ($stat) {
                    return [
                        'period' => $stat->period,
                        'conversations' => (int) $stat->conversations,
                        'messages' => (int) $stat->messages,
                        'cost' => round($stat->cost ?? 0, 6),
                        'avg_response_time_ms' => round($stat->avg_response_time ?? 0, 2),
                    ];
                })
                ->toArray();
        });
    }

    /**
     * Get conversation type statistics.
     */
    public function getConversationTypeStatistics(array $filters = []): array
    {
        $cacheKey = 'conversation_stats_types_' . md5(serialize($filters));

        return Cache::remember($cacheKey, $this->cacheDuration, function () use ($filters) {
            $query = AIConversation::query();
            $this->applyFilters($query, $filters);

            return $query->selectRaw('
                conversation_type,
                COUNT(*) as count,
                SUM(total_messages) as total_messages,
                SUM(total_cost) as total_cost,
                AVG(total_messages) as avg_messages
            ')
                ->groupBy('conversation_type')
                ->orderByDesc('count')
                ->get()
                ->map(function ($stat) {
                    return [
                        'type' => $stat->conversation_type,
                        'count' => (int) $stat->count,
                        'total_messages' => (int) $stat->total_messages,
                        'total_cost' => round($stat->total_cost ?? 0, 6),
                        'avg_messages' => round($stat->avg_messages ?? 0, 2),
                    ];
                })
                ->toArray();
        });
    }

    /**
     * Get user activity statistics.
     */
    public function getUserActivityStatistics(array $filters = []): array
    {
        $cacheKey = 'conversation_stats_users_' . md5(serialize($filters));

        return Cache::remember($cacheKey, $this->cacheDuration, function () use ($filters) {
            $query = AIConversation::query();
            $this->applyFilters($query, $filters);

            return $query->selectRaw('
                user_id,
                user_type,
                COUNT(*) as conversation_count,
                SUM(total_messages) as total_messages,
                SUM(total_cost) as total_cost,
                MAX(last_activity_at) as last_activity
            ')
                ->whereNotNull('user_id')
                ->groupBy('user_id', 'user_type')
                ->orderByDesc('conversation_count')
                ->limit(50) // Top 50 most active users
                ->get()
                ->map(function ($stat) {
                    return [
                        'user_id' => $stat->user_id,
                        'user_type' => $stat->user_type,
                        'conversations' => (int) $stat->conversation_count,
                        'messages' => (int) $stat->total_messages,
                        'cost' => round($stat->total_cost ?? 0, 6),
                        'last_activity' => $stat->last_activity,
                    ];
                })
                ->toArray();
        });
    }

    /**
     * Get cost breakdown statistics.
     */
    public function getCostBreakdown(array $filters = []): array
    {
        $cacheKey = 'conversation_stats_costs_' . md5(serialize($filters));

        return Cache::remember($cacheKey, $this->cacheDuration, function () use ($filters) {
            $query = AIConversation::query();
            $this->applyFilters($query, $filters);

            $totalCost = $query->sum('total_cost');

            $providerCosts = $query->selectRaw('
                provider_name,
                SUM(total_cost) as cost,
                COUNT(*) as conversations
            ')
                ->whereNotNull('provider_name')
                ->groupBy('provider_name')
                ->get()
                ->map(function ($stat) use ($totalCost) {
                    $cost = $stat->cost ?? 0;

                    return [
                        'provider' => $stat->provider_name,
                        'cost' => round($cost, 6),
                        'percentage' => $totalCost > 0 ? round(($cost / $totalCost) * 100, 2) : 0,
                        'conversations' => (int) $stat->conversations,
                    ];
                })
                ->toArray();

            return [
                'total_cost' => round($totalCost, 6),
                'currency' => 'USD',
                'by_provider' => $providerCosts,
                'cost_per_token' => $this->calculateCostPerToken($filters),
                'cost_per_message' => $this->getAverageCostPerMessage($filters),
            ];
        });
    }

    /**
     * Get performance metrics.
     */
    public function getPerformanceMetrics(array $filters = []): array
    {
        $cacheKey = 'conversation_stats_performance_' . md5(serialize($filters));

        return Cache::remember($cacheKey, $this->cacheDuration, function () use ($filters) {
            $query = AIConversation::query();
            $this->applyFilters($query, $filters);

            $stats = $query->selectRaw('
                AVG(avg_response_time_ms) as avg_response_time,
                MIN(avg_response_time_ms) as min_response_time,
                MAX(avg_response_time_ms) as max_response_time,
                SUM(successful_requests) as successful_requests,
                SUM(failed_requests) as failed_requests,
                AVG(avg_quality_rating) as avg_quality_rating
            ')->first();

            return [
                'response_time' => [
                    'average_ms' => round($stats->avg_response_time ?? 0, 2),
                    'min_ms' => round($stats->min_response_time ?? 0, 2),
                    'max_ms' => round($stats->max_response_time ?? 0, 2),
                ],
                'reliability' => [
                    'success_rate' => $this->calculateSuccessRate($stats),
                    'successful_requests' => (int) $stats->successful_requests,
                    'failed_requests' => (int) $stats->failed_requests,
                    'total_requests' => (int) ($stats->successful_requests + $stats->failed_requests),
                ],
                'quality' => [
                    'avg_rating' => round($stats->avg_quality_rating ?? 0, 2),
                    'rated_conversations' => $this->getRatedConversationsCount($filters),
                ],
            ];
        });
    }

    /**
     * Apply filters to query.
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['provider_name'])) {
            $query->where('provider_name', $filters['provider_name']);
        }

        if (! empty($filters['model_name'])) {
            $query->where('model_name', $filters['model_name']);
        }

        if (! empty($filters['conversation_type'])) {
            $query->where('conversation_type', $filters['conversation_type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['date_from']));
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['date_to']));
        }
    }

    /**
     * Calculate success rate from statistics.
     */
    protected function calculateSuccessRate($stats): float
    {
        $successful = $stats->successful_requests ?? 0;
        $failed = $stats->failed_requests ?? 0;
        $total = $successful + $failed;

        return $total > 0 ? round(($successful / $total) * 100, 2) : 0.0;
    }

    /**
     * Get date format for time series.
     */
    protected function getDateFormat(string $period): string
    {
        return match ($period) {
            'hour' => '%Y-%m-%d %H:00:00',
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            'year' => '%Y',
            default => '%Y-%m-%d',
        };
    }

    /**
     * Get GROUP BY clause for time series.
     */
    protected function getGroupByClause(string $period): string
    {
        return match ($period) {
            'hour' => "DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00')",
            'day' => 'DATE(created_at)',
            'week' => 'YEARWEEK(created_at)',
            'month' => "DATE_FORMAT(created_at, '%Y-%m')",
            'year' => 'YEAR(created_at)',
            default => 'DATE(created_at)',
        };
    }

    /**
     * Helper methods for specific counts.
     */
    protected function getActiveConversationsCount(array $filters): int
    {
        $query = AIConversation::where('status', AIConversation::STATUS_ACTIVE);
        $this->applyFilters($query, $filters);

        return $query->count();
    }

    protected function getArchivedConversationsCount(array $filters): int
    {
        $query = AIConversation::where('status', AIConversation::STATUS_ARCHIVED);
        $this->applyFilters($query, $filters);

        return $query->count();
    }

    protected function getUserMessagesCount(array $filters): int
    {
        return AIMessageRecord::whereHas('conversation', function ($q) use ($filters) {
            $this->applyFilters($q, $filters);
        })->where('role', 'user')->count();
    }

    protected function getAssistantMessagesCount(array $filters): int
    {
        return AIMessageRecord::whereHas('conversation', function ($q) use ($filters) {
            $this->applyFilters($q, $filters);
        })->where('role', 'assistant')->count();
    }

    protected function getAverageTokensPerConversation(array $filters): float
    {
        $query = AIConversation::query();
        $this->applyFilters($query, $filters);

        $avg = $query->selectRaw('AVG(total_input_tokens + total_output_tokens) as avg_tokens')->first();

        return round($avg->avg_tokens ?? 0, 2);
    }

    protected function getAverageCostPerMessage(array $filters): float
    {
        $query = AIConversation::query();
        $this->applyFilters($query, $filters);

        $stats = $query->selectRaw('SUM(total_cost) as total_cost, SUM(total_messages) as total_messages')->first();

        return $stats->total_messages > 0 ? round($stats->total_cost / $stats->total_messages, 6) : 0.0;
    }

    protected function calculateCostPerToken(array $filters): float
    {
        $query = AIConversation::query();
        $this->applyFilters($query, $filters);

        $stats = $query->selectRaw('
            SUM(total_cost) as total_cost, 
            SUM(total_input_tokens + total_output_tokens) as total_tokens
        ')->first();

        return $stats->total_tokens > 0 ? round($stats->total_cost / $stats->total_tokens, 8) : 0.0;
    }

    protected function getRatedConversationsCount(array $filters): int
    {
        $query = AIConversation::whereNotNull('avg_quality_rating');
        $this->applyFilters($query, $filters);

        return $query->count();
    }

    protected function getPeriodInfo(array $filters): array
    {
        $query = AIConversation::query();
        $this->applyFilters($query, $filters);

        $dates = $query->selectRaw('MIN(created_at) as start_date, MAX(last_activity_at) as end_date')->first();

        return [
            'start_date' => $dates->start_date,
            'end_date' => $dates->end_date,
            'duration_days' => $dates->start_date && $dates->end_date
                ? Carbon::parse($dates->start_date)->diffInDays(Carbon::parse($dates->end_date))
                : 0,
        ];
    }

    /**
     * Clear statistics cache.
     */
    public function clearCache(): void
    {
        $patterns = [
            'conversation_stats_overall_*',
            'conversation_stats_providers_*',
            'conversation_stats_models_*',
            'conversation_stats_timeseries_*',
            'conversation_stats_types_*',
            'conversation_stats_users_*',
            'conversation_stats_costs_*',
            'conversation_stats_performance_*',
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }
}
