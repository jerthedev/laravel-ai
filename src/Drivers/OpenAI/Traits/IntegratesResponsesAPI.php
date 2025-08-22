<?php

namespace JTD\LaravelAI\Drivers\OpenAI\Traits;

use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use JTD\LaravelAI\Models\TokenUsage;

/**
 * Integrates OpenAI Responses API
 *
 * Handles the new OpenAI Responses API for GPT-5
 * and advanced function calling features.
 */
trait IntegratesResponsesAPI
{
    /**
     * Check if we should use the Responses API.
     */
    protected function shouldUseResponsesAPI(array $options): bool
    {
        // Use Responses API if explicitly requested
        if (isset($options['use_responses_api']) && $options['use_responses_api']) {
            return true;
        }

        // Use Responses API for newer models that support it better
        $model = $options['model'] ?? $this->defaultModel;
        $responsesAPIModels = [
            'gpt-5', 
            'gpt-5-2025-08-07',  // Actual GPT-5 model name
            'gpt-4o-2024-12-17', 
            'gpt-4o-mini-2024-12-17'
        ];
        
        return in_array($model, $responsesAPIModels) || str_starts_with($model, 'gpt-5');
    }

    /**
     * Prepare parameters for Responses API.
     */
    protected function prepareResponsesAPIParameters(array $messages, array $options): array
    {
        $params = [
            'model' => $options['model'] ?? $this->defaultModel,
            'input' => $this->formatMessagesForResponsesAPI($messages, $options),
        ];

        // Map max_tokens to max_output_tokens for Responses API
        if (isset($options['max_tokens'])) {
            $params['max_output_tokens'] = $options['max_tokens'];
        }

        // Add other parameters (skip temperature for GPT-5 as it's not supported)
        // Note: GPT-5 doesn't support temperature parameter in Responses API
        // if (isset($options['temperature'])) {
        //     $params['temperature'] = $options['temperature'];
        // }

        // Handle tools for Responses API
        if (isset($options['tools'])) {
            $params['tools'] = $this->formatToolsForResponsesAPI($options['tools']);
        }

        // Handle tool choice
        if (isset($options['tool_choice'])) {
            $params['tool_choice'] = $options['tool_choice'];
        }

        return $params;
    }

    /**
     * Format messages for Responses API.
     */
    protected function formatMessagesForResponsesAPI(array $messages, array $options): array
    {
        $input = [];

        foreach ($messages as $message) {
            if ($message instanceof AIMessage) {
                $input[] = [
                    'type' => 'message',
                    'role' => $message->role,
                    'content' => $message->content,
                ];
            } elseif (is_array($message) && isset($message['role'], $message['content'])) {
                // Handle array format messages
                $input[] = [
                    'type' => 'message',
                    'role' => $message['role'],
                    'content' => $message['content'],
                ];
            }
        }

        return $input;
    }

    /**
     * Format tools for Responses API.
     */
    protected function formatToolsForResponsesAPI(array $tools): array
    {
        return array_map(function ($tool) {
            if ($tool['type'] === 'function') {
                return [
                    'type' => 'function',
                    'function' => [
                        'name' => $tool['function']['name'],
                        'description' => $tool['function']['description'],
                        'parameters' => $tool['function']['parameters'] ?? [],
                    ],
                ];
            }
            return $tool;
        }, $tools);
    }

    /**
     * Parse Responses API response.
     */
    protected function parseResponsesAPIResponse($response, float $responseTime, array $options): AIResponse
    {
        // Parse the new Responses API format
        $content = '';
        $functionCalls = null;
        $toolCalls = null;
        $finishReason = 'stop';

        // Process output items
        if (isset($response->output) && is_array($response->output)) {
            foreach ($response->output as $item) {
                if (isset($item->type)) {
                    switch ($item->type) {
                        case 'reasoning':
                            // GPT-5 reasoning - could be logged or processed separately
                            // For now, we don't include reasoning in the main content
                            break;

                        case 'message':
                            // Handle the new Responses API content format
                            if (is_array($item->content)) {
                                // Content is an array of objects with text fields
                                foreach ($item->content as $contentItem) {
                                    if (is_object($contentItem) && isset($contentItem->text)) {
                                        $content .= $contentItem->text;
                                    } elseif (is_string($contentItem)) {
                                        $content .= $contentItem;
                                    }
                                }
                            } else {
                                $content .= $item->content ?? '';
                            }
                            break;

                        case 'tool_calls':
                            // Handle tool calls in Responses API
                            if (isset($item->tool_calls)) {
                                $toolCalls = array_map(fn($call) => (array) $call, $item->tool_calls);
                            }
                            break;
                    }
                }
            }
        }

        // Extract token usage (may not be available in Responses API)
        $tokenUsage = new TokenUsage(0, 0, 0);
        if (isset($response->usage)) {
            $tokenUsage = new TokenUsage(
                $response->usage->input_tokens ?? 0,
                $response->usage->output_tokens ?? 0,
                ($response->usage->input_tokens ?? 0) + ($response->usage->output_tokens ?? 0)
            );
        }

        // Determine finish reason
        if (isset($response->status)) {
            $finishReason = match ($response->status) {
                'completed' => 'stop',
                'incomplete' => 'length',
                'failed' => 'error',
                default => 'stop',
            };
        }

        return new AIResponse(
            $content,
            $tokenUsage,
            $response->model ?? ($options['model'] ?? $this->defaultModel),
            $this->providerName,
            AIMessage::ROLE_ASSISTANT,
            $finishReason,
            $functionCalls,
            $toolCalls,
            $responseTime,
            [
                'response_id' => $response->id ?? null,
                'api_type' => 'responses',
                'has_reasoning' => $this->hasReasoningInResponse($response),
            ]
        );
    }

    /**
     * Check if response contains reasoning.
     */
    protected function hasReasoningInResponse($response): bool
    {
        if (!isset($response->output) || !is_array($response->output)) {
            return false;
        }

        foreach ($response->output as $item) {
            if (isset($item->type) && $item->type === 'reasoning') {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract reasoning from response.
     */
    protected function extractReasoningFromResponse($response): ?string
    {
        if (!isset($response->output) || !is_array($response->output)) {
            return null;
        }

        $reasoning = '';
        foreach ($response->output as $item) {
            if (isset($item->type) && $item->type === 'reasoning') {
                if (is_array($item->content)) {
                    foreach ($item->content as $contentItem) {
                        if (is_object($contentItem) && isset($contentItem->text)) {
                            $reasoning .= $contentItem->text;
                        } elseif (is_string($contentItem)) {
                            $reasoning .= $contentItem;
                        }
                    }
                } else {
                    $reasoning .= $item->content ?? '';
                }
            }
        }

        return !empty($reasoning) ? $reasoning : null;
    }

    /**
     * Get supported Responses API models.
     */
    protected function getResponsesAPIModels(): array
    {
        return [
            'gpt-5',
            'gpt-5-2025-08-07',
            'gpt-4o-2024-12-17',
            'gpt-4o-mini-2024-12-17',
        ];
    }

    /**
     * Check if model supports Responses API.
     */
    protected function supportsResponsesAPI(string $model): bool
    {
        $supportedModels = $this->getResponsesAPIModels();
        return in_array($model, $supportedModels) || str_starts_with($model, 'gpt-5');
    }

    /**
     * Get Responses API capabilities.
     */
    protected function getResponsesAPICapabilities(): array
    {
        return [
            'reasoning' => true,
            'advanced_function_calling' => true,
            'structured_output' => true,
            'multi_step_reasoning' => true,
            'enhanced_context_understanding' => true,
        ];
    }

    /**
     * Convert Chat API parameters to Responses API parameters.
     */
    protected function convertChatToResponsesAPIParams(array $chatParams): array
    {
        $responsesParams = [
            'model' => $chatParams['model'],
            'input' => [],
        ];

        // Convert messages
        if (isset($chatParams['messages'])) {
            foreach ($chatParams['messages'] as $message) {
                $responsesParams['input'][] = [
                    'type' => 'message',
                    'role' => $message['role'],
                    'content' => $message['content'],
                ];
            }
        }

        // Convert max_tokens
        if (isset($chatParams['max_tokens'])) {
            $responsesParams['max_output_tokens'] = $chatParams['max_tokens'];
        }

        // Convert tools
        if (isset($chatParams['tools'])) {
            $responsesParams['tools'] = $this->formatToolsForResponsesAPI($chatParams['tools']);
        }

        // Convert tool_choice
        if (isset($chatParams['tool_choice'])) {
            $responsesParams['tool_choice'] = $chatParams['tool_choice'];
        }

        // Skip unsupported parameters like temperature for GPT-5

        return $responsesParams;
    }

    /**
     * Get Responses API usage recommendations.
     */
    public function getResponsesAPIRecommendations(string $model): array
    {
        if (!$this->supportsResponsesAPI($model)) {
            return [
                'recommended' => false,
                'reason' => 'Model does not support Responses API',
                'alternatives' => ['Use Chat API instead'],
            ];
        }

        $recommendations = [
            'recommended' => true,
            'benefits' => [
                'Enhanced reasoning capabilities',
                'Better function calling support',
                'Structured output format',
                'Multi-step reasoning',
            ],
            'considerations' => [],
        ];

        if (str_starts_with($model, 'gpt-5')) {
            $recommendations['considerations'][] = 'Temperature parameter not supported';
            $recommendations['considerations'][] = 'Requires higher token limits for reasoning + response';
            $recommendations['token_recommendation'] = 'Use at least 500 tokens for optimal results';
        }

        return $recommendations;
    }
}
