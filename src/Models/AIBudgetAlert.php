<?php

namespace JTD\LaravelAI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIBudgetAlert extends Model
{
    protected $table = 'ai_budget_alerts';

    protected $fillable = [
        'user_id',
        'budget_type',
        'threshold_percentage',
        'current_spending',
        'budget_limit',
        'additional_cost',
        'severity',
        'channels',
        'project_id',
        'organization_id',
        'metadata',
        'sent_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'threshold_percentage' => 'decimal:2',
        'current_spending' => 'decimal:6',
        'budget_limit' => 'decimal:6',
        'additional_cost' => 'decimal:6',
        'channels' => 'array',
        'metadata' => 'array',
        'sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(AIBudget::class, 'user_id', 'user_id')
            ->where('type', $this->budget_type);
    }

    public function alertConfig(): BelongsTo
    {
        return $this->belongsTo(AIBudgetAlertConfig::class, 'user_id', 'user_id')
            ->where('budget_type', $this->budget_type);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByBudgetType($query, string $budgetType)
    {
        return $query->where('budget_type', $budgetType);
    }

    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    public function scopeByChannel($query, string $channel)
    {
        return $query->whereJsonContains('channels', $channel);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('sent_at', '>=', now()->subDays($days));
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

    public function getExceededAmountAttribute(): float
    {
        return max(0, $this->current_spending - $this->budget_limit);
    }

    public function getRemainingBudgetAttribute(): float
    {
        return max(0, $this->budget_limit - $this->current_spending);
    }

    public function getProjectedSpendingAttribute(): float
    {
        return $this->current_spending + $this->additional_cost;
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

    public function wasSentVia(string $channel): bool
    {
        return in_array($channel, $this->channels ?? []);
    }

    public function wasSentViaEmail(): bool
    {
        return $this->wasSentVia('email');
    }

    public function wasSentViaSlack(): bool
    {
        return $this->wasSentVia('slack');
    }

    public function wasSentViaSms(): bool
    {
        return $this->wasSentVia('sms');
    }

    public function getFormattedSpendingAttribute(): string
    {
        return '$' . number_format($this->current_spending, 2);
    }

    public function getFormattedLimitAttribute(): string
    {
        return '$' . number_format($this->budget_limit, 2);
    }

    public function getFormattedThresholdAttribute(): string
    {
        return number_format($this->threshold_percentage, 1) . '%';
    }

    public static function createAlert(array $data): self
    {
        return self::create(array_merge($data, [
            'sent_at' => now(),
        ]));
    }

    public static function getRecentAlertsForUser(int $userId, int $days = 30)
    {
        return self::forUser($userId)
            ->recent($days)
            ->orderBy('sent_at', 'desc')
            ->get();
    }

    public static function getAlertStatistics(int $userId, string $period = 'month'): array
    {
        $startDate = match ($period) {
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'quarter' => now()->startOfQuarter(),
            'year' => now()->startOfYear(),
            default => now()->startOfMonth(),
        };

        $alerts = self::forUser($userId)
            ->where('sent_at', '>=', $startDate)
            ->get();

        return [
            'total_alerts' => $alerts->count(),
            'by_budget_type' => $alerts->groupBy('budget_type')->map->count()->toArray(),
            'by_severity' => $alerts->groupBy('severity')->map->count()->toArray(),
            'by_channel' => $alerts->flatMap(fn ($alert) => $alert->channels ?? [])
                ->countBy()
                ->toArray(),
            'recent_alerts' => $alerts->sortByDesc('sent_at')->take(10)->values()->toArray(),
        ];
    }
}
