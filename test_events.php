<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Event;
use JTD\LaravelAI\Events\CostCalculated;
use JTD\LaravelAI\Events\MessageSent;
use JTD\LaravelAI\Events\ResponseGenerated;
use JTD\LaravelAI\Facades\AI;
use JTD\LaravelAI\Models\AIMessage;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Load credentials
$credentialsPath = __DIR__ . '/tests/credentials/e2e-credentials.json';
if (! file_exists($credentialsPath)) {
    echo "E2E credentials file not found\n";
    exit(1);
}

$credentials = json_decode(file_get_contents($credentialsPath), true);
if (empty($credentials['openai']['api_key'])) {
    echo "OpenAI API key not configured\n";
    exit(1);
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

// Set up event listeners to capture fired events
$firedEvents = [];

Event::listen(MessageSent::class, function ($event) use (&$firedEvents) {
    $firedEvents['MessageSent'][] = $event;
    echo "✅ MessageSent event fired\n";
    echo "  Provider: {$event->provider}\n";
    echo "  User ID: {$event->userId}\n";
});

Event::listen(ResponseGenerated::class, function ($event) use (&$firedEvents) {
    $firedEvents['ResponseGenerated'][] = $event;
    echo "✅ ResponseGenerated event fired\n";
    echo "  Provider: {$event->response->provider}\n";
    echo "  Model: {$event->response->model}\n";
    echo "  Tokens: {$event->response->tokenUsage->totalTokens}\n";
});

Event::listen(CostCalculated::class, function ($event) use (&$firedEvents) {
    $firedEvents['CostCalculated'][] = $event;
    echo "✅ CostCalculated event fired\n";
    echo "  Provider: {$event->provider}\n";
    echo "  Cost: {$event->cost}\n";
    echo "  Input Tokens: {$event->inputTokens}\n";
    echo "  Output Tokens: {$event->outputTokens}\n";
});

echo "🧪 Testing OpenAI event firing...\n\n";

try {
    $message = AIMessage::user('Say "Hello" in exactly one word.');
    $message->user_id = 123;

    echo "📤 Sending message to OpenAI...\n";
    $response = AI::sendMessage($message, [
        'model' => 'gpt-3.5-turbo',
        'max_tokens' => 10,
        'temperature' => 0.0,
    ]);

    echo "\n📥 Response received:\n";
    echo "  Content: {$response->content}\n";
    echo "  Provider: {$response->provider}\n";
    echo "  Model: {$response->model}\n";
    echo "  Total Cost: {$response->getTotalCost()}\n";

    if ($response->tokenUsage) {
        echo "  Input Tokens: {$response->tokenUsage->inputTokens}\n";
        echo "  Output Tokens: {$response->tokenUsage->outputTokens}\n";
        echo "  Total Tokens: {$response->tokenUsage->totalTokens}\n";
        echo "  Token Usage Total Cost: {$response->tokenUsage->totalCost}\n";
    }

    echo "\n📊 Event Summary:\n";
    echo '  MessageSent events: ' . count($firedEvents['MessageSent'] ?? []) . "\n";
    echo '  ResponseGenerated events: ' . count($firedEvents['ResponseGenerated'] ?? []) . "\n";
    echo '  CostCalculated events: ' . count($firedEvents['CostCalculated'] ?? []) . "\n";
} catch (Exception $e) {
    echo "\n❌ Error occurred:\n";
    echo "  Message: {$e->getMessage()}\n";
    echo "  File: {$e->getFile()}:{$e->getLine()}\n";

    echo "\n📊 Event Summary (after error):\n";
    echo '  MessageSent events: ' . count($firedEvents['MessageSent'] ?? []) . "\n";
    echo '  ResponseGenerated events: ' . count($firedEvents['ResponseGenerated'] ?? []) . "\n";
    echo '  CostCalculated events: ' . count($firedEvents['CostCalculated'] ?? []) . "\n";
}

echo "\n✅ Test completed\n";
