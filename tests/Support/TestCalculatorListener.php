<?php

namespace JTD\LaravelAI\Tests\Support;

use JTD\LaravelAI\Events\FunctionCallRequested;

/**
 * Test Calculator Listener
 */
class TestCalculatorListener
{
    public function handle(FunctionCallRequested $event): void
    {
        $operation = $event->parameters['operation'] ?? 'add';
        $a = $event->parameters['a'] ?? 0;
        $b = $event->parameters['b'] ?? 0;

        $result = match($operation) {
            'add' => $a + $b,
            'subtract' => $a - $b,
            'multiply' => $a * $b,
            'divide' => $b != 0 ? $a / $b : 'Error: Division by zero',
            default => 'Error: Unknown operation',
        };

        logger()->info('Test calculation performed', [
            'operation' => $operation,
            'a' => $a,
            'b' => $b,
            'result' => $result,
        ]);
    }
}
