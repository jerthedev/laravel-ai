<?php

namespace JTD\LaravelAI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIUsageAnalytics extends Model
{
    protected $table = 'ai_usage_analytics';

    protected $fillable = [
        'date',
        'hour',
        'period_type',
        'ai_provider_id',
        'ai_provider_model_id',
        'user_id',
        'user_type',
        'total_requests',
        'successful_requests',
        'failed_requests',
        'total_conversations',
        'total_messages',
        'total_input_tokens',
        'total_output_tokens',
        'total_tokens',
        'avg_tokens_per_request',
        'max_tokens_in_request',
        'min_tokens_in_request',
        'total_cost',
        'avg_cost_per_request',
        'avg_cost_per_token',
        'currency',
        'input_token_cost',
        'output_token_cost',
        'avg_response_time_ms',
        'min_response_time_ms',
        'max_response_time_ms',
        'p95_response_time_ms',
        'p99_response_time_ms',
        'avg_quality_rating',
        'total_ratings',
        'positive_ratings',
        'negative_ratings',
        'user_satisfaction_score',
        'error_breakdown',
        'rate_limit_errors',
        'authentication_errors',
        'timeout_errors',
        'content_filter_errors',
        'other_errors',
        'streaming_requests',
        'function_call_requests',
        'vision_requests',
        'audio_requests',
        'regeneration_requests',
        'content_types',
        'languages',
        'conversation_types',
        'avg_conversation_length',
        'unique_users',
        'new_users',
        'returning_users',
        'user_retention_rate',
        'daily_active_users',
        'monthly_active_users',
        'system_uptime_percent',
        'peak_concurrent_requests',
        'cache_hit_rate',
        'queue_processing_time_ms',
        'metadata',
        'calculated_at',
        'calculation_version',
    ];

    protected $casts = [
        'date' => 'date',
        'hour' => 'integer',
        'ai_provider_id' => 'integer',
        'ai_provider_model_id' => 'integer',
        'user_id' => 'integer',
        'total_requests' => 'integer',
        'successful_requests' => 'integer',
        'failed_requests' => 'integer',
        'total_conversations' => 'integer',
        'total_messages' => 'integer',
        'total_input_tokens' => 'integer',
        'total_output_tokens' => 'integer',
        'total_tokens' => 'integer',
        'avg_tokens_per_request' => 'decimal:2',
        'max_tokens_in_request' => 'integer',
        'min_tokens_in_request' => 'integer',
        'total_cost' => 'decimal:4',
        'avg_cost_per_request' => 'decimal:4',
        'avg_cost_per_token' => 'decimal:6',
        'input_token_cost' => 'decimal:4',
        'output_token_cost' => 'decimal:4',
        'avg_response_time_ms' => 'integer',
        'min_response_time_ms' => 'integer',
        'max_response_time_ms' => 'integer',
        'p95_response_time_ms' => 'integer',
        'p99_response_time_ms' => 'integer',
        'avg_quality_rating' => 'decimal:2',
        'total_ratings' => 'integer',
        'positive_ratings' => 'integer',
        'negative_ratings' => 'integer',
        'user_satisfaction_score' => 'decimal:2',
        'error_breakdown' => 'array',
        'rate_limit_errors' => 'integer',
        'authentication_errors' => 'integer',
        'timeout_errors' => 'integer',
        'content_filter_errors' => 'integer',
        'other_errors' => 'integer',
        'streaming_requests' => 'integer',
        'function_call_requests' => 'integer',
        'vision_requests' => 'integer',
        'audio_requests' => 'integer',
        'regeneration_requests' => 'integer',
        'content_types' => 'array',
        'languages' => 'array',
        'conversation_types' => 'array',
        'avg_conversation_length' => 'decimal:2',
        'unique_users' => 'integer',
        'new_users' => 'integer',
        'returning_users' => 'integer',
        'user_retention_rate' => 'decimal:2',
        'daily_active_users' => 'decimal:2',
        'monthly_active_users' => 'decimal:2',
        'system_uptime_percent' => 'decimal:2',
        'peak_concurrent_requests' => 'integer',
        'cache_hit_rate' => 'decimal:2',
        'queue_processing_time_ms' => 'decimal:2',
        'metadata' => 'array',
        'calculated_at' => 'datetime',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AIProvider::class, 'ai_provider_id');
    }

    public function providerModel(): BelongsTo
    {
        return $this->belongsTo(AIProviderModel::class, 'ai_provider_model_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('date', $date);
    }

    public function scopeForPeriod($query, string $periodType)
    {
        return $query->where('period_type', $periodType);
    }

    public function scopeForProvider($query, int $providerId)
    {
        return $query->where('ai_provider_id', $providerId);
    }

    public function scopeForProviderModel($query, int $providerModelId)
    {
        return $query->where('ai_provider_model_id', $providerModelId);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeHourly($query)
    {
        return $query->where('period_type', 'hourly');
    }

    public function scopeDaily($query)
    {
        return $query->where('period_type', 'daily');
    }

    public function scopeWeekly($query)
    {
        return $query->where('period_type', 'weekly');
    }

    public function scopeMonthly($query)
    {
        return $query->where('period_type', 'monthly');
    }

    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeHighVolume($query, int $requestThreshold = 1000)
    {
        return $query->where('total_requests', '>=', $requestThreshold);
    }

    public function scopeHighCost($query, float $costThreshold = 100.0)
    {
        return $query->where('total_cost', '>=', $costThreshold);
    }

    public function getSuccessRateAttribute(): float
    {
        if ($this->total_requests == 0) {
            return 0;
        }
        
        return ($this->successful_requests / $this->total_requests) * 100;
    }

    public function getFailureRateAttribute(): float
    {
        if ($this->total_requests == 0) {
            return 0;
        }
        
        return ($this->failed_requests / $this->total_requests) * 100;
    }

    public function getTokensPerRequestAttribute(): float
    {
        if ($this->total_requests == 0) {
            return 0;
        }
        
        return $this->total_tokens / $this->total_requests;
    }

    public function getMessagesPerConversationAttribute(): float
    {
        if ($this->total_conversations == 0) {
            return 0;
        }
        
        return $this->total_messages / $this->total_conversations;
    }

    public function getPositiveRatingPercentageAttribute(): float
    {
        if ($this->total_ratings == 0) {
            return 0;
        }
        
        return ($this->positive_ratings / $this->total_ratings) * 100;
    }

    public function getNegativeRatingPercentageAttribute(): float
    {
        if ($this->total_ratings == 0) {
            return 0;
        }
        
        return ($this->negative_ratings / $this->total_ratings) * 100;
    }

    public function getStreamingRequestPercentageAttribute(): float
    {
        if ($this->total_requests == 0) {
            return 0;
        }
        
        return ($this->streaming_requests / $this->total_requests) * 100;
    }

    public function getFunctionCallPercentageAttribute(): float
    {
        if ($this->total_requests == 0) {
            return 0;
        }
        
        return ($this->function_call_requests / $this->total_requests) * 100;
    }

    public function isHighVolume(int $threshold = 1000): bool
    {
        return $this->total_requests >= $threshold;
    }

    public function isHighCost(float $threshold = 100.0): bool
    {
        return $this->total_cost >= $threshold;
    }

    public function hasHighFailureRate(float $threshold = 5.0): bool
    {
        return $this->failure_rate >= $threshold;
    }

    public function hasLowSatisfaction(float $threshold = 3.0): bool
    {
        return $this->user_satisfaction_score < $threshold;
    }

    public function isPerformant(int $responseTimeThreshold = 2000): bool
    {
        return $this->avg_response_time_ms <= $responseTimeThreshold;
    }

    public static function aggregateForDateRange($startDate, $endDate, string $periodType = 'daily'): array
    {
        $analytics = self::betweenDates($startDate, $endDate)
            ->forPeriod($periodType)
            ->get();
        
        return [
            'total_requests' => $analytics->sum('total_requests'),
            'successful_requests' => $analytics->sum('successful_requests'),
            'failed_requests' => $analytics->sum('failed_requests'),
            'total_cost' => $analytics->sum('total_cost'),
            'total_tokens' => $analytics->sum('total_tokens'),
            'unique_users' => $analytics->sum('unique_users'),
            'avg_response_time_ms' => $analytics->avg('avg_response_time_ms'),
            'success_rate' => $analytics->avg('success_rate'),
            'user_satisfaction' => $analytics->avg('user_satisfaction_score'),
            'provider_breakdown' => $analytics->groupBy('ai_provider_id')->map(function($group) {
                return [
                    'requests' => $group->sum('total_requests'),
                    'cost' => $group->sum('total_cost'),
                    'tokens' => $group->sum('total_tokens'),
                ];
            })->toArray(),
        ];
    }

    public static function getTopPerformers(string $metric = 'total_requests', int $limit = 10, ?string $period = null): array
    {
        $query = self::query();
        
        if ($period) {
            $query->forPeriod($period);
        }
        
        return $query->orderBy($metric, 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public static function getProviderComparison(?string $dateRange = null): array
    {
        $query = self::query();
        
        if ($dateRange) {
            $dates = self::parseDateRange($dateRange);
            $query->betweenDates($dates['start'], $dates['end']);
        }
        
        return $query
            ->selectRaw('ai_provider_id, SUM(total_requests) as total_requests, SUM(total_cost) as total_cost, AVG(success_rate) as avg_success_rate, AVG(avg_response_time_ms) as avg_response_time')
            ->groupBy('ai_provider_id')
            ->with('provider')
            ->get()
            ->map(function($item) {
                return [
                    'provider_id' => $item->ai_provider_id,
                    'provider_name' => $item->provider->name ?? 'Unknown',
                    'total_requests' => $item->total_requests,
                    'total_cost' => $item->total_cost,
                    'avg_success_rate' => $item->avg_success_rate,
                    'avg_response_time' => $item->avg_response_time,
                ];
            })
            ->toArray();
    }

    public static function getUserEngagementMetrics(?int $userId = null, ?string $period = null): array
    {
        $query = self::query();
        
        if ($userId) {
            $query->forUser($userId);
        }
        
        if ($period) {
            $query->forPeriod($period);
        }
        
        $metrics = $query->selectRaw('
            SUM(unique_users) as total_unique_users,
            SUM(new_users) as total_new_users,
            SUM(returning_users) as total_returning_users,
            AVG(user_retention_rate) as avg_retention_rate,
            AVG(avg_conversation_length) as avg_conversation_length,
            AVG(user_satisfaction_score) as avg_satisfaction
        ')->first();
        
        return [
            'total_unique_users' => $metrics->total_unique_users ?? 0,
            'total_new_users' => $metrics->total_new_users ?? 0,
            'total_returning_users' => $metrics->total_returning_users ?? 0,
            'avg_retention_rate' => $metrics->avg_retention_rate ?? 0,
            'avg_conversation_length' => $metrics->avg_conversation_length ?? 0,
            'avg_satisfaction' => $metrics->avg_satisfaction ?? 0,
        ];
    }

    protected static function parseDateRange(string $dateRange): array
    {
        return match ($dateRange) {
            'today' => [
                'start' => now()->startOfDay(),
                'end' => now()->endOfDay(),
            ],
            'week' => [
                'start' => now()->startOfWeek(),
                'end' => now()->endOfWeek(),
            ],
            'month' => [
                'start' => now()->startOfMonth(),
                'end' => now()->endOfMonth(),
            ],
            'year' => [
                'start' => now()->startOfYear(),
                'end' => now()->endOfYear(),
            ],
            default => [
                'start' => now()->startOfMonth(),
                'end' => now()->endOfMonth(),
            ],
        };
    }
}