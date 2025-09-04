<?php

namespace JTD\LaravelAI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIPerformanceMetric extends Model
{
    protected $table = 'ai_performance_metrics';

    protected $fillable = [
        'operation',
        'duration_ms',
        'target_ms',
        'user_id',
        'provider',
    ];

    protected $casts = [
        'duration_ms' => 'decimal:2',
        'target_ms' => 'decimal:2',
        'user_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AIProvider::class, 'provider', 'name');
    }

    public function scopeForOperation($query, string $operation)
    {
        return $query->where('operation', $operation);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    public function scopeSlow($query, ?float $threshold = null)
    {
        if ($threshold) {
            return $query->where('duration_ms', '>=', $threshold);
        }
        
        return $query->whereRaw('duration_ms >= target_ms');
    }

    public function scopeFast($query, ?float $threshold = null)
    {
        if ($threshold) {
            return $query->where('duration_ms', '<', $threshold);
        }
        
        return $query->whereRaw('duration_ms < target_ms');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    public function getPerformanceRatioAttribute(): float
    {
        if ($this->target_ms == 0) {
            return 0;
        }
        
        return $this->duration_ms / $this->target_ms;
    }

    public function getVariancePercentAttribute(): float
    {
        if ($this->target_ms == 0) {
            return 0;
        }
        
        return (($this->duration_ms - $this->target_ms) / $this->target_ms) * 100;
    }

    public function getDurationSecondsAttribute(): float
    {
        return $this->duration_ms / 1000;
    }

    public function getTargetSecondsAttribute(): float
    {
        return $this->target_ms / 1000;
    }

    public function isSlowPerformance(): bool
    {
        return $this->duration_ms >= $this->target_ms;
    }

    public function isFastPerformance(): bool
    {
        return $this->duration_ms < $this->target_ms;
    }

    public function isSignificantlySlower(float $threshold = 50.0): bool
    {
        return $this->variance_percent >= $threshold;
    }

    public function getPerformanceStatus(): string
    {
        $ratio = $this->performance_ratio;
        
        if ($ratio >= 2.0) {
            return 'critical';
        } elseif ($ratio >= 1.5) {
            return 'poor';
        } elseif ($ratio >= 1.1) {
            return 'slow';
        } elseif ($ratio <= 0.5) {
            return 'excellent';
        } elseif ($ratio <= 0.8) {
            return 'good';
        } else {
            return 'acceptable';
        }
    }

    public static function recordMetric(string $operation, float $durationMs, float $targetMs, ?int $userId = null, ?string $provider = null): self
    {
        return self::create([
            'operation' => $operation,
            'duration_ms' => $durationMs,
            'target_ms' => $targetMs,
            'user_id' => $userId,
            'provider' => $provider,
        ]);
    }

    public static function getAveragePerformance(string $operation, ?string $provider = null, ?int $hours = 24): array
    {
        $query = self::forOperation($operation)->recent($hours);
        
        if ($provider) {
            $query->forProvider($provider);
        }
        
        $metrics = $query->get();
        
        if ($metrics->isEmpty()) {
            return [
                'operation' => $operation,
                'provider' => $provider,
                'sample_count' => 0,
                'avg_duration_ms' => 0,
                'avg_target_ms' => 0,
                'avg_variance_percent' => 0,
                'slow_count' => 0,
                'slow_percentage' => 0,
            ];
        }
        
        $slowCount = $metrics->filter(fn($m) => $m->isSlowPerformance())->count();
        
        return [
            'operation' => $operation,
            'provider' => $provider,
            'sample_count' => $metrics->count(),
            'avg_duration_ms' => $metrics->avg('duration_ms'),
            'avg_target_ms' => $metrics->avg('target_ms'),
            'min_duration_ms' => $metrics->min('duration_ms'),
            'max_duration_ms' => $metrics->max('duration_ms'),
            'avg_variance_percent' => $metrics->avg(fn($m) => $m->variance_percent),
            'slow_count' => $slowCount,
            'slow_percentage' => ($slowCount / $metrics->count()) * 100,
            'p95_duration_ms' => $metrics->sortBy('duration_ms')->values()->get(intval($metrics->count() * 0.95))?->duration_ms ?? 0,
            'p99_duration_ms' => $metrics->sortBy('duration_ms')->values()->get(intval($metrics->count() * 0.99))?->duration_ms ?? 0,
        ];
    }

    public static function getOperationTrends(string $operation, int $days = 7): array
    {
        $metrics = self::forOperation($operation)
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at')
            ->get()
            ->groupBy(fn($metric) => $metric->created_at->format('Y-m-d'))
            ->map(function($dayMetrics) {
                $slowCount = $dayMetrics->filter(fn($m) => $m->isSlowPerformance())->count();
                
                return [
                    'date' => $dayMetrics->first()->created_at->format('Y-m-d'),
                    'sample_count' => $dayMetrics->count(),
                    'avg_duration_ms' => $dayMetrics->avg('duration_ms'),
                    'slow_count' => $slowCount,
                    'slow_percentage' => $dayMetrics->count() > 0 ? ($slowCount / $dayMetrics->count()) * 100 : 0,
                ];
            })
            ->values()
            ->toArray();
        
        return $metrics;
    }

    public static function getProviderComparison(?string $operation = null, ?int $hours = 24): array
    {
        $query = self::recent($hours);
        
        if ($operation) {
            $query->forOperation($operation);
        }
        
        return $query->get()
            ->groupBy('provider')
            ->map(function($providerMetrics, $provider) {
                $slowCount = $providerMetrics->filter(fn($m) => $m->isSlowPerformance())->count();
                
                return [
                    'provider' => $provider,
                    'sample_count' => $providerMetrics->count(),
                    'avg_duration_ms' => $providerMetrics->avg('duration_ms'),
                    'avg_target_ms' => $providerMetrics->avg('target_ms'),
                    'slow_count' => $slowCount,
                    'slow_percentage' => $providerMetrics->count() > 0 ? ($slowCount / $providerMetrics->count()) * 100 : 0,
                    'avg_variance_percent' => $providerMetrics->avg(fn($m) => $m->variance_percent),
                ];
            })
            ->sortBy('avg_duration_ms')
            ->values()
            ->toArray();
    }

    public static function getSlowOperations(int $limit = 10, ?int $hours = 24): array
    {
        $query = self::slow();
        
        if ($hours) {
            $query->recent($hours);
        }
        
        return $query->orderBy('duration_ms', 'desc')
            ->limit($limit)
            ->get()
            ->map(function($metric) {
                return [
                    'operation' => $metric->operation,
                    'duration_ms' => $metric->duration_ms,
                    'target_ms' => $metric->target_ms,
                    'variance_percent' => $metric->variance_percent,
                    'provider' => $metric->provider,
                    'user_id' => $metric->user_id,
                    'status' => $metric->getPerformanceStatus(),
                    'created_at' => $metric->created_at,
                ];
            })
            ->toArray();
    }
}