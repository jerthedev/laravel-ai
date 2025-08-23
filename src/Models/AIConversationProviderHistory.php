<?php

namespace JTD\LaravelAI\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AI Conversation Provider History Model
 *
 * Tracks the history of provider switches and usage within conversations.
 *
 * @property int $id
 * @property string $uuid
 * @property int $ai_conversation_id
 * @property int|null $ai_provider_id
 * @property int|null $ai_provider_model_id
 * @property string $provider_name
 * @property string $model_name
 * @property string $switch_type
 * @property string|null $switch_reason
 * @property string|null $switch_context
 * @property string|null $previous_provider_name
 * @property string|null $previous_model_name
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property int $message_count
 * @property int $total_input_tokens
 * @property int $total_output_tokens
 * @property float $total_cost
 * @property int $successful_requests
 * @property int $failed_requests
 * @property int|null $avg_response_time_ms
 * @property float|null $success_rate
 * @property float|null $cost_per_message
 * @property float|null $tokens_per_message
 * @property array|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class AIConversationProviderHistory extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'ai_conversation_provider_history';

    /**
     * The columns that should receive a unique identifier.
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'ai_conversation_id',
        'ai_provider_id',
        'ai_provider_model_id',
        'provider_name',
        'model_name',
        'switch_type',
        'switch_reason',
        'switch_context',
        'previous_provider_name',
        'previous_model_name',
        'started_at',
        'ended_at',
        'message_count',
        'total_input_tokens',
        'total_output_tokens',
        'total_cost',
        'successful_requests',
        'failed_requests',
        'avg_response_time_ms',
        'success_rate',
        'cost_per_message',
        'tokens_per_message',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'message_count' => 'integer',
        'total_input_tokens' => 'integer',
        'total_output_tokens' => 'integer',
        'total_cost' => 'decimal:6',
        'successful_requests' => 'integer',
        'failed_requests' => 'integer',
        'avg_response_time_ms' => 'integer',
        'success_rate' => 'decimal:2',
        'cost_per_message' => 'decimal:6',
        'tokens_per_message' => 'decimal:2',
        'metadata' => 'array',
        'switch_context' => 'array',
    ];

    /**
     * Switch type constants.
     */
    public const SWITCH_TYPE_INITIAL = 'initial';

    public const SWITCH_TYPE_MANUAL = 'manual';

    public const SWITCH_TYPE_FALLBACK = 'fallback';

    public const SWITCH_TYPE_AUTOMATIC = 'automatic';

    /**
     * Get the conversation that owns this provider history.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AIConversation::class, 'ai_conversation_id');
    }

    /**
     * Get the provider associated with this history.
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(AIProvider::class, 'ai_provider_id');
    }

    /**
     * Get the model associated with this history.
     */
    public function model(): BelongsTo
    {
        return $this->belongsTo(AIProviderModel::class, 'ai_provider_model_id');
    }

    /**
     * Calculate and update performance metrics.
     */
    public function updatePerformanceMetrics(): void
    {
        $totalRequests = $this->successful_requests + $this->failed_requests;

        if ($totalRequests > 0) {
            $this->success_rate = round(($this->successful_requests / $totalRequests) * 100, 2);
        }

        if ($this->message_count > 0) {
            $this->cost_per_message = round($this->total_cost / $this->message_count, 6);
            $totalTokens = $this->total_input_tokens + $this->total_output_tokens;
            $this->tokens_per_message = round($totalTokens / $this->message_count, 2);
        }

        $this->save();
    }

    /**
     * End the current provider session.
     */
    public function endSession(): void
    {
        $this->ended_at = now();
        $this->updatePerformanceMetrics();
    }

    /**
     * Check if this provider session is currently active.
     */
    public function isActive(): bool
    {
        return is_null($this->ended_at);
    }

    /**
     * Get the duration of this provider session.
     */
    public function getDurationAttribute(): ?int
    {
        if (! $this->ended_at) {
            return null;
        }

        return $this->started_at->diffInMinutes($this->ended_at);
    }

    /**
     * Get the total tokens used.
     */
    public function getTotalTokensAttribute(): int
    {
        return $this->total_input_tokens + $this->total_output_tokens;
    }

    /**
     * Scope to get active provider sessions.
     */
    public function scopeActive($query)
    {
        return $query->whereNull('ended_at');
    }

    /**
     * Scope to get sessions for a specific provider.
     */
    public function scopeForProvider($query, string $providerName)
    {
        return $query->where('provider_name', $providerName);
    }

    /**
     * Scope to get sessions within a date range.
     */
    public function scopeWithinDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('started_at', [$startDate, $endDate]);
    }

    /**
     * Scope to get sessions by switch type.
     */
    public function scopeBySwitchType($query, string $switchType)
    {
        return $query->where('switch_type', $switchType);
    }

    /**
     * Create a new provider history entry.
     */
    public static function createForSwitch(
        AIConversation $conversation,
        string $switchType,
        array $data = []
    ): self {
        return static::create(array_merge([
            'ai_conversation_id' => $conversation->id,
            'ai_provider_id' => $conversation->ai_provider_id,
            'ai_provider_model_id' => $conversation->ai_provider_model_id,
            'provider_name' => $conversation->provider_name,
            'model_name' => $conversation->model_name,
            'switch_type' => $switchType,
            'started_at' => now(),
        ], $data));
    }
}
