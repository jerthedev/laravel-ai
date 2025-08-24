<?php

namespace JTD\LaravelAI\Facades;

use Illuminate\Support\Facades\Facade;
use JTD\LaravelAI\Contracts\AIProviderInterface;
use JTD\LaravelAI\Contracts\ConversationBuilderInterface;
use JTD\LaravelAI\Models\AIMessage;
use JTD\LaravelAI\Models\AIResponse;

/**
 * AI Facade
 *
 * Provides static access to the AI manager and conversation building functionality.
 *
 * @method static ConversationBuilderInterface conversation(string $title = null)
 * @method static AIResponse sendMessage(AIMessage $message, array $options = [])
 * @method static \Generator sendStreamingMessage(AIMessage $message, array $options = [])
 * @method static AIProviderInterface provider(string $name)
 * @method static AIProviderInterface driver(string $name = null)
 * @method static array getProviders()
 * @method static array getModels(string $provider = null)
 * @method static array calculateCost(string $message, string $provider = null, string $model = null)
 * @method static bool validateProvider(string $provider)
 * @method static array getProviderHealth(string $provider = null)
 * @method static void extend(string $name, \Closure $callback)
 * @method static array getUsageStats(string $period = 'day')
 * @method static array getAnalytics(array $filters = [])
 *
 * @see \JTD\LaravelAI\Services\AIManager
 */
class AI extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'laravel-ai';
    }
}
