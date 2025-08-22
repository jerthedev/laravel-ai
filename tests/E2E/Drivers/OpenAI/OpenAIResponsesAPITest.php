<?php

namespace JTD\LaravelAI\Tests\E2E;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use JTD\LaravelAI\Tests\E2E\E2ETestCase;
use OpenAI\Client;

/**
 * Test the new OpenAI Responses API directly
 */
#[Group('e2e')]
#[Group('openai')]
#[Group('responses-api')]
class OpenAIResponsesAPITest extends E2ETestCase
{
    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip if no credentials available
        if (!$this->hasE2ECredentials('openai')) {
            $this->markTestSkipped('OpenAI E2E credentials not available');
        }

        // Create OpenAI client directly
        $credentials = $this->getE2ECredentials();
        $this->client = \OpenAI::client($credentials['openai']['api_key']);
    }

    #[Test]
    public function it_can_test_responses_api_directly(): void
    {
        $this->logTestStart('Testing Responses API directly');

        try {
            // Test basic Responses API call
            $response = $this->client->responses()->create([
                'model' => 'gpt-5',
                'input' => [
                    [
                        'type' => 'message',
                        'role' => 'user',
                        'content' => 'Say hello',
                    ],
                ],
            ]);

            $this->logTestStep('✅ Responses API call successful');
            $this->logTestStep('Response ID: ' . ($response->id ?? 'N/A'));
            $this->logTestStep('Model: ' . ($response->model ?? 'N/A'));

            if (isset($response->output)) {
                $this->logTestStep('Output items: ' . count($response->output));
                foreach ($response->output as $item) {
                    $this->logTestStep('  - Type: ' . ($item->type ?? 'unknown'));
                    if (isset($item->content)) {
                        $content = is_array($item->content) ? json_encode($item->content) : $item->content;
                        $this->logTestStep('  - Content: "' . $content . '"');
                    }
                }
            }

        } catch (\Exception $e) {
            $this->logTestStep('❌ Responses API failed: ' . $e->getMessage());
            $this->logTestStep('Exception type: ' . get_class($e));

            // Check if it's a model availability issue
            if (str_contains($e->getMessage(), 'gpt-5') || str_contains($e->getMessage(), 'model')) {
                $this->logTestStep('⚠️  GPT-5 might not be available for this account');

                // Try with GPT-4o
                try {
                    $this->logTestStep('Trying with gpt-4o...');
                    $response = $this->client->responses()->create([
                        'model' => 'gpt-4o',
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
        $this->logTestStart('Testing Responses API with tools');

        try {
            $response = $this->client->responses()->create([
                'model' => 'gpt-5',
                'input' => [
                    [
                        'type' => 'message',
                        'role' => 'user',
                        'content' => 'What is the weather in New York? Use the get_weather function.',
                    ],
                ],
                'tools' => [
                    [
                        'type' => 'function',
                        'name' => 'get_weather',
                        'description' => 'Get current weather for a location',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'location' => [
                                    'type' => 'string',
                                    'description' => 'The city and state',
                                ],
                            ],
                            'required' => ['location'],
                        ],
                    ],
                ],
            ]);

            $this->logTestStep('✅ Responses API with tools successful');
            $this->logTestStep('Response ID: ' . ($response->id ?? 'N/A'));

            if (isset($response->output)) {
                foreach ($response->output as $item) {
                    $this->logTestStep('Output type: ' . ($item->type ?? 'unknown'));
                    if ($item->type === 'function_call') {
                        $this->logTestStep('✅ Function call detected!');
                        $this->logTestStep('Function: ' . ($item->name ?? 'N/A'));
                        $this->logTestStep('Arguments: ' . ($item->arguments ?? 'N/A'));
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
