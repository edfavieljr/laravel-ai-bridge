<?php

namespace Edfavieljr\LaravelAIBridge\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Edfavieljr\LaravelAIBridge\Contracts\AIServiceInterface;
use Edfavieljr\LaravelAIBridge\Exceptions\AIException;

class OpenAIService implements AIServiceInterface
{
    /**
     * The OpenAI API key.
     *
     * @var string
     */
    protected string $apiKey;
    
    /**
     * The OpenAI organization ID.
     *
     * @var string|null
     */
    protected ?string $organization;
    
    /**
     * Additional options.
     *
     * @var array
     */
    protected array $options;
    
    /**
     * The HTTP client instance.
     *
     * @var \Illuminate\Http\Client\PendingRequest
     */
    protected $client;
    
    /**
     * Create a new OpenAI service instance.
     *
     * @param string $apiKey
     * @param string|null $organization
     * @param array $options
     */
    public function __construct(string $apiKey, ?string $organization = null, array $options = [])
    {
        $this->apiKey = $apiKey;
        $this->organization = $organization;
        $this->options = $options;
        
        $this->initializeHttpClient();
    }
    
    /**
     * Initialize the HTTP client.
     *
     * @return void
     */
    protected function initializeHttpClient(): void
    {
        $this->client = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ]);
        
        if ($this->organization) {
            $this->client = $this->client->withHeaders([
                'OpenAI-Organization' => $this->organization,
            ]);
        }
        
        $timeout = $this->options['timeout'] ?? 30;
        $this->client = $this->client->timeout($timeout);
    }
    
    /**
     * Generate text using completion/chat models.
     *
     * @param string $prompt The text prompt
     * @param array $options Additional options (model, temperature, etc)
     * @return string The generated text
     * @throws \Edfavieljr\LaravelAIBridge\Exceptions\AIException
     */
    public function generateText(string $prompt, array $options = []): string
    {
        $cacheKey = $this->getCacheKey('generateText', $prompt, $options);
        
        if ($this->shouldUseCache() && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        
        $model = $options['model'] ?? $this->options['default_model'] ?? 'gpt-4';
        $temperature = $options['temperature'] ?? 0.7;
        $maxTokens = $options['max_tokens'] ?? 500;
        
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ];
        
        try {
            $baseUri = $this->options['base_uri'] ?? 'https://api.openai.com/v1';
            $response = $this->client->post("{$baseUri}/chat/completions", $payload);
            
            if ($this->shouldLog()) {
                Log::channel(config('ai.logging.channel', 'stack'))
                    ->info('OpenAI API Request', [
                        'endpoint' => 'chat/completions',
                        'prompt' => $prompt,
                        'options' => $options,
                    ]);
            }
            
            if ($response->failed()) {
                throw new AIException(
                    'OpenAI API error: ' . ($response->json('error.message') ?? $response->reason()),
                    $response->status()
                );
            }
            
            $result = $response->json();
            $generatedText = $result['choices'][0]['message']['content'] ?? '';
            
            if ($this->shouldUseCache()) {
                Cache::put($cacheKey, $generatedText, now()->addMinutes(config('ai.cache.ttl', 60)));
            }
            
            if ($this->shouldStoreInDatabase()) {
                // Implementar almacenamiento en base de datos
            }
            
            return $generatedText;
        } catch (Exception $e) {
            if ($this->shouldLog()) {
                Log::channel(config('ai.logging.channel', 'stack'))
                    ->error('OpenAI API Error', [
                        'message' => $e->getMessage(),
                        'prompt' => $prompt,
                    ]);
            }
            
            throw new AIException('OpenAI API error: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Generate text embeddings for semantic search and similarity.
     *
     * @param string|array $input The text to embed
     * @param array $options Additional options (model, etc)
     * @return array The embedding vectors
     * @throws \Edfavieljr\LaravelAIBridge\Exceptions\AIException
     */
    public function generateEmbeddings($input, array $options = []): array
    {
        $model = $options['model'] ?? $this->options['default_embedding_model'] ?? 'text-embedding-3-large';
        
        $input = is_array($input) ? $input : [$input];
        
        try {
            $baseUri = $this->options['base_uri'] ?? 'https://api.openai.com/v1';
            $response = $this->client->post("{$baseUri}/embeddings", [
                'model' => $model,
                'input' => $input,
            ]);
            
            if ($response->failed()) {
                throw new AIException(
                    'OpenAI API error: ' . ($response->json('error.message') ?? $response->reason()),
                    $response->status()
                );
            }
            
            $result = $response->json();
            return array_map(function ($item) {
                return $item['embedding'];
            }, $result['data']);
        } catch (Exception $e) {
            throw new AIException('OpenAI embeddings error: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Analyze sentiment of the provided text.
     *
     * @param string $text The text to analyze
     * @param array $options Additional options
     * @return array The sentiment analysis results
     */
    public function analyzeSentiment(string $text, array $options = []): array
    {
        $prompt = "Analyze the sentiment of the following text and provide a score from -1 (very negative) to 1 (very positive), and categorize as 'positive', 'negative', or 'neutral'. Return JSON format with keys 'score' and 'category'. Text: \"{$text}\"";
        
        $response = $this->generateText($prompt, $options);
        
        try {
            // Attempt to parse JSON response
            $json = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                // If not valid JSON, try to extract information with regex
                preg_match('/score"?\s*:\s*(-?\d+\.?\d*)/', $response, $scoreMatches);
                preg_match('/category"?\s*:\s*"(positive|negative|neutral)"/', $response, $categoryMatches);
                
                return [
                    'score' => isset($scoreMatches[1]) ? (float) $scoreMatches[1] : 0,
                    'category' => $categoryMatches[1] ?? 'neutral',
                    'raw_response' => $response,
                ];
            }
            
            return $json;
        } catch (Exception $e) {
            return [
                'score' => 0,
                'category' => 'neutral',
                'error' => $e->getMessage(),
                'raw_response' => $response,
            ];
        }
    }
    
    /**
     * Classify text into categories.
     *
     * @param string $text The text to classify
     * @param array $categories The possible categories
     * @param array $options Additional options
     * @return array Classification results with probabilities
     */
    public function classifyText(string $text, array $categories, array $options = []): array
    {
        $categoriesList = implode(', ', $categories);
        $prompt = "Classify the following text into one of these categories: {$categoriesList}. Return JSON format with keys 'category' and 'confidence' (a number between 0 and 1). Text: \"{$text}\"";
        
        $response = $this->generateText($prompt, $options);
        
        try {
            $result = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Handle non-JSON response
                preg_match('/category"?\s*:\s*"([^"]+)"/', $response, $categoryMatches);
                preg_match('/confidence"?\s*:\s*(\d+\.?\d*)/', $response, $confidenceMatches);
                
                $category = $categoryMatches[1] ?? $categories[0];
                $confidence = isset($confidenceMatches[1]) ? (float) $confidenceMatches[1] : 0.5;
                
                return [
                    'category' => $category,
                    'confidence' => $confidence,
                    'raw_response' => $response,
                ];
            }
            
            return $result;
        } catch (Exception $e) {
            return [
                'category' => $categories[0],
                'confidence' => 0,
                'error' => $e->getMessage(),
                'raw_response' => $response,
            ];
        }
    }
    
    /**
     * Generate an image from a text prompt.
     *
     * @param string $prompt The image description
     * @param array $options Additional options (size, quality, etc)
     * @return string The URL or base64 of the generated image
     * @throws \Edfavieljr\LaravelAIBridge\Exceptions\AIException
     */
    public function generateImage(string $prompt, array $options = []): string
    {
        $model = $options['model'] ?? $this->options['default_image_model'] ?? 'dall-e-3';
        $size = $options['size'] ?? '1024x1024';
        $quality = $options['quality'] ?? 'standard';
        $responseFormat = $options['response_format'] ?? 'url';
        
        try {
            $baseUri = $this->options['base_uri'] ?? 'https://api.openai.com/v1';
            $response = $this->client->post("{$baseUri}/images/generations", [
                'model' => $model,
                'prompt' => $prompt,
                'n' => 1,
                'size' => $size,
                'quality' => $quality,
                'response_format' => $responseFormat,
            ]);
            
            if ($response->failed()) {
                throw new AIException(
                    'OpenAI API error: ' . ($response->json('error.message') ?? $response->reason()),
                    $response->status()
                );
            }
            
            $result = $response->json();
            return $result['data'][0][$responseFormat];
        } catch (Exception $e) {
            throw new AIException('OpenAI image generation error: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Extract entities from text (people, organizations, locations, etc).
     *
     * @param string $text The text to analyze
     * @param array $options Additional options
     * @return array The extracted entities
     */
    public function extractEntities(string $text, array $options = []): array
    {
        $prompt = "Extract all named entities (people, organizations, locations, dates, etc.) from the following text. For each entity, identify its type. Return as a JSON array of objects with 'entity', 'type', and 'count' properties. Text: \"{$text}\"";
        
        $response = $this->generateText($prompt, $options);
        
        try {
            return json_decode($response, true) ?: [];
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Get provider-specific client to access advanced features.
     *
     * @return mixed The provider client
     */
    public function getClient()
    {
        return $this->client;
    }
    
    /**
     * Get a cache key for the given method and parameters.
     *
     * @param string $method
     * @param mixed $input
     * @param array $options
     * @return string
     */
    protected function getCacheKey(string $method, $input, array $options): string
    {
        $inputHash = is_string($input) ? md5($input) : md5(json_encode($input));
        $optionsHash = md5(json_encode($options));
        
        return "ai:{$method}:{$inputHash}:{$optionsHash}";
    }
    
    /**
     * Determine if caching should be used.
     *
     * @return bool
     */
    protected function shouldUseCache(): bool
    {
        return config('ai.cache.enabled', true);
    }
    
    /**
     * Determine if logging should be used.
     *
     * @return bool
     */
    protected function shouldLog(): bool
    {
        return config('ai.logging.enabled', true);
    }
    
    /**
     * Determine if database storage should be used.
     *
     * @return bool
     */
    protected function shouldStoreInDatabase(): bool
    {
        return config('ai.storage.enabled', false);
    }
}