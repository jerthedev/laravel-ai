<?php

namespace JTD\LaravelAI\Drivers\Gemini\Traits;

/**
 * Handles Safety Settings for Google Gemini
 *
 * Manages Gemini's safety settings for content filtering,
 * including harassment, hate speech, sexually explicit content,
 * and dangerous content detection.
 */
trait HandlesSafetySettings
{
    /**
     * Default safety settings for Gemini.
     */
    protected array $defaultSafetySettings = [
        'HARM_CATEGORY_HARASSMENT' => 'BLOCK_MEDIUM_AND_ABOVE',
        'HARM_CATEGORY_HATE_SPEECH' => 'BLOCK_MEDIUM_AND_ABOVE',
        'HARM_CATEGORY_SEXUALLY_EXPLICIT' => 'BLOCK_MEDIUM_AND_ABOVE',
        'HARM_CATEGORY_DANGEROUS_CONTENT' => 'BLOCK_MEDIUM_AND_ABOVE',
    ];

    /**
     * Available safety categories.
     */
    protected array $safetyCategories = [
        'HARM_CATEGORY_HARASSMENT',
        'HARM_CATEGORY_HATE_SPEECH',
        'HARM_CATEGORY_SEXUALLY_EXPLICIT',
        'HARM_CATEGORY_DANGEROUS_CONTENT',
    ];

    /**
     * Available safety thresholds.
     */
    protected array $safetyThresholds = [
        'BLOCK_NONE',
        'BLOCK_ONLY_HIGH',
        'BLOCK_MEDIUM_AND_ABOVE',
        'BLOCK_LOW_AND_ABOVE',
    ];

    /**
     * Prepare safety settings for API request.
     */
    protected function prepareSafetySettings(array $options): array
    {
        // Start with configured default settings
        $configuredSettings = $this->config['safety_settings'] ?? [];
        $defaultSettings = array_merge($this->defaultSafetySettings, $configuredSettings);

        // Override with request-specific settings
        $requestSettings = $options['safety_settings'] ?? [];
        $mergedSettings = array_merge($defaultSettings, $requestSettings);

        // Convert to Gemini API format
        $safetySettings = [];
        foreach ($mergedSettings as $category => $threshold) {
            if ($this->isValidSafetyCategory($category) && $this->isValidSafetyThreshold($threshold)) {
                $safetySettings[] = [
                    'category' => $category,
                    'threshold' => $threshold,
                ];
            }
        }

        return $safetySettings;
    }

    /**
     * Validate safety category.
     */
    protected function isValidSafetyCategory(string $category): bool
    {
        return in_array($category, $this->safetyCategories, true);
    }

    /**
     * Validate safety threshold.
     */
    protected function isValidSafetyThreshold(string $threshold): bool
    {
        return in_array($threshold, $this->safetyThresholds, true);
    }

    /**
     * Get available safety categories.
     */
    public function getAvailableSafetyCategories(): array
    {
        return $this->safetyCategories;
    }

    /**
     * Get available safety thresholds.
     */
    public function getAvailableSafetyThresholds(): array
    {
        return $this->safetyThresholds;
    }

    /**
     * Get current safety settings.
     */
    public function getCurrentSafetySettings(): array
    {
        $configuredSettings = $this->config['safety_settings'] ?? [];

        return array_merge($this->defaultSafetySettings, $configuredSettings);
    }

    /**
     * Set safety settings for the driver instance.
     */
    public function setSafetySettings(array $settings): self
    {
        $validatedSettings = [];

        foreach ($settings as $category => $threshold) {
            if ($this->isValidSafetyCategory($category) && $this->isValidSafetyThreshold($threshold)) {
                $validatedSettings[$category] = $threshold;
            } else {
                throw new \InvalidArgumentException(
                    "Invalid safety setting: {$category} => {$threshold}"
                );
            }
        }

        $this->config['safety_settings'] = array_merge(
            $this->config['safety_settings'] ?? [],
            $validatedSettings
        );

        return $this;
    }

    /**
     * Set safety threshold for a specific category.
     */
    public function setSafetyThreshold(string $category, string $threshold): self
    {
        if (! $this->isValidSafetyCategory($category)) {
            throw new \InvalidArgumentException("Invalid safety category: {$category}");
        }

        if (! $this->isValidSafetyThreshold($threshold)) {
            throw new \InvalidArgumentException("Invalid safety threshold: {$threshold}");
        }

        if (! isset($this->config['safety_settings'])) {
            $this->config['safety_settings'] = [];
        }

        $this->config['safety_settings'][$category] = $threshold;

        return $this;
    }

    /**
     * Disable safety filtering for a category.
     */
    public function disableSafetyFiltering(string $category): self
    {
        return $this->setSafetyThreshold($category, 'BLOCK_NONE');
    }

    /**
     * Enable strict safety filtering for a category.
     */
    public function enableStrictSafetyFiltering(string $category): self
    {
        return $this->setSafetyThreshold($category, 'BLOCK_LOW_AND_ABOVE');
    }

    /**
     * Reset safety settings to defaults.
     */
    public function resetSafetySettings(): self
    {
        $this->config['safety_settings'] = $this->defaultSafetySettings;

        return $this;
    }

    /**
     * Parse safety ratings from response.
     */
    protected function parseSafetyRatings(array $safetyRatings): array
    {
        $parsed = [];

        foreach ($safetyRatings as $rating) {
            $category = $rating['category'] ?? 'UNKNOWN';
            $probability = $rating['probability'] ?? 'NEGLIGIBLE';
            $blocked = $rating['blocked'] ?? false;

            $parsed[$category] = [
                'probability' => $probability,
                'blocked' => $blocked,
                'severity' => $this->mapProbabilityToSeverity($probability),
            ];
        }

        return $parsed;
    }

    /**
     * Map probability to severity level.
     */
    protected function mapProbabilityToSeverity(string $probability): string
    {
        $mapping = [
            'NEGLIGIBLE' => 'low',
            'LOW' => 'low',
            'MEDIUM' => 'medium',
            'HIGH' => 'high',
        ];

        return $mapping[$probability] ?? 'unknown';
    }

    /**
     * Check if content was blocked by safety filters.
     */
    protected function isContentBlocked(array $safetyRatings): bool
    {
        foreach ($safetyRatings as $rating) {
            if ($rating['blocked'] ?? false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get safety settings documentation.
     */
    public function getSafetySettingsDocumentation(): array
    {
        return [
            'categories' => [
                'HARM_CATEGORY_HARASSMENT' => 'Content that is rude, disrespectful, or profane',
                'HARM_CATEGORY_HATE_SPEECH' => 'Content that promotes hatred against groups',
                'HARM_CATEGORY_SEXUALLY_EXPLICIT' => 'Content that contains sexually explicit material',
                'HARM_CATEGORY_DANGEROUS_CONTENT' => 'Content that promotes dangerous activities',
            ],
            'thresholds' => [
                'BLOCK_NONE' => 'No blocking - allow all content',
                'BLOCK_ONLY_HIGH' => 'Block only high-probability harmful content',
                'BLOCK_MEDIUM_AND_ABOVE' => 'Block medium and high-probability harmful content',
                'BLOCK_LOW_AND_ABOVE' => 'Block low, medium, and high-probability harmful content',
            ],
            'default_settings' => $this->defaultSafetySettings,
            'current_settings' => $this->getCurrentSafetySettings(),
        ];
    }

    /**
     * Validate safety settings configuration.
     */
    public function validateSafetySettings(array $settings): array
    {
        $errors = [];

        foreach ($settings as $category => $threshold) {
            if (! $this->isValidSafetyCategory($category)) {
                $errors[] = "Invalid safety category: {$category}";
            }

            if (! $this->isValidSafetyThreshold($threshold)) {
                $errors[] = "Invalid safety threshold for {$category}: {$threshold}";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'valid_categories' => $this->safetyCategories,
            'valid_thresholds' => $this->safetyThresholds,
        ];
    }
}
