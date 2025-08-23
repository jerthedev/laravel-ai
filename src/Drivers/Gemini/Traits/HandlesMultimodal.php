<?php

namespace JTD\LaravelAI\Drivers\Gemini\Traits;

use JTD\LaravelAI\Models\AIMessage;

/**
 * Handles Multimodal Support for Google Gemini
 *
 * Manages text + image processing capabilities for Gemini,
 * including image encoding, format validation, and multimodal
 * message construction.
 */
trait HandlesMultimodal
{
    /**
     * Supported image MIME types.
     */
    protected array $supportedImageTypes = [
        'image/png',
        'image/jpeg',
        'image/jpg',
        'image/webp',
        'image/heic',
        'image/heif',
    ];

    /**
     * Maximum image size in bytes (20MB).
     */
    protected int $maxImageSize = 20 * 1024 * 1024;

    /**
     * Create a multimodal message with text and images.
     */
    public function createMultimodalMessage(string $text, array $images, string $role = 'user'): AIMessage
    {
        $attachments = [];

        foreach ($images as $image) {
            $attachments[] = $this->processImageAttachment($image);
        }

        return new AIMessage($role, $text, AIMessage::CONTENT_TYPE_MULTIMODAL, $attachments);
    }

    /**
     * Process an image attachment for Gemini.
     */
    protected function processImageAttachment($image): array
    {
        if (is_string($image)) {
            // Handle file path or URL
            return $this->processImageFromPath($image);
        }

        if (is_array($image)) {
            // Handle array with image data and metadata
            return $this->processImageFromArray($image);
        }

        throw new \InvalidArgumentException('Image must be a file path, URL, or array with image data');
    }

    /**
     * Process image from file path or URL.
     */
    protected function processImageFromPath(string $path): array
    {
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            // Handle URL
            return $this->processImageFromUrl($path);
        }

        // Handle local file path
        if (! file_exists($path)) {
            throw new \InvalidArgumentException("Image file not found: {$path}");
        }

        $imageData = file_get_contents($path);
        $mimeType = $this->detectMimeType($path, $imageData);

        return $this->createImageAttachment($imageData, $mimeType, basename($path));
    }

    /**
     * Process image from URL.
     */
    protected function processImageFromUrl(string $url): array
    {
        $imageData = file_get_contents($url);

        if ($imageData === false) {
            throw new \InvalidArgumentException("Failed to fetch image from URL: {$url}");
        }

        $mimeType = $this->detectMimeTypeFromData($imageData);
        $filename = basename(parse_url($url, PHP_URL_PATH)) ?: 'image';

        return $this->createImageAttachment($imageData, $mimeType, $filename);
    }

    /**
     * Process image from array data.
     */
    protected function processImageFromArray(array $image): array
    {
        if (! isset($image['data'])) {
            throw new \InvalidArgumentException('Image array must contain "data" key');
        }

        $imageData = $image['data'];
        $mimeType = $image['mime_type'] ?? $this->detectMimeTypeFromData($imageData);
        $filename = $image['filename'] ?? 'image';

        return $this->createImageAttachment($imageData, $mimeType, $filename);
    }

    /**
     * Create standardized image attachment.
     */
    protected function createImageAttachment(string $imageData, string $mimeType, string $filename): array
    {
        // Validate image size
        if (strlen($imageData) > $this->maxImageSize) {
            throw new \InvalidArgumentException(
                "Image size exceeds maximum allowed size of {$this->maxImageSize} bytes"
            );
        }

        // Validate MIME type
        if (! $this->isValidImageType($mimeType)) {
            throw new \InvalidArgumentException(
                "Unsupported image type: {$mimeType}. Supported types: " .
                implode(', ', $this->supportedImageTypes)
            );
        }

        return [
            'type' => 'image',
            'data' => $imageData,
            'mime_type' => $mimeType,
            'filename' => $filename,
            'size' => strlen($imageData),
        ];
    }

    /**
     * Detect MIME type from file path and data.
     */
    protected function detectMimeType(string $path, string $data): string
    {
        // Try to detect from file extension first
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mimeFromExtension = $this->getMimeTypeFromExtension($extension);

        if ($mimeFromExtension) {
            return $mimeFromExtension;
        }

        // Fall back to detecting from data
        return $this->detectMimeTypeFromData($data);
    }

    /**
     * Detect MIME type from image data.
     */
    protected function detectMimeTypeFromData(string $data): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($finfo, $data);
        finfo_close($finfo);

        return $mimeType ?: 'application/octet-stream';
    }

    /**
     * Get MIME type from file extension.
     */
    protected function getMimeTypeFromExtension(string $extension): ?string
    {
        $mapping = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'heic' => 'image/heic',
            'heif' => 'image/heif',
        ];

        return $mapping[$extension] ?? null;
    }

    /**
     * Check if image type is supported.
     */
    protected function isValidImageType(string $mimeType): bool
    {
        return in_array($mimeType, $this->supportedImageTypes, true);
    }

    /**
     * Get supported image types.
     */
    public function getSupportedImageTypes(): array
    {
        return $this->supportedImageTypes;
    }

    /**
     * Get maximum image size.
     */
    public function getMaxImageSize(): int
    {
        return $this->maxImageSize;
    }

    /**
     * Set maximum image size.
     */
    public function setMaxImageSize(int $size): self
    {
        $this->maxImageSize = $size;

        return $this;
    }

    /**
     * Validate multimodal message.
     */
    public function validateMultimodalMessage(AIMessage $message): array
    {
        $errors = [];

        if (empty($message->attachments)) {
            return ['valid' => true, 'errors' => []];
        }

        foreach ($message->attachments as $index => $attachment) {
            if ($attachment['type'] !== 'image') {
                $errors[] = "Attachment {$index}: Only image attachments are supported";

                continue;
            }

            if (! isset($attachment['mime_type'])) {
                $errors[] = "Attachment {$index}: Missing MIME type";

                continue;
            }

            if (! $this->isValidImageType($attachment['mime_type'])) {
                $errors[] = "Attachment {$index}: Unsupported image type {$attachment['mime_type']}";
            }

            if (! isset($attachment['data'])) {
                $errors[] = "Attachment {$index}: Missing image data";

                continue;
            }

            if (strlen($attachment['data']) > $this->maxImageSize) {
                $errors[] = "Attachment {$index}: Image size exceeds maximum allowed size";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Convert image to base64 for API transmission.
     */
    protected function encodeImageForApi(array $attachment): string
    {
        return base64_encode($attachment['data']);
    }

    /**
     * Create Gemini-formatted image part.
     */
    protected function createGeminiImagePart(array $attachment): array
    {
        return [
            'inlineData' => [
                'mimeType' => $attachment['mime_type'],
                'data' => $this->encodeImageForApi($attachment),
            ],
        ];
    }

    /**
     * Check if message contains images.
     */
    protected function hasImages(AIMessage $message): bool
    {
        if (empty($message->attachments)) {
            return false;
        }

        foreach ($message->attachments as $attachment) {
            if ($attachment['type'] === 'image') {
                return true;
            }
        }

        return false;
    }

    /**
     * Get appropriate model for multimodal content.
     */
    protected function getMultimodalModel(?string $requestedModel = null): string
    {
        // If a specific model is requested, use it
        if ($requestedModel) {
            return $requestedModel;
        }

        // Default to gemini-pro-vision for multimodal content
        return 'gemini-pro-vision';
    }

    /**
     * Estimate tokens for multimodal content.
     */
    protected function estimateMultimodalTokens(AIMessage $message): int
    {
        $tokens = $this->estimateStringTokens($message->content ?? '');

        // Add estimated tokens for images (rough estimate)
        if (! empty($message->attachments)) {
            foreach ($message->attachments as $attachment) {
                if ($attachment['type'] === 'image') {
                    $tokens += 258; // Gemini uses ~258 tokens per image
                }
            }
        }

        return $tokens;
    }
}
