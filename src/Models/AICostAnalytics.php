<?php

namespace JTD\LaravelAI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AICostAnalytics extends Model
{
    protected $table = 'ai_cost_analytics';

    protected $fillable = [
        'user_id',
        'provider',
        'model',
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'input_cost',
        'output_cost',
        'total_cost',
        'currency',
        'cost_per_token',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'total_tokens' => 'integer',
        'input_cost' => 'decimal:6',
        'output_cost' => 'decimal:6',
        'total_cost' => 'decimal:6',
        'cost_per_token' => 'decimal:8',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    public function scopeHighCost($query, float $threshold = 1.0)
    {
        return $query->where('total_cost', '>=', $threshold);
    }

    public function scopeHighVolume($query, int $tokenThreshold = 10000)
    {
        return $query->where('total_tokens', '>=', $tokenThreshold);
    }

    public function scopeMostExpensive($query, int $limit = 10)
    {
        return $query->orderBy('total_cost', 'desc')->limit($limit);
    }

    public function scopeMostEfficient($query, int $limit = 10)
    {
        return $query->orderBy('cost_per_token', 'asc')->limit($limit);
    }

    public function getCostPer1kTokensAttribute(): float
    {
        if ($this->total_tokens == 0) {
            return 0;
        }

        return ($this->total_cost / $this->total_tokens) * 1000;
    }

    public function getInputCostPercentageAttribute(): float
    {
        if ($this->total_cost == 0) {
            return 0;
        }

        return ($this->input_cost / $this->total_cost) * 100;
    }

    public function getOutputCostPercentageAttribute(): float
    {
        if ($this->total_cost == 0) {
            return 0;
        }

        return ($this->output_cost / $this->total_cost) * 100;
    }

    public function getTokenRatioAttribute(): float
    {
        if ($this->input_tokens == 0) {
            return 0;
        }

        return $this->output_tokens / $this->input_tokens;
    }

    public function isExpensive(float $threshold = 1.0): bool
    {
        return $this->total_cost >= $threshold;
    }

    public function isHighVolume(int $tokenThreshold = 10000): bool
    {
        return $this->total_tokens >= $tokenThreshold;
    }

    public function isEfficient(float $maxCostPer1k = 10.0): bool
    {
        return $this->cost_per_1k_tokens <= $maxCostPer1k;
    }

    public static function aggregateForUser(int $userId, ?string $period = null): array
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

        $analytics = $query->get();

        return [
            'total_cost' => $analytics->sum('total_cost'),
            'total_tokens' => $analytics->sum('total_tokens'),
            'total_input_tokens' => $analytics->sum('input_tokens'),
            'total_output_tokens' => $analytics->sum('output_tokens'),
            'avg_cost_per_token' => $analytics->avg('cost_per_token'),
            'avg_cost_per_1k_tokens' => $analytics->avg(function ($item) {
                return $item->cost_per_1k_tokens;
            }),
            'provider_breakdown' => $analytics->groupBy('provider')->map(function ($group) {
                return [
                    'cost' => $group->sum('total_cost'),
                    'tokens' => $group->sum('total_tokens'),
                    'requests' => $group->count(),
                ];
            })->toArray(),
            'model_breakdown' => $analytics->groupBy('model')->map(function ($group) {
                return [
                    'cost' => $group->sum('total_cost'),
                    'tokens' => $group->sum('total_tokens'),
                    'requests' => $group->count(),
                ];
            })->toArray(),
        ];
    }

    public static function getTopSpenders(int $limit = 10, ?string $period = null): array
    {
        $query = self::query();

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
            ->selectRaw('user_id, SUM(total_cost) as total_cost, SUM(total_tokens) as total_tokens, COUNT(*) as request_count')
            ->groupBy('user_id')
            ->orderBy('total_cost', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public static function getProviderComparison(?string $period = null): array
    {
        $query = self::query();

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
            ->selectRaw('provider, SUM(total_cost) as total_cost, SUM(total_tokens) as total_tokens, COUNT(*) as request_count, AVG(cost_per_token) as avg_cost_per_token')
            ->groupBy('provider')
            ->orderBy('total_cost', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'provider' => $item->provider,
                    'total_cost' => (float) $item->total_cost,
                    'total_tokens' => (int) $item->total_tokens,
                    'request_count' => (int) $item->request_count,
                    'avg_cost_per_token' => (float) $item->avg_cost_per_token,
                    'cost_per_1k_tokens' => $item->total_tokens > 0
                        ? ($item->total_cost / $item->total_tokens) * 1000
                        : 0,
                ];
            })
            ->toArray();
    }

    public static function getModelComparison(?string $provider = null, ?string $period = null): array
    {
        $query = self::query();

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
            ->selectRaw('provider, model, SUM(total_cost) as total_cost, SUM(total_tokens) as total_tokens, COUNT(*) as request_count, AVG(cost_per_token) as avg_cost_per_token')
            ->groupBy('provider', 'model')
            ->orderBy('total_cost', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'provider' => $item->provider,
                    'model' => $item->model,
                    'total_cost' => (float) $item->total_cost,
                    'total_tokens' => (int) $item->total_tokens,
                    'request_count' => (int) $item->request_count,
                    'avg_cost_per_token' => (float) $item->avg_cost_per_token,
                    'cost_per_1k_tokens' => $item->total_tokens > 0
                        ? ($item->total_cost / $item->total_tokens) * 1000
                        : 0,
                ];
            })
            ->toArray();
    }
}
