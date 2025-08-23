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
 * Conversation Template Model
 *
 * Represents a reusable conversation template with parameters,
 * system prompts, and configuration that can be used to start
 * new conversations with predefined settings.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string|null $description
 * @property string $category
 * @property array $template_data
 * @property array|null $parameters
 * @property array|null $default_configuration
 * @property int|null $ai_provider_id
 * @property int|null $ai_provider_model_id
 * @property string|null $provider_name
 * @property string|null $model_name
 * @property bool $is_public
 * @property bool $is_active
 * @property int|null $created_by_id
 * @property string|null $created_by_type
 * @property int $usage_count
 * @property float|null $avg_rating
 * @property array|null $tags
 * @property array|null $metadata
 * @property string $language
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class ConversationTemplate extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'ai_conversation_templates';

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
        'uuid',
        'name',
        'description',
        'category',
        'template_data',
        'parameters',
        'default_configuration',
        'ai_provider_id',
        'ai_provider_model_id',
        'provider_name',
        'model_name',
        'is_public',
        'is_active',
        'created_by_id',
        'created_by_type',
        'usage_count',
        'avg_rating',
        'tags',
        'metadata',
        'language',
        'published_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'template_data' => 'array',
        'parameters' => 'array',
        'default_configuration' => 'array',
        'is_public' => 'boolean',
        'is_active' => 'boolean',
        'usage_count' => 'integer',
        'avg_rating' => 'decimal:2',
        'tags' => 'array',
        'metadata' => 'array',
        'published_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'created_by_id',
        'created_by_type',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = [
        'is_published',
        'parameter_count',
        'has_system_prompt',
    ];

    /**
     * Category constants.
     */
    public const CATEGORY_GENERAL = 'general';

    public const CATEGORY_BUSINESS = 'business';

    public const CATEGORY_CREATIVE = 'creative';

    public const CATEGORY_TECHNICAL = 'technical';

    public const CATEGORY_EDUCATIONAL = 'educational';

    public const CATEGORY_ANALYSIS = 'analysis';

    public const CATEGORY_SUPPORT = 'support';

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (ConversationTemplate $template) {
            if (empty($template->language)) {
                $template->language = 'en';
            }

            if (empty($template->category)) {
                $template->category = self::CATEGORY_GENERAL;
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
     * Get the user that created this template (polymorphic).
     */
    public function createdBy(): MorphTo
    {
        return $this->morphTo('created_by');
    }

    /**
     * Get the AI provider for this template.
     */
    public function aiProvider(): BelongsTo
    {
        return $this->belongsTo(AIProvider::class, 'ai_provider_id');
    }

    /**
     * Get the AI provider model for this template.
     */
    public function aiProviderModel(): BelongsTo
    {
        return $this->belongsTo(AIProviderModel::class, 'ai_provider_model_id');
    }

    /**
     * Get conversations created from this template.
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(AIConversation::class, 'template_id');
    }

    /**
     * Get is published accessor.
     */
    public function getIsPublishedAttribute(): bool
    {
        return ! is_null($this->published_at) && $this->published_at->isPast();
    }

    /**
     * Get parameter count accessor.
     */
    public function getParameterCountAttribute(): int
    {
        return count($this->parameters ?? []);
    }

    /**
     * Get has system prompt accessor.
     */
    public function getHasSystemPromptAttribute(): bool
    {
        $templateData = $this->template_data ?? [];

        return isset($templateData['system_prompt']) && ! empty($templateData['system_prompt']);
    }

    /**
     * Scope to filter active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter public templates.
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope to filter published templates.
     */
    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * Scope to filter by category.
     */
    public function scopeInCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to filter by creator.
     */
    public function scopeCreatedBy($query, $userId, $userType = null)
    {
        $query->where('created_by_id', $userId);

        if ($userType) {
            $query->where('created_by_type', $userType);
        }

        return $query;
    }

    /**
     * Scope to search templates.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhereJsonContains('tags', $search);
        });
    }

    /**
     * Scope to filter popular templates.
     */
    public function scopePopular($query, int $minUsage = 10)
    {
        return $query->where('usage_count', '>=', $minUsage)
            ->orderByDesc('usage_count');
    }

    /**
     * Scope to filter highly rated templates.
     */
    public function scopeHighlyRated($query, float $minRating = 4.0)
    {
        return $query->whereNotNull('avg_rating')
            ->where('avg_rating', '>=', $minRating)
            ->orderByDesc('avg_rating');
    }

    /**
     * Publish this template.
     */
    public function publish(): bool
    {
        return $this->update([
            'is_public' => true,
            'published_at' => now(),
        ]);
    }

    /**
     * Unpublish this template.
     */
    public function unpublish(): bool
    {
        return $this->update([
            'is_public' => false,
            'published_at' => null,
        ]);
    }

    /**
     * Increment usage count.
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    /**
     * Add a tag to this template.
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
     * Remove a tag from this template.
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
     * Check if template has a specific tag.
     */
    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags ?? []);
    }

    /**
     * Get required parameters.
     */
    public function getRequiredParameters(): array
    {
        $parameters = $this->parameters ?? [];

        return array_filter($parameters, function ($param) {
            return ($param['required'] ?? false) === true;
        });
    }

    /**
     * Get optional parameters.
     */
    public function getOptionalParameters(): array
    {
        $parameters = $this->parameters ?? [];

        return array_filter($parameters, function ($param) {
            return ($param['required'] ?? false) === false;
        });
    }

    /**
     * Validate parameter values.
     */
    public function validateParameters(array $values): array
    {
        $errors = [];
        $parameters = $this->parameters ?? [];

        foreach ($parameters as $key => $param) {
            $required = $param['required'] ?? false;
            $type = $param['type'] ?? 'string';
            $value = $values[$key] ?? null;

            if ($required && empty($value)) {
                $errors[$key] = "Parameter '{$key}' is required";

                continue;
            }

            if (! empty($value) && ! $this->validateParameterType($value, $type)) {
                $errors[$key] = "Parameter '{$key}' must be of type {$type}";
            }
        }

        return $errors;
    }

    /**
     * Validate parameter type.
     */
    protected function validateParameterType($value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'integer' => is_int($value) || (is_string($value) && ctype_digit($value)),
            'float' => is_float($value) || is_numeric($value),
            'boolean' => is_bool($value) || in_array($value, ['true', 'false', '1', '0']),
            'array' => is_array($value),
            default => true,
        };
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \JTD\LaravelAI\Database\Factories\ConversationTemplateFactory::new();
    }
}
