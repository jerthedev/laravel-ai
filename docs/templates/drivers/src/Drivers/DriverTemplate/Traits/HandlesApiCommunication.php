<?php

namespace JTD\LaravelAI\Drivers\DriverTemplate\Traits;

/**
 * Handles API Communication with DriverTemplate
 *
 * Core API communication methods for sending messages,
 * parsing responses, and handling API routing.
 */
trait HandlesApiCommunication
{
    /**
     * Actually send the message to the provider.
     */
    protected function doSendMessage(array $messages, array $options): JTD\LaravelAI\Models\AIResponse
    {
        // TODO: Implement doSendMessage
    }

    /**
     * Send message using traditional Chat API.
     */
    protected function sendMessageWithChatAPI(array $messages, array $options, float $startTime): JTD\LaravelAI\Models\AIResponse
    {
        // TODO: Implement sendMessageWithChatAPI
    }

    /**
     * Send message using the new Responses API.
     */
    protected function sendMessageWithResponsesAPI(array $messages, array $options, float $startTime): JTD\LaravelAI\Models\AIResponse
    {
        // TODO: Implement sendMessageWithResponsesAPI
    }

    /**
     * Parse DriverTemplate Chat API response.
     */
    protected function parseResponse($response, float $responseTime, array $options): JTD\LaravelAI\Models\AIResponse
    {
        // TODO: Implement parseResponse
    }

    /**
     * Build conversation messages from input.
     */
    protected function buildConversationMessages($message, array $options): array
    {
        // TODO: Implement buildConversationMessages
    }

    /**
     * Format messages for API consumption.
     */
    protected function formatMessages($message): array
    {
        // TODO: Implement formatMessages
    }

    /**
     * Format a single message for API consumption.
     */
    protected function formatSingleMessage(JTD\LaravelAI\Models\AIMessage $message): array
    {
        // TODO: Implement formatSingleMessage
    }

    /**
     * Prepare API parameters for Chat API.
     */
    protected function prepareApiParameters(array $messages, array $options): array
    {
        // TODO: Implement prepareApiParameters
    }

    /**
     * Trim conversation context if needed to fit within model limits.
     */
    protected function trimContextIfNeeded(array $messages, array $options): array
    {
        // TODO: Implement trimContextIfNeeded
    }

    /**
     * Update conversation context if needed.
     */
    protected function updateConversationContext($originalMessage, JTD\LaravelAI\Models\AIResponse $response, array $options): void
    {
        // TODO: Implement updateConversationContext
    }
}
