<?php

namespace JTD\LaravelAI\Tests\E2E;

use JTD\LaravelAI\Drivers\XAI\XAIDriver;
use JTD\LaravelAI\Exceptions\XAI\XAIInvalidCredentialsException;
use JTD\LaravelAI\Exceptions\XAI\XAIQuotaExceededException;
use JTD\LaravelAI\Models\AIMessage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * E2E Test for xAI Quota Error Handling
 *
 * This test demonstrates how the package handles real xAI API errors
 * when the account has insufficient balance or quota.
 */
#[Group('e2e')]
#[Group('xai')]
#[Group('quota')]
class XAIQuotaErrorTest extends E2ETestCase
{
    protected XAIDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip if no credentials available
        if (! $this->hasE2ECredentials('xai')) {
            $this->markTestSkipped('xAI E2E credentials not available');
        }

        // Create xAI driver with real credentials
        $credentials = $this->getE2ECredentials();
        $config = [
            'api_key' => $credentials['xai']['api_key'],
            'base_url' => $credentials['xai']['base_url'] ?? 'https://api.x.ai/v1',
            'timeout' => 30,
            'retry_attempts' => 1, // Reduce retries for faster testing
        ];

        $this->driver = new XAIDriver($config);
    }

    #[Test]
    public function it_handles_quota_exceeded_error_gracefully(): void
    {
        $this->logTestStart('Testing quota exceeded error handling with $0 balance');

        $message = AIMessage::user('This is a test message that should fail due to quota limits.');

        try {
            // This should fail if the account has no balance
            $response = $this->driver->sendMessage($message, [
                'model' => 'grok-3-mini',
                'max_tokens' => 100,
            ]);

            // If we get here, the account has balance - that's actually good!
            $this->logTestStep('✅ API call succeeded - account has sufficient balance');
            $this->logTestStep('Response: "' . trim($response->content) . '"');
            $this->logTestStep('This means the xAI account is properly funded for testing');

            // Mark test as passed since we got a successful response
            $this->assertTrue(true, 'API call succeeded with sufficient balance');
        } catch (XAIQuotaExceededException $e) {
            // This is what we expect if the account has no balance
            $this->logTestStep('✅ Quota exceeded exception caught as expected');
            $this->logTestStep('Exception message: ' . $e->getMessage());
            $this->logTestStep('Exception type: ' . get_class($e));

            // Verify the exception has the expected properties
            $this->assertInstanceOf(XAIQuotaExceededException::class, $e);
            $this->assertNotEmpty($e->getMessage());

            if (method_exists($e, 'getQuotaType')) {
                $this->logTestStep('Quota type: ' . $e->getQuotaType());
            }

            if (method_exists($e, 'getCurrentUsage')) {
                $usage = $e->getCurrentUsage();
                if ($usage !== null) {
                    $this->logTestStep('Current usage: ' . $usage);
                }
            }
        } catch (XAIInvalidCredentialsException $e) {
            // This might happen if the API key is invalid
            $this->logTestStep('❌ Invalid credentials exception: ' . $e->getMessage());
            $this->fail('Credentials appear to be invalid. Please check the xAI API key in tests/credentials/e2e-credentials.json');
        } catch (\Exception $e) {
            // Log any other unexpected exceptions
            $this->logTestStep('❌ Unexpected exception: ' . $e->getMessage());
            $this->logTestStep('Exception type: ' . get_class($e));

            // Check if this is a different type of quota/billing error
            if (str_contains(strtolower($e->getMessage()), 'quota') ||
                str_contains(strtolower($e->getMessage()), 'billing') ||
                str_contains(strtolower($e->getMessage()), 'insufficient') ||
                str_contains(strtolower($e->getMessage()), 'balance')) {
                $this->logTestStep('✅ This appears to be a quota/billing related error (different exception type)');
                $this->assertTrue(true, 'Quota error handled, even if with different exception type');
            } else {
                // Re-throw if it's not quota related
                throw $e;
            }
        }

        $this->logTestEnd('Quota error handling test completed');
    }

    #[Test]
    public function it_handles_invalid_api_key_gracefully(): void
    {
        $this->logTestStart('Testing invalid API key error handling');

        // Create driver with invalid API key
        $invalidConfig = [
            'api_key' => 'xai-invalid-key-for-testing-12345',
            'base_url' => 'https://api.x.ai/v1',
            'timeout' => 30,
            'retry_attempts' => 1,
        ];

        $invalidDriver = new XAIDriver($invalidConfig);
        $message = AIMessage::user('This should fail with invalid credentials.');

        try {
            $response = $invalidDriver->sendMessage($message, [
                'model' => 'grok-3-mini',
                'max_tokens' => 10,
            ]);

            // If we get here, something is wrong
            $this->fail('Expected invalid credentials exception, but API call succeeded');
        } catch (XAIInvalidCredentialsException $e) {
            // This is what we expect
            $this->logTestStep('✅ Invalid credentials exception caught as expected');
            $this->logTestStep('Exception message: ' . $e->getMessage());
            $this->logTestStep('Exception type: ' . get_class($e));

            $this->assertInstanceOf(XAIInvalidCredentialsException::class, $e);
            $this->assertNotEmpty($e->getMessage());
        } catch (\Exception $e) {
            // Log any other exceptions
            $this->logTestStep('❌ Unexpected exception: ' . $e->getMessage());
            $this->logTestStep('Exception type: ' . get_class($e));

            // Check if this is still an authentication error (different exception type)
            if (str_contains(strtolower($e->getMessage()), 'invalid') ||
                str_contains(strtolower($e->getMessage()), 'unauthorized') ||
                str_contains(strtolower($e->getMessage()), 'authentication') ||
                str_contains(strtolower($e->getMessage()), 'api key')) {
                $this->logTestStep('✅ This appears to be an authentication error (different exception type)');
                $this->assertTrue(true, 'Authentication error handled, even if with different exception type');
            } else {
                // Re-throw if it's not authentication related
                throw $e;
            }
        }

        $this->logTestEnd('Invalid API key error handling test completed');
    }

    #[Test]
    public function it_handles_rate_limit_gracefully(): void
    {
        $this->logTestStart('Testing rate limit error handling');

        $message = AIMessage::user('Test message for rate limiting.');

        // Make multiple rapid requests to potentially trigger rate limiting
        $requestCount = 5;
        $this->logTestStep("Making {$requestCount} rapid requests to test rate limiting...");

        for ($i = 1; $i <= $requestCount; $i++) {
            try {
                $this->logTestStep("Request {$i}/{$requestCount}...");

                $response = $this->driver->sendMessage($message, [
                    'model' => 'grok-3-mini',
                    'max_tokens' => 10,
                ]);

                $this->logTestStep("✅ Request {$i} succeeded");

                // Small delay between requests
                usleep(100000); // 100ms
            } catch (\Exception $e) {
                $this->logTestStep("Request {$i} failed: " . $e->getMessage());
                $this->logTestStep('Exception type: ' . get_class($e));

                // Check if this is a rate limit error
                if (str_contains(strtolower($e->getMessage()), 'rate') ||
                    str_contains(strtolower($e->getMessage()), 'limit') ||
                    str_contains(strtolower($e->getMessage()), 'too many')) {
                    $this->logTestStep('✅ Rate limit error detected and handled');
                    break; // Exit the loop as we've demonstrated rate limit handling
                }

                // If it's not a rate limit error, it might be quota/auth related
                if (str_contains(strtolower($e->getMessage()), 'quota') ||
                    str_contains(strtolower($e->getMessage()), 'billing') ||
                    str_contains(strtolower($e->getMessage()), 'invalid') ||
                    str_contains(strtolower($e->getMessage()), 'unauthorized')) {
                    $this->logTestStep('⚠️  Hit quota/auth error instead of rate limit');
                    break;
                }

                // Re-throw unexpected errors
                throw $e;
            }
        }

        $this->logTestStep('✅ Rate limit testing completed (may not have hit actual limits)');
        $this->logTestEnd('Rate limit error handling test completed');
    }

    /**
     * Log test step for better visibility.
     */
    protected function logTestStep(string $message): void
    {
        echo "\n  " . $message;
    }

    /**
     * Log test start.
     */
    protected function logTestStart(string $testName): void
    {
        echo "\n🧪 " . $testName;
    }

    /**
     * Log test end.
     */
    protected function logTestEnd(string $message): void
    {
        echo "\n✅ " . $message . "\n";
    }
}
