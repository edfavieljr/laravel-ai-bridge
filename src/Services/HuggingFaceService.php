<?php

namespace YourVendor\LaravelAIBridge\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use YourVendor\LaravelAIBridge\Contracts\AIServiceInterface;
use YourVendor\LaravelAIBridge\Exceptions\AIException;
use YourVendor\LaravelAIBridge\Models\AICompletion;

class HuggingFaceService implements AIServiceInterface
{
    /**
     * The HuggingFace API key.
     *
     * @var string
     */
    protected string $apiKey;
    
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
     * The current model.
     *
     * @var string|null
     */
    protected ?string $currentModel = null;
    
    /**
     * Create a new HuggingFace service instance.
     *
     * @param string $apiKey
     * @param array $options
     */
    public function __construct(string $apiKey, array $options = [])
    {
        $this->apiKey = $apiKey;
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
        
        $timeout = $this->options['timeout'] ?? 30;
        $this->client = $this->client->timeout($timeout);
    }
    
    /**
     * Set the model to use.
     *
     * @param string $model
     * @return $this
     */
    public function model(string $model): self
    {
        $this->currentModel = $model;
        return $this;
    }
    
    /**
     * Generate text using HuggingFace models.
     *
     * @param string $prompt The text prompt
     * @param array $options Additional options (model, temperature, etc)
     * @return string The generated text
     * @throws \YourVendor\LaravelAIBridge\Exceptions\AIException
     */
    public function generateText(string $prompt, array $options = []): string
    {
        $startTime = microtime(true);
        $cacheKey = $this->getCacheKey('generateText', $prompt, $options);
        
        if ($this->shouldUseCache() && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        
        $model = $this->currentModel ?? $options['model'] ?? $this->options['default_model'] ?? 'gpt2';
        $parameters = [
            'temperature' => $options['temperature'] ?? 0.7,
            'max_length' => $options['max_tokens'] ?? 100,
            'return_full_text' => $options['return_full_text'] ?? false,
        ];
        
        // Add any additional parameters specified in options
        if (isset($options['top_k'])) {
            $parameters['top_k'] = $options['top_k'];
        }
        
        if (isset($options['top_p'])) {
            $parameters['top_p'] = $options['top_p'];
        }
        
        if (isset($options['repetition_penalty'])) {
            $parameters['repetition_penalty'] = $options['repetition_penalty'];
        }
        
        try {
            $baseUri = $this->options['base_uri'] ?? 'https://api-inference.huggingface.co/models';
            $endpoint = "{$baseUri}/{$model}";
            
            $payload = [
                'inputs' => $prompt,
                'parameters' => $parameters,
                'options' => [
                    'wait_for_model' => $options['wait_for_model'] ?? true,
                ],
            ];
            
            if ($this->shouldLog()) {
                Log::channel(config('ai.logging.channel', 'stack'))
                    ->info('HuggingFace API Request', [
                        'endpoint' => $endpoint,
                        'model' => $model,
                        'prompt' => $prompt,
                        'options' => $options,
                    ]);
            }
            
            $response = $this->client->post($endpoint, $payload);
            
            if ($response->failed()) {
                throw new AIException(
                    'HuggingFace API error: ' . ($response->json('error') ?? $response->reason()),
                    $response->status()
                );
            }
            
            $result = $response->json();
            
            // HuggingFace can return different response formats depending on the model
            $generatedText = '';
            
            // Handle array of generated sequences
            if (is_array($result) && isset($result[0])) {
                if (isset($result[0]['generated_text'])) {
                    // Format for text generation models
                    $generatedText = $result[0]['generated_text'];
                } else {
                    // Some other format, like a raw array of sequences
                    $generatedText = is_string($result[0]) ? $result[0] : json_encode($result[0]);
                }
            } else if (isset($result['generated_text'])) {
                // Direct generated_text field
                $generatedText = $result['generated_text'];
            } else {
                // Fallback to raw response
                $generatedText = is_string($result) ? $result : json_encode($result);
            }
            
            // Remove the original prompt if full text is not requested
            if (!($options['return_full_text'] ?? false) && strpos($generatedText, $prompt) === 0) {
                $generatedText = substr($generatedText, strlen($prompt));
            }
            
            $executionTime = microtime(true) - $startTime;
            
            if ($this->shouldUseCache()) {
                Cache::put($cacheKey, $generatedText, now()->addMinutes(config('ai.cache.ttl', 60)));
            }
            
            if ($this->shouldStoreInDatabase()) {
                AICompletion::createSuccess(
                    'huggingface',
                    $model,
                    $prompt,
                    $generatedText,
                    [
                        'request_data' => $payload,
                        'response_data' => $result,
                        'execution_time' => $executionTime,
                        'user_id' => auth()->id(),
                    ]
                );
            }
            
            return $generatedText;
        } catch (Exception $e) {
            $executionTime = microtime(true) - $startTime;
            
            if ($this->shouldLog()) {
                Log::channel(config('ai.logging.channel', 'stack'))
                    ->error('HuggingFace API Error', [
                        'message' => $e->getMessage(),
                        'prompt' => $prompt,
                        'model' => $model,
                    ]);
            }
            
            if ($this->shouldStoreInDatabase()) {
                AICompletion::createError(
                    'huggingface',
                    $model,
                    $prompt,
                    $e->getMessage(),
                    [
                        'request_data' => $payload ?? null,
                        'execution_time' => $executionTime,
                        'user_id' => auth()->id(),
                    ]
                );
            }
            
            throw new AIException(
                'HuggingFace API error: ' . $e->getMessage(), 
                0, 
                $e,
                'api_error',
                'huggingface',
                $model
            );
        }
    }
    
    /**
     * Generate text embeddings for semantic search and similarity.
     *
     * @param string|array $input The text to embed
     * @param array $options Additional options (model, etc)
     * @return array The embedding vectors
     * @throws \YourVendor\LaravelAIBridge\Exceptions\AIException
     */
    public function generateEmbeddings($input, array $options = []): array
    {
        $startTime = microtime(true);
        
        $model = $this->currentModel ?? $options['model'] ?? $this->options['default_embedding_model'] ?? 'sentence-transformers/all-mpnet-base-v2';
        
        // If input is a string, wrap it in an array
        $inputs = is_array($input) ? $input : [$input];
        
        try {
            $baseUri = $this->options['base_uri'] ?? 'https://api-inference.huggingface.co/models';
            $endpoint = "{$baseUri}/{$model}";
            
            $payload = [
                'inputs' => $inputs,
                'options' => [
                    'wait_for_model' => $options['wait_for_model'] ?? true,
                    'use_cache' => $options['use_cache'] ?? true,
                ],
            ];
            
            if ($this->shouldLog()) {
                Log::channel(config('ai.logging.channel', 'stack'))
                    ->info('HuggingFace Embeddings API Request', [
                        'endpoint' => $endpoint,
                        'model' => $model,
                        'input_count' => count($inputs),
                    ]);
            }
            
            $response = $this->client->post($endpoint, $payload);
            
            if ($response->failed()) {
                throw new AIException(
                    'HuggingFace API error: ' . ($response->json('error') ?? $response->reason()),
                    $response->status()
                );
            }
            
            $result = $response->json();
            
            $executionTime = microtime(true) - $startTime;
            
            if ($this->shouldStoreInDatabase() && !is_array($input)) {
                AICompletion::createSuccess(
                    'huggingface',
                    $model,
                    is_string($input) ? $input : json_encode($input),
                    'Embedding generated',
                    [
                        'request_data' => $payload,
                        'execution_time' => $executionTime,
                        'user_id' => auth()->id(),
                        'metadata' => [
                            'embedding_dimensions' => is_array($result) && isset($result[0]) ? count($result[0]) : null,
                        ],
                    ]
                );
            }
            
            return $result;
        } catch (Exception $e) {
            $executionTime = microtime(true) - $startTime;
            
            if ($this->shouldLog()) {
                Log::channel(config('ai.logging.channel', 'stack'))
                    ->error('HuggingFace Embeddings API Error', [
                        'message' => $e->getMessage(),
                        'model' => $model,
                    ]);
            }
            
            if ($this->shouldStoreInDatabase() && !is_array($input)) {
                AICompletion::createError(
                    'huggingface',
                    $model,
                    is_string($input) ? $input : json_encode($input),
                    $e->getMessage(),
                    [
                        'request_data' => $payload ?? null,
                        'execution_time' => $executionTime,
                        'user_id' => auth()->id(),
                    ]
                );
            }
            
            throw new AIException(
                'HuggingFace embeddings error: ' . $e->getMessage(), 
                0, 
                $e,
                'api_error',
                'huggingface',
                $model
            );
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
        $startTime = microtime(true);
        
        $model = $this->currentModel ?? $options['model'] ?? 'distilbert-base-uncased-finetuned-sst-2-english';
        
        try {
            $baseUri = $this->options['base_uri'] ?? 'https://api-inference.huggingface.co/models';
            $endpoint = "{$baseUri}/{$model}";
            
            $payload = [
                'inputs' => $text,
            ];
            
            $response = $this->client->post($endpoint, $payload);
            
            if ($response->failed()) {
                // Try the zero-shot approach with larger language model
                return $this->analyzeSentimentWithLLM($text, $options);
            }
            
            $result = $response->json();
            
            // Typical sentiment analysis models return an array of classifications with scores
            if (is_array($result) && isset($result[0]) && is_array($result[0])) {
                // Format the result in a consistent way
                $sentiments = [];
                $highestScore = 0;
                $category = 'neutral';
                $score = 0;
                
                foreach ($result[0] as $sentiment) {
                    $label = strtolower($sentiment['label']);
                    $sentiments[$label] = $sentiment['score'];
                    
                    if ($sentiment['score'] > $highestScore) {
                        $highestScore = $sentiment['score'];
                        $category = $label;
                    }
                }
                
                // Convert to -1 to 1 scale where negative is -1, neutral is 0, positive is 1
                // Most models use labels like POSITIVE, NEGATIVE, NEUTRAL
                if (isset($sentiments['positive']) && isset($sentiments['negative'])) {
                    $score = $sentiments['positive'] - $sentiments['negative'];
                } elseif (isset($sentiments['positive'])) {
                    $score = $sentiments['positive'];
                } elseif (isset($sentiments['negative'])) {
                    $score = -$sentiments['negative'];
                }
                
                $executionTime = microtime(true) - $startTime;
                
                if ($this->shouldStoreInDatabase()) {
                    AICompletion::createSuccess(
                        'huggingface',
                        $model,
                        $text,
                        json_encode(['score' => $score, 'category' => $category]),
                        [
                            'request_data' => $payload,
                            'response_data' => $result,
                            'execution_time' => $executionTime,
                            'user_id' => auth()->id(),
                        ]
                    );
                }
                
                return [
                    'score' => $score,
                    'category' => $category,
                    'details' => $sentiments,
                ];
            }
            
            // Fallback to text generation for sentiment
            return $this->analyzeSentimentWithLLM($text, $options);
        } catch (Exception $e) {
            // Fallback to text generation for sentiment
            return $this->analyzeSentimentWithLLM($text, $options);
        }
    }
    
    /**
     * Use a language model to analyze sentiment when specialized model fails.
     *
     * @param string $text
     * @param array $options
     * @return array
     */
    protected function analyzeSentimentWithLLM(string $text, array $options = []): array
    {
        $prompt = "Analyze the sentiment of the following text and provide a score from -1 (very negative) to 1 (very positive), and categorize as 'positive', 'negative', or 'neutral'. Return JSON format with keys 'score' and 'category'. Text: \"{$text}\"";
        
        $llmModel = $options['llm_model'] ?? 'gpt2';
        $response = $this->generateText($prompt, array_merge($options, ['model' => $llmModel]));
        
        try {
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
        $startTime = microtime(true);
        
        $model = $this->currentModel ?? $options['model'] ?? 'facebook/bart-large-mnli';
        
        try {
            $baseUri = $this->options['base_uri'] ?? 'https://api-inference.huggingface.co/models';
            $endpoint = "{$baseUri}/{$model}";
            
            // For zero-shot classification, we format the inputs with the categories
            $payload = [
                'inputs' => $text,
                'parameters' => [
                    'candidate_labels' => $categories,
                ],
            ];
            
            $response = $this->client->post($endpoint, $payload);
            
            if ($response->failed()) {
                // Fallback to text generation
                return $this->classifyTextWithLLM($text, $categories, $options);
            }
            
            $result = $response->json();
            
            // Most zero-shot classifiers return a structure with labels and scores
            if (isset($result['labels']) && isset($result['scores']) && count($result['labels']) === count($result['scores'])) {
                $classification = [];
                $topCategory = null;
                $topScore = -1;
                
                for ($i = 0; $i < count($result['labels']); $i++) {
                    $category = $result['labels'][$i];
                    $score = $result['scores'][$i];
                    
                    $classification[$category] = $score;
                    
                    if ($score > $topScore) {
                        $topScore = $score;
                        $topCategory = $category;
                    }
                }
                
                $executionTime = microtime(true) - $startTime;
                
                if ($this->shouldStoreInDatabase()) {
                    AICompletion::createSuccess(
                        'huggingface',
                        $model,
                        $text,
                        json_encode(['category' => $topCategory, 'confidence' => $topScore]),
                        [
                            'request_data' => $payload,
                            'response_data' => $result,
                            'execution_time' => $executionTime,
                            'user_id' => auth()->id(),
                        ]
                    );
                }
                
                return [
                    'category' => $topCategory,
                    'confidence' => $topScore,
                    'details' => $classification,
                ];
            }
            
            // Fallback to text generation
            return $this->classifyTextWithLLM($text, $categories, $options);
        } catch (Exception $e) {
            // Fallback to text generation
            return $this->classifyTextWithLLM($text, $categories, $options);
        }
    }
    
    /**
     * Use a language model to classify text when specialized model fails.
     *
     * @param string $text
     * @param array $categories
     * @param array $options
     * @return array
     */
    protected function classifyTextWithLLM(string $text, array $categories, array $options = []): array
    {
        $categoriesList = implode(', ', $categories);
        $prompt = "Classify the following text into one of these categories: {$categoriesList}. Return JSON format with keys 'category' and 'confidence' (a number between 0 and 1). Text: \"{$text}\"";
        
        $llmModel = $options['llm_model'] ?? 'gpt2';
        $response = $this->generateText($prompt, array_merge($options, ['model' => $llmModel]));
        
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
     * @throws \YourVendor\LaravelAIBridge\Exceptions\AIException
     */
    public function generateImage(string $prompt, array $options = []): string
    {
        $model = $this->currentModel ?? $options['model'] ?? 'stabilityai/stable-diffusion-2';
        
        try {
            $baseUri = $this->options['base_uri'] ?? 'https://api-inference.huggingface.co/models';
            $endpoint = "{$baseUri}/{$model}";
            
            $response = $this->client->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($endpoint, [
                'inputs' => $prompt,
            ]);
            
            if ($response->successful()) {
                $imageData = $response->body();
                
                // Return as base64 for easy embedding in HTML
                return 'data:image/jpeg;base64,' . base64_encode($imageData);
            } else {
                throw new AIException(
                    'HuggingFace image generation error: ' . ($response->json('error') ?? $response->reason()),
                    $response->status()
                );
            }
        } catch (Exception $e) {
            throw new AIException('HuggingFace image generation error: ' . $e->getMessage(), 0, $e);
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
        $startTime = microtime(true);
        
        $model = $this->currentModel ?? $options['model'] ?? 'dslim/bert-base-NER';
        
        try {
            $baseUri = $this->options['base_uri'] ?? 'https://api-inference.huggingface.co/models';
            $endpoint = "{$baseUri}/{$model}";
            
            $response = $this->client->post($endpoint, [
                'inputs' => $text,
            ]);
            
            if ($response->failed()) {
                // Fallback to text generation for entity extraction
                return $this->extractEntitiesWithLLM($text, $options);
            }
            
            $results = $response->json();
            
            // Process and format the entity recognition results
            $entities = [];
            $entityMap = [];
            
            foreach ($results as $entity) {
                $entityText = $entity['word'];
                $entityType = isset($entity['entity']) ? str_replace('B-', '', $entity['entity']) : 'UNKNOWN';
                $score = $entity['score'] ?? 1.0;
                
                // Skip low confidence entities
                if ($score < ($options['min_confidence'] ?? 0.5)) {
                    continue;
                }
                
                $key = $entityText . '|' . $entityType;
                
                if (isset($entityMap[$key])) {
                    $entityMap[$key]['count']++;
                    if ($score > $entityMap[$key]['score']) {
                        $entityMap[$key]['score'] = $score;
                    }
                } else {
                    $entityMap[$key] = [
                        'entity' => $entityText,
                        'type' => $entityType,
                        'count' => 1,
                        'score' => $score,
                    ];
                }
            }
            
            $entities = array_values($entityMap);
            
            // Sort by count (descending) and then by score (descending)
            usort($entities, function ($a, $b) {
                if ($a['count'] === $b['count']) {
                    return $b['score'] <=> $a['score'];
                }
                return $b['count'] <=> $a['count'];
            });
            
            $executionTime = microtime(true) - $startTime;
            
            if ($this->shouldStoreInDatabase()) {
                AICompletion::createSuccess(
                    'huggingface',
                    $model,
                    $text,
                    json_encode($entities),
                    [
                        'execution_time' => $executionTime,
                        'user_id' => auth()->id(),
                    ]
                );
            }
            
            return $entities;
        } catch (Exception $e) {
            // Fallback to text generation for entity extraction
            return $this->extractEntitiesWithLLM($text, $options);
        }
    }
    
    /**
     * Use a language model to extract entities when specialized model fails.
     *
     * @param string $text
     * @param array $options
     * @return array
     */
    protected function extractEntitiesWithLLM(string $text, array $options = []): array
    {
        $prompt = "Extract all named entities (people, organizations, locations, dates, etc.) from the following text. For each entity, identify its type. Return as a JSON array of objects with 'entity', 'type', and 'count' properties. Text: \"{$text}\"";
        
        $llmModel = $options['llm_model'] ?? 'gpt2';
        $response = $this->generateText($prompt, array_merge($options, ['model' => $llmModel]));
        
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
        
        return "ai:huggingface:{$method}:{$inputHash}:{$optionsHash}";
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