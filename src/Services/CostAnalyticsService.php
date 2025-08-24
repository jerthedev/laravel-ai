<?php

namespace JTD\LaravelAI\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Cost Analytics Service
 *
 * Provides comprehensive cost analytics by provider, model, and user with
 * historical data preservation and trend analysis using event sourcing patterns.
 */
class CostAnalyticsService
{
    /**
     * Default cache TTL for analytics data (15 minutes).
     */
    protected int $defaultCacheTtl = 900;

    /**
     * Get cost breakdown analytics by provider.
     *
     * @param  int|null  $userId  User ID filter
     * @param  string|null  $dateRange  Date range (today, week, month, year, custom)
     * @param  array  $dateFilter  Custom date filter [start, end]
     * @return array Provider cost breakdown
     */
    public function getCostBreakdownByProvider(?int $userId = null, ?string $dateRange = 'month', array $dateFilter = []): array
    {
        $cacheKey = "cost_breakdown_provider_{$userId}_{$dateRange}_" . md5(json_encode($dateFilter));

        return Cache::remember($cacheKey, $this->defaultCacheTtl, function () use ($userId, $dateRange, $dateFilter) {
            $query = DB::table('ai_usage_costs')
                ->select([
                    'provider',
                    DB::raw('COUNT(*) as request_count'),
                    DB::raw('SUM(total_cost) as total_cost'),
                    DB::raw('SUM(input_tokens) as total_input_tokens'),
                    DB::raw('SUM(output_tokens) as total_output_tokens'),
                    DB::raw('SUM(total_tokens) as total_tokens'),
                    DB::raw('AVG(total_cost) as avg_cost_per_request'),
                    DB::raw('AVG(processing_time_ms) as avg_processing_time'),
                ])
                ->groupBy('provider');

            $this->applyFilters($query, $userId, $dateRange, $dateFilter);

            $results = $query->orderBy('total_cost', 'desc')->get();

            return [
                'breakdown' => $results->map(function ($item) {
                    return [
                        'provider' => $item->provider,
                        'request_count' => (int) $item->request_count,
                        'total_cost' => (float) $item->total_cost,
                        'total_input_tokens' => (int) $item->total_input_tokens,
                        'total_output_tokens' => (int) $item->total_output_tokens,
                        'total_tokens' => (int) $item->total_tokens,
                        'avg_cost_per_request' => (float) $item->avg_cost_per_request,
                        'avg_processing_time_ms' => (float) $item->avg_processing_time,
                        'cost_per_1k_tokens' => $item->total_tokens > 0
                            ? ($item->total_cost / $item->total_tokens) * 1000
                            : 0,
                    ];
                })->toArray(),
                'totals' => $this->calculateTotals($results),
                'metadata' => [
                    'user_id' => $userId,
                    'date_range' => $dateRange,
                    'date_filter' => $dateFilter,
                    'generated_at' => now()->toISOString(),
                ],
            ];
        });
    }

    /**
     * Get cost breakdown analytics by model.
     *
     * @param  int|null  $userId  User ID filter
     * @param  string|null  $provider  Provider filter
     * @param  string|null  $dateRange  Date range
     * @param  array  $dateFilter  Custom date filter
     * @return array Model cost breakdown
     */
    public function getCostBreakdownByModel(?int $userId = null, ?string $provider = null, ?string $dateRange = 'month', array $dateFilter = []): array
    {
        $cacheKey = "cost_breakdown_model_{$userId}_{$provider}_{$dateRange}_" . md5(json_encode($dateFilter));

        return Cache::remember($cacheKey, $this->defaultCacheTtl, function () use ($userId, $provider, $dateRange, $dateFilter) {
            $query = DB::table('ai_usage_costs')
                ->select([
                    'provider',
                    'model',
                    DB::raw('COUNT(*) as request_count'),
                    DB::raw('SUM(total_cost) as total_cost'),
                    DB::raw('SUM(input_tokens) as total_input_tokens'),
                    DB::raw('SUM(output_tokens) as total_output_tokens'),
                    DB::raw('SUM(total_tokens) as total_tokens'),
                    DB::raw('AVG(total_cost) as avg_cost_per_request'),
                    DB::raw('AVG(processing_time_ms) as avg_processing_time'),
                    DB::raw('MIN(created_at) as first_used'),
                    DB::raw('MAX(created_at) as last_used'),
                ])
                ->groupBy('provider', 'model');

            if ($provider) {
                $query->where('provider', $provider);
            }

            $this->applyFilters($query, $userId, $dateRange, $dateFilter);

            $results = $query->orderBy('total_cost', 'desc')->get();

            return [
                'breakdown' => $results->map(function ($item) {
                    return [
                        'provider' => $item->provider,
                        'model' => $item->model,
                        'request_count' => (int) $item->request_count,
                        'total_cost' => (float) $item->total_cost,
                        'total_input_tokens' => (int) $item->total_input_tokens,
                        'total_output_tokens' => (int) $item->total_output_tokens,
                        'total_tokens' => (int) $item->total_tokens,
                        'avg_cost_per_request' => (float) $item->avg_cost_per_request,
                        'avg_processing_time_ms' => (float) $item->avg_processing_time,
                        'cost_per_1k_tokens' => $item->total_tokens > 0
                            ? ($item->total_cost / $item->total_tokens) * 1000
                            : 0,
                        'first_used' => $item->first_used,
                        'last_used' => $item->last_used,
                    ];
                })->toArray(),
                'totals' => $this->calculateTotals($results),
                'metadata' => [
                    'user_id' => $userId,
                    'provider_filter' => $provider,
                    'date_range' => $dateRange,
                    'date_filter' => $dateFilter,
                    'generated_at' => now()->toISOString(),
                ],
            ];
        });
    }

    /**
     * Get cost breakdown analytics by user.
     *
     * @param  array  $userIds  User IDs to include
     * @param  string|null  $dateRange  Date range
     * @param  array  $dateFilter  Custom date filter
     * @return array User cost breakdown
     */
    public function getCostBreakdownByUser(array $userIds = [], ?string $dateRange = 'month', array $dateFilter = []): array
    {
        $cacheKey = "cost_breakdown_user_" . md5(json_encode($userIds)) . "_{$dateRange}_" . md5(json_encode($dateFilter));

        return Cache::remember($cacheKey, $this->defaultCacheTtl, function () use ($userIds, $dateRange, $dateFilter) {
            $query = DB::table('ai_usage_costs')
                ->select([
                    'user_id',
                    DB::raw('COUNT(*) as request_count'),
                    DB::raw('SUM(total_cost) as total_cost'),
                    DB::raw('SUM(input_tokens) as total_input_tokens'),
                    DB::raw('SUM(output_tokens) as total_output_tokens'),
                    DB::raw('SUM(total_tokens) as total_tokens'),
                    DB::raw('AVG(total_cost) as avg_cost_per_request'),
                    DB::raw('COUNT(DISTINCT provider) as providers_used'),
                    DB::raw('COUNT(DISTINCT model) as models_used'),
                    DB::raw('MIN(created_at) as first_request'),
                    DB::raw('MAX(created_at) as last_request'),
                ])
                ->groupBy('user_id');

            if (!empty($userIds)) {
                $query->whereIn('user_id', $userIds);
            }

            $this->applyFilters($query, null, $dateRange, $dateFilter);

            $results = $query->orderBy('total_cost', 'desc')->get();

            return [
                'breakdown' => $results->map(function ($item) {
                    return [
                        'user_id' => (int) $item->user_id,
                        'request_count' => (int) $item->request_count,
                        'total_cost' => (float) $item->total_cost,
                        'total_input_tokens' => (int) $item->total_input_tokens,
                        'total_output_tokens' => (int) $item->total_output_tokens,
                        'total_tokens' => (int) $item->total_tokens,
                        'avg_cost_per_request' => (float) $item->avg_cost_per_request,
                        'providers_used' => (int) $item->providers_used,
                        'models_used' => (int) $item->models_used,
                        'cost_per_1k_tokens' => $item->total_tokens > 0
                            ? ($item->total_cost / $item->total_tokens) * 1000
                            : 0,
                        'first_request' => $item->first_request,
                        'last_request' => $item->last_request,
                    ];
                })->toArray(),
                'totals' => $this->calculateTotals($results),
                'metadata' => [
                    'user_ids_filter' => $userIds,
                    'date_range' => $dateRange,
                    'date_filter' => $dateFilter,
                    'generated_at' => now()->toISOString(),
                ],
            ];
        });
    }

    /**
     * Get historical cost trends over time.
     *
     * @param  int|null  $userId  User ID filter
     * @param  string  $groupBy  Group by period (hour, day, week, month)
     * @param  string|null  $dateRange  Date range
     * @param  array  $dateFilter  Custom date filter
     * @return array Historical trends
     */
    public function getHistoricalTrends(?int $userId = null, string $groupBy = 'day', ?string $dateRange = 'month', array $dateFilter = []): array
    {
        $cacheKey = "historical_trends_{$userId}_{$groupBy}_{$dateRange}_" . md5(json_encode($dateFilter));

        return Cache::remember($cacheKey, $this->defaultCacheTtl, function () use ($userId, $groupBy, $dateRange, $dateFilter) {
            $dateFormat = $this->getDateFormat($groupBy);

            $query = DB::table('ai_usage_costs')
                ->select([
                    DB::raw("DATE_FORMAT(created_at, '{$dateFormat}') as period"),
                    DB::raw('COUNT(*) as request_count'),
                    DB::raw('SUM(total_cost) as total_cost'),
                    DB::raw('SUM(total_tokens) as total_tokens'),
                    DB::raw('AVG(total_cost) as avg_cost'),
                    DB::raw('COUNT(DISTINCT user_id) as unique_users'),
                    DB::raw('COUNT(DISTINCT provider) as providers_used'),
                ])
                ->groupBy('period');

            $this->applyFilters($query, $userId, $dateRange, $dateFilter);

            $results = $query->orderBy('period')->get();

            return [
                'trends' => $results->map(function ($item) {
                    return [
                        'period' => $item->period,
                        'request_count' => (int) $item->request_count,
                        'total_cost' => (float) $item->total_cost,
                        'total_tokens' => (int) $item->total_tokens,
                        'avg_cost' => (float) $item->avg_cost,
                        'unique_users' => (int) $item->unique_users,
                        'providers_used' => (int) $item->providers_used,
                        'cost_per_1k_tokens' => $item->total_tokens > 0
                            ? ($item->total_cost / $item->total_tokens) * 1000
                            : 0,
                    ];
                })->toArray(),
                'summary' => $this->calculateTrendSummary($results),
                'metadata' => [
                    'user_id' => $userId,
                    'group_by' => $groupBy,
                    'date_range' => $dateRange,
                    'date_filter' => $dateFilter,
                    'generated_at' => now()->toISOString(),
                ],
            ];
        });
    }

    /**
     * Get cost efficiency metrics comparing providers and models.
     *
     * @param  int|null  $userId  User ID filter
     * @param  string|null  $dateRange  Date range
     * @param  array  $dateFilter  Custom date filter
     * @return array Efficiency metrics
     */
    public function getCostEfficiencyMetrics(?int $userId = null, ?string $dateRange = 'month', array $dateFilter = []): array
    {
        $cacheKey = "cost_efficiency_{$userId}_{$dateRange}_" . md5(json_encode($dateFilter));

        return Cache::remember($cacheKey, $this->defaultCacheTtl, function () use ($userId, $dateRange, $dateFilter) {
            $query = DB::table('ai_usage_costs')
                ->select([
                    'provider',
                    'model',
                    DB::raw('COUNT(*) as request_count'),
                    DB::raw('SUM(total_cost) as total_cost'),
                    DB::raw('SUM(total_tokens) as total_tokens'),
                    DB::raw('AVG(processing_time_ms) as avg_processing_time'),
                    DB::raw('SUM(total_cost) / SUM(total_tokens) * 1000 as cost_per_1k_tokens'),
                    DB::raw('SUM(total_tokens) / AVG(processing_time_ms) * 1000 as tokens_per_second'),
                ])
                ->groupBy('provider', 'model')
                ->having('request_count', '>=', 10); // Minimum requests for meaningful metrics

            $this->applyFilters($query, $userId, $dateRange, $dateFilter);

            $results = $query->get();

            // Calculate efficiency scores
            $efficiency = $results->map(function ($item) {
                $costEfficiency = $item->cost_per_1k_tokens > 0 ? 1 / $item->cost_per_1k_tokens : 0;
                $speedEfficiency = $item->tokens_per_second ?? 0;

                return [
                    'provider' => $item->provider,
                    'model' => $item->model,
                    'request_count' => (int) $item->request_count,
                    'total_cost' => (float) $item->total_cost,
                    'total_tokens' => (int) $item->total_tokens,
                    'cost_per_1k_tokens' => (float) $item->cost_per_1k_tokens,
                    'tokens_per_second' => (float) $item->tokens_per_second,
                    'avg_processing_time_ms' => (float) $item->avg_processing_time,
                    'cost_efficiency_score' => round($costEfficiency * 1000, 2),
                    'speed_efficiency_score' => round($speedEfficiency, 2),
                    'overall_efficiency_score' => round(($costEfficiency * 1000 + $speedEfficiency) / 2, 2),
                ];
            })->sortByDesc('overall_efficiency_score')->values()->toArray();

            return [
                'efficiency_metrics' => $efficiency,
                'recommendations' => $this->generateEfficiencyRecommendations($efficiency),
                'metadata' => [
                    'user_id' => $userId,
                    'date_range' => $dateRange,
                    'date_filter' => $dateFilter,
                    'generated_at' => now()->toISOString(),
                ],
            ];
        });
    }

    /**
     * Get conversation-level cost analytics.
     *
     * @param  int|null  $userId  User ID filter
     * @param  string|null  $dateRange  Date range
     * @param  array  $dateFilter  Custom date filter
     * @return array Conversation analytics
     */
    public function getConversationAnalytics(?int $userId = null, ?string $dateRange = 'month', array $dateFilter = []): array
    {
        $cacheKey = "conversation_analytics_{$userId}_{$dateRange}_" . md5(json_encode($dateFilter));

        return Cache::remember($cacheKey, $this->defaultCacheTtl, function () use ($userId, $dateRange, $dateFilter) {
            $query = DB::table('ai_usage_costs')
                ->select([
                    'conversation_id',
                    'user_id',
                    DB::raw('COUNT(*) as message_count'),
                    DB::raw('SUM(total_cost) as total_cost'),
                    DB::raw('SUM(total_tokens) as total_tokens'),
                    DB::raw('AVG(total_cost) as avg_cost_per_message'),
                    DB::raw('MIN(created_at) as conversation_start'),
                    DB::raw('MAX(created_at) as conversation_end'),
                    DB::raw('TIMESTAMPDIFF(MINUTE, MIN(created_at), MAX(created_at)) as duration_minutes'),
                ])
                ->whereNotNull('conversation_id')
                ->groupBy('conversation_id', 'user_id');

            $this->applyFilters($query, $userId, $dateRange, $dateFilter);

            $results = $query->orderBy('total_cost', 'desc')->limit(100)->get();

            return [
                'conversations' => $results->map(function ($item) {
                    return [
                        'conversation_id' => $item->conversation_id,
                        'user_id' => (int) $item->user_id,
                        'message_count' => (int) $item->message_count,
                        'total_cost' => (float) $item->total_cost,
                        'total_tokens' => (int) $item->total_tokens,
                        'avg_cost_per_message' => (float) $item->avg_cost_per_message,
                        'cost_per_1k_tokens' => $item->total_tokens > 0
                            ? ($item->total_cost / $item->total_tokens) * 1000
                            : 0,
                        'conversation_start' => $item->conversation_start,
                        'conversation_end' => $item->conversation_end,
                        'duration_minutes' => (int) $item->duration_minutes,
                        'cost_per_minute' => $item->duration_minutes > 0
                            ? $item->total_cost / $item->duration_minutes
                            : 0,
                    ];
                })->toArray(),
                'summary' => $this->calculateConversationSummary($results),
                'metadata' => [
                    'user_id' => $userId,
                    'date_range' => $dateRange,
                    'date_filter' => $dateFilter,
                    'generated_at' => now()->toISOString(),
                ],
            ];
        });
    }

    /**
     * Apply common filters to query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query  Query builder
     * @param  int|null  $userId  User ID filter
     * @param  string|null  $dateRange  Date range
     * @param  array  $dateFilter  Custom date filter
     */
    protected function applyFilters($query, ?int $userId, ?string $dateRange, array $dateFilter): void
    {
        if ($userId) {
            $query->where('user_id', $userId);
        }

        if (!empty($dateFilter) && count($dateFilter) === 2) {
            $query->whereBetween('created_at', $dateFilter);
        } elseif ($dateRange) {
            $this->applyDateRangeFilter($query, $dateRange);
        }
    }

    /**
     * Apply date range filter to query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query  Query builder
     * @param  string  $dateRange  Date range
     */
    protected function applyDateRangeFilter($query, string $dateRange): void
    {
        $now = Carbon::now();

        match ($dateRange) {
            'today' => $query->whereDate('created_at', $now->toDateString()),
            'yesterday' => $query->whereDate('created_at', $now->subDay()->toDateString()),
            'week' => $query->where('created_at', '>=', $now->startOfWeek()),
            'month' => $query->where('created_at', '>=', $now->startOfMonth()),
            'quarter' => $query->where('created_at', '>=', $now->startOfQuarter()),
            'year' => $query->where('created_at', '>=', $now->startOfYear()),
            'last_7_days' => $query->where('created_at', '>=', $now->subDays(7)),
            'last_30_days' => $query->where('created_at', '>=', $now->subDays(30)),
            'last_90_days' => $query->where('created_at', '>=', $now->subDays(90)),
            default => $query->where('created_at', '>=', $now->startOfMonth()),
        };
    }

    /**
     * Get date format for grouping.
     *
     * @param  string  $groupBy  Group by period
     * @return string Date format
     */
    protected function getDateFormat(string $groupBy): string
    {
        return match ($groupBy) {
            'hour' => '%Y-%m-%d %H:00:00',
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            'year' => '%Y',
            default => '%Y-%m-%d',
        };
    }

    /**
     * Calculate totals from results.
     *
     * @param  \Illuminate\Support\Collection  $results  Query results
     * @return array Calculated totals
     */
    protected function calculateTotals($results): array
    {
        return [
            'total_requests' => $results->sum('request_count'),
            'total_cost' => (float) $results->sum('total_cost'),
            'total_tokens' => $results->sum('total_input_tokens') + $results->sum('total_output_tokens'),
            'avg_cost_per_request' => $results->count() > 0 ? $results->avg('avg_cost_per_request') : 0,
            'unique_providers' => $results->pluck('provider')->unique()->count(),
        ];
    }

    /**
     * Calculate trend summary.
     *
     * @param  \Illuminate\Support\Collection  $results  Trend results
     * @return array Trend summary
     */
    protected function calculateTrendSummary($results): array
    {
        if ($results->count() < 2) {
            return ['trend' => 'insufficient_data'];
        }

        $first = $results->first();
        $last = $results->last();

        $costChange = $last->total_cost - $first->total_cost;
        $costChangePercent = $first->total_cost > 0 ? ($costChange / $first->total_cost) * 100 : 0;

        return [
            'trend' => $costChange > 0 ? 'increasing' : ($costChange < 0 ? 'decreasing' : 'stable'),
            'cost_change' => (float) $costChange,
            'cost_change_percent' => round($costChangePercent, 2),
            'total_periods' => $results->count(),
            'avg_cost_per_period' => (float) $results->avg('total_cost'),
            'peak_cost_period' => $results->sortByDesc('total_cost')->first()->period,
            'peak_cost_amount' => (float) $results->max('total_cost'),
        ];
    }

    /**
     * Generate efficiency recommendations.
     *
     * @param  array  $efficiency  Efficiency metrics
     * @return array Recommendations
     */
    protected function generateEfficiencyRecommendations(array $efficiency): array
    {
        $recommendations = [];

        if (count($efficiency) > 1) {
            $best = $efficiency[0];
            $worst = end($efficiency);

            $recommendations[] = [
                'type' => 'cost_optimization',
                'message' => "Consider using {$best['provider']}/{$best['model']} more often. It has {$best['cost_efficiency_score']}x better cost efficiency than {$worst['provider']}/{$worst['model']}.",
                'priority' => 'high',
                'potential_savings' => $this->calculatePotentialSavings($best, $worst, $efficiency),
            ];

            // Speed recommendations
            $fastest = collect($efficiency)->sortByDesc('speed_efficiency_score')->first();
            if ($fastest['speed_efficiency_score'] > $best['speed_efficiency_score']) {
                $recommendations[] = [
                    'type' => 'performance_optimization',
                    'message' => "For faster responses, consider {$fastest['provider']}/{$fastest['model']} which processes {$fastest['tokens_per_second']} tokens/second.",
                    'priority' => 'medium',
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Calculate potential savings.
     *
     * @param  array  $best  Best performing model
     * @param  array  $worst  Worst performing model
     * @param  array  $efficiency  All efficiency data
     * @return float Potential savings
     */
    protected function calculatePotentialSavings(array $best, array $worst, array $efficiency): float
    {
        $totalCost = collect($efficiency)->sum('total_cost');
        $worstCostRatio = $worst['total_cost'] / $totalCost;
        $costDifference = $worst['cost_per_1k_tokens'] - $best['cost_per_1k_tokens'];

        return $worstCostRatio * $costDifference * ($worst['total_tokens'] / 1000);
    }

    /**
     * Calculate conversation summary.
     *
     * @param  \Illuminate\Support\Collection  $results  Conversation results
     * @return array Conversation summary
     */
    protected function calculateConversationSummary($results): array
    {
        return [
            'total_conversations' => $results->count(),
            'total_messages' => $results->sum('message_count'),
            'total_cost' => (float) $results->sum('total_cost'),
            'avg_messages_per_conversation' => $results->count() > 0 ? $results->avg('message_count') : 0,
            'avg_cost_per_conversation' => (float) $results->avg('total_cost'),
            'avg_duration_minutes' => (float) $results->avg('duration_minutes'),
            'most_expensive_conversation' => [
                'id' => $results->sortByDesc('total_cost')->first()->conversation_id ?? null,
                'cost' => (float) $results->max('total_cost'),
            ],
            'longest_conversation' => [
                'id' => $results->sortByDesc('duration_minutes')->first()->conversation_id ?? null,
                'duration_minutes' => (int) $results->max('duration_minutes'),
            ],
        ];
    }

    /**
     * Clear analytics cache.
     *
     * @param  string|null  $pattern  Cache key pattern to clear
     */
    public function clearCache(?string $pattern = null): void
    {
        if ($pattern) {
            // Clear specific pattern (would need Redis for pattern matching)
            Cache::forget($pattern);
        } else {
            // Clear common analytics cache keys
            $patterns = [
                'cost_breakdown_provider_*',
                'cost_breakdown_model_*',
                'cost_breakdown_user_*',
                'historical_trends_*',
                'cost_efficiency_*',
                'conversation_analytics_*',
            ];

            foreach ($patterns as $pattern) {
                // In a real implementation, you'd use Redis KEYS command
                // For now, we'll just document this limitation
            }
        }
    }
}
