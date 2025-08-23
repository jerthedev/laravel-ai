<?php

namespace JTD\LaravelAI\Tests\E2E;

use JTD\LaravelAI\Tests\TestCase;
use JTD\LaravelAI\Tests\Traits\HasE2ECredentials;
use PHPUnit\Framework\Attributes\Group;

/**
 * Base test case for End-to-End (E2E) testing with real AI provider APIs.
 *
 * This class provides common functionality for E2E tests including:
 * - Credential management
 * - Rate limiting to respect API limits
 * - Common setup and teardown
 * - Logging and monitoring for real API calls
 */
#[Group('e2e')]
abstract class E2ETestCase extends TestCase
{
    use HasE2ECredentials;

    /**
     * Track last API call time for rate limiting.
     */
    protected static ?float $lastApiCall = null;

    /**
     * Minimum delay between API calls in seconds.
     */
    protected float $minDelayBetweenCalls = 1.0;

    /**
     * Maximum number of retries for failed API calls.
     */
    protected int $maxRetries = 3;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Add rate limiting for real API calls
        $this->setupRateLimiting();

        // Log E2E test start
        $this->logE2ETestStart();
    }

    /**
     * Clean up after test.
     */
    protected function tearDown(): void
    {
        // Log E2E test completion
        $this->logE2ETestEnd();

        parent::tearDown();
    }

    /**
     * Set up rate limiting to respect API provider limits.
     */
    protected function setupRateLimiting(): void
    {
        if (self::$lastApiCall !== null) {
            $timeSinceLastCall = microtime(true) - self::$lastApiCall;
            if ($timeSinceLastCall < $this->minDelayBetweenCalls) {
                $sleepTime = $this->minDelayBetweenCalls - $timeSinceLastCall;
                usleep((int) ($sleepTime * 1000000));
            }
        }
        self::$lastApiCall = microtime(true);
    }

    /**
     * Configure provider with E2E credentials.
     *
     * @param  string  $provider  Provider name
     */
    protected function configureProviderWithE2ECredentials(string $provider): void
    {
        $this->skipIfNoE2ECredentials($provider);

        $credentials = $this->getProviderCredentials($provider);

        switch ($provider) {
            case 'openai':
                config([
                    'ai.providers.openai.api_key' => $credentials['api_key'],
                    'ai.providers.openai.organization' => $credentials['organization'] ?? null,
                    'ai.providers.openai.project' => $credentials['project'] ?? null,
                ]);
                break;

            case 'gemini':
                config([
                    'ai.providers.gemini.api_key' => $credentials['api_key'],
                ]);
                break;

            case 'xai':
                config([
                    'ai.providers.xai.api_key' => $credentials['api_key'],
                ]);
                break;

            case 'ollama':
                config([
                    'ai.providers.ollama.base_url' => $credentials['base_url'] ?? 'http://localhost:11434',
                ]);
                break;

            default:
                throw new \InvalidArgumentException("Unsupported provider for E2E testing: {$provider}");
        }
    }

    /**
     * Log the start of an E2E test.
     */
    protected function logE2ETestStart(): void
    {
        if (app()->environment('testing')) {
            logger()->info('E2E Test Started', [
                'test_class' => static::class,
                'test_method' => $this->name(),
                'timestamp' => now()->toISOString(),
            ]);
        }
    }

    /**
     * Log the end of an E2E test.
     */
    protected function logE2ETestEnd(): void
    {
        if (app()->environment('testing')) {
            logger()->info('E2E Test Completed', [
                'test_class' => static::class,
                'test_method' => $this->name(),
                'timestamp' => now()->toISOString(),
            ]);
        }
    }

    /**
     * Assert that a response contains expected AI-generated content patterns.
     *
     * @param  string  $content  Response content to check
     * @param  array  $patterns  Expected patterns or keywords
     */
    protected function assertContainsAIContent(string $content, array $patterns = []): void
    {
        $this->assertNotEmpty($content, 'AI response should not be empty');
        $this->assertGreaterThan(10, strlen($content), 'AI response should be substantial');

        foreach ($patterns as $pattern) {
            $this->assertStringContainsStringIgnoringCase(
                $pattern,
                $content,
                "AI response should contain expected pattern: {$pattern}"
            );
        }
    }

    /**
     * Assert that token usage is reasonable for the given input.
     *
     * @param  int  $totalTokens  Total tokens used
     * @param  int  $minExpected  Minimum expected tokens
     * @param  int  $maxExpected  Maximum expected tokens
     */
    protected function assertReasonableTokenUsage(int $totalTokens, int $minExpected = 1, int $maxExpected = 10000): void
    {
        $this->assertGreaterThan($minExpected, $totalTokens, 'Token usage should be greater than minimum');
        $this->assertLessThan($maxExpected, $totalTokens, 'Token usage should be less than maximum');
    }
}
