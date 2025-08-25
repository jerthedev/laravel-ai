<?php

namespace JTD\LaravelAI\Tests\Support;

use JTD\LaravelAI\Events\FunctionCallRequested;

/**
 * Test Weather Service Listener
 */
class TestWeatherServiceListener
{
    public function handle(FunctionCallRequested $event): void
    {
        $location = $event->parameters['location'] ?? 'Unknown';
        
        logger()->info('Test weather service called', [
            'location' => $location,
            'temperature' => rand(15, 30) . '°C',
            'condition' => 'Sunny',
        ]);
    }
}
