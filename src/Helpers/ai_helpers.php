<?php

use Edfavieljr\LaravelAIBridge\Facades\AI;

if (! function_exists('ai')) {
    /**
     * Get the AI service instance or generate text with the AI service.
     *
     * @param string|null $prompt The text prompt
     * @param array $options Additional options
     * @return mixed
     */
    function ai(?string $prompt = null, array $options = [])
    {
        if (is_null($prompt)) {
            return app('Edfavieljr\LaravelAIBridge\Services\AIService');
        }

        return AI::generateText($prompt, $options);
    }
}

if (! function_exists('ai_embed')) {
    /**
     * Generate embeddings for the given text.
     *
     * @param string|array $input
     * @param array $options
     * @return array
     */
    function ai_embed($input, array $options = [])
    {
        return AI::generateEmbeddings($input, $options);
    }
}

if (! function_exists('ai_sentiment')) {
    /**
     * Analyze sentiment of the given text.
     *
     * @param string $text
     * @param array $options
     * @return array
     */
    function ai_sentiment(string $text, array $options = [])
    {
        return AI::analyzeSentiment($text, $options);
    }
}

if (! function_exists('ai_classify')) {
    /**
     * Classify the given text into categories.
     *
     * @param string $text
     * @param array $categories
     * @param array $options
     * @return array
     */
    function ai_classify(string $text, array $categories, array $options = [])
    {
        return AI::classifyText($text, $categories, $options);
    }
}

if (! function_exists('ai_image')) {
    /**
     * Generate an image from the given prompt.
     *
     * @param string $prompt
     * @param array $options
     * @return string
     */
    function ai_image(string $prompt, array $options = [])
    {
        return AI::generateImage($prompt, $options);
    }
}

if (! function_exists('ai_entities')) {
    /**
     * Extract entities from the given text.
     *
     * @param string $text
     * @param array $options
     * @return array
     */
    function ai_entities(string $text, array $options = [])
    {
        return AI::extractEntities($text, $options);
    }
}

if (! function_exists('ai_provider')) {
    /**
     * Get an AI service for a specific provider.
     *
     * @param string $provider
     * @return mixed
     */
    function ai_provider(string $provider)
    {
        return AI::provider($provider);
    }
}

if (! function_exists('ai_model')) {
    /**
     * Set the model to use for AI operations.
     *
     * @param string $model
     * @return mixed
     */
    function ai_model(string $model)
    {
        return AI::model($model);
    }
}