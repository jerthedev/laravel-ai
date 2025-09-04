<?php

namespace JTD\LaravelAI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIPerformanceAlert extends Model
{
    protected $table = 'ai_performance_alerts';

    protected $fillable = [
        'component',
        'component_name',
        'severity',
        'message',
        'detailed_message',
        'duration_ms',
        'threshold_ms',
        'threshold_exceeded_percentage',
        'context_data',
        'recommended_actions',
        'status',
        'channels_sent',
        'escalation_level',
        'occurrence_count',
        'acknowledged_at',
        'acknowledged_by',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
    ];

    protected $casts = [
        'duration_ms' => 'decimal:2',
        'threshold_ms' => 'decimal:2',
        'threshold_exceeded_percentage' => 'decimal:1',
        'context_data' => 'array',
        'recommended_actions' => 'array',
        'channels_sent' => 'array',
        'escalation_level' => 'integer',
        'occurrence_count' => 'integer',
        'acknowledged_at' => 'datetime',
        'acknowledged_by' => 'integer',
        'resolved_at' => 'datetime',
        'resolved_by' => 'integer',
    ];

    public function acknowledgedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeAcknowledged($query)
    {
        return $query->where('status', 'acknowledged');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    public function scopeHigh($query)
    {
        return $query->where('severity', 'high');
    }

    public function scopeMedium($query)
    {
        return $query->where('severity', 'medium');
    }

    public function scopeLow($query)
    {
        return $query->where('severity', 'low');
    }

    public function scopeByComponent($query, string $component)
    {
        return $query->where('component', $component);
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    public function scopeEscalated($query, int $level = 2)
    {
        return $query->where('escalation_level', '>=', $level);
    }

    public function scopeFrequent($query, int $occurrences = 5)
    {
        return $query->where('occurrence_count', '>=', $occurrences);
    }

    public function getTimeToAcknowledgeAttribute(): ?int
    {
        if (! $this->acknowledged_at) {
            return null;
        }

        return $this->created_at->diffInMinutes($this->acknowledged_at);
    }

    public function getTimeToResolveAttribute(): ?int
    {
        if (! $this->resolved_at) {
            return null;
        }

        return $this->created_at->diffInMinutes($this->resolved_at);
    }

    public function getActiveTimeAttribute(): int
    {
        $endTime = $this->resolved_at ?? now();

        return $this->created_at->diffInMinutes($endTime);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isAcknowledged(): bool
    {
        return $this->status === 'acknowledged';
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    public function isCritical(): bool
    {
        return $this->severity === 'critical';
    }

    public function isHigh(): bool
    {
        return $this->severity === 'high';
    }

    public function isMedium(): bool
    {
        return $this->severity === 'medium';
    }

    public function isLow(): bool
    {
        return $this->severity === 'low';
    }

    public function isEscalated(): bool
    {
        return $this->escalation_level > 1;
    }

    public function isFrequent(int $threshold = 5): bool
    {
        return $this->occurrence_count >= $threshold;
    }

    public function wasSentVia(string $channel): bool
    {
        return in_array($channel, $this->channels_sent ?? []);
    }

    public function acknowledge(int $userId, ?string $notes = null): bool
    {
        return $this->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
            'acknowledged_by' => $userId,
            'resolution_notes' => $notes,
        ]);
    }

    public function resolve(int $userId, ?string $notes = null): bool
    {
        return $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => $userId,
            'resolution_notes' => $notes,
        ]);
    }

    public function escalate(): bool
    {
        return $this->increment('escalation_level');
    }

    public function incrementOccurrence(): bool
    {
        return $this->increment('occurrence_count');
    }

    public static function createAlert(array $data): self
    {
        $severity = self::determineSeverity($data);

        return self::create(array_merge($data, [
            'severity' => $severity,
            'status' => 'active',
            'escalation_level' => 1,
            'occurrence_count' => 1,
        ]));
    }

    protected static function determineSeverity(array $data): string
    {
        $thresholdExceeded = $data['threshold_exceeded_percentage'] ?? 0;

        if ($thresholdExceeded >= 200) {
            return 'critical';
        } elseif ($thresholdExceeded >= 150) {
            return 'high';
        } elseif ($thresholdExceeded >= 120) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    public static function getActiveAlertsCount(): int
    {
        return self::active()->count();
    }

    public static function getCriticalAlertsCount(): int
    {
        return self::critical()->active()->count();
    }

    public static function getAlertsByComponent(int $hours = 24): array
    {
        return self::recent($hours)
            ->selectRaw('component, component_name, COUNT(*) as alert_count, MAX(severity) as max_severity')
            ->groupBy('component', 'component_name')
            ->orderBy('alert_count', 'desc')
            ->get()
            ->toArray();
    }

    public static function getAlertTrends(int $days = 7): array
    {
        return self::where('created_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total_alerts, 
                        SUM(CASE WHEN severity = "critical" THEN 1 ELSE 0 END) as critical_alerts,
                        SUM(CASE WHEN severity = "high" THEN 1 ELSE 0 END) as high_alerts,
                        SUM(CASE WHEN status = "resolved" THEN 1 ELSE 0 END) as resolved_alerts')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    public static function getResolutionMetrics(int $days = 30): array
    {
        $alerts = self::where('created_at', '>=', now()->subDays($days))->get();

        $resolvedAlerts = $alerts->filter(fn ($alert) => $alert->isResolved());
        $acknowledgedAlerts = $alerts->filter(fn ($alert) => $alert->isAcknowledged() || $alert->isResolved());

        return [
            'total_alerts' => $alerts->count(),
            'resolved_alerts' => $resolvedAlerts->count(),
            'acknowledged_alerts' => $acknowledgedAlerts->count(),
            'resolution_rate' => $alerts->count() > 0 ? ($resolvedAlerts->count() / $alerts->count()) * 100 : 0,
            'acknowledgment_rate' => $alerts->count() > 0 ? ($acknowledgedAlerts->count() / $alerts->count()) * 100 : 0,
            'avg_time_to_acknowledge' => $acknowledgedAlerts->avg('time_to_acknowledge'),
            'avg_time_to_resolve' => $resolvedAlerts->avg('time_to_resolve'),
            'escalated_alerts' => $alerts->filter(fn ($alert) => $alert->isEscalated())->count(),
            'frequent_alerts' => $alerts->filter(fn ($alert) => $alert->isFrequent())->count(),
        ];
    }

    public static function getTopComponents(int $limit = 10, int $days = 30): array
    {
        return self::where('created_at', '>=', now()->subDays($days))
            ->selectRaw('component, component_name, COUNT(*) as alert_count, 
                        AVG(threshold_exceeded_percentage) as avg_threshold_exceeded,
                        MAX(threshold_exceeded_percentage) as max_threshold_exceeded')
            ->groupBy('component', 'component_name')
            ->orderBy('alert_count', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
