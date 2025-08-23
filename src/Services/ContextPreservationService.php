<?php

namespace JTD\LaravelAI\Services;

use Illuminate\Support\Collection;

/**
 * Context Preservation Service
 *
 * Manages context preservation markers and strategies for maintaining
 * important conversation elements across context windows and provider switches.
 */
class ContextPreservationService
{
    /**
     * Create comprehensive preservation markers for messages.
     */
    public function createPreservationMarkers(Collection $messages, array $options = []): array
    {
        $markers = [];
        $conversationFlow = $this->analyzeConversationFlow($messages);

        foreach ($messages as $message) {
            $messageMarkers = $this->analyzeMessage($message, $conversationFlow, $options);

            if (! empty($messageMarkers)) {
                $markers[$message->id] = [
                    'markers' => $messageMarkers,
                    'priority_score' => $this->calculatePreservationPriority($messageMarkers),
                    'preservation_reason' => $this->getPreservationReason($messageMarkers),
                ];
            }
        }

        return $markers;
    }

    /**
     * Analyze individual message for preservation markers.
     */
    protected function analyzeMessage($message, array $conversationFlow, array $options = []): array
    {
        $markers = [];
        $content = strtolower($message->content);

        // System message marker
        if ($message->role === 'system') {
            $markers[] = 'system_message';
        }

        // Important content markers
        if ($this->hasImportantKeywords($content)) {
            $markers[] = 'important_content';
        }

        // Question markers
        if ($this->isQuestion($content)) {
            $markers[] = 'question';
        }

        // Code content markers
        if ($this->hasCodeContent($content)) {
            $markers[] = 'code_content';
        }

        // Detailed content markers
        if ($this->isDetailedContent($message->content)) {
            $markers[] = 'detailed_content';
        }

        // Recency markers
        if ($this->isRecentMessage($message)) {
            $markers[] = 'recent';
        }

        // Conversation flow markers
        if (isset($conversationFlow[$message->id])) {
            $flowMarkers = $conversationFlow[$message->id];
            $markers = array_merge($markers, $flowMarkers);
        }

        // Context reference markers
        if ($this->referencesContext($content)) {
            $markers[] = 'context_reference';
        }

        // Error/problem markers
        if ($this->containsErrorOrProblem($content)) {
            $markers[] = 'error_or_problem';
        }

        // Solution markers
        if ($this->containsSolution($content)) {
            $markers[] = 'solution';
        }

        // Definition markers
        if ($this->containsDefinition($content)) {
            $markers[] = 'definition';
        }

        // User preference markers
        if ($this->containsUserPreference($content)) {
            $markers[] = 'user_preference';
        }

        return array_unique($markers);
    }

    /**
     * Check if content has important keywords.
     */
    protected function hasImportantKeywords(string $content): bool
    {
        $importantKeywords = [
            'remember', 'important', 'note', 'warning', 'error', 'critical',
            'urgent', 'attention', 'caution', 'alert', 'notice', 'key',
            'essential', 'crucial', 'vital', 'significant', 'major',
        ];

        foreach ($importantKeywords as $keyword) {
            if (str_contains($content, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if content is a question.
     */
    protected function isQuestion(string $content): bool
    {
        return str_contains($content, '?') ||
               preg_match('/\b(what|how|why|when|where|who|which|can|could|would|should|is|are|do|does|did)\b/', $content);
    }

    /**
     * Check if content contains code.
     */
    protected function hasCodeContent(string $content): bool
    {
        return preg_match('/```|`[^`]+`|\b(function|class|method|variable|array|object|if|else|for|while|return)\b/', $content);
    }

    /**
     * Check if content is detailed (long and informative).
     */
    protected function isDetailedContent(string $content): bool
    {
        return strlen($content) > 500;
    }

    /**
     * Check if message is recent.
     */
    protected function isRecentMessage($message): bool
    {
        return $message->created_at && now()->diffInHours($message->created_at) < 24;
    }

    /**
     * Check if content references previous context.
     */
    protected function referencesContext(string $content): bool
    {
        $contextPatterns = [
            'earlier', 'before', 'previous', 'mentioned', 'discussed',
            'talked about', 'you said', 'we covered', 'as we', 'like we',
        ];

        foreach ($contextPatterns as $pattern) {
            if (str_contains($content, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if content contains error or problem description.
     */
    protected function containsErrorOrProblem(string $content): bool
    {
        $problemKeywords = [
            'error', 'bug', 'issue', 'problem', 'fail', 'broken', 'wrong',
            'exception', 'crash', 'stuck', 'help', 'trouble', 'difficulty',
        ];

        foreach ($problemKeywords as $keyword) {
            if (str_contains($content, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if content contains solution or answer.
     */
    protected function containsSolution(string $content): bool
    {
        $solutionKeywords = [
            'solution', 'fix', 'resolve', 'answer', 'try this', 'here\'s how',
            'you can', 'to solve', 'the way to', 'approach', 'method',
        ];

        foreach ($solutionKeywords as $keyword) {
            if (str_contains($content, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if content contains definition or explanation.
     */
    protected function containsDefinition(string $content): bool
    {
        $definitionPatterns = [
            'is defined as', 'means', 'refers to', 'is a', 'are a',
            'definition', 'explanation', 'in other words', 'essentially',
        ];

        foreach ($definitionPatterns as $pattern) {
            if (str_contains($content, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if content contains user preference or personal information.
     */
    protected function containsUserPreference(string $content): bool
    {
        $preferencePatterns = [
            'my favorite', 'i prefer', 'i like', 'i love', 'i hate',
            'i usually', 'i always', 'i never', 'my name is', 'i am',
        ];

        foreach ($preferencePatterns as $pattern) {
            if (str_contains($content, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Analyze conversation flow to identify important sequences.
     */
    protected function analyzeConversationFlow(Collection $messages): array
    {
        $flowMarkers = [];
        $messageArray = $messages->sortBy('sequence_number')->values()->all();

        for ($i = 0; $i < count($messageArray); $i++) {
            $message = $messageArray[$i];
            $markers = [];

            // Question-answer pairs
            if ($message->role === 'user' && isset($messageArray[$i + 1]) &&
                $messageArray[$i + 1]->role === 'assistant') {
                $markers[] = 'question_in_pair';
            } elseif ($message->role === 'assistant' && isset($messageArray[$i - 1]) &&
                     $messageArray[$i - 1]->role === 'user') {
                $markers[] = 'answer_in_pair';
            }

            // Follow-up questions
            if ($message->role === 'user' && $i > 0) {
                $prevMessage = $messageArray[$i - 1];
                if ($this->isFollowUpQuestion($message, $prevMessage)) {
                    $markers[] = 'follow_up_question';
                }
            }

            // Conversation starters
            if ($i === 0 || ($i > 0 && $this->isConversationRestart($message, $messageArray[$i - 1]))) {
                $markers[] = 'conversation_starter';
            }

            // Topic changes
            if ($i > 0 && $this->isTopicChange($message, $messageArray[$i - 1])) {
                $markers[] = 'topic_change';
            }

            if (! empty($markers)) {
                $flowMarkers[$message->id] = $markers;
            }
        }

        return $flowMarkers;
    }

    /**
     * Check if message is a follow-up question.
     */
    protected function isFollowUpQuestion($currentMessage, $previousMessage): bool
    {
        $content = strtolower($currentMessage->content);
        $followUpIndicators = ['also', 'additionally', 'furthermore', 'and', 'but', 'however', 'what about'];

        foreach ($followUpIndicators as $indicator) {
            if (str_starts_with(trim($content), $indicator)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if message represents a conversation restart.
     */
    protected function isConversationRestart($currentMessage, $previousMessage): bool
    {
        // Large time gap
        if ($currentMessage->created_at && $previousMessage->created_at) {
            $hoursDiff = $currentMessage->created_at->diffInHours($previousMessage->created_at);
            if ($hoursDiff > 24) {
                return true;
            }
        }

        // Greeting patterns
        $content = strtolower($currentMessage->content);
        $greetings = ['hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening'];

        foreach ($greetings as $greeting) {
            if (str_starts_with($content, $greeting)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if message represents a topic change.
     */
    protected function isTopicChange($currentMessage, $previousMessage): bool
    {
        $content = strtolower($currentMessage->content);
        $topicChangeIndicators = [
            'by the way', 'speaking of', 'on another note', 'changing topics',
            'different question', 'new topic', 'something else',
        ];

        foreach ($topicChangeIndicators as $indicator) {
            if (str_contains($content, $indicator)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate preservation priority score based on markers.
     */
    protected function calculatePreservationPriority(array $markers): float
    {
        $score = 0.0;
        $markerWeights = [
            'system_message' => 1.0,
            'important_content' => 0.9,
            'error_or_problem' => 0.8,
            'solution' => 0.8,
            'user_preference' => 0.7,
            'definition' => 0.7,
            'question_in_pair' => 0.6,
            'answer_in_pair' => 0.6,
            'code_content' => 0.6,
            'context_reference' => 0.5,
            'detailed_content' => 0.4,
            'question' => 0.4,
            'follow_up_question' => 0.3,
            'recent' => 0.3,
            'conversation_starter' => 0.2,
            'topic_change' => 0.2,
        ];

        foreach ($markers as $marker) {
            $score += $markerWeights[$marker] ?? 0.1;
        }

        return min($score, 2.0); // Cap at 2.0
    }

    /**
     * Get human-readable preservation reason.
     */
    protected function getPreservationReason(array $markers): string
    {
        $reasons = [];

        if (in_array('system_message', $markers)) {
            $reasons[] = 'System instruction';
        }
        if (in_array('important_content', $markers)) {
            $reasons[] = 'Contains important keywords';
        }
        if (in_array('user_preference', $markers)) {
            $reasons[] = 'User preference or personal info';
        }
        if (in_array('error_or_problem', $markers)) {
            $reasons[] = 'Error or problem description';
        }
        if (in_array('solution', $markers)) {
            $reasons[] = 'Solution or answer';
        }
        if (in_array('question_in_pair', $markers)) {
            $reasons[] = 'Question in Q&A pair';
        }
        if (in_array('code_content', $markers)) {
            $reasons[] = 'Contains code';
        }
        if (in_array('context_reference', $markers)) {
            $reasons[] = 'References previous context';
        }

        return implode(', ', $reasons) ?: 'General preservation';
    }

    /**
     * Apply preservation markers to filter messages.
     */
    public function filterByPreservationMarkers(
        Collection $messages,
        array $preservationMarkers,
        array $requiredMarkers = [],
        float $minPriorityScore = 0.0
    ): Collection {
        return $messages->filter(function ($message) use ($preservationMarkers, $requiredMarkers, $minPriorityScore) {
            $messageId = $message->id;

            if (! isset($preservationMarkers[$messageId])) {
                return false;
            }

            $markerData = $preservationMarkers[$messageId];

            // Check priority score
            if ($markerData['priority_score'] < $minPriorityScore) {
                return false;
            }

            // Check required markers
            if (! empty($requiredMarkers)) {
                $messageMarkers = $markerData['markers'];
                foreach ($requiredMarkers as $required) {
                    if (! in_array($required, $messageMarkers)) {
                        return false;
                    }
                }
            }

            return true;
        });
    }
}
