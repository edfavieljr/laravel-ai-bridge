<?php

declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Facades;

use Illuminate\Support\Facades\Facade;
use Edfavieljr\LaravelAIBridge\Services\GeminiService;

/**
 * @method static string generateText(string $prompt, array $options = [])
 * @method static array generateEmbeddings($input, array $options = [])
 * @method static array analyzeSentiment(string $text, array $options = [])
 * @method static array classifyText(string $text, array $categories, array $options = [])
 * @method static array extractEntities(string $text, array $options = [])
 * @method static mixed getClient()
 * @method static mixed model(string $model)
 *
 * @see \Edfavieljr\LaravelAIBridge\Services\GeminiService
 *
 * @deprecated Use AI::provider('gemini') instead. Will be removed in v2.0.
 */
class Gemini extends Facade
{
    protected static function getFacadeAccessor()
    {
        return GeminiService::class;
    }
}
