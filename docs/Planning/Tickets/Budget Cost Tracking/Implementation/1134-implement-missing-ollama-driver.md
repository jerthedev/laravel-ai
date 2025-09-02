# Implementation Ticket 1020

**Ticket ID**: Implementation/1020-implement-missing-ollama-driver  
**Date Created**: 2025-01-26  
**Status**: Not Started  

## Title
Implement Missing Ollama Driver for Local AI Model Support

## Description
**CRITICAL COMPLETENESS ISSUE**: The audit revealed that Ollama is configured in `config/ai.php` and referenced throughout documentation as a supported provider, but no driver implementation exists. This creates a gap between specification and implementation, causing fatal errors when users try to use the Ollama provider.

**Current State**:
- Ollama configured in `config/ai.php` with full provider settings
- Documentation references Ollama as supported provider
- No driver implementation exists in `src/Drivers/Ollama/`
- Users get fatal errors when trying to use Ollama provider
- Specification shows 4 providers but only 3 are implemented

**Desired State**:
- Complete Ollama driver implementation following established patterns
- Trait-based architecture consistent with other providers
- Local model support with zero-cost pricing (local models are free)
- Full integration with AI request flow and middleware system
- Comprehensive testing and documentation

**Architecture Requirements**:
- Follow existing driver patterns (OpenAI, XAI, Gemini)
- Implement trait-based architecture with appropriate traits
- Support Ollama API format and local model communication
- Handle zero-cost pricing for local models
- Integrate with response-level cost calculation system
- Support streaming and function calling if available in Ollama

## Related Documentation
- [ ] docs/templates/drivers/ - Driver template files for consistent implementation
- [ ] docs/DRIVER_SYSTEM.md - Driver development standards and patterns
- [ ] config/ai.php - Existing Ollama configuration
- [ ] src/Drivers/OpenAI/ - Reference implementation pattern

## Related Files
- [ ] src/Drivers/Ollama/OllamaDriver.php - CREATE: Main driver class
- [ ] src/Drivers/Ollama/Traits/ - CREATE: Directory for trait-based architecture
- [ ] src/Drivers/Ollama/Traits/HandlesApiCommunication.php - CREATE: API communication trait
- [ ] src/Drivers/Ollama/Traits/CalculatesCosts.php - CREATE: Cost calculation (zero-cost for local)
- [ ] src/Drivers/Ollama/Traits/HandlesErrors.php - CREATE: Error handling trait
- [ ] src/Drivers/Ollama/Traits/ManagesModels.php - CREATE: Model management trait
- [ ] src/Drivers/Ollama/Traits/ValidatesHealth.php - CREATE: Health check trait
- [ ] src/Drivers/Ollama/Support/ModelPricing.php - CREATE: Zero-cost pricing support
- [ ] src/Drivers/Ollama/Support/ErrorMapper.php - CREATE: Ollama-specific error mapping
- [ ] src/Drivers/Ollama/Support/ModelCapabilities.php - CREATE: Model capabilities mapping

## Related Tests
- [ ] tests/Unit/Drivers/Ollama/OllamaDriverTest.php - CREATE: Unit tests for driver
- [ ] tests/Unit/Drivers/Ollama/Traits/ - CREATE: Unit tests for all traits
- [ ] tests/E2E/Drivers/Ollama/OllamaE2ETest.php - CREATE: E2E tests (if Ollama available)
- [ ] tests/Integration/OllamaIntegrationTest.php - CREATE: Integration tests
- [ ] tests/Feature/OllamaProviderTest.php - CREATE: Feature tests for provider selection

## Acceptance Criteria
- [ ] OllamaDriver class extends AbstractAIProvider correctly
- [ ] Trait-based architecture follows established patterns
- [ ] API communication works with Ollama local server
- [ ] Cost calculation returns zero for local models
- [ ] Error handling maps Ollama errors to standard format
- [ ] Model management supports Ollama model listing and selection
- [ ] Health validation checks Ollama server availability
- [ ] Integration with AI Facade works (AI::provider('ollama')->sendMessage())
- [ ] Response-level cost calculation works (returns 0 for local models)
- [ ] Event-based cost aggregation works with zero costs
- [ ] Comprehensive test coverage including unit and integration tests
- [ ] Documentation updated to reflect complete implementation

## AI Prompt
```
You are a Laravel AI package development expert. Please read this ticket fully: docs/Planning/Tickets/Budget Cost Tracking/Implementation/1020-implement-missing-ollama-driver.md, including the title, description, related documentation, files, and tests listed above.

CRITICAL CONTEXT: Ollama is configured and documented as supported but completely missing from implementation. This is a 4th provider that needs to be implemented following established patterns.

ARCHITECTURE REQUIREMENTS:
1. Follow trait-based architecture like OpenAI, XAI, Gemini drivers
2. Support local model communication (different from cloud APIs)
3. Handle zero-cost pricing (local models are free)
4. Integrate with response-level cost calculation system
5. Use driver templates from docs/templates/drivers/ for consistency

OLLAMA SPECIFICS:
- Local server communication (typically http://localhost:11434)
- Different API format from OpenAI-compatible providers
- Zero-cost pricing for local models
- May have different capabilities (streaming, function calling)

Based on this ticket:
1. Create a comprehensive task list for implementing complete Ollama driver
2. Design the trait architecture following established patterns
3. Plan the API communication strategy for local Ollama server
4. Design zero-cost pricing integration
5. Plan comprehensive testing strategy including local server scenarios
6. Pause and wait for my review before proceeding with implementation

Please be thorough and consider local server communication, zero-cost pricing, and integration with existing systems.
```

## Notes
- Completes the 4/4 provider implementation (OpenAI, XAI, Gemini, Ollama)
- Local model support is different from cloud API providers
- Zero-cost pricing requires special handling in cost calculation systems
- May require different testing approach due to local server dependency
- Should follow driver templates for consistency

## Estimated Effort
XL (2+ days)

## Dependencies
- [ ] Driver templates in docs/templates/drivers/
- [ ] Understanding of Ollama API format and local server communication
- [ ] Response-level cost calculation system (ticket 1016) for zero-cost integration
- [ ] Existing driver patterns for trait-based architecture reference
