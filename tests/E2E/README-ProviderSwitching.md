# Provider Switching E2E Tests

This directory contains End-to-End (E2E) tests for provider switching functionality using real AI provider APIs.

## Overview

The `ProviderSwitchingE2ETest` validates:

1. **Provider Switching**: Seamlessly switching between different AI providers (OpenAI, Gemini, xAI) within a single conversation
2. **Context Preservation**: Ensuring conversation context is maintained across provider switches
3. **Provider Fallback**: Testing automatic fallback to alternative providers when the primary provider fails
4. **Real API Integration**: Using actual API calls to verify end-to-end functionality

## Prerequisites

### 1. API Credentials

You need valid API credentials for at least 2 providers. Create the credentials file:

```bash
cp tests/credentials/e2e-credentials.example.json tests/credentials/e2e-credentials.json
```

Edit `tests/credentials/e2e-credentials.json` with your real API keys:

```json
{
  "openai": {
    "api_key": "sk-your-openai-api-key-here",
    "organization": "org-your-organization-id",
    "project": "proj-your-project-id",
    "enabled": true
  },
  "gemini": {
    "api_key": "your-gemini-api-key-here",
    "enabled": true
  },
  "xai": {
    "api_key": "your-xai-api-key-here",
    "enabled": true
  }
}
```

### 2. Account Balance

Ensure your API accounts have sufficient balance for testing. The tests use minimal tokens but will make real API calls.

## Running the Tests

### Run All Provider Switching E2E Tests

```bash
vendor/bin/phpunit tests/E2E/ProviderSwitchingE2ETest.php --testdox
```

### Run Specific Test

```bash
# Test basic provider switching with context preservation
vendor/bin/phpunit tests/E2E/ProviderSwitchingE2ETest.php::it_switches_providers_while_preserving_context

# Test provider fallback scenarios
vendor/bin/phpunit tests/E2E/ProviderSwitchingE2ETest.php::it_handles_provider_fallback_with_context_preservation
```

### Run with Groups

```bash
# Run all E2E tests
vendor/bin/phpunit --group=e2e

# Run only provider switching E2E tests
vendor/bin/phpunit --group=provider-switching
```

## Test Scenarios

### Test 1: Provider Switching with Context Preservation

**What it tests:**
- Creates a conversation with the first available provider
- Sends an initial message containing personal information (name, interests)
- Switches to a second provider
- Sends a follow-up message asking about the previously shared information
- Verifies the second provider can access the conversation context

**Expected behavior:**
- Context is preserved across provider switches
- Each provider can reference information from previous messages
- Provider metadata is properly tracked

### Test 2: Provider Fallback with Context Preservation

**What it tests:**
- Creates a conversation and establishes context
- Tests the fallback mechanism with a priority list of providers
- Verifies context is preserved even during fallback scenarios
- Handles graceful degradation when providers are unavailable

**Expected behavior:**
- Fallback mechanism works correctly
- Context is maintained during provider transitions
- System remains stable even when some providers fail

## Test Output

The tests provide detailed logging:

```
1. Creating conversation and initial message...
✅ Conversation created with ID: 123
2. Sending initial message with openai...
✅ First response from openai: Hello Alice! I can see that you love programming...
3. Switching to gemini and testing context preservation...
✅ Successfully switched to gemini
4. Testing context preservation with follow-up message...
✅ Context preserved! Second response from gemini: You told me your name is Alice...
🎉 Provider switching E2E test completed successfully!
```

## Troubleshooting

### Tests Skipped

If tests are skipped with a message like "Provider switching E2E tests require at least 2 providers", check:

1. Your `e2e-credentials.json` file exists and is valid JSON
2. At least 2 providers have `"enabled": true`
3. API keys are valid and not expired
4. Accounts have sufficient balance

### API Errors

Common issues:
- **Rate limiting**: Tests include built-in rate limiting, but you may need to wait between runs
- **Invalid credentials**: Verify API keys are correct and active
- **Insufficient balance**: Ensure accounts have funds for API calls
- **Model availability**: Some models may not be available in your region

### Context Not Preserved

If context preservation fails:
1. Check that the `ConversationContextManager` service is properly configured
2. Verify database migrations have run successfully
3. Ensure the `context_length` field is set correctly on models

## Cost Considerations

These tests make real API calls and will incur costs:
- Each test typically uses 200-500 tokens total
- Costs are usually under $0.01 per test run
- Use low-cost models (gpt-3.5-turbo, gemini-pro) for testing

## Integration with CI/CD

To run these tests in CI/CD:

1. Store API credentials as encrypted environment variables
2. Create the credentials file in the CI pipeline
3. Run tests only when credentials are available
4. Consider running on a schedule rather than every commit to manage costs

Example GitHub Actions:

```yaml
- name: Setup E2E Credentials
  if: ${{ secrets.OPENAI_API_KEY && secrets.GEMINI_API_KEY }}
  run: |
    mkdir -p tests/credentials
    echo '{"openai":{"api_key":"${{ secrets.OPENAI_API_KEY }}","enabled":true},"gemini":{"api_key":"${{ secrets.GEMINI_API_KEY }}","enabled":true}}' > tests/credentials/e2e-credentials.json

- name: Run E2E Tests
  if: ${{ secrets.OPENAI_API_KEY && secrets.GEMINI_API_KEY }}
  run: vendor/bin/phpunit --group=e2e
```
