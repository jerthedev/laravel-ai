<?php

namespace JTD\LaravelAI\Services;

use JTD\LaravelAI\Models\ConversationTemplate;

/**
 * Template Validation Service
 *
 * Comprehensive validation system for conversation templates including
 * structure validation, parameter validation, content validation, and
 * compatibility checks.
 */
class TemplateValidationService
{
    /**
     * Validate a complete template.
     */
    public function validateTemplate(array $templateData): array
    {
        $errors = [];

        // Basic structure validation
        $structureErrors = $this->validateStructure($templateData);
        if (! empty($structureErrors)) {
            $errors['structure'] = $structureErrors;
        }

        // Parameter validation
        if (isset($templateData['parameters'])) {
            $parameterErrors = $this->validateParameterDefinitions($templateData['parameters']);
            if (! empty($parameterErrors)) {
                $errors['parameters'] = $parameterErrors;
            }
        }

        // Template data validation
        if (isset($templateData['template_data'])) {
            $templateDataErrors = $this->validateTemplateData($templateData['template_data'], $templateData['parameters'] ?? []);
            if (! empty($templateDataErrors)) {
                $errors['template_data'] = $templateDataErrors;
            }
        }

        // Configuration validation
        if (isset($templateData['default_configuration'])) {
            $configErrors = $this->validateConfiguration($templateData['default_configuration']);
            if (! empty($configErrors)) {
                $errors['configuration'] = $configErrors;
            }
        }

        return $errors;
    }

    /**
     * Validate basic template structure.
     */
    protected function validateStructure(array $templateData): array
    {
        $errors = [];

        // Required fields
        $requiredFields = ['name', 'category', 'template_data'];
        foreach ($requiredFields as $field) {
            if (! isset($templateData[$field]) || empty($templateData[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }

        // Name validation
        if (isset($templateData['name'])) {
            if (! is_string($templateData['name'])) {
                $errors[] = 'Template name must be a string';
            } elseif (strlen($templateData['name']) > 255) {
                $errors[] = 'Template name cannot exceed 255 characters';
            } elseif (strlen($templateData['name']) < 3) {
                $errors[] = 'Template name must be at least 3 characters';
            }
        }

        // Category validation
        if (isset($templateData['category'])) {
            $validCategories = [
                ConversationTemplate::CATEGORY_GENERAL,
                ConversationTemplate::CATEGORY_BUSINESS,
                ConversationTemplate::CATEGORY_CREATIVE,
                ConversationTemplate::CATEGORY_TECHNICAL,
                ConversationTemplate::CATEGORY_EDUCATIONAL,
                ConversationTemplate::CATEGORY_ANALYSIS,
                ConversationTemplate::CATEGORY_SUPPORT,
            ];

            if (! in_array($templateData['category'], $validCategories)) {
                $errors[] = 'Invalid category. Must be one of: ' . implode(', ', $validCategories);
            }
        }

        // Description validation
        if (isset($templateData['description']) && ! is_string($templateData['description'])) {
            $errors[] = 'Template description must be a string';
        }

        // Tags validation
        if (isset($templateData['tags'])) {
            if (! is_array($templateData['tags'])) {
                $errors[] = 'Tags must be an array';
            } else {
                foreach ($templateData['tags'] as $tag) {
                    if (! is_string($tag)) {
                        $errors[] = 'All tags must be strings';
                        break;
                    }
                    if (strlen($tag) > 50) {
                        $errors[] = 'Tags cannot exceed 50 characters';
                        break;
                    }
                }
            }
        }

        // Language validation
        if (isset($templateData['language']) && (! is_string($templateData['language']) || strlen($templateData['language']) !== 2)) {
            $errors[] = 'Language must be a 2-character ISO code';
        }

        return $errors;
    }

    /**
     * Validate parameter definitions.
     */
    protected function validateParameterDefinitions(array $parameters): array
    {
        $errors = [];

        if (! is_array($parameters)) {
            return ['Parameters must be an array'];
        }

        foreach ($parameters as $name => $definition) {
            $paramErrors = [];

            // Parameter name validation
            if (! is_string($name) || empty($name)) {
                $paramErrors[] = 'Parameter name must be a non-empty string';
            } elseif (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
                $paramErrors[] = 'Parameter name must be a valid identifier (letters, numbers, underscore)';
            }

            // Parameter definition validation
            if (! is_array($definition)) {
                $paramErrors[] = 'Parameter definition must be an array';
                $errors[$name] = $paramErrors;

                continue;
            }

            // Type validation
            $validTypes = ['string', 'integer', 'float', 'boolean', 'array', 'enum'];
            $type = $definition['type'] ?? 'string';
            if (! in_array($type, $validTypes)) {
                $paramErrors[] = 'Invalid parameter type. Must be one of: ' . implode(', ', $validTypes);
            }

            // Required validation
            if (isset($definition['required']) && ! is_bool($definition['required'])) {
                $paramErrors[] = 'Required field must be a boolean';
            }

            // Description validation
            if (isset($definition['description']) && ! is_string($definition['description'])) {
                $paramErrors[] = 'Description must be a string';
            }

            // Default value validation
            if (isset($definition['default'])) {
                $defaultErrors = $this->validateParameterValue($definition['default'], $definition);
                if (! empty($defaultErrors)) {
                    $paramErrors[] = 'Invalid default value: ' . implode(', ', $defaultErrors);
                }
            }

            // Type-specific validation
            switch ($type) {
                case 'enum':
                    if (! isset($definition['options']) || ! is_array($definition['options'])) {
                        $paramErrors[] = 'Enum parameters must have an options array';
                    } elseif (empty($definition['options'])) {
                        $paramErrors[] = 'Enum parameters must have at least one option';
                    }
                    break;

                case 'string':
                    if (isset($definition['max_length']) && (! is_int($definition['max_length']) || $definition['max_length'] < 1)) {
                        $paramErrors[] = 'max_length must be a positive integer';
                    }
                    if (isset($definition['min_length']) && (! is_int($definition['min_length']) || $definition['min_length'] < 0)) {
                        $paramErrors[] = 'min_length must be a non-negative integer';
                    }
                    break;

                case 'integer':
                case 'float':
                    if (isset($definition['min']) && ! is_numeric($definition['min'])) {
                        $paramErrors[] = 'min must be a number';
                    }
                    if (isset($definition['max']) && ! is_numeric($definition['max'])) {
                        $paramErrors[] = 'max must be a number';
                    }
                    if (isset($definition['min'], $definition['max']) && $definition['min'] > $definition['max']) {
                        $paramErrors[] = 'min cannot be greater than max';
                    }
                    break;

                case 'array':
                    if (isset($definition['min_items']) && (! is_int($definition['min_items']) || $definition['min_items'] < 0)) {
                        $paramErrors[] = 'min_items must be a non-negative integer';
                    }
                    if (isset($definition['max_items']) && (! is_int($definition['max_items']) || $definition['max_items'] < 1)) {
                        $paramErrors[] = 'max_items must be a positive integer';
                    }
                    break;
            }

            if (! empty($paramErrors)) {
                $errors[$name] = $paramErrors;
            }
        }

        return $errors;
    }

    /**
     * Validate template data content.
     */
    protected function validateTemplateData(array $templateData, array $parameterDefinitions = []): array
    {
        $errors = [];

        // System prompt validation
        if (isset($templateData['system_prompt'])) {
            if (! is_string($templateData['system_prompt'])) {
                $errors[] = 'System prompt must be a string';
            } else {
                $promptErrors = $this->validateContentParameters($templateData['system_prompt'], $parameterDefinitions);
                if (! empty($promptErrors)) {
                    $errors[] = 'System prompt parameter errors: ' . implode(', ', $promptErrors);
                }
            }
        }

        // Initial messages validation
        if (isset($templateData['initial_messages'])) {
            if (! is_array($templateData['initial_messages'])) {
                $errors[] = 'Initial messages must be an array';
            } else {
                foreach ($templateData['initial_messages'] as $index => $message) {
                    $messageErrors = $this->validateMessage($message, $parameterDefinitions);
                    if (! empty($messageErrors)) {
                        $errors[] = "Message {$index}: " . implode(', ', $messageErrors);
                    }
                }
            }
        }

        // Title validation
        if (isset($templateData['title'])) {
            if (! is_string($templateData['title'])) {
                $errors[] = 'Title must be a string';
            } else {
                $titleErrors = $this->validateContentParameters($templateData['title'], $parameterDefinitions);
                if (! empty($titleErrors)) {
                    $errors[] = 'Title parameter errors: ' . implode(', ', $titleErrors);
                }
            }
        }

        return $errors;
    }

    /**
     * Validate a message structure.
     */
    protected function validateMessage(array $message, array $parameterDefinitions = []): array
    {
        $errors = [];

        // Required fields
        if (! isset($message['role']) || ! is_string($message['role'])) {
            $errors[] = 'Message role is required and must be a string';
        } elseif (! in_array($message['role'], ['system', 'user', 'assistant'])) {
            $errors[] = 'Message role must be system, user, or assistant';
        }

        if (! isset($message['content']) || ! is_string($message['content'])) {
            $errors[] = 'Message content is required and must be a string';
        } else {
            $contentErrors = $this->validateContentParameters($message['content'], $parameterDefinitions);
            if (! empty($contentErrors)) {
                $errors = array_merge($errors, $contentErrors);
            }
        }

        return $errors;
    }

    /**
     * Validate parameter references in content.
     */
    protected function validateContentParameters(string $content, array $parameterDefinitions): array
    {
        $errors = [];

        // Find all parameter references
        preg_match_all('/\{\{([^}]+)\}\}/', $content, $matches);

        if (empty($matches[1])) {
            return $errors;
        }

        foreach ($matches[1] as $parameterRef) {
            $parameterRef = trim($parameterRef);

            // Skip conditional blocks and loops
            if (str_starts_with($parameterRef, '#') || str_starts_with($parameterRef, '/')) {
                continue;
            }

            // Handle dot notation
            if (str_contains($parameterRef, '.')) {
                $rootParam = explode('.', $parameterRef)[0];
                if (! isset($parameterDefinitions[$rootParam])) {
                    $errors[] = "Referenced parameter '{$rootParam}' is not defined";
                }

                continue;
            }

            // Handle function calls
            if (str_contains($parameterRef, ' ')) {
                $parts = explode(' ', $parameterRef, 2);
                $paramName = trim($parts[1]);
                if (! isset($parameterDefinitions[$paramName])) {
                    $errors[] = "Referenced parameter '{$paramName}' is not defined";
                }

                continue;
            }

            // Simple parameter reference
            if (! isset($parameterDefinitions[$parameterRef])) {
                $errors[] = "Referenced parameter '{$parameterRef}' is not defined";
            }
        }

        return $errors;
    }

    /**
     * Validate configuration settings.
     */
    protected function validateConfiguration(array $configuration): array
    {
        $errors = [];

        // Temperature validation
        if (isset($configuration['temperature'])) {
            if (! is_numeric($configuration['temperature'])) {
                $errors[] = 'Temperature must be a number';
            } elseif ($configuration['temperature'] < 0 || $configuration['temperature'] > 2) {
                $errors[] = 'Temperature must be between 0 and 2';
            }
        }

        // Max tokens validation
        if (isset($configuration['max_tokens'])) {
            if (! is_int($configuration['max_tokens']) || $configuration['max_tokens'] < 1) {
                $errors[] = 'Max tokens must be a positive integer';
            }
        }

        // Top P validation
        if (isset($configuration['top_p'])) {
            if (! is_numeric($configuration['top_p'])) {
                $errors[] = 'Top P must be a number';
            } elseif ($configuration['top_p'] < 0 || $configuration['top_p'] > 1) {
                $errors[] = 'Top P must be between 0 and 1';
            }
        }

        return $errors;
    }

    /**
     * Validate a parameter value against its definition.
     */
    protected function validateParameterValue($value, array $definition): array
    {
        $errors = [];
        $type = $definition['type'] ?? 'string';

        switch ($type) {
            case 'string':
                if (! is_string($value)) {
                    $errors[] = 'Value must be a string';
                }
                break;

            case 'integer':
                if (! is_int($value) && ! ctype_digit((string) $value)) {
                    $errors[] = 'Value must be an integer';
                }
                break;

            case 'float':
                if (! is_numeric($value)) {
                    $errors[] = 'Value must be a number';
                }
                break;

            case 'boolean':
                if (! is_bool($value)) {
                    $errors[] = 'Value must be a boolean';
                }
                break;

            case 'array':
                if (! is_array($value)) {
                    $errors[] = 'Value must be an array';
                }
                break;

            case 'enum':
                $options = $definition['options'] ?? [];
                if (! in_array($value, $options)) {
                    $errors[] = 'Value must be one of: ' . implode(', ', $options);
                }
                break;
        }

        return $errors;
    }

    /**
     * Check template compatibility with current system.
     */
    public function checkCompatibility(array $templateData): array
    {
        $issues = [];

        // Check if referenced provider exists
        if (! empty($templateData['provider_name'])) {
            // This would check against available providers
            // For now, just validate the format
            if (! is_string($templateData['provider_name'])) {
                $issues[] = 'Provider name must be a string';
            }
        }

        // Check if referenced model exists
        if (! empty($templateData['model_name'])) {
            if (! is_string($templateData['model_name'])) {
                $issues[] = 'Model name must be a string';
            }
        }

        // Check for deprecated features
        if (isset($templateData['template_data']['deprecated_field'])) {
            $issues[] = 'Template uses deprecated features that may not work in current version';
        }

        return $issues;
    }
}
