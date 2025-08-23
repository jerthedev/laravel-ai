<?php

namespace JTD\LaravelAI\Services;

use Illuminate\Support\Facades\Validator;

/**
 * Template Parameter Service
 *
 * Advanced parameter handling for conversation templates including validation,
 * type conversion, conditional logic, and complex substitution patterns.
 */
class TemplateParameterService
{
    /**
     * Process template content with advanced parameter substitution.
     */
    public function processContent(string $content, array $parameters, array $parameterDefinitions = []): string
    {
        // First pass: Handle conditional blocks
        $content = $this->processConditionalBlocks($content, $parameters);

        // Second pass: Handle loops
        $content = $this->processLoops($content, $parameters);

        // Third pass: Handle simple parameter substitution (includes function calls)
        $content = $this->processSimpleSubstitution($content, $parameters, $parameterDefinitions);

        return $content;
    }

    /**
     * Process conditional blocks: {{#if condition}}...{{/if}}
     */
    protected function processConditionalBlocks(string $content, array $parameters): string
    {
        // Keep processing until no more conditionals are found
        $maxIterations = 10; // Prevent infinite loops
        $iteration = 0;

        while ($iteration < $maxIterations && str_contains($content, '{{#if')) {
            $iteration++;
            $oldContent = $content;

            // Process innermost conditionals first (those without nested {{#if}} inside)
            $content = preg_replace_callback(
                '/\{\{#if\s+([^}]+)\}\}((?:(?!\{\{#if).)*?)\{\{\/if\}\}/s',
                function ($matches) use ($parameters) {
                    $condition = trim($matches[1]);
                    $blockContent = $matches[2];

                    if ($this->evaluateCondition($condition, $parameters)) {
                        return $blockContent;
                    }

                    return '';
                },
                $content
            );

            // If no changes were made, break to prevent infinite loop
            if ($content === $oldContent) {
                break;
            }
        }

        return $content;
    }

    /**
     * Process loops: {{#each items}}...{{/each}}
     */
    protected function processLoops(string $content, array $parameters): string
    {
        $pattern = '/\{\{#each\s+([^}]+)\}\}(.*?)\{\{\/each\}\}/s';

        return preg_replace_callback($pattern, function ($matches) use ($parameters) {
            $arrayName = trim($matches[1]);
            $loopContent = $matches[2];

            $items = $parameters[$arrayName] ?? [];
            if (! is_array($items)) {
                return '';
            }

            $result = '';
            foreach ($items as $index => $item) {
                $loopParameters = array_merge($parameters, [
                    'this' => $item,
                    'index' => $index,
                    'first' => $index === 0,
                    'last' => $index === count($items) - 1,
                ]);

                $result .= $this->processSimpleSubstitution($loopContent, $loopParameters);
            }

            return $result;
        }, $content);
    }

    /**
     * Process simple parameter substitution: {{parameter}}
     */
    protected function processSimpleSubstitution(string $content, array $parameters, array $parameterDefinitions = []): string
    {
        $pattern = '/\{\{([^#\/][^}]*)\}\}/';

        return preg_replace_callback($pattern, function ($matches) use ($parameters, $parameterDefinitions) {
            $parameterExpression = trim($matches[1]);

            // Handle function calls first: {{upper name}}
            if (str_contains($parameterExpression, ' ')) {
                $parts = explode(' ', $parameterExpression, 2);
                $functionName = trim($parts[0]);
                $argument = trim($parts[1]);

                // Get the raw value to process (not formatted)
                $value = $this->getRawParameterValue($argument, $parameters, $parameterDefinitions);

                return $this->callFunction($functionName, $value);
            }

            // Handle dot notation: user.name
            if (str_contains($parameterExpression, '.')) {
                return $this->getNestedValue($parameters, $parameterExpression);
            }

            // Handle simple parameter
            return $this->getParameterValue($parameterExpression, $parameters, $parameterDefinitions);
        }, $content);
    }

    /**
     * Get parameter value with default fallback.
     */
    protected function getParameterValue(string $parameterName, array $parameters, array $parameterDefinitions = []): string
    {
        $value = $this->getRawParameterValue($parameterName, $parameters, $parameterDefinitions);

        return $this->formatValue($value, $parameterDefinitions[$parameterName] ?? []);
    }

    /**
     * Get raw parameter value with default fallback (not formatted).
     */
    protected function getRawParameterValue(string $parameterName, array $parameters, array $parameterDefinitions = [])
    {
        $value = $parameters[$parameterName] ?? null;

        // Use default value if available
        if ($value === null && isset($parameterDefinitions[$parameterName]['default'])) {
            $value = $parameterDefinitions[$parameterName]['default'];
        }

        return $value;
    }

    /**
     * Evaluate a condition for conditional blocks.
     */
    protected function evaluateCondition(string $condition, array $parameters): bool
    {
        // Handle simple existence checks
        if (! str_contains($condition, ' ')) {
            // Handle dot notation
            if (str_contains($condition, '.')) {
                $value = $this->getNestedValueRaw($parameters, $condition);
            } else {
                $value = $parameters[$condition] ?? null;
            }

            return ! empty($value);
        }

        // Handle comparison operators (order matters - check >= before >)
        if (preg_match('/([a-zA-Z_][a-zA-Z0-9_.]*)\s*(>=|<=|==|!=|>|<)\s*(.+)/', $condition, $matches)) {
            $paramName = $matches[1];
            $operator = $matches[2];
            $compareValue = trim($matches[3], '"\'');

            // Handle dot notation in parameter name
            if (str_contains($paramName, '.')) {
                $paramValue = $this->getNestedValueRaw($parameters, $paramName);
            } else {
                $paramValue = $parameters[$paramName] ?? null;
            }

            return $this->compareValues($paramValue, $operator, $compareValue);
        }

        return false;
    }

    /**
     * Compare two values using an operator.
     */
    protected function compareValues($value1, string $operator, $value2): bool
    {
        // Convert string numbers to actual numbers for comparison
        if (is_numeric($value1)) {
            $value1 = is_float($value1) ? (float) $value1 : (int) $value1;
        }
        if (is_numeric($value2)) {
            $value2 = is_float($value2) ? (float) $value2 : (int) $value2;
        }

        return match ($operator) {
            '==' => $value1 == $value2,
            '!=' => $value1 != $value2,
            '>' => $value1 > $value2,
            '<' => $value1 < $value2,
            '>=' => $value1 >= $value2,
            '<=' => $value1 <= $value2,
            default => false,
        };
    }

    /**
     * Get nested value using dot notation.
     */
    protected function getNestedValue(array $data, string $path): string
    {
        $value = $this->getNestedValueRaw($data, $path);

        return $this->formatValue($value);
    }

    /**
     * Get nested value using dot notation (raw value).
     */
    protected function getNestedValueRaw(array $data, string $path)
    {
        $keys = explode('.', $path);
        $value = $data;

        foreach ($keys as $key) {
            if (is_array($value) && isset($value[$key])) {
                $value = $value[$key];
            } else {
                return;
            }
        }

        return $value;
    }

    /**
     * Format a value for output.
     */
    protected function formatValue($value, array $parameterDefinition = []): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            $separator = $parameterDefinition['array_separator'] ?? ', ';

            return implode($separator, $value);
        }

        // Apply formatting based on parameter type
        $type = $parameterDefinition['type'] ?? 'string';

        return match ($type) {
            'currency' => '$' . number_format((float) $value, 2),
            'percentage' => number_format((float) $value * 100, 1) . '%',
            'date' => $this->formatDate($value, $parameterDefinition['date_format'] ?? 'Y-m-d'),
            'number' => number_format((float) $value),
            default => (string) $value,
        };
    }

    /**
     * Format a date value.
     */
    protected function formatDate($value, string $format): string
    {
        try {
            if (is_string($value)) {
                $date = new \DateTime($value);
            } elseif ($value instanceof \DateTime) {
                $date = $value;
            } else {
                return (string) $value;
            }

            return $date->format($format);
        } catch (\Exception $e) {
            return (string) $value;
        }
    }

    /**
     * Call a template function.
     */
    protected function callFunction(string $functionName, $value): string
    {
        return match ($functionName) {
            'upper' => strtoupper((string) $value),
            'lower' => strtolower((string) $value),
            'title' => ucwords((string) $value),
            'capitalize' => ucfirst((string) $value),
            'trim' => trim((string) $value),
            'length' => (string) (is_array($value) ? count($value) : mb_strlen((string) $value)),
            'reverse' => strrev((string) $value),
            'slug' => $this->slugify((string) $value),
            'truncate' => $this->truncate((string) $value, 100),
            default => (string) $value,
        };
    }

    /**
     * Create a URL-friendly slug.
     */
    protected function slugify(string $text): string
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);

        return trim($text, '-');
    }

    /**
     * Truncate text to a specified length.
     */
    protected function truncate(string $text, int $length): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length - 3) . '...';
    }

    /**
     * Validate parameter values against definitions.
     */
    public function validateParameters(array $values, array $definitions): array
    {
        $rules = [];
        $messages = [];

        foreach ($definitions as $name => $definition) {
            $rule = [];

            // Required validation
            if ($definition['required'] ?? false) {
                $rule[] = 'required';
            } else {
                $rule[] = 'nullable';
            }

            // Type validation
            $type = $definition['type'] ?? 'string';
            switch ($type) {
                case 'string':
                    $rule[] = 'string';
                    if (isset($definition['max_length'])) {
                        $rule[] = 'max:' . $definition['max_length'];
                    }
                    break;
                case 'integer':
                    $rule[] = 'integer';
                    if (isset($definition['min'])) {
                        $rule[] = 'min:' . $definition['min'];
                    }
                    if (isset($definition['max'])) {
                        $rule[] = 'max:' . $definition['max'];
                    }
                    break;
                case 'float':
                    $rule[] = 'numeric';
                    break;
                case 'boolean':
                    $rule[] = 'boolean';
                    break;
                case 'array':
                    $rule[] = 'array';
                    if (isset($definition['min_items'])) {
                        $rule[] = 'min:' . $definition['min_items'];
                    }
                    if (isset($definition['max_items'])) {
                        $rule[] = 'max:' . $definition['max_items'];
                    }
                    break;
                case 'enum':
                    if (isset($definition['options'])) {
                        $rule[] = 'in:' . implode(',', $definition['options']);
                    }
                    break;
            }

            $rules[$name] = implode('|', $rule);

            // Custom messages
            if (isset($definition['description'])) {
                $messages[$name . '.required'] = "The {$definition['description']} field is required.";
            }
        }

        $validator = Validator::make($values, $rules, $messages);

        return $validator->errors()->toArray();
    }

    /**
     * Get parameter schema for frontend forms.
     */
    public function getParameterSchema(array $definitions): array
    {
        $schema = [];

        foreach ($definitions as $name => $definition) {
            $schema[$name] = [
                'name' => $name,
                'type' => $definition['type'] ?? 'string',
                'required' => $definition['required'] ?? false,
                'description' => $definition['description'] ?? '',
                'default' => $definition['default'] ?? null,
                'placeholder' => $definition['placeholder'] ?? '',
                'options' => $definition['options'] ?? null,
                'validation' => $this->getValidationRules($definition),
            ];
        }

        return $schema;
    }

    /**
     * Get validation rules for a parameter definition.
     */
    protected function getValidationRules(array $definition): array
    {
        $rules = [];

        if ($definition['required'] ?? false) {
            $rules['required'] = true;
        }

        $type = $definition['type'] ?? 'string';
        $rules['type'] = $type;

        switch ($type) {
            case 'string':
                if (isset($definition['max_length'])) {
                    $rules['maxLength'] = $definition['max_length'];
                }
                if (isset($definition['min_length'])) {
                    $rules['minLength'] = $definition['min_length'];
                }
                break;
            case 'integer':
            case 'float':
                if (isset($definition['min'])) {
                    $rules['min'] = $definition['min'];
                }
                if (isset($definition['max'])) {
                    $rules['max'] = $definition['max'];
                }
                break;
            case 'array':
                if (isset($definition['min_items'])) {
                    $rules['minItems'] = $definition['min_items'];
                }
                if (isset($definition['max_items'])) {
                    $rules['maxItems'] = $definition['max_items'];
                }
                break;
        }

        return $rules;
    }
}
