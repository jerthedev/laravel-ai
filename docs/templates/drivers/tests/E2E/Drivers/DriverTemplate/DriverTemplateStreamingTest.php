<?php

namespace JTD\LaravelAI\Tests\E2E;

use JTD\LaravelAI\Drivers\DriverTemplate\DriverTemplateDriver;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * E2E Tests for DriverTemplate Streaming Functionality
 *
 * Tests streaming responses with real DriverTemplate API including
 * chunk processing, progress tracking, and error scenarios.
 */
#[Group('e2e')]
#[Group('drivertemplate')]
#[Group('streaming')]
class DriverTemplateStreamingTest extends E2ETestCase
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
            'retry_attempts' => 2,
        ];

        $this->driver = new DriverTemplateDriver($config);
    }

    #[Test]
    public function it_can_stream_basic_response(): void
    {

        // TODO: Implement test
                }
            });

            $this->assertInstanceOf(AIResponse::class, $response);
            $this->assertNotEmpty($response->content, 'Final response should have content');
            $this->assertGreaterThan(0, $chunkCount, 'Should receive multiple chunks');

            // Token usage may not be available in streaming mode
            if ($response->tokenUsage->totalTokens > 0) {
                $this->logTestStep("Token usage available: " . $response->tokenUsage->totalTokens);
            } else {
                $this->logTestStep("⚠️  Token usage not available in streaming mode (this is normal)");
            }

            $this->logTestStep("✅ Streaming completed successfully");
            $this->logTestStep("Chunks received: {$chunkCount}");
            $this->logTestStep("Total content length: " . strlen($totalContent));
            $this->logTestStep("Final response: \"" . trim($response->content) . "\"");
            $this->logTestStep("Token usage: " . $response->tokenUsage->totalTokens);

            // Verify content consistency
            $this->assertEquals(trim($totalContent), trim($response->content),
                'Streamed content should match final response content');

        } catch (\Exception $e) {
            $this->logTestStep('❌ Streaming test failed: ' . $e->getMessage());
            $this->logTestStep('Exception type: ' . get_class($e));
            throw $e;
        }

        $this->logTestEnd('Basic streaming test completed');
    }

    #[Test]
    public function it_can_stream_longer_response(): void
    {

        // TODO: Implement test
                }
                $lastChunkTime = $currentTime;

                $chunks[] = $chunk;
                $chunkCount++;
            });

            $endTime = microtime(true);
            $totalTime = ($endTime - $startTime) * 1000;
            $streamingDuration = ($lastChunkTime - $firstChunkTime) * 1000;

            $this->assertInstanceOf(AIResponse::class, $response);
            $this->assertGreaterThan(5, $chunkCount, 'Longer response should have more chunks');

            $this->logTestStep("✅ Longer streaming completed");
            $this->logTestStep("Total time: " . round($totalTime) . "ms");
            $this->logTestStep("Streaming duration: " . round($streamingDuration) . "ms");
            $this->logTestStep("Chunks: {$chunkCount}");
            $this->logTestStep("Average chunk interval: " . round($streamingDuration / max($chunkCount - 1, 1)) . "ms");
            $this->logTestStep("Response length: " . strlen($response->content) . " chars");

            // Verify response quality
            $sentences = explode('.', trim($response->content));
            $sentences = array_filter($sentences, fn($s) => !empty(trim($s)));
            $this->logTestStep("Sentences detected: " . count($sentences));

        } catch (\Exception $e) {
            $this->logTestStep('❌ Longer streaming test failed: ' . $e->getMessage());
            throw $e;
        }

        $this->logTestEnd('Longer streaming test completed');
    }

    #[Test]
    public function it_can_handle_streaming_with_conversation_context(): void
    {

        // TODO: Implement test
            });

            $this->assertInstanceOf(AIResponse::class, $response2);
            $this->assertNotEmpty($response2->content);

            $this->logTestStep('✅ Context-aware streaming completed');
            $this->logTestStep('Streamed response: "' . trim($response2->content) . '"');
            $this->logTestStep('Chunks received: ' . count($chunks));

            // Check if the AI remembered the context
            $responseContent = strtolower($response2->content);
            $this->assertStringContainsString('blue', $responseContent,
                'AI should remember the favorite color from context');
            $this->logTestStep('✅ Context was properly maintained');

        } catch (\Exception $e) {
            $this->logTestStep('❌ Context streaming test failed: ' . $e->getMessage());
            throw $e;
        }

        $this->logTestEnd('Context streaming test completed');
    }

    #[Test]
    public function it_can_handle_streaming_with_different_parameters(): void
    {

        // TODO: Implement test
                });

                $this->assertInstanceOf(AIResponse::class, $response);
                $this->assertNotEmpty($response->content);
                $this->assertGreaterThan(0, count($chunks));

                $this->logTestStep("✅ {$name}: \"" . trim($response->content) . "\"");
                $this->logTestStep("   Chunks: " . count($chunks) . ", Length: " . strlen($response->content));

            } catch (\Exception $e) {
                $this->logTestStep("❌ {$name} failed: " . $e->getMessage());
                throw $e;
            }
        }

        $this->logTestEnd('Parameter variation streaming test completed');
    }

    #[Test]
    public function it_can_handle_streaming_errors_gracefully(): void
    {

        // TODO: Implement test
            });

            $this->fail('Expected exception for invalid model');

        } catch (\Exception $e) {
            $this->logTestStep('✅ Invalid model properly rejected');
            $this->logTestStep('Error: ' . $e->getMessage());
            $this->assertStringContainsString('model', strtolower($e->getMessage()));
        }

        // Test with extremely low token limit
        try {
            $chunks = [];
            $response = $this->driver->sendStreamingMessageWithCallback($message, [
                'model' => 'default-model-3.5-turbo',
                'max_tokens' => 1, // Very low limit
            ], function ($chunk) use (&$chunks) {
                $chunks[] = $chunk;
            });

            $this->assertInstanceOf(AIResponse::class, $response);
            $this->logTestStep('✅ Low token limit handled gracefully');
            $this->logTestStep('Response: "' . trim($response->content) . '"');
            $this->logTestStep('Chunks: ' . count($chunks));

        } catch (\Exception $e) {
            $this->logTestStep('⚠️  Low token limit caused error (acceptable): ' . $e->getMessage());
        }

        $this->logTestEnd('Streaming error handling test completed');
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
