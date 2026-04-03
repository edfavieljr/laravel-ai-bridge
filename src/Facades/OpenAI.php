<?php

declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Facades;

use Illuminate\Support\Facades\Facade;
use Edfavieljr\LaravelAIBridge\Services\OpenAIService;

/**
 * @method static string generateText(string $prompt, array $options = [])
 * @method static array generateEmbeddings(string|array $input, array $options = [])
 * @method static array analyzeSentiment(string $text, array $options = [])
 * @method static array classifyText(string $text, array $categories, array $options = [])
 * @method static string generateImage(string $prompt, array $options = [])
 * @method static array extractEntities(string $text, array $options = [])
 * @method static mixed getClient()
 *
 * @see \Edfavieljr\LaravelAIBridge\Services\OpenAIService
 *
 * @deprecated Use AI::provider('openai') instead. Will be removed in v2.0.
 */
class OpenAI extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return OpenAIService::class;
    }
}