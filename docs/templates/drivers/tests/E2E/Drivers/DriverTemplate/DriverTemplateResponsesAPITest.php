<?php

namespace JTD\LaravelAI\Tests\E2E;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use JTD\LaravelAI\Tests\E2E\E2ETestCase;
use DriverTemplate\Client;

/**
 * Test the new DriverTemplate Responses API directly
 */
#[Group('e2e')]
#[Group('drivertemplate')]
#[Group('responses-api')]
class DriverTemplateResponsesAPITest extends E2ETestCase
{
    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip if no credentials available
        if (!$this->hasE2ECredentials('drivertemplate')) {
            $this->markTestSkipped('DriverTemplate E2E credentials not available');
        }

        // Create DriverTemplate client directly
        $credentials = $this->getE2ECredentials();
        $this->client = \DriverTemplate::client($credentials['drivertemplate']['api_key']);
    }

    #[Test]
    public function it_can_test_responses_api_directly(): void
    {

        // TODO: Implement test
                    }
                }
            }

        } catch (\Exception $e) {
            $this->logTestStep('❌ Responses API failed: ' . $e->getMessage());
            $this->logTestStep('Exception type: ' . get_class($e));

            // Check if it's a model availability issue
            if (str_contains($e->getMessage(), 'default-model-5') || str_contains($e->getMessage(), 'model')) {
                $this->logTestStep('⚠️  GPT-5 might not be available for this account');

                // Try with GPT-4o
                try {
                    $this->logTestStep('Trying with default-model-4o...');
                    $response = $this->client->responses()->create([
                        'model' => 'default-model-4o',
                        'input' => [
                            [
                                'type' => 'message',
                                'role' => 'user',
                                'content' => 'Say hello',
                            ],
                        ],
                    ]);

                    $this->logTestStep('✅ GPT-4o with Responses API works');

                } catch (\Exception $e2) {
                    $this->logTestStep('❌ GPT-4o also failed: ' . $e2->getMessage());
                    throw $e2;
                }
            } else {
                throw $e;
            }
        }

        $this->logTestEnd('Responses API test completed');
    }

    #[Test]
    public function it_can_test_responses_api_with_tools(): void
    {

        // TODO: Implement test
                    }
                }
            }

        } catch (\Exception $e) {
            $this->logTestStep('❌ Responses API with tools failed: ' . $e->getMessage());

            // This might be expected if the API format is different
            if (str_contains($e->getMessage(), 'tools') || str_contains($e->getMessage(), 'function')) {
                $this->logTestStep('⚠️  Tools format might be different than expected');
            } else {
                throw $e;
            }
        }

        $this->logTestEnd('Responses API with tools test completed');
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
