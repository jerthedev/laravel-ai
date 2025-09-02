# Cleanup Ticket 1028

**Ticket ID**: Cleanup/1028-create-provider-response-format-documentation  
**Date Created**: 2025-01-26  
**Status**: Not Started  

## Title
Create Provider Response Format Documentation

## Description
**MEDIUM PRIORITY DOCUMENTATION ISSUE**: The audit revealed significant differences in how providers structure their API responses and how the drivers extract token usage data. This information is not documented anywhere, making it difficult for developers to understand provider differences or debug issues.

**Current State**:
- No documentation of provider response format differences
- Token extraction logic varies by provider but isn't documented
- Field name differences not explained (promptTokens vs promptTokenCount)
- Access pattern differences not documented (object vs array access)
- Developers must read source code to understand provider differences

**Desired State**:
- Comprehensive documentation of each provider's response format
- Clear explanation of token extraction differences
- Field name mapping documentation
- Access pattern documentation (object vs array)
- Troubleshooting guide for provider-specific issues

**Provider Response Differences Identified**:
1. **OpenAI**: Object access (`$response->usage->promptTokens`)
2. **XAI**: Array access (`$response['usage']['prompt_tokens']`) - same format as OpenAI but parsed differently
3. **Gemini**: Array access (`$data['usageMetadata']['promptTokenCount']`) - different field names
4. **Ollama**: TBD - will need documentation after implementation

## Related Documentation
- [ ] docs/PROVIDERS.md - Provider-specific documentation
- [ ] docs/TROUBLESHOOTING.md - Provider troubleshooting guide
- [ ] API_REFERENCE.md - API reference with provider differences

## Related Files
- [ ] docs/PROVIDER_RESPONSE_FORMATS.md - CREATE: New comprehensive provider response documentation
- [ ] docs/PROVIDERS.md - UPDATE: Add response format section for each provider
- [ ] docs/TROUBLESHOOTING.md - UPDATE: Add provider-specific troubleshooting
- [ ] API_REFERENCE.md - UPDATE: Document provider differences in API reference
- [ ] src/Drivers/OpenAI/Traits/HandlesApiCommunication.php - REFERENCE: OpenAI response parsing
- [ ] src/Drivers/XAI/Traits/HandlesApiCommunication.php - REFERENCE: XAI response parsing
- [ ] src/Drivers/Gemini/Traits/HandlesApiCommunication.php - REFERENCE: Gemini response parsing

## Related Tests
- [ ] Documentation examples should include real response format examples
- [ ] Provider response format examples should be validated against actual API responses
- [ ] Troubleshooting examples should be tested

## Acceptance Criteria
- [ ] PROVIDER_RESPONSE_FORMATS.md created with comprehensive response format documentation
- [ ] Each provider's response structure documented with real examples
- [ ] Token extraction differences clearly explained with code examples
- [ ] Field name mapping table created (promptTokens vs promptTokenCount, etc.)
- [ ] Access pattern differences documented (object vs array access)
- [ ] Troubleshooting guide includes provider-specific common issues
- [ ] Code examples show how to handle each provider's response format
- [ ] Performance characteristics documented for each provider
- [ ] Error response formats documented for each provider
- [ ] Integration examples show provider-specific considerations

## AI Prompt
```
You are a Laravel AI package development expert. Please read this ticket fully: docs/Planning/Tickets/Budget Cost Tracking/Cleanup/1028-create-provider-response-format-documentation.md, including the title, description, related documentation, files, and tests listed above.

CONTEXT: The audit revealed significant provider response format differences:
- OpenAI: Object access ($response->usage->promptTokens)
- XAI: Array access ($response['usage']['prompt_tokens']) - same format as OpenAI but parsed differently
- Gemini: Array access ($data['usageMetadata']['promptTokenCount']) - different field names
- Ollama: TBD after implementation

DOCUMENTATION REQUIREMENTS:
1. Comprehensive response format documentation for each provider
2. Token extraction differences with code examples
3. Field name mapping and access pattern differences
4. Troubleshooting guide for provider-specific issues
5. Real API response examples

Based on this ticket:
1. Create a comprehensive task list for documenting provider response formats
2. Design the structure for PROVIDER_RESPONSE_FORMATS.md
3. Plan the field name mapping documentation
4. Design troubleshooting guide for provider differences
5. Plan real API response examples and validation
6. Pause and wait for my review before proceeding with implementation

Please be thorough and consider developer debugging needs, provider differences, and practical examples.
```

## Notes
- Important for developer understanding of provider differences
- Should include real API response examples from audit testing
- Critical for debugging provider-specific issues
- Should be updated when Ollama provider is implemented

## Estimated Effort
Medium (4-8 hours)

## Dependencies
- [ ] Real API response examples from audit Phase 6 testing
- [ ] Provider driver source code for response parsing logic
- [ ] Understanding of token extraction differences from audit findings
