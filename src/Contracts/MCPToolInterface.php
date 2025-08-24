<?php

namespace JTD\LaravelAI\Contracts;

/**
 * Interface for MCP tool implementations.
 *
 * MCP tools provide specific functionality that can be called by AI providers
 * or executed as part of MCP server processing. Tools have defined schemas
 * for input validation and provide structured output.
 */
interface MCPToolInterface
{
    /**
     * Get the unique identifier for this tool.
     *
     * @return string Tool identifier (e.g., 'web_search', 'create_repository')
     */
    public function getName(): string;

    /**
     * Get the display name for this tool.
     *
     * @return string Human-readable tool name
     */
    public function getDisplayName(): string;

    /**
     * Get the description of what this tool does.
     *
     * @return string Tool description
     */
    public function getDescription(): string;

    /**
     * Get the input schema for this tool.
     *
     * Returns a JSON Schema definition that describes the expected
     * input parameters for this tool.
     *
     * @return array JSON Schema for input validation
     */
    public function getInputSchema(): array;

    /**
     * Get the output schema for this tool.
     *
     * Returns a JSON Schema definition that describes the structure
     * of the tool's output.
     *
     * @return array JSON Schema for output structure
     */
    public function getOutputSchema(): array;

    /**
     * Execute the tool with the given parameters.
     *
     * @param array $parameters Input parameters matching the input schema
     * @return array Tool execution result matching the output schema
     *
     * @throws \JTD\LaravelAI\Exceptions\MCPToolException
     * @throws \JTD\LaravelAI\Exceptions\ValidationException
     */
    public function execute(array $parameters = []): array;

    /**
     * Validate input parameters against the tool's schema.
     *
     * @param array $parameters Parameters to validate
     * @return bool True if parameters are valid
     *
     * @throws \JTD\LaravelAI\Exceptions\ValidationException
     */
    public function validateInput(array $parameters): bool;

    /**
     * Get examples of how to use this tool.
     *
     * @return array Array of usage examples with input/output pairs
     */
    public function getExamples(): array;

    /**
     * Check if this tool requires authentication or API keys.
     *
     * @return bool True if tool requires authentication
     */
    public function requiresAuthentication(): bool;

    /**
     * Get the required permissions or scopes for this tool.
     *
     * @return array Array of required permissions
     */
    public function getRequiredPermissions(): array;

    /**
     * Get the estimated execution time for this tool.
     *
     * @return int Estimated execution time in milliseconds
     */
    public function getEstimatedExecutionTime(): int;

    /**
     * Check if this tool supports batch operations.
     *
     * @return bool True if tool supports batch execution
     */
    public function supportsBatch(): bool;

    /**
     * Get the tool category for organization purposes.
     *
     * @return string Tool category (e.g., 'search', 'development', 'analysis')
     */
    public function getCategory(): string;

    /**
     * Get the tool version.
     *
     * @return string Tool version
     */
    public function getVersion(): string;
}
