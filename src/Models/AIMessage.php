<?php

namespace JTD\LaravelAI\Models;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Data Transfer Object for AI messages.
 *
 * Represents a message in an AI conversation, supporting various content types,
 * roles, and metadata. This class is used throughout the system for both user
 * inputs and AI responses, providing a consistent interface for message handling.
 *
 * Supports multiple message roles:
 * - System: Instructions and context for the AI
 * - User: Messages from the human user
 * - Assistant: Responses from the AI
 * - Function: Results from function calls
 * - Tool: Tool call results and metadata
 *
 * Supports multiple content types:
 * - Text: Plain text messages
 * - Image: Image content with URLs or base64 data
 * - Audio: Audio content and transcriptions
 * - File: File attachments and references
 * - Multimodal: Mixed content types in a single message
 *
 * @package JTD\LaravelAI\Models
 * @version 1.0.0
 * @since 1.0.0
 *
 * @example
 * ```php
 * // Simple text message
 * $message = AIMessage::user('Hello, world!');
 *
 * // System message with instructions
 * $system = AIMessage::system('You are a helpful assistant.');
 *
 * // Function call result
 * $result = AIMessage::tool('call_123', json_encode(['result' => 'success']));
 *
 * // Image message
 * $image = AIMessage::user('What do you see?', AIMessage::CONTENT_TYPE_IMAGE, [
 *     ['type' => 'image_url', 'image_url' => ['url' => 'https://example.com/image.jpg']]
 * ]);
 * ```
 */
class AIMessage
{
    /**
     * Message role constants.
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

    public const CONTENT_TYPE_MULTIMODAL = 'multimodal';

    /**
     * Message role identifier.
     *
     * Defines the role of the message sender in the conversation.
     * Must be one of the ROLE_* constants.
     *
     * @var string One of: 'system', 'user', 'assistant', 'function', 'tool'
     */
    public string $role;

    /**
     * Message content.
     *
     * Can be a simple string for text messages, or an array for complex
     * content types like multimodal messages with images, audio, etc.
     *
     * @var string|array Simple text string or structured content array
     */
    public $content;

    /**
     * Content type identifier.
     *
     * Specifies the type of content in this message.
     * Must be one of the CONTENT_TYPE_* constants.
     *
     * @var string One of: 'text', 'image', 'audio', 'file', 'multimodal'
     */
    public string $contentType;

    /**
     * Message attachments.
     *
     * Array of file attachments, images, or other media associated
     * with this message. Structure varies by content type.
     *
     * @var array|null Array of attachment objects or null if no attachments
     */
    public ?array $attachments;

    /**
     * Function calls (deprecated).
     *
     * Legacy function call data. Use toolCalls instead for new implementations.
     * Maintained for backward compatibility with older OpenAI API versions.
     *
     * @var array|null Array of function call objects or null
     * @deprecated Use toolCalls instead
     */
    public ?array $functionCalls;

    /**
     * Tool calls.
     *
     * Array of tool/function calls made by the AI assistant.
     * Each tool call includes an ID, function name, and arguments.
     *
     * @var array|null Array of tool call objects or null
     */
    public ?array $toolCalls;

    /**
     * Additional metadata.
     *
     * Arbitrary metadata associated with the message, such as
     * timestamps, user IDs, conversation context, etc.
     *
     * @var array|null Associative array of metadata or null
     */
    public ?array $metadata;

    /**
     * Message name.
     *
     * Required for function and tool messages to identify the
     * specific function or tool being called or responded to.
     *
     * @var string|null Function/tool name or null for other message types
     */
    public ?string $name;

    /**
     * @var \DateTime|null Message timestamp
     */
    public ?\DateTime $timestamp;

    /**
     * Create a new AIMessage instance.
     *
     * @param  string  $role  Message role
     * @param  string|array  $content  Message content
     * @param  string  $contentType  Content type
     * @param  array|null  $attachments  File attachments
     * @param  array|null  $functionCalls  Function calls
     * @param  array|null  $toolCalls  Tool calls
     * @param  array|null  $metadata  Additional metadata
     * @param  string|null  $name  Message name
     * @param  \DateTime|null  $timestamp  Message timestamp
     */
    public function __construct(
        string $role,
        $content,
        string $contentType = self::CONTENT_TYPE_TEXT,
        ?array $attachments = null,
        ?array $functionCalls = null,
        ?array $toolCalls = null,
        ?array $metadata = null,
        ?string $name = null,
        ?\DateTime $timestamp = null
    ) {
        $this->role = $role;
        $this->content = $content;
        $this->contentType = $contentType;
        $this->attachments = $attachments;
        $this->functionCalls = $functionCalls;
        $this->toolCalls = $toolCalls;
        $this->metadata = $metadata ?? [];
        $this->name = $name;
        $this->timestamp = $timestamp ?? new \DateTime;

        $this->validate();
    }

    /**
     * Create a user message.
     *
     * @param  string|array  $content  Message content
     * @param  string  $contentType  Content type
     * @param  array|null  $attachments  Attachments
     */
    public static function user($content, string $contentType = self::CONTENT_TYPE_TEXT, ?array $attachments = null): static
    {
        return new static(self::ROLE_USER, $content, $contentType, $attachments);
    }

    /**
     * Create a system message.
     *
     * @param  string  $content  System prompt content
     */
    public static function system(string $content): static
    {
        return new static(self::ROLE_SYSTEM, $content);
    }

    /**
     * Create an assistant message.
     *
     * @param  string|array  $content  Assistant response content
     * @param  array|null  $functionCalls  Function calls made
     * @param  array|null  $toolCalls  Tool calls made
     */
    public static function assistant($content, ?array $functionCalls = null, ?array $toolCalls = null): static
    {
        return new static(self::ROLE_ASSISTANT, $content, self::CONTENT_TYPE_TEXT, null, $functionCalls, $toolCalls);
    }

    /**
     * Create a function message.
     *
     * @param  string  $name  Function name
     * @param  string  $content  Function result
     */
    public static function function(string $name, string $content): static
    {
        return new static(self::ROLE_FUNCTION, $content, self::CONTENT_TYPE_TEXT, null, null, null, null, $name);
    }

    /**
     * Create a tool message.
     *
     * @param  string  $name  Tool name
     * @param  string  $content  Tool result
     */
    public static function tool(string $name, string $content): static
    {
        return new static(self::ROLE_TOOL, $content, self::CONTENT_TYPE_TEXT, null, null, null, null, $name);
    }

    /**
     * Validate the message data.
     *
     * @throws ValidationException
     */
    protected function validate(): void
    {
        $validator = Validator::make([
            'role' => $this->role,
            'content' => $this->content,
            'content_type' => $this->contentType,
            'attachments' => $this->attachments,
            'function_calls' => $this->functionCalls,
            'tool_calls' => $this->toolCalls,
            'name' => $this->name,
        ], [
            'role' => 'required|in:system,user,assistant,function,tool',
            'content' => 'nullable|string',
            'content_type' => 'required|in:text,image,audio,file,multimodal',
            'attachments' => 'nullable|array',
            'function_calls' => 'nullable|array',
            'tool_calls' => 'nullable|array',
            'name' => 'nullable|string|required_if:role,function,tool',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Convert the message to an array.
     */
    public function toArray(): array
    {
        $data = [
            'role' => $this->role,
            'content' => $this->content,
            'content_type' => $this->contentType,
            'timestamp' => $this->timestamp?->format('c'),
        ];

        if ($this->attachments) {
            $data['attachments'] = $this->attachments;
        }

        if ($this->functionCalls) {
            $data['function_calls'] = $this->functionCalls;
        }

        if ($this->toolCalls) {
            $data['tool_calls'] = $this->toolCalls;
        }

        if ($this->metadata) {
            $data['metadata'] = $this->metadata;
        }

        if ($this->name) {
            $data['name'] = $this->name;
        }

        return $data;
    }

    /**
     * Create an AIMessage from an array.
     *
     * @param  array  $data  Message data
     */
    public static function fromArray(array $data): static
    {
        return new static(
            $data['role'],
            $data['content'],
            $data['content_type'] ?? self::CONTENT_TYPE_TEXT,
            $data['attachments'] ?? null,
            $data['function_calls'] ?? null,
            $data['tool_calls'] ?? null,
            $data['metadata'] ?? null,
            $data['name'] ?? null,
            isset($data['timestamp']) ? new \DateTime($data['timestamp']) : null
        );
    }

    /**
     * Get the message content as a string.
     */
    public function getContentAsString(): string
    {
        if (is_string($this->content)) {
            return $this->content;
        }

        if (is_array($this->content)) {
            // Handle multimodal content
            $textParts = [];
            foreach ($this->content as $part) {
                if (is_string($part)) {
                    $textParts[] = $part;
                } elseif (is_array($part) && isset($part['text'])) {
                    $textParts[] = $part['text'];
                }
            }

            return implode(' ', $textParts);
        }

        return (string) $this->content;
    }

    /**
     * Convert the message to a string.
     */
    public function __toString(): string
    {
        return $this->getContentAsString();
    }

    /**
     * Check if the message has attachments.
     */
    public function hasAttachments(): bool
    {
        return ! empty($this->attachments);
    }

    /**
     * Check if the message has function calls.
     */
    public function hasFunctionCalls(): bool
    {
        return ! empty($this->functionCalls);
    }

    /**
     * Check if the message has tool calls.
     */
    public function hasToolCalls(): bool
    {
        return ! empty($this->toolCalls);
    }

    /**
     * Get the estimated token count for this message.
     *
     * This is a rough estimation based on content length.
     * For accurate counts, use the provider's token estimation.
     */
    public function getEstimatedTokenCount(): int
    {
        $content = $this->getContentAsString();

        // Rough estimation: ~4 characters per token for English text
        $baseTokens = (int) ceil(strlen($content) / 4);

        // Add tokens for role and structure
        $baseTokens += 10;

        // Add tokens for attachments
        if ($this->hasAttachments()) {
            $baseTokens += count($this->attachments) * 50; // Rough estimate
        }

        // Add tokens for function/tool calls
        if ($this->hasFunctionCalls()) {
            $baseTokens += count($this->functionCalls) * 20;
        }

        if ($this->hasToolCalls()) {
            $baseTokens += count($this->toolCalls) * 20;
        }

        return $baseTokens;
    }
}
