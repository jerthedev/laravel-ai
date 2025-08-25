<?php

namespace JTD\LaravelAI\Tests\E2E;

use JTD\LaravelAI\Drivers\DriverTemplate\DriverTemplateDriver;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * E2E Test for DriverTemplate Successful API Calls
 *
 * This test demonstrates successful DriverTemplate API integration
 * when the account has sufficient balance.
 */
#[Group('e2e')]
#[Group('drivertemplate')]
#[Group('success')]
class DriverTemplateSuccessfulCallsTest extends E2ETestCase
{
    protected DriverTemplateDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip if no credentials available
        if (! $this->hasE2ECredentials('drivertemplate')) {
            $this->markTestSkipped('DriverTemplate E2E credentials not available');
        }

        // Create DriverTemplate driver with real credentials
        $credentials = $this->getE2ECredentials();
        $config = [
            'api_key' => $credentials['drivertemplate']['api_key'],
            'organization' => $credentials['drivertemplate']['organization'] ?? null,
            'project' => $credentials['drivertemplate']['project'] ?? null,
            'timeout' => 30,
            'retry_attempts' => 2,
        ];

        $this->driver = new DriverTemplateDriver($config);
    }

    #[Test]
    public function it_can_send_successful_message(): void
    {

        // TODO: Implement test
            } else {
                $this->logTestStep('⚠️  Token usage not reported (this may be normal for some API responses)');
            }

            $this->assertGreaterThan(0, $response->responseTimeMs, 'Response time should be greater than 0');
            $this->logTestStep('Response time: ' . round($response->responseTimeMs) . 'ms');
        } catch (\Exception $e) {
            $this->logTestStep('❌ API call failed: ' . $e->getMessage());
            $this->logTestStep('Exception type: ' . get_class($e));

            // Log more details for debugging
            if (method_exists($e, 'getTrace')) {
                $this->logTestStep('Stack trace: ' . $e->getTraceAsString());
            }

            $this->fail('Expected successful API call, but got exception: ' . $e->getMessage());
        }

        $this->logTestEnd('Successful API call test completed');
    }

    #[Test]
    public function it_can_calculate_cost_accurately(): void
    {

        // TODO: Implement test
            } else {
                $this->logTestStep('⚠️  No token usage data available from API response');
                $this->logTestStep('This may be normal for some API configurations');

                // Still verify that cost estimation works
                $this->assertGreaterThan(0, $estimatedCost['estimated_total_cost']);
                $this->assertGreaterThan(0, $estimatedCost['input_tokens']);
                $this->logTestStep('✅ Cost estimation logic is working (even without actual token data)');
            }
        } catch (\Exception $e) {
            $this->logTestStep('❌ Cost calculation test failed: ' . $e->getMessage());
            $this->logTestStep('Exception type: ' . get_class($e));
            throw $e;
        }

        $this->logTestEnd('Cost calculation test completed');
    }

    #[Test]
    public function it_can_validate_credentials_successfully(): void
    {

        // TODO: Implement test
            }
        } catch (\Exception $e) {
            $this->logTestStep('❌ Credential validation failed: ' . $e->getMessage());
            throw $e;
        }

        $this->logTestEnd('Credential validation test completed');
    }

    #[Test]
    public function it_can_get_health_status(): void
    {

        // TODO: Implement test
            }

            if (isset($status['details']['completions_working'])) {
                $this->logTestStep('Completions working: ' . ($status['details']['completions_working'] ? 'Yes' : 'No'));
            }

            if (! empty($status['issues'])) {
                $this->logTestStep('Issues found:');
                foreach ($status['issues'] as $issue) {
                    $this->logTestStep('  - ' . $issue);
                }
            }
        } catch (\Exception $e) {
            $this->logTestStep('❌ Health status check failed: ' . $e->getMessage());
            throw $e;
        }

        $this->logTestEnd('Health status test completed');
    }

    #[Test]
    public function it_can_get_available_models(): void
    {

        // TODO: Implement test
            }
        } catch (\Exception $e) {
            $this->logTestStep('❌ Model listing failed: ' . $e->getMessage());
            throw $e;
        }

        $this->logTestEnd('Model listing test completed');
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
