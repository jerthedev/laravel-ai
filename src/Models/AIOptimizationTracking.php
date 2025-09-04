<?php

namespace JTD\LaravelAI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIOptimizationTracking extends Model
{
    protected $table = 'ai_optimization_tracking';

    protected $fillable = [
        'optimization_id',
        'optimization_type',
        'component',
        'component_name',
        'status',
        'description',
        'implementation_details',
        'baseline_metrics',
        'target_metrics',
        'actual_metrics',
        'implementation_metrics',
        'expected_improvement_percentage',
        'actual_improvement_percentage',
        'implementation_hours_estimated',
        'implementation_hours_actual',
        'planned_start_date',
        'actual_start_date',
        'planned_completion_date',
        'actual_completion_date',
        'assigned_to',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'implementation_details' => 'array',
        'baseline_metrics' => 'array',
        'target_metrics' => 'array',
        'actual_metrics' => 'array',
        'implementation_metrics' => 'array',
        'expected_improvement_percentage' => 'decimal:2',
        'actual_improvement_percentage' => 'decimal:2',
        'implementation_hours_estimated' => 'integer',
        'implementation_hours_actual' => 'integer',
        'planned_start_date' => 'datetime',
        'actual_start_date' => 'datetime',
        'planned_completion_date' => 'datetime',
        'actual_completion_date' => 'datetime',
        'assigned_to' => 'integer',
        'created_by' => 'integer',
    ];

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePlanned($query)
    {
        return $query->where('status', 'planned');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('optimization_type', $type);
    }

    public function scopeByComponent($query, string $component)
    {
        return $query->where('component', $component);
    }

    public function scopeAssignedTo($query, int $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeCreatedBy($query, int $userId)
    {
        return $query->where('created_by', $userId);
    }

    public function scopeOverdue($query)
    {
        return $query->where('planned_completion_date', '<', now())
            ->whereIn('status', ['planned', 'in_progress']);
    }

    public function scopeDueThis Week($query)
    {
        return $query->whereBetween('planned_completion_date', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    public function scopeHighImpact($query, float $threshold = 20.0)
    {
        return $query->where('expected_improvement_percentage', '>=', $threshold);
    }

    public function getActualDurationDaysAttribute(): ?int
    {
        if (!$this->actual_start_date || !$this->actual_completion_date) {
            return null;
        }
        
        return $this->actual_start_date->diffInDays($this->actual_completion_date);
    }

    public function getPlannedDurationDaysAttribute(): ?int
    {
        if (!$this->planned_start_date || !$this->planned_completion_date) {
            return null;
        }
        
        return $this->planned_start_date->diffInDays($this->planned_completion_date);
    }

    public function getScheduleVarianceDaysAttribute(): ?int
    {
        if (!$this->actual_completion_date || !$this->planned_completion_date) {
            return null;
        }
        
        return $this->planned_completion_date->diffInDays($this->actual_completion_date, false);
    }

    public function getEffortVarianceHoursAttribute(): ?int
    {
        if (!$this->implementation_hours_actual || !$this->implementation_hours_estimated) {
            return null;
        }
        
        return $this->implementation_hours_actual - $this->implementation_hours_estimated;
    }

    public function getImprovementVarianceAttribute(): ?float
    {
        if (!$this->actual_improvement_percentage || !$this->expected_improvement_percentage) {
            return null;
        }
        
        return $this->actual_improvement_percentage - $this->expected_improvement_percentage;
    }

    public function isPlanned(): bool
    {
        return $this->status === 'planned';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isOverdue(): bool
    {
        if (!$this->planned_completion_date) {
            return false;
        }
        
        return $this->planned_completion_date->isPast() && 
               in_array($this->status, ['planned', 'in_progress']);
    }

    public function isOnSchedule(): bool
    {
        return $this->schedule_variance_days <= 0;
    }

    public function isWithinBudget(): bool
    {
        return $this->effort_variance_hours <= 0;
    }

    public function exceededExpectations(): bool
    {
        return $this->improvement_variance > 0;
    }

    public function metExpectations(float $tolerance = 5.0): bool
    {
        $variance = abs($this->improvement_variance ?? 0);
        return $variance <= $tolerance;
    }

    public function start(int $userId): bool
    {
        if ($this->status !== 'planned') {
            return false;
        }
        
        return $this->update([
            'status' => 'in_progress',
            'actual_start_date' => now(),
            'assigned_to' => $userId,
        ]);
    }

    public function complete(array $actualMetrics, float $actualImprovement, int $actualHours): bool
    {
        if ($this->status !== 'in_progress') {
            return false;
        }
        
        return $this->update([
            'status' => 'completed',
            'actual_completion_date' => now(),
            'actual_metrics' => $actualMetrics,
            'actual_improvement_percentage' => $actualImprovement,
            'implementation_hours_actual' => $actualHours,
        ]);
    }

    public function fail(string $reason): bool
    {
        if (!in_array($this->status, ['planned', 'in_progress'])) {
            return false;
        }
        
        return $this->update([
            'status' => 'failed',
            'notes' => ($this->notes ?? '') . "\n\nFailed: " . $reason,
        ]);
    }

    public function cancel(string $reason): bool
    {
        if (!in_array($this->status, ['planned', 'in_progress'])) {
            return false;
        }
        
        return $this->update([
            'status' => 'cancelled',
            'notes' => ($this->notes ?? '') . "\n\nCancelled: " . $reason,
        ]);
    }

    public static function createOptimization(array $data): self
    {
        return self::create(array_merge([
            'optimization_id' => uniqid('opt_'),
            'status' => 'planned',
        ], $data));
    }

    public static function getCompletionStats(int $days = 30): array
    {
        $optimizations = self::where('created_at', '>=', now()->subDays($days))->get();
        
        $completed = $optimizations->filter(fn($opt) => $opt->isCompleted());
        $failed = $optimizations->filter(fn($opt) => $opt->isFailed());
        $cancelled = $optimizations->filter(fn($opt) => $opt->isCancelled());
        
        return [
            'total' => $optimizations->count(),
            'completed' => $completed->count(),
            'failed' => $failed->count(),
            'cancelled' => $cancelled->count(),
            'in_progress' => $optimizations->filter(fn($opt) => $opt->isInProgress())->count(),
            'planned' => $optimizations->filter(fn($opt) => $opt->isPlanned())->count(),
            'completion_rate' => $optimizations->count() > 0 ? ($completed->count() / $optimizations->count()) * 100 : 0,
            'avg_implementation_hours' => $completed->avg('implementation_hours_actual'),
            'avg_improvement_achieved' => $completed->avg('actual_improvement_percentage'),
            'on_time_deliveries' => $completed->filter(fn($opt) => $opt->isOnSchedule())->count(),
        ];
    }

    public static function getImpactAnalysis(int $days = 90): array
    {
        $completed = self::completed()
            ->where('actual_completion_date', '>=', now()->subDays($days))
            ->get();
        
        return [
            'total_optimizations' => $completed->count(),
            'total_improvement_percentage' => $completed->sum('actual_improvement_percentage'),
            'avg_improvement_percentage' => $completed->avg('actual_improvement_percentage'),
            'high_impact_optimizations' => $completed->filter(fn($opt) => $opt->actual_improvement_percentage >= 20)->count(),
            'by_type' => $completed->groupBy('optimization_type')->map(function($group) {
                return [
                    'count' => $group->count(),
                    'avg_improvement' => $group->avg('actual_improvement_percentage'),
                    'total_hours' => $group->sum('implementation_hours_actual'),
                ];
            })->toArray(),
            'by_component' => $completed->groupBy('component')->map(function($group) {
                return [
                    'count' => $group->count(),
                    'avg_improvement' => $group->avg('actual_improvement_percentage'),
                ];
            })->toArray(),
        ];
    }

    public static function getUpcomingOptimizations(int $days = 14): array
    {
        return self::whereIn('status', ['planned', 'in_progress'])
            ->where('planned_completion_date', '<=', now()->addDays($days))
            ->orderBy('planned_completion_date')
            ->get()
            ->map(function($opt) {
                return [
                    'optimization_id' => $opt->optimization_id,
                    'component' => $opt->component,
                    'component_name' => $opt->component_name,
                    'status' => $opt->status,
                    'planned_completion_date' => $opt->planned_completion_date,
                    'is_overdue' => $opt->isOverdue(),
                    'assigned_user' => $opt->assignedUser?->name,
                    'expected_improvement' => $opt->expected_improvement_percentage,
                ];
            })
            ->toArray();
    }

    public static function getTeamPerformance(): array
    {
        $optimizations = self::completed()
            ->where('actual_completion_date', '>=', now()->subMonths(3))
            ->with('assignedUser')
            ->get();
        
        return $optimizations->groupBy('assigned_to')->map(function($userOptimizations) {
            $user = $userOptimizations->first()->assignedUser;
            
            return [
                'user_id' => $user?->id,
                'user_name' => $user?->name,
                'completed_optimizations' => $userOptimizations->count(),
                'total_improvement_achieved' => $userOptimizations->sum('actual_improvement_percentage'),
                'avg_improvement_per_optimization' => $userOptimizations->avg('actual_improvement_percentage'),
                'total_hours_spent' => $userOptimizations->sum('implementation_hours_actual'),
                'avg_hours_per_optimization' => $userOptimizations->avg('implementation_hours_actual'),
                'on_time_delivery_rate' => ($userOptimizations->filter(fn($opt) => $opt->isOnSchedule())->count() / $userOptimizations->count()) * 100,
            ];
        })->values()->toArray();
    }
}