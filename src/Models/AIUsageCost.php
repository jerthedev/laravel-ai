<?php

namespace JTD\LaravelAI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AIUsageCost extends Model
{
    protected $table = 'ai_cost_records';

    protected $fillable = [
        'user_id',
        'conversation_id',
        'message_id',
        'provider',
        'model',
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'input_cost',
        'output_cost',
        'total_cost',
        'currency',
        'pricing_source',
        'processing_time_ms',
        'metadata',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'message_id' => 'integer',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'total_tokens' => 'integer',
        'input_cost' => 'decimal:6',
        'output_cost' => 'decimal:6',
        'total_cost' => 'decimal:6',
        'processing_time_ms' => 'integer',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AIConversation::class, 'conversation_id', 'id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(AIMessage::class, 'message_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AIProvider::class, 'provider', 'name');
    }

    public function providerModel(): BelongsTo
    {
        return $this->belongsTo(AIProviderModel::class, 'model', 'name')
            ->where('provider_name', $this->provider);
    }

    public function costValidation(): HasOne
    {
        return $this->hasOne(AICostValidation::class, 'provider', 'provider');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    public function scopeForModel($query, string $model)
    {
        return $query->where('model', $model);
    }

    public function scopeForConversation($query, string $conversationId)
    {
        return $query->where('conversation_id', $conversationId);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);
    }

    public function scopeThisYear($query)
    {
        return $query->whereYear('created_at', now()->year);
    }

    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    public function scopeExpensive($query, float $threshold = 1.0)
    {
        return $query->where('total_cost', '>=', $threshold);
    }

    public function scopeHighUsage($query, int $tokenThreshold = 10000)
    {
        return $query->where('total_tokens', '>=', $tokenThreshold);
    }

    public function getCostPer1kTokensAttribute(): float
    {
        if ($this->total_tokens == 0) {
            return 0;
        }

        return ($this->total_cost / $this->total_tokens) * 1000;
    }

    public function getTokensPerDollarAttribute(): float
    {
        if ($this->total_cost == 0) {
            return 0;
        }

        return $this->total_tokens / $this->total_cost;
    }

    public function getProcessingTimeSecondsAttribute(): float
    {
        return $this->processing_time_ms / 1000;
    }

    public function getTokensPerSecondAttribute(): float
    {
        if ($this->processing_time_ms == 0) {
            return 0;
        }

        return ($this->total_tokens / $this->processing_time_ms) * 1000;
    }

    public function getEfficiencyScoreAttribute(): float
    {
        $costEfficiency = $this->cost_per_1k_tokens > 0 ? 1000 / $this->cost_per_1k_tokens : 0;
        $speedEfficiency = $this->tokens_per_second;

        return ($costEfficiency + $speedEfficiency) / 2;
    }

    public function isExpensive(float $threshold = 1.0): bool
    {
        return $this->total_cost >= $threshold;
    }

    public function isHighUsage(int $tokenThreshold = 10000): bool
    {
        return $this->total_tokens >= $tokenThreshold;
    }

    public function isSlow(int $timeThreshold = 5000): bool
    {
        return $this->processing_time_ms >= $timeThreshold;
    }

    public static function getTotalCostForUser(int $userId, ?string $period = null): float
    {
        $query = self::forUser($userId);

        if ($period) {
            $query = match ($period) {
                'today' => $query->today(),
                'week' => $query->thisWeek(),
                'month' => $query->thisMonth(),
                'year' => $query->thisYear(),
                default => $query,
            };
        }

        return $query->sum('total_cost');
    }

    public static function getTotalTokensForUser(int $userId, ?string $period = null): int
    {
        $query = self::forUser($userId);

        if ($period) {
            $query = match ($period) {
                'today' => $query->today(),
                'week' => $query->thisWeek(),
                'month' => $query->thisMonth(),
                'year' => $query->thisYear(),
                default => $query,
            };
        }

        return $query->sum('total_tokens');
    }

    public static function getProviderBreakdown(int $userId, ?string $period = null): array
    {
        $query = self::forUser($userId);

        if ($period) {
            $query = match ($period) {
                'today' => $query->today(),
                'week' => $query->thisWeek(),
                'month' => $query->thisMonth(),
                'year' => $query->thisYear(),
                default => $query,
            };
        }

        return $query
            ->selectRaw('provider, COUNT(*) as request_count, SUM(total_cost) as total_cost, SUM(total_tokens) as total_tokens')
            ->groupBy('provider')
            ->orderBy('total_cost', 'desc')
            ->get()
            ->toArray();
    }

    public static function getModelBreakdown(int $userId, ?string $provider = null, ?string $period = null): array
    {
        $query = self::forUser($userId);

        if ($provider) {
            $query->forProvider($provider);
        }

        if ($period) {
            $query = match ($period) {
                'today' => $query->today(),
                'week' => $query->thisWeek(),
                'month' => $query->thisMonth(),
                'year' => $query->thisYear(),
                default => $query,
            };
        }

        return $query
            ->selectRaw('provider, model, COUNT(*) as request_count, SUM(total_cost) as total_cost, SUM(total_tokens) as total_tokens')
            ->groupBy('provider', 'model')
            ->orderBy('total_cost', 'desc')
            ->get()
            ->toArray();
    }
}
