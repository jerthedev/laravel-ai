<?php

namespace JTD\LaravelAI\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use JTD\LaravelAI\Contracts\AIProviderInterface;
use JTD\LaravelAI\Events\ConversationCreated;
use JTD\LaravelAI\Events\ConversationUpdated;
use JTD\LaravelAI\Events\MessageAdded;
use JTD\LaravelAI\Exceptions\ConversationException;
use JTD\LaravelAI\Models\AIConversation;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIMessageRecord;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Models\ConversationTemplate;
use JTD\LaravelAI\Models\TokenUsage;

/**
 * Conversation Service
 *
 * Handles conversation lifecycle, message management, and template processing.
 * Provides high-level operations for conversation management with proper
 * event handling, cost tracking, and performance monitoring.
 */
class ConversationService
{
    /**
     * Create a new conversation.
     */
    public function createConversation(array $data = []): AIConversation
    {
        $conversation = DB::transaction(function () use ($data) {
            $conversation = AIConversation::create([
                'uuid' => $data['uuid'] ?? (string) Str::uuid(),
                'title' => $data['title'] ?? 'New Conversation',
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? AIConversation::STATUS_ACTIVE,
                'user_id' => $data['user_id'] ?? null,
                'user_type' => $data['user_type'] ?? null,
                'session_id' => $data['session_id'] ?? null,
                'template_id' => $data['template_id'] ?? null,
                'ai_provider_id' => $data['ai_provider_id'] ?? null,
                'ai_provider_model_id' => $data['ai_provider_model_id'] ?? null,
                'provider_name' => $data['provider_name'] ?? null,
                'model_name' => $data['model_name'] ?? null,
                'system_prompt' => $data['system_prompt'] ?? null,
                'configuration' => $data['configuration'] ?? null,
                'context_data' => $data['context_data'] ?? null,
                'max_messages' => $data['max_messages'] ?? null,
                'auto_title' => $data['auto_title'] ?? true,
                'tags' => $data['tags'] ?? null,
                'metadata' => $data['metadata'] ?? null,
                'language' => $data['language'] ?? 'en',
                'conversation_type' => $data['conversation_type'] ?? AIConversation::TYPE_CHAT,
                'total_cost' => $data['total_cost'] ?? 0,
                'total_messages' => $data['total_messages'] ?? 0,
                'total_input_tokens' => $data['total_input_tokens'] ?? 0,
                'total_output_tokens' => $data['total_output_tokens'] ?? 0,
                'total_requests' => $data['total_requests'] ?? 0,
                'successful_requests' => $data['successful_requests'] ?? 0,
                'failed_requests' => $data['failed_requests'] ?? 0,
                'avg_response_time_ms' => $data['avg_response_time_ms'] ?? null,
            ]);

            // Add system prompt as first message if provided
            if (! empty($data['system_prompt'])) {
                $systemPromptContent = is_array($data['system_prompt'])
                    ? $data['system_prompt']['content']
                    : $data['system_prompt'];
                $this->addSystemMessage($conversation, $systemPromptContent);
            }

            return $conversation;
        });

        Event::dispatch(new ConversationCreated($conversation));

        return $conversation;
    }

    /**
     * Create conversation from template.
     */
    public function createFromTemplate(ConversationTemplate $template, array $parameters = [], array $overrides = []): AIConversation
    {
        // Validate template parameters
        $validationErrors = $template->validateParameters($parameters);
        if (! empty($validationErrors)) {
            throw new ConversationException('Invalid template parameters: ' . implode(', ', $validationErrors));
        }

        // Process template data with parameters
        $templateData = $this->processTemplateData($template->template_data, $parameters);

        // Merge template configuration with overrides
        $configuration = array_merge(
            $template->default_configuration ?? [],
            $overrides['configuration'] ?? []
        );

        $conversationData = array_merge([
            'title' => $this->processTemplateString($template->name, $parameters),
            'description' => $this->processTemplateString($template->description ?? '', $parameters),
            'template_id' => $template->id,
            'ai_provider_id' => $template->ai_provider_id,
            'ai_provider_model_id' => $template->ai_provider_model_id,
            'provider_name' => $template->provider_name,
            'model_name' => $template->model_name,
            'system_prompt' => isset($templateData['system_prompt'])
                ? ['role' => 'system', 'content' => $this->processTemplateString($templateData['system_prompt'], $parameters)]
                : null,
            'configuration' => $configuration,
            'language' => $template->language,
            'tags' => $template->tags,
        ], $overrides);

        $conversation = $this->createConversation($conversationData);

        // Add initial messages from template
        if (isset($templateData['initial_messages'])) {
            foreach ($templateData['initial_messages'] as $messageData) {
                $content = $this->processTemplateString($messageData['content'], $parameters);
                $message = match ($messageData['role']) {
                    'user' => AIMessage::user($content),
                    'system' => AIMessage::system($content),
                    'assistant' => AIMessage::assistant($content),
                    default => throw new \InvalidArgumentException("Unsupported role: {$messageData['role']}")
                };
                $this->addMessage($conversation, $message);
            }
        }

        // Increment template usage
        $template->incrementUsage();

        return $conversation;
    }

    /**
     * Add a message to a conversation.
     */
    public function addMessage(AIConversation $conversation, AIMessage $message, array $metadata = []): AIMessageRecord
    {
        $messageRecord = DB::transaction(function () use ($conversation, $message, $metadata) {
            // Create message record
            $messageRecord = AIMessageRecord::fromAIMessage($message, $conversation->id);
            $messageRecord->fill($metadata);
            $messageRecord->save();

            // Update conversation statistics
            $this->updateConversationStats($conversation, $messageRecord);

            return $messageRecord;
        });

        Event::dispatch(new MessageAdded($conversation, $messageRecord));

        return $messageRecord;
    }

    /**
     * Add a system message to a conversation.
     */
    public function addSystemMessage(AIConversation $conversation, string $content, array $metadata = []): AIMessageRecord
    {
        $message = AIMessage::system($content);

        return $this->addMessage($conversation, $message, $metadata);
    }

    /**
     * Send a message and get AI response.
     */
    public function sendMessage(
        AIConversation $conversation,
        AIMessage $message,
        AIProviderInterface $provider,
        array $options = []
    ): AIResponse {
        return DB::transaction(function () use ($conversation, $message, $provider, $options) {
            // Add user message
            $userMessageRecord = $this->addMessage($conversation, $message);

            // Get conversation context
            $messages = $this->getConversationMessages($conversation);

            // Send to AI provider
            $startTime = microtime(true);
            $response = $provider->sendMessage($messages, $options);
            $responseTime = (microtime(true) - $startTime) * 1000;

            // Add AI response message
            $assistantMessage = AIMessage::assistant($response->content);
            $assistantMessageRecord = $this->addMessage($conversation, $assistantMessage, [
                'provider_message_id' => $response->id ?? null,
                'request_parameters' => $options,
                'response_metadata' => $response->metadata ?? [],
                'finish_reason' => $response->finishReason,
                'input_tokens' => $response->tokenUsage->inputTokens,
                'output_tokens' => $response->tokenUsage->outputTokens,
                'total_tokens' => $response->tokenUsage->totalTokens,
                'cost' => $response->getTotalCost() ?? 0,
                'cost_breakdown' => $response->costBreakdown ?? [],
                'response_time_ms' => $responseTime,
                'tool_calls' => $response->toolCalls ?? null,
            ]);

            // Update conversation with response data
            $this->updateConversationWithResponse($conversation, $response, $responseTime);

            // Auto-generate title if needed
            if ($conversation->auto_title && $conversation->title === 'New Conversation' && $conversation->total_messages >= 2) {
                $this->generateConversationTitle($conversation, $provider);
            }

            return $response;
        });
    }

    /**
     * Get conversation messages as AIMessage array.
     */
    public function getConversationMessages(AIConversation $conversation): array
    {
        return $conversation->messages()
            ->orderBy('sequence_number')
            ->get()
            ->map(fn (AIMessageRecord $record) => $record->toAIMessage())
            ->toArray();
    }

    /**
     * Update conversation statistics.
     */
    protected function updateConversationStats(AIConversation $conversation, AIMessageRecord $message): void
    {
        $conversation->increment('total_messages');
        $conversation->update([
            'last_message_at' => now(),
            'last_activity_at' => now(),
        ]);
    }

    /**
     * Update conversation with AI response data.
     */
    protected function updateConversationWithResponse(AIConversation $conversation, AIResponse $response, float $responseTime): void
    {
        $conversation->increment('total_requests');
        $conversation->increment('successful_requests');
        $conversation->increment('total_input_tokens', $response->tokenUsage->inputTokens);
        $conversation->increment('total_output_tokens', $response->tokenUsage->outputTokens);
        $conversation->increment('total_cost', $response->getTotalCost() ?? 0);

        // Update average response time
        $totalRequests = $conversation->total_requests;
        $currentAvg = $conversation->avg_response_time_ms ?? 0;
        $newAvg = (($currentAvg * ($totalRequests - 1)) + $responseTime) / $totalRequests;

        $conversation->update([
            'avg_response_time_ms' => round($newAvg),
            'last_activity_at' => now(),
        ]);

        // Create a simple ConversationUpdated event with just the conversation
        // For now, we'll create a simpler version that just takes the conversation
        Event::dispatch(new ConversationUpdated(
            $conversation->uuid,
            $response->toMessage(),
            $response,
            $response->provider,
            $response->model,
            $conversation->total_messages,
            $conversation->total_cost
        ));
    }

    /**
     * Process template data with parameters.
     */
    protected function processTemplateData(array $templateData, array $parameters): array
    {
        $processed = [];

        foreach ($templateData as $key => $value) {
            if (is_string($value)) {
                $processed[$key] = $this->processTemplateString($value, $parameters);
            } elseif (is_array($value)) {
                $processed[$key] = $this->processTemplateData($value, $parameters);
            } else {
                $processed[$key] = $value;
            }
        }

        return $processed;
    }

    /**
     * Process template string with parameter substitution.
     */
    protected function processTemplateString(string $template, array $parameters): string
    {
        return preg_replace_callback('/\{\{(\w+)\}\}/', function ($matches) use ($parameters) {
            $key = $matches[1];

            return $parameters[$key] ?? $matches[0];
        }, $template);
    }

    /**
     * Generate conversation title using AI.
     */
    protected function generateConversationTitle(AIConversation $conversation, AIProviderInterface $provider): void
    {
        try {
            $messages = $this->getConversationMessages($conversation);
            $recentMessages = array_slice($messages, 0, 4); // Use first 4 messages

            $titlePrompt = AIMessage::user(
                'Generate a short, descriptive title (max 50 characters) for this conversation based on the content. ' .
                'Respond with only the title, no quotes or extra text.'
            );

            $titleMessages = array_merge($recentMessages, [$titlePrompt]);
            $response = $provider->sendMessage($titleMessages, ['max_tokens' => 20]);

            $title = trim($response->content);
            if (! empty($title) && strlen($title) <= 50) {
                $conversation->update(['title' => $title]);
            }
        } catch (\Exception $e) {
            // Silently fail title generation - not critical
        }
    }

    /**
     * Archive a conversation.
     */
    public function archiveConversation(AIConversation $conversation): bool
    {
        $result = $conversation->archive();

        if ($result) {
            // Create a ConversationUpdated event for archive operation
            // Use dummy values since this is just an archive operation
            $dummyMessage = AIMessage::system('Conversation archived');
            $dummyResponse = AIResponse::success('', new TokenUsage(0, 0, 0), '', '');

            Event::dispatch(new ConversationUpdated(
                $conversation->uuid,
                $dummyMessage,
                $dummyResponse,
                $conversation->provider_name ?? 'unknown',
                $conversation->model_name ?? 'unknown',
                $conversation->total_messages ?? 0,
                $conversation->total_cost ?? 0.0
            ));
        }

        return $result;
    }

    /**
     * Delete a conversation.
     */
    public function deleteConversation(AIConversation $conversation): bool
    {
        return DB::transaction(function () use ($conversation) {
            // Soft delete all messages
            $conversation->messages()->delete();

            // Soft delete conversation
            return $conversation->delete();
        });
    }

    /**
     * Get conversation statistics.
     */
    public function getConversationStats(AIConversation $conversation): array
    {
        return [
            'total_messages' => $conversation->total_messages,
            'total_tokens' => $conversation->total_tokens,
            'total_cost' => $conversation->total_cost,
            'avg_response_time_ms' => $conversation->avg_response_time_ms,
            'success_rate' => $conversation->success_rate,
            'duration_minutes' => $conversation->last_activity_at
                ? abs($conversation->created_at->diffInMinutes($conversation->last_activity_at))
                : $conversation->created_at->diffInMinutes(now()),
            'messages_per_hour' => $this->calculateMessagesPerHour($conversation),
        ];
    }

    /**
     * Calculate messages per hour for a conversation.
     */
    protected function calculateMessagesPerHour(AIConversation $conversation): float
    {
        $durationHours = max(1, $conversation->created_at->diffInHours(now()));

        return round($conversation->total_messages / $durationHours, 2);
    }
}
