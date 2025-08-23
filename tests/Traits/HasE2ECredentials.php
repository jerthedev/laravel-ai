<?php

namespace JTD\LaravelAI\Tests\Traits;

/**
 * Trait for managing E2E testing credentials
 *
 * Provides methods to load and validate real API credentials for E2E testing.
 * Tests are automatically skipped if credentials are not available.
 */
trait HasE2ECredentials
{
    /**
     * Cached E2E credentials to avoid multiple file reads.
     */
    protected static ?array $e2eCredentials = null;

    /**
     * Skip test if E2E credentials are not available for the specified provider.
     *
     * @param  string  $provider  Provider name (openai, gemini, xai, ollama)
     */
    protected function skipIfNoE2ECredentials(string $provider): void
    {
        $credentials = $this->getE2ECredentials();

        if (! $credentials || ! isset($credentials[$provider]) || ! ($credentials[$provider]['enabled'] ?? false)) {
            $this->markTestSkipped("E2E credentials not available for {$provider}. Set up credentials in tests/credentials/e2e-credentials.json");
        }
    }

    /**
     * Get all E2E credentials from the credentials file.
     *
     * @return array|null Credentials array or null if file doesn't exist
     */
    protected function getE2ECredentials(): ?array
    {
        if (self::$e2eCredentials === null) {
            $credentialsPath = __DIR__ . '/../credentials/e2e-credentials.json';

            if (! file_exists($credentialsPath)) {
                return null;
            }

            $content = file_get_contents($credentialsPath);
            if ($content === false) {
                return null;
            }

            self::$e2eCredentials = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Invalid JSON in e2e-credentials.json: ' . json_last_error_msg());
            }
        }

        return self::$e2eCredentials;
    }

    /**
     * Get credentials for a specific provider.
     *
     * @param  string  $provider  Provider name
     * @return array Provider credentials
     */
    protected function getProviderCredentials(string $provider): array
    {
        $credentials = $this->getE2ECredentials();

        return $credentials[$provider] ?? [];
    }

    /**
     * Check if E2E credentials are available for a provider.
     *
     * @param  string  $provider  Provider name
     * @return bool True if credentials are available and enabled
     */
    protected function hasE2ECredentials(string $provider): bool
    {
        $credentials = $this->getE2ECredentials();

        return $credentials && isset($credentials[$provider]) && ($credentials[$provider]['enabled'] ?? false);
    }

    /**
     * Get the credentials file path.
     *
     * @return string Path to credentials file
     */
    protected function getCredentialsFilePath(): string
    {
        return __DIR__ . '/../credentials/e2e-credentials.json';
    }

    /**
     * Validate that credentials file format is correct.
     *
     * @return array Validation result with 'valid' boolean and 'errors' array
     */
    protected function validateCredentialsFile(): array
    {
        $errors = [];
        $credentials = $this->getE2ECredentials();

        if (! $credentials) {
            $errors[] = 'Credentials file not found or invalid JSON';

            return ['valid' => false, 'errors' => $errors];
        }

        // Check required structure
        $requiredProviders = ['openai', 'gemini', 'xai', 'ollama'];
        foreach ($requiredProviders as $provider) {
            if (! isset($credentials[$provider])) {
                $errors[] = "Missing provider configuration: {$provider}";
            } elseif (! isset($credentials[$provider]['enabled'])) {
                $errors[] = "Missing 'enabled' field for provider: {$provider}";
            }
        }

        // Check metadata
        if (! isset($credentials['metadata'])) {
            $errors[] = 'Missing metadata section';
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }
}
