<?php

namespace JTD\LaravelAI\Tests\Support;

use JTD\LaravelAI\Events\FunctionCallRequested;

/**
 * Test Calculate Tip Listener
 */
class TestCalculateTipListener
{
    public function handle(FunctionCallRequested $event): void
    {
        $amount = $event->parameters['amount'] ?? 0;
        $percentage = $event->parameters['percentage'] ?? 15;
        
        $tip = ($amount * $percentage) / 100;
        $total = $amount + $tip;
        
        logger()->info('Test tip calculation performed', [
            'original_amount' => $amount,
            'tip_percentage' => $percentage,
            'tip_amount' => round($tip, 2),
            'total_amount' => round($total, 2),
        ]);
    }
}
