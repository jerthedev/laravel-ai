# MCP Setup Feature Tests

**Sprint4b Story 5**: Easy MCP Setup System

## Acceptance Criteria
- Interactive artisan command lists available MCP installations
- Supports Sequential Thinking, GitHub MCP, Brave Search, and extensible for more
- Prompts for API keys and configuration details using laravel/prompts
- Automatically installs global packages and updates .mcp.json
- Validates configurations and tests MCP server connectivity
- Provides clear error messages and troubleshooting guidance

## Test Coverage Areas
- ai:mcp:setup interactive command functionality
- MCP server installation workflows (Sequential Thinking, GitHub, Brave Search)
- laravel/prompts integration and API key handling
- Automatic .mcp.json configuration updates
- MCP server validation and connectivity testing
- ai:mcp:discover command and tool discovery
- Error handling and troubleshooting guidance

## Supported MCP Servers
- Sequential Thinking (@modelcontextprotocol/server-sequential-thinking)
- GitHub MCP (https://github.com/github/github-mcp-server)
- Brave Search (@modelcontextprotocol/server-brave-search)
