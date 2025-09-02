# Ticket Template

**Ticket ID**: [Phase]/[Number]-[short-description]  
**Date Created**: [YYYY-MM-DD]  
**Status**: Not Started  

## Title
[Clear, concise title describing what needs to be done]

## Description
[Comprehensive details about the task, including:]
- What needs to be accomplished
- Why this work is necessary
- Current state vs desired state
- Any specific requirements or constraints
- Dependencies on other tickets or systems
- Expected outcomes and success criteria

## Related Documentation
- [ ] [Document 1] - [Brief description of relevance]
- [ ] [Document 2] - [Brief description of relevance]
- [ ] [Additional specs, architecture docs, etc.]

## Related Files
- [ ] [File path 1] - [What needs to be done with this file]
- [ ] [File path 2] - [What needs to be done with this file]
- [ ] [Additional source files, configs, etc.]

## Related Tests
- [ ] [Test file 1] - [What needs to be tested/verified]
- [ ] [Test file 2] - [What needs to be tested/verified]
- [ ] [Additional test files, test scenarios, etc.]

## Acceptance Criteria
- [ ] [Specific, measurable criterion 1]
- [ ] [Specific, measurable criterion 2]
- [ ] [Additional criteria that define "done"]

## AI Prompt
```
You are a Laravel AI package development expert. Please read this ticket fully: [TICKET_PATH], including the title, description, related documentation, files, and tests listed above.

TICKET DIRECTORY STRUCTURE:
- Template: docs/Planning/Tickets/template.md
- Major Features: Budget Cost Tracking, Unified Tool System
- Phases: Audit, Implementation, Cleanup, Test Implementation, Test Cleanup
- Format: docs/Planning/Tickets/<Major Feature>/<Phase>/####-short-description.md
- Numbering: 1000s for Budget Cost Tracking, 2000s for Unified Tool System

Based on this ticket:
1. Create a comprehensive task list breaking down all work needed to complete this ticket
2. Identify any dependencies or prerequisites
3. Suggest the order of execution for maximum efficiency
4. Highlight any potential risks or challenges
5. If this is an AUDIT ticket, plan the creation of subsequent phase tickets using the template
6. Pause and wait for my review before proceeding with implementation

Please be thorough and consider all aspects of Laravel development including code implementation, testing, documentation, and integration.
```

## Notes
[Any additional context, decisions made, or important considerations]

## Estimated Effort
[Time estimate: Small (< 4 hours), Medium (4-8 hours), Large (1-2 days), XL (2+ days)]

## Dependencies
- [ ] [Ticket ID or external dependency]
- [ ] [Additional dependencies]
