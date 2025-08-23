<?php

namespace JTD\LaravelAI\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AI Provider Model Cost
 *
 * Represents pricing information for AI models with historical tracking
 * and different pricing tiers or regions.
 *
 * @property int $id
 * @property int $ai_provider_model_id
 * @property string $cost_type
 * @property float $cost_per_unit
 * @property string $unit_type
 * @property int $unit_size
 * @property string $currency
 * @property string|null $region
 * @property string|null $tier
 * @property Carbon|null $effective_from
 * @property Carbon|null $effective_until
 * @property bool $is_active
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AIProviderModelCost extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'ai_provider_model_costs';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'ai_provider_model_id',
        'cost_type',
        'cost_per_unit',
        'unit_type',
        'unit_size',
        'currency',
        'region',
        'tier',
        'effective_from',
        'effective_until',
        'is_active',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'cost_per_unit' => 'decimal:8',
        'unit_size' => 'integer',
        'effective_from' => 'datetime',
        'effective_until' => 'datetime',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Cost type constants.
     */
    public const TYPE_INPUT = 'input';

    public const TYPE_OUTPUT = 'output';

    public const TYPE_TRAINING = 'training';

    public const TYPE_FINE_TUNING = 'fine_tuning';

    public const TYPE_EMBEDDING = 'embedding';

    public const TYPE_IMAGE = 'image';

    public const TYPE_AUDIO = 'audio';

    /**
     * Unit type constants.
     */
    public const UNIT_TOKEN = 'token';

    public const UNIT_CHARACTER = 'character';

    public const UNIT_REQUEST = 'request';

    public const UNIT_SECOND = 'second';

    public const UNIT_IMAGE = 'image';

    /**
     * Get the model this cost belongs to.
     */
    public function model(): BelongsTo
    {
        return $this->belongsTo(AIProviderModel::class, 'ai_provider_model_id');
    }

    /**
     * Scope to filter active costs.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by cost type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('cost_type', $type);
    }

    /**
     * Scope to filter current costs.
     */
    public function scopeCurrent($query)
    {
        $now = now();

        return $query->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('effective_until')
                    ->orWhere('effective_until', '>', $now);
            });
    }

    /**
     * Scope to filter by region.
     */
    public function scopeForRegion($query, ?string $region = null)
    {
        if ($region) {
            return $query->where('region', $region);
        }

        return $query->whereNull('region');
    }

    /**
     * Scope to filter by tier.
     */
    public function scopeForTier($query, ?string $tier = null)
    {
        if ($tier) {
            return $query->where('tier', $tier);
        }

        return $query->whereNull('tier');
    }

    /**
     * Check if cost is currently effective.
     */
    public function isEffective(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();

        if ($this->effective_from && $this->effective_from->isAfter($now)) {
            return false;
        }

        if ($this->effective_until && $this->effective_until->isBefore($now)) {
            return false;
        }

        return true;
    }

    /**
     * Calculate cost for given units.
     */
    public function calculateCost(int $units): float
    {
        return ($units / $this->unit_size) * $this->cost_per_unit;
    }
}
