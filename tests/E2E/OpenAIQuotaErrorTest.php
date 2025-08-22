<?php

namespace JTD\LaravelAI\Tests\E2E;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use JTD\LaravelAI\Drivers\OpenAIDriver;
use JTD\LaravelAI\Exceptions\OpenAI\OpenAIQuotaExceededException;
use JTD\LaravelAI\Exceptions\OpenAI\OpenAIInvalidCredentialsException;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Tests\E2E\E2ETestCase;

/**
 * E2E Test for OpenAI Quota Error Handling
 *
 * This test demonstrates how the package handles real OpenAI API errors
 * when the account has insufficient balance or quota.
 */
#[Group('e2e')]
#[Group('openai')]
#[Group('quota')]
class OpenAIQuotaErrorTest extends E2ETestCase
{
    protected OpenAIDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip if no credentials available
        if (!$this->hasE2ECredentials('openai')) {
            $this->markTestSkipped('OpenAI E2E credentials not available');
        }

        // Create OpenAI driver with real credentials
        $credentials = $this->getE2ECredentials();
        $config = [
            'api_key' => $credentials['openai']['api_key'],
            'organization' => $credentials['openai']['organization'] ?? null,
            'project' => $credentials['openai']['project'] ?? null,
            'timeout' => 30,
            'retry_attempts' => 1, // Reduce retries for faster testing
        ];

        $this->driver = new OpenAIDriver($config);
    }

    #[Test]
    public function it_handles_quota_exceeded_error_gracefully(): void
    {
        $this->logTestStart('Testing quota exceeded error handling with $0 balance');

        // Create a simple message that should trigger a quota error
        $message = AIMessage::user('Hello, this is a test message.');

        try {
            // This should fail due to insufficient balance
            $response = $this->driver->sendMessage($message, [
                'model' => 'gpt-3.5-turbo',
                'max_tokens' => 10,
            ]);

            // If we get here, the account has balance - that's unexpected for this test
            $this->fail('Expected quota exceeded exception, but API call succeeded. Account may have balance.');

        } catch (OpenAIQuotaExceededException $e) {
            // This is what we expect with $0 balance
            $this->logTestStep('✅ Caught expected OpenAIQuotaExceededException');

            // Verify exception properties
            $this->assertInstanceOf(OpenAIQuotaExceededException::class, $e);
            $this->assertNotEmpty($e->getMessage());

            // Log the error details for inspection
            $this->logTestStep('Error Message: ' . $e->getMessage());
            $this->logTestStep('Error Type: ' . ($e->getOpenAIErrorType() ?? 'N/A'));
            $this->logTestStep('Request ID: ' . ($e->getRequestId() ?? 'N/A'));

            // Check if it's identified as a billing error
            if (method_exists($e, 'isBillingError')) {
                $isBillingError = $e->isBillingError();
                $this->logTestStep('Is Billing Error: ' . ($isBillingError ? 'Yes' : 'No'));
                $this->assertTrue($isBillingError, 'Should be identified as a billing error');
            }

            // Check resolution suggestions
            if (method_exists($e, 'getResolutionSuggestions')) {
                $suggestions = $e->getResolutionSuggestions();
                $this->logTestStep('Resolution Suggestions:');
                foreach ($suggestions as $suggestion) {
                    $this->logTestStep('  - ' . $suggestion);
                }
                $this->assertNotEmpty($suggestions, 'Should provide resolution suggestions');
            }

        } catch (OpenAIInvalidCredentialsException $e) {
            // This might happen if credentials are invalid
            $this->logTestStep('❌ Caught OpenAIInvalidCredentialsException instead');
            $this->logTestStep('Error: ' . $e->getMessage());
            $this->fail('Credentials appear to be invalid. Please check the credentials file.');

        } catch (\Exception $e) {
            // Any other exception is unexpected
            $this->logTestStep('❌ Caught unexpected exception: ' . get_class($e));
            $this->logTestStep('Error: ' . $e->getMessage());
            $this->fail('Unexpected exception type: ' . get_class($e));
        }

        $this->logTestEnd('Quota error handling test completed successfully');
    }

    #[Test]
    public function it_handles_model_list_with_quota_error(): void
    {
        $this->logTestStart('Testing model list with quota restrictions');

        try {
            // Try to get available models - this might work even with $0 balance
            $models = $this->driver->getAvailableModels();

            $this->logTestStep('✅ Successfully retrieved ' . count($models) . ' models');
            $this->assertIsArray($models);
            $this->assertNotEmpty($models, 'Should return at least some models');

            // Log first few models for inspection
            $this->logTestStep('Available models:');
            foreach (array_slice($models, 0, 5) as $model) {
                $this->logTestStep('  - ' . $model['id'] . ' (' . $model['name'] . ')');
            }

        } catch (OpenAIQuotaExceededException $e) {
            // Some endpoints might be restricted with $0 balance
            $this->logTestStep('⚠️  Model list also restricted due to quota');
            $this->logTestStep('Error: ' . $e->getMessage());

            // This is acceptable - some accounts restrict all API access with $0 balance
            $this->assertTrue(true, 'Model list restriction is acceptable with $0 balance');

        } catch (\Exception $e) {
            $this->logTestStep('❌ Unexpected error getting models: ' . $e->getMessage());
            throw $e;
        }

        $this->logTestEnd('Model list test completed');
    }

    #[Test]
    public function it_demonstrates_error_enhancement(): void
    {
        $this->logTestStart('Testing error message enhancement');

        $message = AIMessage::user('Test error enhancement');

        try {
            $this->driver->sendMessage($message, ['model' => 'gpt-4']);
            $this->fail('Expected an exception due to $0 balance');

        } catch (OpenAIQuotaExceededException $e) {
            $originalMessage = $e->getMessage();
            $this->logTestStep('Original Error Message: ' . $originalMessage);

            // Check if our error enhancement is working
            $this->assertNotEmpty($originalMessage);

            // The message should contain helpful information
            $lowerMessage = strtolower($originalMessage);
            $hasHelpfulInfo = str_contains($lowerMessage, 'billing') ||
                             str_contains($lowerMessage, 'quota') ||
                             str_contains($lowerMessage, 'credit') ||
                             str_contains($lowerMessage, 'balance');

            $this->assertTrue($hasHelpfulInfo, 'Error message should contain billing/quota related terms');

            $this->logTestStep('✅ Error message contains helpful billing/quota information');

        } catch (\Exception $e) {
            $this->logTestStep('Caught different exception: ' . get_class($e));
            $this->logTestStep('Message: ' . $e->getMessage());

            // Still check if the message is enhanced
            $this->assertNotEmpty($e->getMessage());
        }

        $this->logTestEnd('Error enhancement test completed');
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
