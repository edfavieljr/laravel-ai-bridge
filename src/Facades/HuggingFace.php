<?php

declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Facades;

use Illuminate\Support\Facades\Facade;
use Edfavieljr\LaravelAIBridge\Services\HuggingFaceService;

/**
 * @method static string generateText(string $prompt, array $options = [])
 * @method static array generateEmbeddings($input, array $options = [])
 * @method static array analyzeSentiment(string $text, array $options = [])
 * @method static array classifyText(string $text, array $categories, array $options = [])
 * @method static array extractEntities(string $text, array $options = [])
 * @method static mixed getClient()
 * @method static mixed model(string $model)
 *
 * @see \Edfavieljr\LaravelAIBridge\Services\HuggingFaceService
 *
 * @deprecated Use AI::provider('huggingface') instead. Will be removed in v2.0.
 */
class HuggingFace extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return HuggingFaceService::class;
    }
}