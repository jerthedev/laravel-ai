<?php

namespace JTD\LaravelAI\Tests\Support;

use JTD\LaravelAI\Events\FunctionCallRequested;

/**
 * Test Current Weather Listener
 */
class TestCurrentWeatherListener
{
    public function handle(FunctionCallRequested $event): void
    {
        $location = $event->parameters['location'] ?? 'Unknown';
        $unit = $event->parameters['unit'] ?? 'celsius';

        // Simulate weather API call
        $temperature = $unit === 'fahrenheit' ? rand(60, 85) : rand(15, 30);
        $conditions = ['sunny', 'cloudy', 'rainy', 'partly cloudy'][rand(0, 3)];

        logger()->info('Test weather API called', [
            'location' => $location,
            'temperature' => $temperature,
            'unit' => $unit,
            'condition' => $conditions,
        ]);
    }
}
