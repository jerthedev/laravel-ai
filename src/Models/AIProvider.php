<?php

namespace JTD\LaravelAI\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * AI Provider Model
 *
 * Represents an AI service provider (OpenAI, xAI, Gemini, etc.)
 * with configuration, capabilities, and status information.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $display_name
 * @property string|null $description
 * @property string $driver_class
 * @property bool $is_active
 * @property bool $is_default
 * @property array|null $configuration
 * @property array|null $capabilities
 * @property array|null $rate_limits
 * @property string|null $api_version
 * @property string|null $base_url
 * @property int $priority
 * @property array|null $metadata
 * @property Carbon|null $last_sync_at
 * @property Carbon|null $last_health_check_at
 * @property string|null $health_status
 * @property array|null $health_details
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AIProvider extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'ai_providers';

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \JTD\LaravelAI\Database\Factories\AIProviderFactory::new();
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'slug',
        'driver',
        'config',
        'status',
        'description',
        'website_url',
        'documentation_url',
        'supports_streaming',
        'supports_function_calling',
        'supports_vision',
        'max_tokens',
        'max_context_length',
        'default_temperature',
        'supported_formats',
        'rate_limits',
        'last_synced_at',
        'last_health_check_at',
        'health_status',
        'health_message',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'config' => 'array',
        'supports_streaming' => 'boolean',
        'supports_function_calling' => 'boolean',
        'supports_vision' => 'boolean',
        'max_tokens' => 'integer',
        'max_context_length' => 'integer',
        'default_temperature' => 'decimal:2',
        'supported_formats' => 'array',
        'rate_limits' => 'array',
        'last_synced_at' => 'datetime',
        'last_health_check_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'config',
    ];

    /**
     * Health status constants.
     */
    public const HEALTH_HEALTHY = 'healthy';

    public const HEALTH_DEGRADED = 'degraded';

    public const HEALTH_UNHEALTHY = 'unhealthy';

    public const HEALTH_UNKNOWN = 'unknown';

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Get all models for this provider.
     */
    public function models(): HasMany
    {
        return $this->hasMany(AIProviderModel::class, 'ai_provider_id');
    }

    /**
     * Get active models for this provider.
     */
    public function activeModels(): HasMany
    {
        return $this->models()->where('is_active', true);
    }

    /**
     * Get conversations using this provider.
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(AIConversation::class, 'ai_provider_id');
    }

    /**
     * Get messages using this provider.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(AIMessageRecord::class, 'ai_provider_id');
    }

    /**
     * Scope to filter active providers.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by health status.
     */
    public function scopeHealthy($query)
    {
        return $query->where('health_status', self::HEALTH_HEALTHY);
    }

    /**
     * Scope to order by priority.
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority');
    }

    /**
     * Check if provider is healthy.
     */
    public function isHealthy(): bool
    {
        return $this->health_status === self::HEALTH_HEALTHY;
    }

    /**
     * Check if provider supports a capability.
     */
    public function supports(string $capability): bool
    {
        $capabilities = $this->capabilities ?? [];

        return isset($capabilities[$capability]) && $capabilities[$capability] === true;
    }

    /**
     * Update health status.
     */
    public function updateHealthStatus(string $status, array $details = []): void
    {
        $this->update([
            'health_status' => $status,
            'health_details' => $details,
            'last_health_check_at' => now(),
        ]);
    }

    /**
     * Mark sync as completed.
     */
    public function markSyncCompleted(): void
    {
        $this->update(['last_sync_at' => now()]);
    }
}
