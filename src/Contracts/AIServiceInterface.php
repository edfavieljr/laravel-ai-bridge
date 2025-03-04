<?php

namespace Edfavieljr\LaravelAIBridge\Contracts;

interface AIServiceInterface
{
    /**
     * Generate text using completion/chat models.
     *
     * @param string $prompt The text prompt
     * @param array $options Additional options (model, temperature, etc)
     * @return string The generated text
     */
    public function generateText(string $prompt, array $options = []): string;
    
    /**
     * Generate text embeddings for semantic search and similarity.
     *
     * @param string|array $input The text to embed
     * @param array $options Additional options (model, etc)
     * @return array The embedding vectors
     */
    public function generateEmbeddings($input, array $options = []): array;
    
    /**
     * Analyze sentiment of the provided text.
     *
     * @param string $text The text to analyze
     * @param array $options Additional options
     * @return array The sentiment analysis results
     */
    public function analyzeSentiment(string $text, array $options = []): array;
    
    /**
     * Classify text into categories.
     *
     * @param string $text The text to classify
     * @param array $categories The possible categories
     * @param array $options Additional options
     * @return array Classification results with probabilities
     */
    public function classifyText(string $text, array $categories, array $options = []): array;
    
    /**
     * Generate an image from a text prompt.
     *
     * @param string $prompt The image description
     * @param array $options Additional options (size, quality, etc)
     * @return string The URL or base64 of the generated image
     */
    public function generateImage(string $prompt, array $options = []): string;
    
    /**
     * Extract entities from text (people, organizations, locations, etc).
     *
     * @param string $text The text to analyze
     * @param array $options Additional options
     * @return array The extracted entities
     */
    public function extractEntities(string $text, array $options = []): array;
    
    /**
     * Get provider-specific client to access advanced features.
     *
     * @return mixed The provider client
     */
    public function getClient();
}