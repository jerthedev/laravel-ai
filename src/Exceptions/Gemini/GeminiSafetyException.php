<?php

namespace JTD\LaravelAI\Exceptions\Gemini;

/**
 * Exception for Gemini safety filter violations.
 *
 * This exception is unique to Gemini and handles cases where
 * content is blocked by safety filters.
 */
class GeminiSafetyException extends GeminiException
{
    /**
     * Reason content was blocked.
     */
    public ?string $blockedReason = null;

    /**
     * Safety category that triggered the block.
     */
    public ?string $safetyCategory = null;

    /**
     * Create a new Gemini safety exception.
     *
     * @param  string  $message  Exception message
     * @param  array  $safetyRatings  Safety ratings from response
     * @param  string|null  $blockedReason  Reason content was blocked
     * @param  string|null  $safetyCategory  Safety category that triggered block
     * @param  string|null  $requestId  Request ID
     * @param  array  $details  Additional error details
     * @param  int  $code  Exception code
     * @param  \Exception|null  $previous  Previous exception
     */
    public function __construct(
        string $message = 'Content blocked by Gemini safety filters',
        array $safetyRatings = [],
        ?string $blockedReason = null,
        ?string $safetyCategory = null,
        ?string $requestId = null,
        array $details = [],
        int $code = 400,
        ?\Exception $previous = null
    ) {
        $details['safety_ratings'] = $safetyRatings;

        parent::__construct(
            $message,
            'safety_violation',
            null,
            $requestId,
            $details,
            false, // Not retryable without content changes
            $code,
            $previous
        );

        $this->blockedReason = $blockedReason;
        $this->safetyCategory = $safetyCategory;
    }

    /**
     * Get the reason content was blocked.
     */
    public function getBlockedReason(): ?string
    {
        return $this->blockedReason;
    }

    /**
     * Get the safety category that triggered the block.
     */
    public function getSafetyCategory(): ?string
    {
        return $this->safetyCategory;
    }

    /**
     * Get all safety categories that had concerns.
     */
    public function getConcernedCategories(): array
    {
        $concerned = [];

        foreach ($this->safetyRatings as $rating) {
            $probability = $rating['probability'] ?? 'NEGLIGIBLE';
            if (in_array($probability, ['MEDIUM', 'HIGH'])) {
                $concerned[] = [
                    'category' => $rating['category'] ?? 'UNKNOWN',
                    'probability' => $probability,
                ];
            }
        }

        return $concerned;
    }

    /**
     * Get safety categories with high probability.
     */
    public function getHighRiskCategories(): array
    {
        $highRisk = [];

        foreach ($this->safetyRatings as $rating) {
            if (($rating['probability'] ?? 'NEGLIGIBLE') === 'HIGH') {
                $highRisk[] = $rating['category'] ?? 'UNKNOWN';
            }
        }

        return $highRisk;
    }

    /**
     * Get safety categories with medium probability.
     */
    public function getMediumRiskCategories(): array
    {
        $mediumRisk = [];

        foreach ($this->safetyRatings as $rating) {
            if (($rating['probability'] ?? 'NEGLIGIBLE') === 'MEDIUM') {
                $mediumRisk[] = $rating['category'] ?? 'UNKNOWN';
            }
        }

        return $mediumRisk;
    }

    /**
     * Get human-readable category names.
     */
    public function getHumanReadableCategories(): array
    {
        $categoryNames = [
            'HARM_CATEGORY_HARASSMENT' => 'Harassment',
            'HARM_CATEGORY_HATE_SPEECH' => 'Hate Speech',
            'HARM_CATEGORY_SEXUALLY_EXPLICIT' => 'Sexually Explicit Content',
            'HARM_CATEGORY_DANGEROUS_CONTENT' => 'Dangerous Content',
        ];

        $concerned = $this->getConcernedCategories();
        $readable = [];

        foreach ($concerned as $category) {
            $categoryKey = $category['category'];
            $readable[] = [
                'name' => $categoryNames[$categoryKey] ?? $categoryKey,
                'probability' => $category['probability'],
                'severity' => $this->getProbabilitySeverity($category['probability']),
            ];
        }

        return $readable;
    }

    /**
     * Get severity level for probability.
     */
    protected function getProbabilitySeverity(string $probability): string
    {
        return match ($probability) {
            'HIGH' => 'High Risk',
            'MEDIUM' => 'Medium Risk',
            'LOW' => 'Low Risk',
            'NEGLIGIBLE' => 'Negligible Risk',
            default => 'Unknown Risk',
        };
    }

    /**
     * Get user-friendly error message.
     */
    public function getUserFriendlyMessage(): string
    {
        $concerned = $this->getConcernedCategories();

        if (empty($concerned)) {
            return 'Your request was blocked by safety filters. Please modify your content and try again.';
        }

        $categories = array_map(function ($cat) {
            $categoryNames = [
                'HARM_CATEGORY_HARASSMENT' => 'harassment',
                'HARM_CATEGORY_HATE_SPEECH' => 'hate speech',
                'HARM_CATEGORY_SEXUALLY_EXPLICIT' => 'sexually explicit content',
                'HARM_CATEGORY_DANGEROUS_CONTENT' => 'dangerous content',
            ];

            return $categoryNames[$cat['category']] ?? strtolower($cat['category']);
        }, $concerned);

        $categoryList = implode(', ', $categories);

        return "Your request was blocked due to potential {$categoryList}. Please modify your content and try again.";
    }

    /**
     * Get suggested actions for the error.
     */
    public function getSuggestedActions(): array
    {
        $actions = [
            'Review your input for potentially harmful content',
            'Modify your request to comply with safety guidelines',
            'Try rephrasing your request in a different way',
        ];

        $highRisk = $this->getHighRiskCategories();
        $mediumRisk = $this->getMediumRiskCategories();

        if (! empty($highRisk)) {
            $actions[] = 'Remove content related to: ' . implode(', ', $this->humanizeCategories($highRisk));
        }

        if (! empty($mediumRisk)) {
            $actions[] = 'Consider revising content related to: ' . implode(', ', $this->humanizeCategories($mediumRisk));
        }

        $actions[] = 'Check Gemini\'s usage policies and safety guidelines';
        $actions[] = 'If you believe this is an error, try a different approach to your request';

        return $actions;
    }

    /**
     * Convert category codes to human-readable names.
     */
    protected function humanizeCategories(array $categories): array
    {
        $categoryNames = [
            'HARM_CATEGORY_HARASSMENT' => 'harassment',
            'HARM_CATEGORY_HATE_SPEECH' => 'hate speech',
            'HARM_CATEGORY_SEXUALLY_EXPLICIT' => 'sexually explicit content',
            'HARM_CATEGORY_DANGEROUS_CONTENT' => 'dangerous content',
        ];

        return array_map(function ($category) use ($categoryNames) {
            return $categoryNames[$category] ?? strtolower($category);
        }, $categories);
    }

    /**
     * Get safety filter recommendations.
     */
    public function getSafetyRecommendations(): array
    {
        return [
            'content_guidelines' => [
                'Avoid harmful, offensive, or inappropriate content',
                'Use respectful and inclusive language',
                'Avoid content that could be used to harm others',
                'Keep requests professional and constructive',
            ],
            'best_practices' => [
                'Review content before submitting',
                'Use clear and specific language',
                'Avoid ambiguous phrasing that could be misinterpreted',
                'Consider the context and potential interpretations',
            ],
            'alternatives' => [
                'Rephrase your request using different words',
                'Break complex requests into smaller parts',
                'Focus on the specific information you need',
                'Use examples to clarify your intent',
            ],
        ];
    }

    /**
     * Convert exception to array for logging.
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'blocked_reason' => $this->blockedReason,
            'safety_category' => $this->safetyCategory,
            'concerned_categories' => $this->getConcernedCategories(),
            'high_risk_categories' => $this->getHighRiskCategories(),
            'medium_risk_categories' => $this->getMediumRiskCategories(),
            'human_readable_categories' => $this->getHumanReadableCategories(),
        ]);
    }
}
