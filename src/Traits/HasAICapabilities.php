<?php

namespace Edfavieljr\LaravelAIBridge\Traits;

use Edfavieljr\LaravelAIBridge\Facades\AI;

trait HasAICapabilities
{
    /**
     * Generate a text completion based on a model attribute.
     *
     * @param string $attribute The attribute to use as prompt
     * @param string|null $promptTemplate Optional template to wrap the attribute value
     * @param array $options Additional AI options
     * @return string
     */
    public function completeText(string $attribute, ?string $promptTemplate = null, array $options = []): string
    {
        $value = $this->{$attribute};
        
        if (!$value) {
            return '';
        }
        
        $prompt = $promptTemplate ? sprintf($promptTemplate, $value) : $value;
        
        return AI::generateText($prompt, $options);
    }
    
    /**
     * Generate an embedding for a model attribute.
     *
     * @param string $attribute The attribute to embed
     * @param array $options Additional AI options
     * @return array
     */
    public function embedAttribute(string $attribute, array $options = []): array
    {
        $value = $this->{$attribute};
        
        if (!$value) {
            return [];
        }
        
        return AI::generateEmbeddings($value, $options);
    }
    
    /**
     * Analyze sentiment of a model attribute.
     *
     * @param string $attribute The attribute to analyze
     * @param array $options Additional AI options
     * @return array
     */
    public function analyzeSentimentOf(string $attribute, array $options = []): array
    {
        $value = $this->{$attribute};
        
        if (!$value) {
            return [
                'score' => 0,
                'category' => 'neutral',
            ];
        }
        
        return AI::analyzeSentiment($value, $options);
    }
    
    /**
     * Classify a model attribute into categories.
     *
     * @param string $attribute The attribute to classify
     * @param array $categories The possible categories
     * @param array $options Additional AI options
     * @return array
     */
    public function classifyAttribute(string $attribute, array $categories, array $options = []): array
    {
        $value = $this->{$attribute};
        
        if (!$value) {
            return [
                'category' => $categories[0] ?? 'unknown',
                'confidence' => 0,
            ];
        }
        
        return AI::classifyText($value, $categories, $options);
    }
    
    /**
     * Extract entities from a model attribute.
     *
     * @param string $attribute The attribute to analyze
     * @param array $options Additional AI options
     * @return array
     */
    public function extractEntitiesFrom(string $attribute, array $options = []): array
    {
        $value = $this->{$attribute};
        
        if (!$value) {
            return [];
        }
        
        return AI::extractEntities($value, $options);
    }
    
    /**
     * Generate an image based on a model attribute.
     *
     * @param string $attribute The attribute to use as prompt
     * @param array $options Additional AI options
     * @return string The URL or base64 of the generated image
     */
    public function generateImageFrom(string $attribute, array $options = []): string
    {
        $value = $this->{$attribute};
        
        if (!$value) {
            return '';
        }
        
        return AI::generateImage($value, $options);
    }
    
    /**
     * Summarize a model attribute.
     *
     * @param string $attribute The attribute to summarize
     * @param int $maxLength Maximum length of the summary
     * @param array $options Additional AI options
     * @return string
     */
    public function summarizeAttribute(string $attribute, int $maxLength = 100, array $options = []): string
    {
        $value = $this->{$attribute};
        
        if (!$value) {
            return '';
        }
        
        $prompt = "Summarize the following text in {$maxLength} characters or less: {$value}";
        
        return AI::generateText($prompt, $options);
    }
    
    /**
     * Translate a model attribute to another language.
     *
     * @param string $attribute The attribute to translate
     * @param string $targetLanguage The target language
     * @param array $options Additional AI options
     * @return string
     */
    public function translateAttribute(string $attribute, string $targetLanguage, array $options = []): string
    {
        $value = $this->{$attribute};
        
        if (!$value) {
            return '';
        }
        
        $prompt = "Translate the following text to {$targetLanguage}: {$value}";
        
        return AI::generateText($prompt, $options);
    }
}