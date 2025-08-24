<?php

namespace JTD\LaravelAI\Tests\E2E;

use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Simple test to debug OpenAI integration.
 */
class SimpleOpenAITest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Check if E2E credentials are available
        $credentialsPath = __DIR__ . '/../credentials/e2e-credentials.json';
        
        if (!file_exists($credentialsPath)) {
            $this->markTestSkipped('E2E credentials file not found');
        }
        
        $credentials = json_decode(file_get_contents($credentialsPath), true);
        
        if (empty($credentials['openai']['api_key'])) {
            $this->markTestSkipped('OpenAI API key not configured');
        }
        
        // Configure for real OpenAI testing
        config([
            'ai.default' => 'openai',
            'ai.providers.openai.enabled' => true,
            'ai.providers.openai.api_key' => $credentials['openai']['api_key'],
            'ai.providers.openai.organization' => $credentials['openai']['organization'] ?? null,
            'ai.providers.openai.project' => $credentials['openai']['project'] ?? null,
            'ai.events.enabled' => true,
        ]);
    }

    #[Test]
    public function it_can_call_openai_and_debug_response()
    {
        $message = AIMessage::user('Say "Hello" in exactly one word.');
        
        $response = AI::sendMessage($message, [
            'model' => 'gpt-3.5-turbo',
            'max_tokens' => 10,
            'temperature' => 0.0,
        ]);
        
        // Debug the response structure
        echo "\n=== OpenAI Response Debug ===";
        echo "\nContent: " . $response->content;
        echo "\nProvider: " . $response->provider;
        echo "\nModel: " . $response->model;
        echo "\nFinish Reason: " . $response->finishReason;
        
        if ($response->tokenUsage) {
            echo "\n--- Token Usage ---";
            echo "\nInput Tokens: " . $response->tokenUsage->inputTokens;
            echo "\nOutput Tokens: " . $response->tokenUsage->outputTokens;
            echo "\nTotal Tokens: " . $response->tokenUsage->totalTokens;
            echo "\nInput Cost: " . $response->tokenUsage->inputCost;
            echo "\nOutput Cost: " . $response->tokenUsage->outputCost;
            echo "\nTotal Cost: " . $response->tokenUsage->totalCost;
            echo "\nCurrency: " . $response->tokenUsage->currency;
        } else {
            echo "\nNo token usage data";
        }
        
        echo "\ngetTotalCost(): " . $response->getTotalCost();
        
        if ($response->costBreakdown) {
            echo "\n--- Cost Breakdown ---";
            print_r($response->costBreakdown);
        } else {
            echo "\nNo cost breakdown";
        }
        
        echo "\n=== End Debug ===\n";
        
        // Basic assertions
        $this->assertNotNull($response);
        $this->assertNotEmpty($response->content);
        $this->assertEquals('openai', $response->provider);
        $this->assertNotNull($response->tokenUsage);
        $this->assertGreaterThan(0, $response->tokenUsage->totalTokens);
    }
}
