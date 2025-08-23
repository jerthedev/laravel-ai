<?php

namespace JTD\LaravelAI\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use JTD\LaravelAI\Models\AIConversation;
use JTD\LaravelAI\Models\ConversationTemplate;

/**
 * Conversation Template Service
 *
 * Manages conversation templates including creation, validation, parameter substitution,
 * and template-based conversation generation.
 */
class ConversationTemplateService
{
    /**
     * Create a new conversation template.
     */
    public function createTemplate(array $data): ConversationTemplate
    {
        $validatedData = $this->validateTemplateData($data);

        return DB::transaction(function () use ($validatedData) {
            $template = ConversationTemplate::create($validatedData);

            Log::info('Conversation template created', [
                'template_id' => $template->id,
                'template_uuid' => $template->uuid,
                'name' => $template->name,
                'category' => $template->category,
            ]);

            return $template;
        });
    }

    /**
     * Update an existing conversation template.
     */
    public function updateTemplate(ConversationTemplate $template, array $data): ConversationTemplate
    {
        $validatedData = $this->validateTemplateData($data, $template);

        return DB::transaction(function () use ($template, $validatedData) {
            $template->update($validatedData);

            Log::info('Conversation template updated', [
                'template_id' => $template->id,
                'template_uuid' => $template->uuid,
                'changes' => array_keys($validatedData),
            ]);

            return $template->fresh();
        });
    }

    /**
     * Delete a conversation template.
     */
    public function deleteTemplate(ConversationTemplate $template): bool
    {
        return DB::transaction(function () use ($template) {
            $deleted = $template->delete();

            if ($deleted) {
                Log::info('Conversation template deleted', [
                    'template_id' => $template->id,
                    'template_uuid' => $template->uuid,
                    'name' => $template->name,
                ]);
            }

            return $deleted;
        });
    }

    /**
     * Get templates with filtering and pagination.
     */
    public function getTemplates(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ConversationTemplate::query()->active();

        // Apply filters
        if (! empty($filters['category'])) {
            $query->inCategory($filters['category']);
        }

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (! empty($filters['tags'])) {
            $tags = is_array($filters['tags']) ? $filters['tags'] : [$filters['tags']];
            foreach ($tags as $tag) {
                $query->whereJsonContains('tags', $tag);
            }
        }

        if (! empty($filters['created_by'])) {
            $query->createdBy($filters['created_by']['id'], $filters['created_by']['type'] ?? null);
        }

        if (isset($filters['is_public'])) {
            if ($filters['is_public']) {
                $query->public();
            } else {
                $query->where('is_public', false);
            }
        }

        if (! empty($filters['language'])) {
            $query->where('language', $filters['language']);
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';

        switch ($sortBy) {
            case 'popularity':
                $query->orderByDesc('usage_count');
                break;
            case 'rating':
                $query->orderByDesc('avg_rating');
                break;
            case 'name':
                $query->orderBy('name', $sortDirection);
                break;
            default:
                $query->orderBy($sortBy, $sortDirection);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get popular templates.
     */
    public function getPopularTemplates(int $limit = 10, int $minUsage = 5): Collection
    {
        return ConversationTemplate::active()
            ->public()
            ->popular($minUsage)
            ->limit($limit)
            ->get();
    }

    /**
     * Get highly rated templates.
     */
    public function getHighlyRatedTemplates(int $limit = 10, float $minRating = 4.0): Collection
    {
        return ConversationTemplate::active()
            ->public()
            ->highlyRated($minRating)
            ->limit($limit)
            ->get();
    }

    /**
     * Get templates by category.
     */
    public function getTemplatesByCategory(string $category, int $limit = 20): Collection
    {
        return ConversationTemplate::active()
            ->public()
            ->inCategory($category)
            ->orderByDesc('usage_count')
            ->limit($limit)
            ->get();
    }

    /**
     * Search templates.
     */
    public function searchTemplates(string $query, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $filters['search'] = $query;

        return $this->getTemplates($filters, $perPage);
    }

    /**
     * Create conversation from template.
     */
    public function createConversationFromTemplate(
        ConversationTemplate $template,
        array $parameters = [],
        array $conversationData = []
    ): AIConversation {
        // Validate parameters
        $parameterErrors = $template->validateParameters($parameters);
        if (! empty($parameterErrors)) {
            throw new \InvalidArgumentException('Invalid parameters: ' . implode(', ', $parameterErrors));
        }

        return DB::transaction(function () use ($template, $parameters, $conversationData) {
            // Process template data with parameters
            $processedData = $this->processTemplateData($template, $parameters);

            // Merge with conversation data
            $conversationData = array_merge([
                'template_id' => $template->id,
                'title' => $processedData['title'] ?? $template->name,
                'ai_provider_id' => $template->ai_provider_id,
                'ai_provider_model_id' => $template->ai_provider_model_id,
                'provider_name' => $template->provider_name,
                'model_name' => $template->model_name,
                'configuration' => array_merge(
                    $template->default_configuration ?? [],
                    $conversationData['configuration'] ?? []
                ),
                'metadata' => array_merge(
                    ['template_uuid' => $template->uuid, 'template_parameters' => $parameters],
                    $conversationData['metadata'] ?? []
                ),
            ], $conversationData);

            // Create conversation
            $conversation = AIConversation::create($conversationData);

            // Add initial messages from template
            if (! empty($processedData['initial_messages'])) {
                foreach ($processedData['initial_messages'] as $messageData) {
                    $conversation->messages()->create([
                        'role' => $messageData['role'],
                        'content' => $messageData['content'],
                        'sequence_number' => $conversation->messages()->count() + 1,
                    ]);
                }
            }

            // Increment template usage
            $template->incrementUsage();

            Log::info('Conversation created from template', [
                'template_id' => $template->id,
                'template_uuid' => $template->uuid,
                'conversation_id' => $conversation->id,
                'conversation_uuid' => $conversation->uuid,
                'parameters_count' => count($parameters),
            ]);

            return $conversation;
        });
    }

    /**
     * Duplicate a template.
     */
    public function duplicateTemplate(ConversationTemplate $template, array $overrides = []): ConversationTemplate
    {
        $data = array_merge(
            $template->only([
                'name', 'description', 'category', 'template_data', 'parameters',
                'default_configuration', 'ai_provider_id', 'ai_provider_model_id',
                'provider_name', 'model_name', 'tags', 'language',
            ]),
            $overrides
        );

        // Ensure unique name
        if (! isset($overrides['name'])) {
            $data['name'] = $template->name . ' (Copy)';
        }

        // Reset public/published status for duplicates
        $data['is_public'] = false;
        $data['published_at'] = null;
        $data['usage_count'] = 0;
        $data['avg_rating'] = null;

        return $this->createTemplate($data);
    }

    /**
     * Validate template data.
     */
    protected function validateTemplateData(array $data, ?ConversationTemplate $template = null): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category' => 'required|string|in:' . implode(',', [
                ConversationTemplate::CATEGORY_GENERAL,
                ConversationTemplate::CATEGORY_BUSINESS,
                ConversationTemplate::CATEGORY_CREATIVE,
                ConversationTemplate::CATEGORY_TECHNICAL,
                ConversationTemplate::CATEGORY_EDUCATIONAL,
                ConversationTemplate::CATEGORY_ANALYSIS,
                ConversationTemplate::CATEGORY_SUPPORT,
            ]),
            'template_data' => 'required|array',
            'template_data.system_prompt' => 'nullable|string',
            'template_data.initial_messages' => 'nullable|array',
            'template_data.initial_messages.*.role' => 'required|string|in:system,user,assistant',
            'template_data.initial_messages.*.content' => 'required|string',
            'parameters' => 'nullable|array',
            'parameters.*.type' => 'nullable|string|in:string,integer,float,boolean,array,enum',
            'parameters.*.required' => 'nullable|boolean',
            'parameters.*.default' => 'nullable',
            'parameters.*.description' => 'nullable|string',
            'default_configuration' => 'nullable|array',
            'ai_provider_id' => 'nullable|exists:ai_providers,id',
            'ai_provider_model_id' => 'nullable|exists:ai_provider_models,id',
            'provider_name' => 'nullable|string|max:100',
            'model_name' => 'nullable|string|max:100',
            'is_public' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'language' => 'nullable|string|size:2',
            'metadata' => 'nullable|array',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new \InvalidArgumentException('Validation failed: ' . implode(', ', $validator->errors()->all()));
        }

        return $validator->validated();
    }

    /**
     * Process template data with parameter substitution.
     */
    protected function processTemplateData(ConversationTemplate $template, array $parameters): array
    {
        $templateData = $template->template_data ?? [];
        $processedData = [];

        // Process system prompt
        if (! empty($templateData['system_prompt'])) {
            $processedData['system_prompt'] = $this->substituteParameters(
                $templateData['system_prompt'],
                $parameters,
                $template->parameters ?? []
            );
        }

        // Process title
        if (! empty($templateData['title'])) {
            $processedData['title'] = $this->substituteParameters(
                $templateData['title'],
                $parameters,
                $template->parameters ?? []
            );
        }

        // Process initial messages
        if (! empty($templateData['initial_messages'])) {
            $processedData['initial_messages'] = [];
            foreach ($templateData['initial_messages'] as $message) {
                $processedData['initial_messages'][] = [
                    'role' => $message['role'],
                    'content' => $this->substituteParameters(
                        $message['content'],
                        $parameters,
                        $template->parameters ?? []
                    ),
                ];
            }
        }

        return $processedData;
    }

    /**
     * Substitute parameters in text content.
     */
    protected function substituteParameters(string $content, array $values, array $parameterDefinitions): string
    {
        // Find all parameter placeholders: {{parameter_name}}
        preg_match_all('/\{\{([^}]+)\}\}/', $content, $matches);

        if (empty($matches[1])) {
            return $content;
        }

        $replacements = [];

        foreach ($matches[1] as $parameterName) {
            $parameterName = trim($parameterName);

            // Get value from provided values or default
            $value = $values[$parameterName] ?? null;

            if ($value === null && isset($parameterDefinitions[$parameterName]['default'])) {
                $value = $parameterDefinitions[$parameterName]['default'];
            }

            // Convert value to string
            $stringValue = $this->convertValueToString($value);

            $replacements['{{' . $parameterName . '}}'] = $stringValue;
        }

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    /**
     * Convert parameter value to string representation.
     */
    protected function convertValueToString($value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return implode(', ', $value);
        }

        return (string) $value;
    }

    /**
     * Get template statistics.
     */
    public function getTemplateStatistics(): array
    {
        return [
            'total_templates' => ConversationTemplate::count(),
            'active_templates' => ConversationTemplate::active()->count(),
            'public_templates' => ConversationTemplate::public()->count(),
            'published_templates' => ConversationTemplate::published()->count(),
            'templates_by_category' => ConversationTemplate::active()
                ->selectRaw('category, COUNT(*) as count')
                ->groupBy('category')
                ->pluck('count', 'category')
                ->toArray(),
            'most_used_template' => ConversationTemplate::active()
                ->orderByDesc('usage_count')
                ->first()?->only(['uuid', 'name', 'usage_count']),
            'highest_rated_template' => ConversationTemplate::active()
                ->whereNotNull('avg_rating')
                ->orderByDesc('avg_rating')
                ->first()?->only(['uuid', 'name', 'avg_rating']),
        ];
    }

    /**
     * Export template data.
     */
    public function exportTemplate(ConversationTemplate $template): array
    {
        return [
            'name' => $template->name,
            'description' => $template->description,
            'category' => $template->category,
            'template_data' => $template->template_data,
            'parameters' => $template->parameters,
            'default_configuration' => $template->default_configuration,
            'provider_name' => $template->provider_name,
            'model_name' => $template->model_name,
            'tags' => $template->tags,
            'language' => $template->language,
            'metadata' => $template->metadata,
            'exported_at' => now()->toISOString(),
            'export_version' => '1.0',
        ];
    }

    /**
     * Import template data.
     */
    public function importTemplate(array $templateData, array $overrides = []): ConversationTemplate
    {
        // Validate import data
        $requiredFields = ['name', 'category', 'template_data'];
        foreach ($requiredFields as $field) {
            if (! isset($templateData[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        // Merge with overrides
        $data = array_merge($templateData, $overrides);

        // Remove export metadata
        unset($data['exported_at'], $data['export_version']);

        return $this->createTemplate($data);
    }

    /**
     * Get available categories.
     */
    public function getAvailableCategories(): array
    {
        return [
            ConversationTemplate::CATEGORY_GENERAL => 'General',
            ConversationTemplate::CATEGORY_BUSINESS => 'Business',
            ConversationTemplate::CATEGORY_CREATIVE => 'Creative',
            ConversationTemplate::CATEGORY_TECHNICAL => 'Technical',
            ConversationTemplate::CATEGORY_EDUCATIONAL => 'Educational',
            ConversationTemplate::CATEGORY_ANALYSIS => 'Analysis',
            ConversationTemplate::CATEGORY_SUPPORT => 'Support',
        ];
    }

    /**
     * Get parameter type options.
     */
    public function getParameterTypes(): array
    {
        return [
            'string' => 'Text',
            'integer' => 'Number (Integer)',
            'float' => 'Number (Decimal)',
            'boolean' => 'True/False',
            'array' => 'List',
            'enum' => 'Selection',
        ];
    }
}
