<?php

namespace JTD\LaravelAI\Tests\E2E;

use JTD\LaravelAI\Drivers\DriverTemplate\DriverTemplateDriver;
use JTD\LaravelAI\Exceptions\DriverTemplate\DriverTemplateInvalidCredentialsException;
use JTD\LaravelAI\Exceptions\DriverTemplate\DriverTemplateQuotaExceededException;
use JTD\LaravelAI\Models\AIMessage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * E2E Test for DriverTemplate Quota Error Handling
 *
 * This test demonstrates how the package handles real DriverTemplate API errors
 * when the account has insufficient balance or quota.
 */
#[Group('e2e')]
#[Group('drivertemplate')]
#[Group('quota')]
class DriverTemplateQuotaErrorTest extends E2ETestCase
{
    protected DriverTemplateDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip if no credentials available
        if (!$this->hasE2ECredentials('drivertemplate')) {
            $this->markTestSkipped('DriverTemplate E2E credentials not available');
        }

        // Create DriverTemplate driver with real credentials
        $credentials = $this->getE2ECredentials();
        $config = [
            'api_key' => $credentials['drivertemplate']['api_key'],
            'organization' => $credentials['drivertemplate']['organization'] ?? null,
            'project' => $credentials['drivertemplate']['project'] ?? null,
            'timeout' => 30,
            'retry_attempts' => 1, // Reduce retries for faster testing
        ];

        $this->driver = new DriverTemplateDriver($config);
    }

    #[Test]
    public function it_handles_quota_exceeded_error_gracefully(): void
    {

        // TODO: Implement test

        } catch (DriverTemplateQuotaExceededException $e) {
            // This is what we expect with $0 balance
            $this->logTestStep('✅ Caught expected DriverTemplateQuotaExceededException');

            // Verify exception properties
            $this->assertInstanceOf(DriverTemplateQuotaExceededException::class, $e);
            $this->assertNotEmpty($e->getMessage());

            // Log the error details for inspection
            $this->logTestStep('Error Message: ' . $e->getMessage());
            $this->logTestStep('Error Type: ' . ($e->getDriverTemplateErrorType() ?? 'N/A'));
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

        } catch (DriverTemplateInvalidCredentialsException $e) {
            // This might happen if credentials are invalid
            $this->logTestStep('❌ Caught DriverTemplateInvalidCredentialsException instead');
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

        // TODO: Implement test
            }

        } catch (DriverTemplateQuotaExceededException $e) {
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

        // TODO: Implement test

        } catch (DriverTemplateQuotaExceededException $e) {
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
