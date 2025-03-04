<?php

namespace YourVendor\LaravelAIBridge\Facades;

use Illuminate\Support\Facades\Facade;
use YourVendor\LaravelAIBridge\Services\HuggingFaceService;

/**
 * @method static string generateText(string $prompt, array $options = [])
 * @method static array generateEmbeddings($input, array $options = [])
 * @method static array analyzeSentiment(string $text, array $options = [])
 * @method static array classifyText(string $text, array $categories, array $options = [])
 * @method static array extractEntities(string $text, array $options = [])
 * @method static mixed getClient()
 * @method static mixed model(string $model)
 * 
 * @see \YourVendor\LaravelAIBridge\Services\HuggingFaceService
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