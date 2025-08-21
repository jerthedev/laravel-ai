# Conversation Management

## Overview

JTD Laravel AI provides a comprehensive conversation management system that allows you to maintain persistent chat threads, track message history, and seamlessly switch between AI providers while maintaining context.

## Basic Conversation Usage

### Creating a Conversation

```php
use JTD\LaravelAI\Facades\AI;

// Create a new conversation
$conversation = AI::conversation('My AI Assistant');

// Create with specific user
$conversation = AI::conversation('Support Chat')
    ->user(auth()->user());

// Create with custom metadata
$conversation = AI::conversation('Project Planning')
    ->metadata([
        'project_id' => 123,
        'department' => 'engineering',
        'priority' => 'high',
    ]);
```

### Sending Messages

```php
// Simple message
$response = $conversation
    ->message('Hello, how are you today?')
    ->send();

// Message with specific provider/model
$response = $conversation
    ->provider('openai')
    ->model('gpt-4')
    ->message('Explain quantum computing')
    ->send();

// Message with parameters
$response = $conversation
    ->temperature(0.8)
    ->maxTokens(1000)
    ->message('Write a creative story')
    ->send();
```

### Accessing Response

```php
$response = $conversation->message('Hello')->send();

echo $response->content;        // Response text
echo $response->tokens_used;    // Total tokens
echo $response->cost;          // Cost in USD
echo $response->response_time; // Response time in ms
echo $response->provider;      // Provider used
echo $response->model;         // Model used
```

## Advanced Conversation Features

### Conversation Context

```php
// Set system context
$conversation = AI::conversation('Code Assistant')
    ->systemMessage('You are a helpful coding assistant specializing in Laravel.')
    ->message('How do I create a migration?')
    ->send();

// Add context from previous conversations
$conversation->addContext($previousConversation);

// Set context window size
$conversation->contextWindow(10); // Keep last 10 messages
```

### Multi-turn Conversations

```php
$conversation = AI::conversation('Learning Session');

// First exchange
$response1 = $conversation
    ->message('What is Laravel?')
    ->send();

// Second exchange (maintains context)
$response2 = $conversation
    ->message('How do I install it?')
    ->send();

// Third exchange
$response3 = $conversation
    ->message('Show me a simple example')
    ->send();

// Get full conversation history
$history = $conversation->getHistory();
```

### Provider Switching

```php
$conversation = AI::conversation('Multi-Provider Chat');

// Start with OpenAI
$response1 = $conversation
    ->provider('openai')
    ->message('Explain machine learning')
    ->send();

// Switch to Gemini (context is maintained)
$response2 = $conversation
    ->provider('gemini')
    ->message('What did you just explain?')
    ->send();

// Switch to xAI
$response3 = $conversation
    ->provider('xai')
    ->message('Can you elaborate on that?')
    ->send();
```

## Conversation Management

### Loading Existing Conversations

```php
// Load by ID
$conversation = AI::conversation()->load(123);

// Load by name for user
$conversation = AI::conversation('My Chat')
    ->user(auth()->user())
    ->loadOrCreate();

// Load recent conversations
$recent = AI::getRecentConversations(auth()->user(), 10);

// Search conversations
$conversations = AI::searchConversations('machine learning', auth()->user());
```

### Conversation Metadata

```php
// Set metadata
$conversation->setMetadata([
    'project_id' => 456,
    'tags' => ['ai', 'research', 'important'],
    'priority' => 'high',
]);

// Get metadata
$metadata = $conversation->getMetadata();

// Update specific metadata
$conversation->updateMetadata('status', 'completed');

// Filter conversations by metadata
$conversations = AI::getConversations()
    ->whereMetadata('project_id', 456)
    ->whereMetadata('priority', 'high')
    ->get();
```

### Conversation Participants

```php
// Add participants to conversation
$conversation->addParticipant($user1, 'member');
$conversation->addParticipant($user2, 'observer');

// Get participants
$participants = $conversation->getParticipants();

// Remove participant
$conversation->removeParticipant($user1);

// Check if user can access conversation
if ($conversation->canAccess(auth()->user())) {
    // User has access
}
```

## Message Management

### Message Types

```php
// User message
$conversation->userMessage('Hello AI');

// System message
$conversation->systemMessage('You are a helpful assistant');

// Assistant message (for context)
$conversation->assistantMessage('Hello! How can I help you?');

// Function call result
$conversation->functionMessage('weather_result', ['temp' => 72, 'condition' => 'sunny']);
```

### Message Attachments

```php
// Attach image
$response = $conversation
    ->message('What do you see in this image?')
    ->attachImage('path/to/image.jpg')
    ->send();

// Attach file
$response = $conversation
    ->message('Analyze this document')
    ->attachFile('path/to/document.pdf')
    ->send();

// Multiple attachments
$response = $conversation
    ->message('Review these files')
    ->attachFiles(['file1.pdf', 'file2.docx'])
    ->send();
```

### Message Editing and Deletion

```php
// Edit last message
$conversation->editLastMessage('Updated message content');

// Delete last message
$conversation->deleteLastMessage();

// Edit specific message
$conversation->editMessage($messageId, 'New content');

// Delete specific message
$conversation->deleteMessage($messageId);

// Clear conversation history
$conversation->clearHistory();
```

## Conversation Branching

### Creating Branches

```php
$conversation = AI::conversation('Main Thread');

// Send initial message
$response1 = $conversation->message('What is AI?')->send();

// Create branch from specific message
$branch1 = $conversation->branch('Technical Details', $response1->message_id);
$branch1->message('Explain the technical aspects')->send();

// Create another branch
$branch2 = $conversation->branch('Applications', $response1->message_id);
$branch2->message('What are practical applications?')->send();

// List branches
$branches = $conversation->getBranches();
```

### Managing Branches

```php
// Switch to branch
$conversation->switchToBranch($branchId);

// Merge branch back to main
$conversation->mergeBranch($branchId);

// Delete branch
$conversation->deleteBranch($branchId);

// Get branch history
$branchHistory = $conversation->getBranchHistory($branchId);
```

## Conversation Analytics

### Usage Statistics

```php
// Get conversation statistics
$stats = $conversation->getStatistics();

echo $stats['message_count'];     // Total messages
echo $stats['total_tokens'];      // Total tokens used
echo $stats['total_cost'];        // Total cost
echo $stats['avg_response_time']; // Average response time
echo $stats['providers_used'];    // Providers used

// Get user conversation statistics
$userStats = AI::getUserStatistics(auth()->user());
```

### Cost Tracking

```php
// Get conversation costs
$costs = $conversation->getCosts();

echo $costs['total'];           // Total cost
echo $costs['by_provider'];     // Cost breakdown by provider
echo $costs['by_model'];        // Cost breakdown by model
echo $costs['by_date'];         // Daily cost breakdown

// Set cost limits
$conversation->setCostLimit(10.00); // $10 limit

// Check if approaching limit
if ($conversation->isApproachingCostLimit()) {
    // Warn user
}
```

## Conversation Events

### Event Listeners

```php
use JTD\LaravelAI\Events\ConversationStarted;
use JTD\LaravelAI\Events\MessageSent;
use JTD\LaravelAI\Events\ResponseReceived;

// Listen for conversation events
Event::listen(ConversationStarted::class, function ($event) {
    // Log conversation start
    Log::info('Conversation started', ['id' => $event->conversation->id]);
});

Event::listen(MessageSent::class, function ($event) {
    // Track message sending
    Analytics::track('message_sent', [
        'conversation_id' => $event->conversation->id,
        'provider' => $event->provider,
    ]);
});
```

### Custom Events

```php
// Fire custom events
$conversation->fireEvent('custom_event', ['data' => 'value']);

// Listen for custom events
$conversation->on('custom_event', function ($data) {
    // Handle custom event
});
```

## Conversation Templates

### Creating Templates

```php
// Create conversation template
$template = AI::createTemplate('Customer Support', [
    'system_message' => 'You are a helpful customer support agent.',
    'initial_context' => 'Customer needs assistance with their order.',
    'suggested_responses' => [
        'How can I help you today?',
        'Let me look into that for you.',
        'Is there anything else I can assist with?',
    ],
]);

// Use template
$conversation = AI::conversation()->fromTemplate($template);
```

### Template Management

```php
// List templates
$templates = AI::getTemplates();

// Get specific template
$template = AI::getTemplate('customer-support');

// Update template
AI::updateTemplate('customer-support', [
    'system_message' => 'Updated system message',
]);

// Delete template
AI::deleteTemplate('customer-support');
```

## Conversation Export/Import

### Exporting Conversations

```php
// Export conversation to JSON
$json = $conversation->exportToJson();

// Export to CSV
$csv = $conversation->exportToCsv();

// Export with options
$export = $conversation->export([
    'format' => 'json',
    'include_metadata' => true,
    'include_costs' => true,
    'date_range' => ['2024-01-01', '2024-01-31'],
]);
```

### Importing Conversations

```php
// Import from JSON
$conversation = AI::importFromJson($jsonData);

// Import from file
$conversation = AI::importFromFile('conversation.json');

// Bulk import
$conversations = AI::bulkImport('conversations.zip');
```

## Performance Optimization

### Conversation Caching

```php
// Enable conversation caching
$conversation = AI::conversation('Cached Chat')
    ->cache(3600) // Cache for 1 hour
    ->message('Hello')
    ->send();

// Cache conversation context
$conversation->cacheContext();

// Clear conversation cache
$conversation->clearCache();
```

### Lazy Loading

```php
// Lazy load conversation history
$conversation = AI::conversation()->load(123, ['lazy' => true]);

// Load specific message range
$messages = $conversation->getMessages(50, 100); // Messages 50-100

// Paginate messages
$messages = $conversation->paginateMessages(20); // 20 per page
```

### Background Processing

```php
// Process conversation in background
$conversation->processInBackground([
    'message' => 'Long analysis task...',
    'callback_url' => route('ai.callback'),
]);

// Queue conversation for processing
dispatch(new ProcessConversationJob($conversation));
```

## Security and Privacy

### Access Control

```php
// Set conversation visibility
$conversation->setVisibility('private'); // private, public, team

// Check access permissions
if ($conversation->canAccess(auth()->user())) {
    // User has access
}

// Share conversation
$conversation->shareWith($user, 'read'); // read, write, admin
```

### Data Privacy

```php
// Enable privacy mode (no logging)
$conversation->privacyMode(true);

// Anonymize conversation
$conversation->anonymize();

// Set data retention
$conversation->setRetention(30); // 30 days

// Export user data (GDPR compliance)
$userData = AI::exportUserData(auth()->user());
```
