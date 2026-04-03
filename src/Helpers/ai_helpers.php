<?php

declare(strict_types=1);

use Edfavieljr\LaravelAIBridge\Facades\AI;

if (! function_exists('ai')) {
    /**
     * Get the AI service instance or generate text.
     *
     * @deprecated Use AI facade directly: AI::generateText(). Will be removed in v2.0.
     */
    function ai(?string $prompt = null, array $options = [])
    {
        if (is_null($prompt)) {
            return app(\Edfavieljr\LaravelAIBridge\Services\AIService::class);
        }

        return AI::generateText($prompt, $options);
    }
}

if (! function_exists('ai_embed')) {
    /**
     * @deprecated Use AI::generateEmbeddings(). Will be removed in v2.0.
     */
    function ai_embed($input, array $options = [])
    {
        return AI::generateEmbeddings($input, $options);
    }
}

if (! function_exists('ai_sentiment')) {
    /**
     * @deprecated Use AI::analyzeSentiment(). Will be removed in v2.0.
     */
    function ai_sentiment(string $text, array $options = [])
    {
        return AI::analyzeSentiment($text, $options);
    }
}

if (! function_exists('ai_classify')) {
    /**
     * @deprecated Use AI::classifyText(). Will be removed in v2.0.
     */
    function ai_classify(string $text, array $categories, array $options = [])
    {
        return AI::classifyText($text, $categories, $options);
    }
}

if (! function_exists('ai_image')) {
    /**
     * @deprecated Use AI::generateImage(). Will be removed in v2.0.
     */
    function ai_image(string $prompt, array $options = [])
    {
        return AI::generateImage($prompt, $options);
    }
}

if (! function_exists('ai_entities')) {
    /**
     * @deprecated Use AI::extractEntities(). Will be removed in v2.0.
     */
    function ai_entities(string $text, array $options = [])
    {
        return AI::extractEntities($text, $options);
    }
}

if (! function_exists('ai_provider')) {
    /**
     * @deprecated Use AI::provider(). Will be removed in v2.0.
     */
    function ai_provider(string $provider)
    {
        return AI::provider($provider);
    }
}

if (! function_exists('ai_model')) {
    /**
     * @deprecated Use AI::model(). Will be removed in v2.0.
     */
    function ai_model(string $model)
    {
        return AI::model($model);
    }
}
