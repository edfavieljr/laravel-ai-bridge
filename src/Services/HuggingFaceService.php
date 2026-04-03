<?php

declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Edfavieljr\LaravelAIBridge\Contracts\AIServiceInterface;
use Edfavieljr\LaravelAIBridge\Enums\Provider;
use Edfavieljr\LaravelAIBridge\Exceptions\AIException;
use Edfavieljr\LaravelAIBridge\Services\Concerns\HasAIInstrumentation;

class HuggingFaceService implements AIServiceInterface
{
    use HasAIInstrumentation;

    protected string $apiKey;
    protected array $options;
    protected $client;
    protected ?string $currentModel = null;

    public function __construct(string $apiKey, array $options = [])
    {
        $this->apiKey = $apiKey;
        $this->options = $options;

        $this->initializeHttpClient();
    }

    protected function providerName(): Provider
    {
        return Provider::HuggingFace;
    }

    protected function initializeHttpClient(): void
    {
        $this->client = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout($this->options['timeout'] ?? 30);
    }

    public function model(string $model): self
    {
        $this->currentModel = $model;
        return $this;
    }

    public function generateText(string $prompt, array $options = []): string
    {
        $startTime = microtime(true);
        $cacheKey = $this->getCacheKey('generateText', $prompt, $options);

        $cached = $this->getCachedResult($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $model = $this->currentModel ?? $options['model'] ?? $this->options['default_model'] ?? 'gpt2';
        $parameters = [
            'temperature' => $options['temperature'] ?? 0.7,
            'max_length' => $options['max_tokens'] ?? 100,
            'return_full_text' => $options['return_full_text'] ?? false,
        ];

        if (isset($options['top_k'])) {
            $parameters['top_k'] = $options['top_k'];
        }
        if (isset($options['top_p'])) {
            $parameters['top_p'] = $options['top_p'];
        }
        if (isset($options['repetition_penalty'])) {
            $parameters['repetition_penalty'] = $options['repetition_penalty'];
        }

        $payload = [
            'inputs' => $prompt,
            'parameters' => $parameters,
            'options' => [
                'wait_for_model' => $options['wait_for_model'] ?? true,
            ],
        ];

        try {
            $baseUri = $this->options['base_uri'] ?? 'https://api-inference.huggingface.co/models';
            $endpoint = "{$baseUri}/{$model}";

            $this->logRequest($endpoint, $model, $prompt);

            $response = $this->client->post($endpoint, $payload);

            if ($response->failed()) {
                throw new AIException(
                    'HuggingFace API error: ' . ($response->json('error') ?? $response->reason()),
                    $response->status(),
                    null,
                    'api_error',
                    Provider::HuggingFace->value,
                    $model
                );
            }

            $result = $response->json();
            $generatedText = $this->extractGeneratedText($result, $prompt, $options);
            $executionTime = microtime(true) - $startTime;

            $this->cacheResult($cacheKey, $generatedText);

            $this->storeSuccess($model, $prompt, $generatedText, [
                'request_data' => $payload,
                'response_data' => $result,
                'execution_time' => $executionTime,
            ]);

            return $generatedText;
        } catch (AIException $e) {
            throw $e;
        } catch (Exception $e) {
            $executionTime = microtime(true) - $startTime;

            $this->logError($e->getMessage(), $prompt, $model);

            $this->storeError($model, $prompt, $e->getMessage(), [
                'request_data' => $payload ?? null,
                'execution_time' => $executionTime,
            ]);

            throw new AIException(
                'HuggingFace API error: ' . $e->getMessage(),
                0,
                $e,
                'api_error',
                Provider::HuggingFace->value,
                $model
            );
        }
    }

    public function generateChat(array $messages, array $options = []): string
    {
        // HuggingFace Inference API doesn't support native messages.
        // Concatenate into a single prompt as fallback.
        $parts = [];
        if (isset($options['system'])) {
            $parts[] = $options['system'];
        }
        foreach ($messages as $msg) {
            $parts[] = ucfirst($msg['role']) . ': ' . $msg['content'];
        }

        return $this->generateText(implode("\n", $parts), $options);
    }

    public function streamText(string $prompt, array $options = []): \Generator
    {
        // HuggingFace Inference API has limited streaming support.
        // Fallback: generate full text and yield it in one chunk.
        $result = $this->generateText($prompt, $options);

        yield $result;
    }

    public function generateEmbeddings(string|array $input, array $options = []): array
    {
        $model = $this->currentModel ?? $options['model'] ?? $this->options['default_embedding_model'] ?? 'sentence-transformers/all-mpnet-base-v2';
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

            $response = $this->client->post($endpoint, $payload);

            if ($response->failed()) {
                throw new AIException(
                    'HuggingFace API error: ' . ($response->json('error') ?? $response->reason()),
                    $response->status(),
                    null,
                    'api_error',
                    Provider::HuggingFace->value,
                    $model
                );
            }

            return $response->json();
        } catch (AIException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new AIException(
                'HuggingFace embeddings error: ' . $e->getMessage(),
                0,
                $e,
                'api_error',
                Provider::HuggingFace->value,
                $model
            );
        }
    }

    public function analyzeSentiment(string $text, array $options = []): array
    {
        $model = $this->currentModel ?? $options['model'] ?? 'distilbert-base-uncased-finetuned-sst-2-english';

        try {
            $baseUri = $this->options['base_uri'] ?? 'https://api-inference.huggingface.co/models';
            $endpoint = "{$baseUri}/{$model}";

            $response = $this->client->post($endpoint, ['inputs' => $text]);

            if ($response->failed()) {
                return $this->analyzeSentimentWithLLM($text, $options);
            }

            $result = $response->json();

            if (is_array($result) && isset($result[0]) && is_array($result[0])) {
                $sentiments = [];
                $highestScore = 0;
                $category = 'neutral';

                foreach ($result[0] as $sentiment) {
                    $label = strtolower($sentiment['label']);
                    $sentiments[$label] = $sentiment['score'];

                    if ($sentiment['score'] > $highestScore) {
                        $highestScore = $sentiment['score'];
                        $category = $label;
                    }
                }

                $score = 0;
                if (isset($sentiments['positive']) && isset($sentiments['negative'])) {
                    $score = $sentiments['positive'] - $sentiments['negative'];
                } elseif (isset($sentiments['positive'])) {
                    $score = $sentiments['positive'];
                } elseif (isset($sentiments['negative'])) {
                    $score = -$sentiments['negative'];
                }

                return [
                    'score' => $score,
                    'category' => $category,
                    'details' => $sentiments,
                ];
            }

            return $this->analyzeSentimentWithLLM($text, $options);
        } catch (Exception) {
            return $this->analyzeSentimentWithLLM($text, $options);
        }
    }

    public function classifyText(string $text, array $categories, array $options = []): array
    {
        $model = $this->currentModel ?? $options['model'] ?? 'facebook/bart-large-mnli';

        try {
            $baseUri = $this->options['base_uri'] ?? 'https://api-inference.huggingface.co/models';
            $endpoint = "{$baseUri}/{$model}";

            $payload = [
                'inputs' => $text,
                'parameters' => ['candidate_labels' => $categories],
            ];

            $response = $this->client->post($endpoint, $payload);

            if ($response->failed()) {
                return $this->classifyTextWithLLM($text, $categories, $options);
            }

            $result = $response->json();

            if (isset($result['labels'], $result['scores']) && count($result['labels']) === count($result['scores'])) {
                $classification = [];
                $topCategory = null;
                $topScore = -1;

                for ($i = 0; $i < count($result['labels']); $i++) {
                    $classification[$result['labels'][$i]] = $result['scores'][$i];

                    if ($result['scores'][$i] > $topScore) {
                        $topScore = $result['scores'][$i];
                        $topCategory = $result['labels'][$i];
                    }
                }

                return [
                    'category' => $topCategory,
                    'confidence' => $topScore,
                    'details' => $classification,
                ];
            }

            return $this->classifyTextWithLLM($text, $categories, $options);
        } catch (Exception) {
            return $this->classifyTextWithLLM($text, $categories, $options);
        }
    }

    public function generateImage(string $prompt, array $options = []): string
    {
        $model = $this->currentModel ?? $options['model'] ?? 'stabilityai/stable-diffusion-2';

        try {
            $baseUri = $this->options['base_uri'] ?? 'https://api-inference.huggingface.co/models';
            $endpoint = "{$baseUri}/{$model}";

            $response = $this->client->post($endpoint, ['inputs' => $prompt]);

            if ($response->successful()) {
                return 'data:image/jpeg;base64,' . base64_encode($response->body());
            }

            throw new AIException(
                'HuggingFace image generation error: ' . ($response->json('error') ?? $response->reason()),
                $response->status(),
                null,
                'api_error',
                Provider::HuggingFace->value,
                $model
            );
        } catch (AIException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new AIException(
                'HuggingFace image generation error: ' . $e->getMessage(),
                0,
                $e,
                'api_error',
                Provider::HuggingFace->value,
                $model
            );
        }
    }

    public function extractEntities(string $text, array $options = []): array
    {
        $model = $this->currentModel ?? $options['model'] ?? 'dslim/bert-base-NER';

        try {
            $baseUri = $this->options['base_uri'] ?? 'https://api-inference.huggingface.co/models';
            $endpoint = "{$baseUri}/{$model}";

            $response = $this->client->post($endpoint, ['inputs' => $text]);

            if ($response->failed()) {
                return $this->extractEntitiesWithLLM($text, $options);
            }

            $results = $response->json();
            $entityMap = [];

            foreach ($results as $entity) {
                $entityText = $entity['word'];
                $entityType = isset($entity['entity']) ? str_replace('B-', '', $entity['entity']) : 'UNKNOWN';
                $score = $entity['score'] ?? 1.0;

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

            usort($entities, function ($a, $b) {
                return $a['count'] === $b['count']
                    ? $b['score'] <=> $a['score']
                    : $b['count'] <=> $a['count'];
            });

            return $entities;
        } catch (Exception) {
            return $this->extractEntitiesWithLLM($text, $options);
        }
    }

    public function getClient(): mixed
    {
        return $this->client;
    }

    protected function extractGeneratedText(mixed $result, string $prompt, array $options): string
    {
        $generatedText = '';

        if (is_array($result) && isset($result[0])) {
            if (isset($result[0]['generated_text'])) {
                $generatedText = $result[0]['generated_text'];
            } else {
                $generatedText = is_string($result[0]) ? $result[0] : json_encode($result[0]);
            }
        } elseif (isset($result['generated_text'])) {
            $generatedText = $result['generated_text'];
        } else {
            $generatedText = is_string($result) ? $result : json_encode($result);
        }

        if (!($options['return_full_text'] ?? false) && str_starts_with($generatedText, $prompt)) {
            $generatedText = substr($generatedText, strlen($prompt));
        }

        return $generatedText;
    }

    protected function analyzeSentimentWithLLM(string $text, array $options = []): array
    {
        $prompt = "Analyze the sentiment of the following text and provide a score from -1 (very negative) to 1 (very positive), and categorize as 'positive', 'negative', or 'neutral'. Return JSON format with keys 'score' and 'category'. Text: \"{$text}\"";

        $response = $this->generateText($prompt, array_merge($options, ['model' => $options['llm_model'] ?? 'gpt2']));

        return $this->parseJsonResponse($response, [
            'score' => 0,
            'category' => 'neutral',
        ]);
    }

    protected function classifyTextWithLLM(string $text, array $categories, array $options = []): array
    {
        $categoriesList = implode(', ', $categories);
        $prompt = "Classify the following text into one of these categories: {$categoriesList}. Return JSON format with keys 'category' and 'confidence' (a number between 0 and 1). Text: \"{$text}\"";

        $response = $this->generateText($prompt, array_merge($options, ['model' => $options['llm_model'] ?? 'gpt2']));

        return $this->parseJsonResponse($response, [
            'category' => $categories[0] ?? '',
            'confidence' => 0,
        ]);
    }

    protected function extractEntitiesWithLLM(string $text, array $options = []): array
    {
        $prompt = "Extract all named entities (people, organizations, locations, dates, etc.) from the following text. For each entity, identify its type. Return as a JSON array of objects with 'entity', 'type', and 'count' properties. Text: \"{$text}\"";

        $response = $this->generateText($prompt, array_merge($options, ['model' => $options['llm_model'] ?? 'gpt2']));

        $result = json_decode($response, true);

        return is_array($result) ? $result : [];
    }
}
