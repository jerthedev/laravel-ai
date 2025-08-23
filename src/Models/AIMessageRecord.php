<?php

namespace JTD\LaravelAI\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * AI Message Record Model
 *
 * Eloquent model for persisting AI messages in the database.
 * This is separate from the AIMessage DTO to maintain clean separation
 * between data transfer and persistence layers.
 *
 * @property int $id
 * @property int $ai_conversation_id
 * @property string $uuid
 * @property int $sequence_number
 * @property string $role
 * @property string $content
 * @property array|null $content_metadata
 * @property string $content_type
 * @property array|null $attachments
 * @property int|null $ai_provider_id
 * @property int|null $ai_provider_model_id
 * @property string|null $provider_message_id
 * @property array|null $request_parameters
 * @property array|null $response_metadata
 * @property string|null $finish_reason
 * @property bool $is_streaming
 * @property int|null $stream_chunks
 * @property int|null $input_tokens
 * @property int|null $output_tokens
 * @property int|null $total_tokens
 * @property float|null $cost
 * @property string $cost_currency
 * @property array|null $cost_breakdown
 * @property int|null $response_time_ms
 * @property float|null $quality_rating
 * @property string|null $quality_feedback
 * @property array|null $tool_calls
 * @property string|null $tool_call_id
 * @property array|null $function_call
 * @property string|null $function_name
 * @property array|null $error_details
 * @property string|null $error_code
 * @property bool $is_edited
 * @property Carbon|null $edited_at
 * @property array|null $edit_history
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AIMessageRecord extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'ai_messages';

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \JTD\LaravelAI\Database\Factories\AIMessageRecordFactory::new();
    }

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
        'uuid',
        'sequence_number',
        'role',
        'content',
        'content_metadata',
        'content_type',
        'attachments',
        'ai_provider_id',
        'ai_provider_model_id',
        'provider_message_id',
        'request_parameters',
        'response_metadata',
        'finish_reason',
        'is_streaming',
        'stream_chunks',
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'cost',
        'cost_currency',
        'cost_breakdown',
        'response_time_ms',
        'quality_rating',
        'quality_feedback',
        'tool_calls',
        'tool_call_id',
        'function_call',
        'function_name',
        'error_details',
        'error_code',
        'is_edited',
        'edited_at',
        'edit_history',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'content_metadata' => 'array',
        'attachments' => 'array',
        'request_parameters' => 'array',
        'response_metadata' => 'array',
        'is_streaming' => 'boolean',
        'stream_chunks' => 'integer',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'total_tokens' => 'integer',
        'cost' => 'decimal:6',
        'cost_breakdown' => 'array',
        'response_time_ms' => 'integer',
        'quality_rating' => 'decimal:2',
        'tool_calls' => 'array',
        'function_call' => 'array',
        'error_details' => 'array',
        'is_edited' => 'boolean',
        'edited_at' => 'datetime',
        'edit_history' => 'array',
        'metadata' => 'array',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'ai_provider_id',
        'ai_provider_model_id',
        'provider_message_id',
        'error_details',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = [
        'has_tokens',
        'has_cost',
        'is_user_message',
        'is_assistant_message',
        'is_system_message',
    ];

    /**
     * Role constants.
     */
    public const ROLE_SYSTEM = 'system';

    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    public const ROLE_FUNCTION = 'function';

    public const ROLE_TOOL = 'tool';

    /**
     * Content type constants.
     */
    public const CONTENT_TYPE_TEXT = 'text';

    public const CONTENT_TYPE_IMAGE = 'image';

    public const CONTENT_TYPE_AUDIO = 'audio';

    public const CONTENT_TYPE_FILE = 'file';

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (AIMessageRecord $message) {
            // Set sequence number
            $message->sequence_number = static::where('ai_conversation_id', $message->ai_conversation_id)
                ->max('sequence_number') + 1;

            if (is_null($message->sequence_number)) {
                $message->sequence_number = static::where('ai_conversation_id', $message->ai_conversation_id)
                    ->max('sequence_number') + 1;
            }

            if (empty($message->content_type)) {
                $message->content_type = self::CONTENT_TYPE_TEXT;
            }

            if (empty($message->cost_currency)) {
                $message->cost_currency = 'USD';
            }

            // Set default boolean values
            if (is_null($message->is_streaming)) {
                $message->is_streaming = false;
            }

            if (is_null($message->is_edited)) {
                $message->is_edited = false;
            }
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
     * Get the conversation this message belongs to.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AIConversation::class, 'ai_conversation_id');
    }

    /**
     * Get the AI provider for this message.
     */
    public function aiProvider(): BelongsTo
    {
        return $this->belongsTo(AIProvider::class, 'ai_provider_id');
    }

    /**
     * Get the AI provider model for this message.
     */
    public function aiProviderModel(): BelongsTo
    {
        return $this->belongsTo(AIProviderModel::class, 'ai_provider_model_id');
    }

    /**
     * Get has tokens accessor.
     */
    public function getHasTokensAttribute(): bool
    {
        return ! is_null($this->total_tokens) && $this->total_tokens > 0;
    }

    /**
     * Get has cost accessor.
     */
    public function getHasCostAttribute(): bool
    {
        return ! is_null($this->cost) && $this->cost > 0;
    }

    /**
     * Get is user message accessor.
     */
    public function getIsUserMessageAttribute(): bool
    {
        return $this->role === self::ROLE_USER;
    }

    /**
     * Get is assistant message accessor.
     */
    public function getIsAssistantMessageAttribute(): bool
    {
        return $this->role === self::ROLE_ASSISTANT;
    }

    /**
     * Get is system message accessor.
     */
    public function getIsSystemMessageAttribute(): bool
    {
        return $this->role === self::ROLE_SYSTEM;
    }

    /**
     * Scope to filter by role.
     */
    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Scope to filter user messages.
     */
    public function scopeUserMessages($query)
    {
        return $query->where('role', self::ROLE_USER);
    }

    /**
     * Scope to filter assistant messages.
     */
    public function scopeAssistantMessages($query)
    {
        return $query->where('role', self::ROLE_ASSISTANT);
    }

    /**
     * Scope to filter system messages.
     */
    public function scopeSystemMessages($query)
    {
        return $query->where('role', self::ROLE_SYSTEM);
    }

    /**
     * Scope to filter by content type.
     */
    public function scopeByContentType($query, string $contentType)
    {
        return $query->where('content_type', $contentType);
    }

    /**
     * Scope to filter messages with tokens.
     */
    public function scopeWithTokens($query)
    {
        return $query->whereNotNull('total_tokens')->where('total_tokens', '>', 0);
    }

    /**
     * Scope to filter messages with cost.
     */
    public function scopeWithCost($query)
    {
        return $query->whereNotNull('cost')->where('cost', '>', 0);
    }

    /**
     * Scope to filter streaming messages.
     */
    public function scopeStreaming($query)
    {
        return $query->where('is_streaming', true);
    }

    /**
     * Scope to filter messages with errors.
     */
    public function scopeWithErrors($query)
    {
        return $query->whereNotNull('error_code');
    }

    /**
     * Convert to AIMessage DTO.
     */
    public function toAIMessage(): AIMessage
    {
        return new AIMessage(
            content: $this->content,
            role: $this->role,
            contentType: $this->content_type,
            metadata: array_merge(
                $this->content_metadata ?? [],
                [
                    'uuid' => $this->uuid,
                    'sequence_number' => $this->sequence_number,
                    'created_at' => $this->created_at?->toISOString(),
                ]
            )
        );
    }

    /**
     * Create from AIMessage DTO.
     */
    public static function fromAIMessage(AIMessage $message, int $conversationId): self
    {
        $instance = new self([
            'ai_conversation_id' => $conversationId,
            'role' => $message->role,
            'content' => $message->content,
            'content_type' => $message->contentType,
            'content_metadata' => $message->metadata,
            'attachments' => $message->attachments ?? null,
        ]);

        // Set sequence number
        $instance->sequence_number = static::where('ai_conversation_id', $conversationId)
            ->max('sequence_number') + 1;

        return $instance;
    }
}
