<?php

namespace JTD\LaravelAI\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * AI Conversation Model
 *
 * Represents a conversation thread with an AI provider, including
 * messages, cost tracking, performance metrics, and metadata.
 *
 * @property int $id
 * @property string $uuid
 * @property string $title
 * @property string|null $description
 * @property string $status
 * @property int|null $user_id
 * @property string|null $user_type
 * @property string|null $session_id
 * @property array|null $participants
 * @property int|null $ai_provider_id
 * @property int|null $ai_provider_model_id
 * @property string|null $provider_name
 * @property string|null $model_name
 * @property array|null $system_prompt
 * @property array|null $configuration
 * @property array|null $context_data
 * @property int|null $max_messages
 * @property bool $auto_title
 * @property float $total_cost
 * @property int $total_input_tokens
 * @property int $total_output_tokens
 * @property int $total_messages
 * @property int $total_requests
 * @property int|null $avg_response_time_ms
 * @property float|null $avg_quality_rating
 * @property int $successful_requests
 * @property int $failed_requests
 * @property array|null $tags
 * @property array|null $metadata
 * @property string $language
 * @property string $conversation_type
 * @property Carbon|null $last_message_at
 * @property Carbon|null $last_activity_at
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AIConversation extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'ai_conversations';

    /**
     * The columns that should receive a unique identifier.
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \JTD\LaravelAI\Database\Factories\AIConversationFactory::new();
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uuid',
        'title',
        'description',
        'status',
        'user_id',
        'user_type',
        'session_id',
        'participants',
        'ai_provider_id',
        'ai_provider_model_id',
        'template_id',
        'provider_name',
        'model_name',
        'system_prompt',
        'configuration',
        'context_data',
        'max_messages',
        'auto_title',
        'total_cost',
        'total_input_tokens',
        'total_output_tokens',
        'total_messages',
        'total_requests',
        'avg_response_time_ms',
        'avg_quality_rating',
        'successful_requests',
        'failed_requests',
        'tags',
        'metadata',
        'language',
        'conversation_type',
        'last_message_at',
        'last_activity_at',
        'archived_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'participants' => 'array',
        'system_prompt' => 'array',
        'configuration' => 'array',
        'context_data' => 'array',
        'auto_title' => 'boolean',
        'total_cost' => 'decimal:6',
        'total_input_tokens' => 'integer',
        'total_output_tokens' => 'integer',
        'total_messages' => 'integer',
        'total_requests' => 'integer',
        'avg_response_time_ms' => 'integer',
        'avg_quality_rating' => 'decimal:2',
        'successful_requests' => 'integer',
        'failed_requests' => 'integer',
        'tags' => 'array',
        'metadata' => 'array',
        'last_message_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'user_id',
        'user_type',
        'session_id',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = [
        'total_tokens',
        'success_rate',
        'is_active',
        'is_archived',
    ];

    /**
     * Status constants.
     */
    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUS_DELETED = 'deleted';

    /**
     * Conversation type constants.
     */
    public const TYPE_CHAT = 'chat';

    public const TYPE_COMPLETION = 'completion';

    public const TYPE_ANALYSIS = 'analysis';

    public const TYPE_CREATIVE = 'creative';

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (AIConversation $conversation) {
            if (empty($conversation->title)) {
                $conversation->title = 'New Conversation';
            }

            if (empty($conversation->status)) {
                $conversation->status = self::STATUS_ACTIVE;
            }

            if (empty($conversation->language)) {
                $conversation->language = 'en';
            }

            if (empty($conversation->conversation_type)) {
                $conversation->conversation_type = self::TYPE_CHAT;
            }

            if (is_null($conversation->auto_title)) {
                $conversation->auto_title = true;
            }

            // Set default values for numeric fields (only if not explicitly provided)
            $attributes = $conversation->getAttributes();

            // Skip total_cost default - let it be set by the migration default or explicit values
            if (! array_key_exists('total_messages', $attributes) || is_null($conversation->total_messages)) {
                $conversation->total_messages = 0;
            }
            if (! array_key_exists('total_requests', $attributes) || is_null($conversation->total_requests)) {
                $conversation->total_requests = 0;
            }
            if (! array_key_exists('successful_requests', $attributes) || is_null($conversation->successful_requests)) {
                $conversation->successful_requests = 0;
            }
            if (! array_key_exists('failed_requests', $attributes) || is_null($conversation->failed_requests)) {
                $conversation->failed_requests = 0;
            }
            if (is_null($conversation->total_input_tokens)) {
                $conversation->total_input_tokens = 0;
            }
            if (is_null($conversation->total_output_tokens)) {
                $conversation->total_output_tokens = 0;
            }

            if (is_null($conversation->last_activity_at)) {
                $conversation->last_activity_at = now();
            }
        });

        static::updating(function (AIConversation $conversation) {
            $conversation->last_activity_at = now();
        });
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Get the user that owns the conversation (polymorphic).
     */
    public function user(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the AI provider for this conversation.
     */
    public function aiProvider(): BelongsTo
    {
        return $this->belongsTo(AIProvider::class, 'ai_provider_id');
    }

    /**
     * Get the AI provider model for this conversation.
     */
    public function aiProviderModel(): BelongsTo
    {
        return $this->belongsTo(AIProviderModel::class, 'ai_provider_model_id');
    }

    /**
     * Get all messages in this conversation.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(AIMessageRecord::class, 'ai_conversation_id')
            ->orderBy('sequence_number');
    }

    /**
     * Get the latest message in this conversation.
     */
    public function latestMessage(): HasMany
    {
        return $this->hasMany(AIMessageRecord::class, 'ai_conversation_id')
            ->latest('sequence_number')
            ->limit(1);
    }

    /**
     * Get user messages only.
     */
    public function userMessages(): HasMany
    {
        return $this->messages()->where('role', 'user');
    }

    /**
     * Get assistant messages only.
     */
    public function assistantMessages(): HasMany
    {
        return $this->messages()->where('role', 'assistant');
    }

    /**
     * Get system messages only.
     */
    public function systemMessages(): HasMany
    {
        return $this->messages()->where('role', 'system');
    }

    /**
     * Get provider history for this conversation.
     */
    public function providerHistory(): HasMany
    {
        return $this->hasMany(AIConversationProviderHistory::class, 'ai_conversation_id')
            ->orderBy('started_at', 'asc');
    }

    /**
     * Get the current active provider session.
     */
    public function activeProviderSession(): ?AIConversationProviderHistory
    {
        return $this->providerHistory()
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first();
    }

    /**
     * Get total tokens accessor.
     */
    public function getTotalTokensAttribute(): int
    {
        return $this->total_input_tokens + $this->total_output_tokens;
    }

    /**
     * Get success rate accessor.
     */
    public function getSuccessRateAttribute(): float
    {
        if (! $this->total_requests || $this->total_requests === 0) {
            return 0.0;
        }

        return round(($this->successful_requests / $this->total_requests) * 100, 2);
    }

    /**
     * Get is active accessor.
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Get is archived accessor.
     */
    public function getIsArchivedAttribute(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    /**
     * Scope to filter active conversations.
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope to filter archived conversations.
     */
    public function scopeArchived($query)
    {
        return $query->where('status', self::STATUS_ARCHIVED);
    }

    /**
     * Scope to filter by user.
     */
    public function scopeForUser($query, $userId, $userType = null)
    {
        $query->where('user_id', $userId);

        if ($userType) {
            $query->where('user_type', $userType);
        }

        return $query;
    }

    /**
     * Scope to filter by session.
     */
    public function scopeForSession($query, string $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    /**
     * Scope to filter by provider.
     */
    public function scopeForProvider($query, $providerId)
    {
        return $query->where('ai_provider_id', $providerId);
    }

    /**
     * Scope to filter by conversation type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('conversation_type', $type);
    }

    /**
     * Scope to filter recent conversations.
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('last_activity_at', '>=', now()->subDays($days));
    }

    /**
     * Scope to search conversations by title or description.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }

    /**
     * Archive this conversation.
     */
    public function archive(): bool
    {
        return $this->update([
            'status' => self::STATUS_ARCHIVED,
            'archived_at' => now(),
        ]);
    }

    /**
     * Restore this conversation from archive.
     */
    public function restore(): bool
    {
        return $this->update([
            'status' => self::STATUS_ACTIVE,
            'archived_at' => null,
        ]);
    }

    /**
     * Add a tag to this conversation.
     */
    public function addTag(string $tag): void
    {
        $tags = $this->tags ?? [];

        if (! in_array($tag, $tags)) {
            $tags[] = $tag;
            $this->update(['tags' => $tags]);
        }
    }

    /**
     * Remove a tag from this conversation.
     */
    public function removeTag(string $tag): void
    {
        $tags = $this->tags ?? [];

        if (($key = array_search($tag, $tags)) !== false) {
            unset($tags[$key]);
            $this->update(['tags' => array_values($tags)]);
        }
    }

    /**
     * Check if conversation has a specific tag.
     */
    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags ?? []);
    }

    // ========================================================================
    // CONTEXT MANAGEMENT METHODS
    // ========================================================================

    /**
     * Get context settings for this conversation.
     */
    public function getContextSettings(): array
    {
        return $this->context_data['settings'] ?? [];
    }

    /**
     * Set context settings for this conversation.
     */
    public function setContextSettings(array $settings): void
    {
        $contextData = $this->context_data ?? [];
        $contextData['settings'] = array_merge($contextData['settings'] ?? [], $settings);

        $this->update(['context_data' => $contextData]);
    }

    /**
     * Get context window size for this conversation.
     */
    public function getContextWindow(): ?int
    {
        return $this->getContextSettings()['window_size'] ?? null;
    }

    /**
     * Set context window size for this conversation.
     */
    public function setContextWindow(int $windowSize): void
    {
        $this->setContextSettings(['window_size' => $windowSize]);
    }

    /**
     * Get context preservation strategy for this conversation.
     */
    public function getContextStrategy(): string
    {
        return $this->getContextSettings()['preservation_strategy'] ?? 'intelligent_truncation';
    }

    /**
     * Set context preservation strategy for this conversation.
     */
    public function setContextStrategy(string $strategy): void
    {
        $this->setContextSettings(['preservation_strategy' => $strategy]);
    }

    /**
     * Get context ratio (percentage of context window to use).
     */
    public function getContextRatio(): float
    {
        return $this->getContextSettings()['context_ratio'] ?? 0.8;
    }

    /**
     * Set context ratio for this conversation.
     */
    public function setContextRatio(float $ratio): void
    {
        $this->setContextSettings(['context_ratio' => max(0.1, min(1.0, $ratio))]);
    }

    /**
     * Check if search-enhanced context is enabled.
     */
    public function isSearchEnhancedContextEnabled(): bool
    {
        return $this->getContextSettings()['search_enhanced'] ?? true;
    }

    /**
     * Enable or disable search-enhanced context.
     */
    public function setSearchEnhancedContext(bool $enabled): void
    {
        $this->setContextSettings(['search_enhanced' => $enabled]);
    }

    /**
     * Get context cache TTL in seconds.
     */
    public function getContextCacheTtl(): int
    {
        return $this->getContextSettings()['cache_ttl'] ?? 300;
    }

    /**
     * Set context cache TTL.
     */
    public function setContextCacheTtl(int $ttl): void
    {
        $this->setContextSettings(['cache_ttl' => max(60, $ttl)]);
    }

    /**
     * Get all context configuration as array.
     */
    public function getContextConfiguration(): array
    {
        $settings = $this->getContextSettings();

        return [
            'window_size' => $settings['window_size'] ?? null,
            'preservation_strategy' => $settings['preservation_strategy'] ?? 'intelligent_truncation',
            'context_ratio' => $settings['context_ratio'] ?? 0.8,
            'search_enhanced' => $settings['search_enhanced'] ?? true,
            'cache_ttl' => $settings['cache_ttl'] ?? 300,
            'max_search_results' => $settings['max_search_results'] ?? 10,
            'relevance_threshold' => $settings['relevance_threshold'] ?? 0.7,
        ];
    }

    /**
     * Update multiple context settings at once.
     */
    public function updateContextConfiguration(array $config): void
    {
        $allowedKeys = [
            'window_size', 'preservation_strategy', 'context_ratio',
            'search_enhanced', 'cache_ttl', 'max_search_results', 'relevance_threshold',
        ];

        $filteredConfig = array_intersect_key($config, array_flip($allowedKeys));

        if (! empty($filteredConfig)) {
            $this->setContextSettings($filteredConfig);
        }
    }
}
