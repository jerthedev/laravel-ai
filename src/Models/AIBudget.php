<?php

namespace JTD\LaravelAI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class AIBudget extends Model
{
    protected $table = 'ai_budgets';

    protected $fillable = [
        'user_id',
        'type',
        'limit_amount',
        'current_usage',
        'currency',
        'warning_threshold',
        'critical_threshold',
        'period_start',
        'period_end',
        'is_active',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'limit_amount' => 'decimal:4',
        'current_usage' => 'decimal:4',
        'warning_threshold' => 'decimal:2',
        'critical_threshold' => 'decimal:2',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(AIBudgetAlert::class, 'user_id', 'user_id')
            ->where('budget_type', $this->type);
    }

    public function usageCosts(): HasMany
    {
        return $this->hasMany(AIUsageCost::class, 'user_id', 'user_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeCurrentPeriod($query)
    {
        $now = now();
        return $query->where('period_start', '<=', $now)
            ->where('period_end', '>=', $now);
    }

    public function getUsagePercentageAttribute(): float
    {
        if ($this->limit_amount == 0) {
            return 0;
        }
        
        return ($this->current_usage / $this->limit_amount) * 100;
    }

    public function getRemainingAmountAttribute(): float
    {
        return max(0, $this->limit_amount - $this->current_usage);
    }

    public function getStatusAttribute(): string
    {
        $percentage = $this->usage_percentage;
        
        if ($percentage >= $this->critical_threshold) {
            return 'critical';
        } elseif ($percentage >= $this->warning_threshold) {
            return 'warning';
        }
        
        return 'normal';
    }

    public function isExceeded(): bool
    {
        return $this->current_usage >= $this->limit_amount;
    }

    public function isCritical(): bool
    {
        return $this->usage_percentage >= $this->critical_threshold;
    }

    public function isWarning(): bool
    {
        return $this->usage_percentage >= $this->warning_threshold;
    }

    public function isCurrentPeriod(): bool
    {
        $now = now();
        return $this->period_start <= $now && $this->period_end >= $now;
    }

    public function addUsage(float $amount): void
    {
        $this->increment('current_usage', $amount);
    }

    public static function createForUser(int $userId, string $type, array $data = []): self
    {
        $periodDates = self::calculatePeriodDates($type);
        
        return self::create(array_merge([
            'user_id' => $userId,
            'type' => $type,
            'limit_amount' => 0,
            'current_usage' => 0,
            'currency' => 'USD',
            'warning_threshold' => 80.0,
            'critical_threshold' => 90.0,
            'period_start' => $periodDates['start'],
            'period_end' => $periodDates['end'],
            'is_active' => true,
        ], $data));
    }

    protected static function calculatePeriodDates(string $type): array
    {
        $now = now();
        
        return match ($type) {
            'daily' => [
                'start' => $now->copy()->startOfDay(),
                'end' => $now->copy()->endOfDay(),
            ],
            'weekly' => [
                'start' => $now->copy()->startOfWeek(),
                'end' => $now->copy()->endOfWeek(),
            ],
            'monthly' => [
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->endOfMonth(),
            ],
            'yearly' => [
                'start' => $now->copy()->startOfYear(),
                'end' => $now->copy()->endOfYear(),
            ],
            default => [
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->endOfMonth(),
            ],
        };
    }

    public function resetPeriod(): void
    {
        $periodDates = self::calculatePeriodDates($this->type);
        
        $this->update([
            'current_usage' => 0,
            'period_start' => $periodDates['start'],
            'period_end' => $periodDates['end'],
        ]);
    }
}