<?php

namespace JTD\LaravelAI\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * AI Provider Model
 *
 * Represents a specific AI model from a provider (e.g., gpt-4, grok-3-mini)
 * with capabilities, pricing, and performance information.
 *
 * @property int $id
 * @property int $ai_provider_id
 * @property string $model_id
 * @property string $name
 * @property string|null $description
 * @property string $model_type
 * @property bool $is_active
 * @property bool $is_default
 * @property array|null $capabilities
 * @property int|null $context_length
 * @property int|null $max_output_tokens
 * @property array|null $supported_formats
 * @property array|null $pricing
 * @property array|null $performance_metrics
 * @property string|null $version
 * @property Carbon|null $deprecated_at
 * @property array|null $metadata
 * @property Carbon|null $last_sync_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AIProviderModel extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'ai_provider_models';

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \JTD\LaravelAI\Database\Factories\AIProviderModelFactory::new();
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'ai_provider_id',
        'model_id',
        'name',
        'version',
        'description',
        'status',
        'supports_chat',
        'supports_completion',
        'supports_streaming',
        'supports_function_calling',
        'supports_vision',
        'supports_audio',
        'supports_embeddings',
        'supports_fine_tuning',
        'max_tokens',
        'context_length',
        'context_window',
        'is_default',
        'default_temperature',
        'min_temperature',
        'max_temperature',
        'max_top_p',
        'supported_formats',
        'supported_languages',
        'input_token_cost',
        'output_token_cost',
        'training_cost',
        'pricing_currency',
        'pricing_model',
        'avg_response_time_ms',
        'avg_quality_score',
        'reliability_score',
        'total_requests',
        'successful_requests',
        'failed_requests',
        'last_synced_at',
        'deprecated_at',
        'sunset_date',
        'provider_metadata',
        'custom_metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'capabilities' => 'array',
        'context_length' => 'integer',
        'max_output_tokens' => 'integer',
        'supported_formats' => 'array',
        'pricing' => 'array',
        'performance_metrics' => 'array',
        'deprecated_at' => 'datetime',
        'metadata' => 'array',
        'last_sync_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = [
        'is_deprecated',
        'full_name',
    ];

    /**
     * Model type constants.
     */
    public const TYPE_CHAT = 'chat';

    public const TYPE_COMPLETION = 'completion';

    public const TYPE_EMBEDDING = 'embedding';

    public const TYPE_IMAGE = 'image';

    public const TYPE_AUDIO = 'audio';

    public const TYPE_MULTIMODAL = 'multimodal';

    /**
     * Get the provider this model belongs to.
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(AIProvider::class, 'ai_provider_id');
    }

    /**
     * Get conversations using this model.
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(AIConversation::class, 'ai_provider_model_id');
    }

    /**
     * Get messages using this model.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(AIMessageRecord::class, 'ai_provider_model_id');
    }

    /**
     * Get model costs.
     */
    public function costs(): HasMany
    {
        return $this->hasMany(AIProviderModelCost::class, 'ai_provider_model_id');
    }

    /**
     * Get is deprecated accessor.
     */
    public function getIsDeprecatedAttribute(): bool
    {
        return ! is_null($this->deprecated_at) && $this->deprecated_at->isPast();
    }

    /**
     * Get full name accessor.
     */
    public function getFullNameAttribute(): string
    {
        return $this->provider->display_name . ' - ' . $this->name;
    }

    /**
     * Scope to filter active models.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by model type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('model_type', $type);
    }

    /**
     * Scope to filter non-deprecated models.
     */
    public function scopeNotDeprecated($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('deprecated_at')
                ->orWhere('deprecated_at', '>', now());
        });
    }

    /**
     * Scope to filter by provider.
     */
    public function scopeForProvider($query, $providerId)
    {
        return $query->where('ai_provider_id', $providerId);
    }

    /**
     * Check if model supports a capability.
     */
    public function supports(string $capability): bool
    {
        $capabilities = $this->capabilities ?? [];

        return isset($capabilities[$capability]) && $capabilities[$capability] === true;
    }

    /**
     * Get input cost per token.
     */
    public function getInputCostPerToken(): ?float
    {
        $pricing = $this->pricing ?? [];

        return $pricing['input_per_token'] ?? null;
    }

    /**
     * Get output cost per token.
     */
    public function getOutputCostPerToken(): ?float
    {
        $pricing = $this->pricing ?? [];

        return $pricing['output_per_token'] ?? null;
    }

    /**
     * Calculate cost for token usage.
     */
    public function calculateCost(int $inputTokens, int $outputTokens): float
    {
        $inputCost = ($this->getInputCostPerToken() ?? 0) * $inputTokens;
        $outputCost = ($this->getOutputCostPerToken() ?? 0) * $outputTokens;

        return $inputCost + $outputCost;
    }

    /**
     * Mark as deprecated.
     */
    public function deprecate(): void
    {
        $this->update(['deprecated_at' => now()]);
    }

    /**
     * Mark sync as completed.
     */
    public function markSyncCompleted(): void
    {
        $this->update(['last_sync_at' => now()]);
    }
}
