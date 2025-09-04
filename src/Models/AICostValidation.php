<?php

namespace JTD\LaravelAI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AICostValidation extends Model
{
    protected $table = 'ai_cost_validations';

    protected $fillable = [
        'provider',
        'total_records',
        'validated_records',
        'accurate_records',
        'discrepant_records',
        'validation_errors',
        'overall_accuracy',
        'total_calculated_cost',
        'total_provider_cost',
        'cost_difference',
        'cost_difference_percent',
        'discrepancies',
        'validation_summary',
    ];

    protected $casts = [
        'total_records' => 'integer',
        'validated_records' => 'integer',
        'accurate_records' => 'integer',
        'discrepant_records' => 'integer',
        'validation_errors' => 'integer',
        'overall_accuracy' => 'decimal:2',
        'total_calculated_cost' => 'decimal:6',
        'total_provider_cost' => 'decimal:6',
        'cost_difference' => 'decimal:6',
        'cost_difference_percent' => 'decimal:4',
        'discrepancies' => 'array',
        'validation_summary' => 'array',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AIProvider::class, 'provider', 'name');
    }

    public function usageCosts(): HasMany
    {
        return $this->hasMany(AIUsageCost::class, 'provider', 'provider');
    }

    public function scopeForProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    public function scopeAccurate($query, float $threshold = 95.0)
    {
        return $query->where('overall_accuracy', '>=', $threshold);
    }

    public function scopeInaccurate($query, float $threshold = 95.0)
    {
        return $query->where('overall_accuracy', '<', $threshold);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function getValidationRateAttribute(): float
    {
        if ($this->total_records == 0) {
            return 0;
        }

        return ($this->validated_records / $this->total_records) * 100;
    }

    public function getErrorRateAttribute(): float
    {
        if ($this->validated_records == 0) {
            return 0;
        }

        return ($this->validation_errors / $this->validated_records) * 100;
    }

    public function getDiscrepancyRateAttribute(): float
    {
        if ($this->validated_records == 0) {
            return 0;
        }

        return ($this->discrepant_records / $this->validated_records) * 100;
    }

    public function getAbsoluteCostDifferenceAttribute(): float
    {
        return abs($this->cost_difference);
    }

    public function getAbsoluteCostDifferencePercentAttribute(): float
    {
        return abs($this->cost_difference_percent);
    }

    public function isAccurate(float $threshold = 95.0): bool
    {
        return $this->overall_accuracy >= $threshold;
    }

    public function hasSignificantCostDifference(float $threshold = 5.0): bool
    {
        return $this->absolute_cost_difference_percent >= $threshold;
    }

    public function isOverestimating(): bool
    {
        return $this->total_calculated_cost > $this->total_provider_cost;
    }

    public function isUnderestimating(): bool
    {
        return $this->total_calculated_cost < $this->total_provider_cost;
    }

    public function getStatusAttribute(): string
    {
        if ($this->isAccurate()) {
            return 'accurate';
        } elseif ($this->hasSignificantCostDifference()) {
            return 'significant_variance';
        } elseif ($this->error_rate > 10) {
            return 'high_error_rate';
        } else {
            return 'minor_issues';
        }
    }

    public function getRecommendationsAttribute(): array
    {
        $recommendations = [];

        if (! $this->isAccurate()) {
            $recommendations[] = 'Review pricing calculation methodology';
        }

        if ($this->hasSignificantCostDifference()) {
            $recommendations[] = 'Investigate pricing discrepancies with provider';
        }

        if ($this->error_rate > 10) {
            $recommendations[] = 'Improve validation error handling';
        }

        if ($this->validation_rate < 80) {
            $recommendations[] = 'Increase validation coverage';
        }

        return $recommendations;
    }

    public static function createValidationReport(string $provider, array $validationData): self
    {
        $totalRecords = $validationData['total_records'] ?? 0;
        $validatedRecords = $validationData['validated_records'] ?? 0;
        $accurateRecords = $validationData['accurate_records'] ?? 0;

        $overallAccuracy = $validatedRecords > 0
            ? ($accurateRecords / $validatedRecords) * 100
            : 0;

        $totalCalculated = $validationData['total_calculated_cost'] ?? 0;
        $totalProvider = $validationData['total_provider_cost'] ?? 0;
        $costDifference = $totalCalculated - $totalProvider;
        $costDifferencePercent = $totalProvider > 0
            ? ($costDifference / $totalProvider) * 100
            : 0;

        return self::create([
            'provider' => $provider,
            'total_records' => $totalRecords,
            'validated_records' => $validatedRecords,
            'accurate_records' => $accurateRecords,
            'discrepant_records' => $validationData['discrepant_records'] ?? 0,
            'validation_errors' => $validationData['validation_errors'] ?? 0,
            'overall_accuracy' => $overallAccuracy,
            'total_calculated_cost' => $totalCalculated,
            'total_provider_cost' => $totalProvider,
            'cost_difference' => $costDifference,
            'cost_difference_percent' => $costDifferencePercent,
            'discrepancies' => $validationData['discrepancies'] ?? [],
            'validation_summary' => $validationData['validation_summary'] ?? [],
        ]);
    }

    public static function getLatestValidationForProvider(string $provider): ?self
    {
        return self::forProvider($provider)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    public static function getAccuracyTrend(string $provider, int $days = 30): array
    {
        return self::forProvider($provider)
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at')
            ->get(['created_at', 'overall_accuracy', 'cost_difference_percent'])
            ->toArray();
    }

    public static function getProviderAccuracyComparison(int $days = 30): array
    {
        return self::selectRaw('provider, AVG(overall_accuracy) as avg_accuracy, AVG(ABS(cost_difference_percent)) as avg_cost_variance')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('provider')
            ->orderBy('avg_accuracy', 'desc')
            ->get()
            ->toArray();
    }
}
