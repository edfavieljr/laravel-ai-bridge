<?php

declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Edfavieljr\LaravelAIBridge\Contracts\AIServiceInterface;
use Edfavieljr\LaravelAIBridge\Enums\Provider;
use Edfavieljr\LaravelAIBridge\Exceptions\AIException;
use Edfavieljr\LaravelAIBridge\Services\Concerns\HasAIInstrumentation;

class GeminiService implements AIServiceInterface
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
        return Provider::Gemini;
    }

    protected function initializeHttpClient(): void
    {
        $this->client = Http::withHeaders([
            'Content-Type' => 'application/json',
            'x-goog-api-key' => $this->apiKey,
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

        $model = $this->currentModel ?? $options['model'] ?? $this->options['default_model'] ?? 'gemini-2.0-flash';

        $payload = [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
        ];

        if (isset($options['temperature']) || isset($options['max_tokens']) || isset($options['top_p'])) {
            $generationConfig = [];
            if (isset($options['temperature'])) {
                $generationConfig['temperature'] = $options['temperature'];
            }
            if (isset($options['max_tokens'])) {
                $generationConfig['maxOutputTokens'] = $options['max_tokens'];
            }
            if (isset($options['top_p'])) {
                $generationConfig['topP'] = $options['top_p'];
            }
            $payload['generationConfig'] = $generationConfig;
        }

        if (isset($options['system'])) {
            $payload['systemInstruction'] = [
                'parts' => [['text' => $options['system']]],
            ];
        }

        try {
            $baseUri = $this->options['base_uri'] ?? 'https://generativelanguage.googleapis.com/v1beta';
            $endpoint = "{$baseUri}/models/{$model}:generateContent";

            $this->logRequest("models/{$model}:generateContent", $model, $prompt);

            $response = $this->client->post($endpoint, $payload);

            if ($response->failed()) {
                throw new AIException(
                    'Gemini API error: ' . ($response->json('error.message') ?? $response->reason()),
                    $response->status(),
                    null,
                    'api_error',
                    Provider::Gemini->value,
                    $model
                );
            }

            $result = $response->json();
            $generatedText = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $executionTime = microtime(true) - $startTime;

            $this->cacheResult($cacheKey, $generatedText);

            $this->storeSuccess($model, $prompt, $generatedText, [
                'prompt_tokens' => $result['usageMetadata']['promptTokenCount'] ?? null,
                'completion_tokens' => $result['usageMetadata']['candidatesTokenCount'] ?? null,
                'total_tokens' => $result['usageMetadata']['totalTokenCount'] ?? null,
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
                'request_data' => $payload,
                'execution_time' => $executionTime,
            ]);

            throw new AIException(
                'Gemini API error: ' . $e->getMessage(),
                0,
                $e,
                'api_error',
                Provider::Gemini->value,
                $model
            );
        }
    }

    public function generateChat(array $messages, array $options = []): string
    {
        $model = $this->currentModel ?? $options['model'] ?? $this->options['default_model'] ?? 'gemini-2.0-flash';

        // Convert messages to Gemini's contents format
        $contents = [];
        foreach ($messages as $msg) {
            $role = $msg['role'] === 'assistant' ? 'model' : 'user';
            $contents[] = [
                'role' => $role,
                'parts' => [['text' => $msg['content']]],
            ];
        }

        $payload = ['contents' => $contents];

        if (isset($options['system'])) {
            $payload['systemInstruction'] = [
                'parts' => [['text' => $options['system']]],
            ];
        }

        if (isset($options['temperature']) || isset($options['max_tokens'])) {
            $generationConfig = [];
            if (isset($options['temperature'])) {
                $generationConfig['temperature'] = $options['temperature'];
            }
            if (isset($options['max_tokens'])) {
                $generationConfig['maxOutputTokens'] = $options['max_tokens'];
            }
            $payload['generationConfig'] = $generationConfig;
        }

        try {
            $baseUri = $this->options['base_uri'] ?? 'https://generativelanguage.googleapis.com/v1beta';
            $endpoint = "{$baseUri}/models/{$model}:generateContent";

            $this->logRequest("models/{$model}:generateContent", $model, '[chat]');

            $response = $this->client->post($endpoint, $payload);

            if ($response->failed()) {
                throw new AIException(
                    'Gemini API error: ' . ($response->json('error.message') ?? $response->reason()),
                    $response->status(),
                    null,
                    'api_error',
                    Provider::Gemini->value,
                    $model
                );
            }

            return $response->json('candidates.0.content.parts.0.text') ?? '';
        } catch (AIException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new AIException(
                'Gemini API error: ' . $e->getMessage(),
                0,
                $e,
                'api_error',
                Provider::Gemini->value,
                $model
            );
        }
    }

    public function streamText(string $prompt, array $options = []): \Generator
    {
        $model = $this->currentModel ?? $options['model'] ?? $this->options['default_model'] ?? 'gemini-2.0-flash';
        $baseUri = $this->options['base_uri'] ?? 'https://generativelanguage.googleapis.com/v1beta';

        $payload = [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
        ];

        if (isset($options['temperature']) || isset($options['max_tokens'])) {
            $generationConfig = [];
            if (isset($options['temperature'])) {
                $generationConfig['temperature'] = $options['temperature'];
            }
            if (isset($options['max_tokens'])) {
                $generationConfig['maxOutputTokens'] = $options['max_tokens'];
            }
            $payload['generationConfig'] = $generationConfig;
        }

        if (isset($options['system'])) {
            $payload['systemInstruction'] = [
                'parts' => [['text' => $options['system']]],
            ];
        }

        $this->logRequest("models/{$model}:streamGenerateContent", $model, $prompt);

        $endpoint = "{$baseUri}/models/{$model}:streamGenerateContent?alt=sse";

        $response = $this->client
            ->withOptions(['stream' => true])
            ->post($endpoint, $payload);

        if ($response->failed()) {
            throw new AIException(
                'Gemini API error: ' . ($response->json('error.message') ?? $response->reason()),
                $response->status(),
                null,
                'api_error',
                Provider::Gemini->value,
                $model
            );
        }

        $body = $response->toPsrResponse()->getBody();
        $buffer = '';

        while (!$body->eof()) {
            $buffer .= $body->read(1024);
            $lines = explode("\n", $buffer);
            $buffer = array_pop($lines);

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || !str_starts_with($line, 'data: ')) {
                    continue;
                }

                $json = json_decode(substr($line, 6), true);
                $chunk = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';

                if ($chunk !== '') {
                    yield $chunk;
                }
            }
        }
    }

    public function generateEmbeddings(string|array $input, array $options = []): array
    {
        $model = $this->currentModel ?? $options['model'] ?? $this->options['default_embedding_model'] ?? 'text-embedding-004';
        $inputs = is_array($input) ? $input : [$input];

        try {
            $baseUri = $this->options['base_uri'] ?? 'https://generativelanguage.googleapis.com/v1beta';
            $embeddings = [];

            foreach ($inputs as $text) {
                $endpoint = "{$baseUri}/models/{$model}:embedContent";

                $response = $this->client->post($endpoint, [
                    'model' => "models/{$model}",
                    'content' => [
                        'parts' => [['text' => $text]],
                    ],
                ]);

                if ($response->failed()) {
                    throw new AIException(
                        'Gemini embeddings error: ' . ($response->json('error.message') ?? $response->reason()),
                        $response->status(),
                        null,
                        'api_error',
                        Provider::Gemini->value,
                        $model
                    );
                }

                $embeddings[] = $response->json('embedding.values') ?? [];
            }

            return $embeddings;
        } catch (AIException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new AIException(
                'Gemini embeddings error: ' . $e->getMessage(),
                0,
                $e,
                'api_error',
                Provider::Gemini->value,
                $model
            );
        }
    }

    public function analyzeSentiment(string $text, array $options = []): array
    {
        $prompt = "Analyze the sentiment of the following text. Return ONLY a JSON object with keys 'score' (from -1 very negative to 1 very positive) and 'category' ('positive', 'negative', or 'neutral'). Text: \"{$text}\"";

        $response = $this->generateText($prompt, array_merge($options, [
            'system' => 'You are a sentiment analysis tool. Always respond with valid JSON only, no additional text.',
        ]));

        return $this->parseJsonResponse($response, [
            'score' => 0,
            'category' => 'neutral',
        ]);
    }

    public function classifyText(string $text, array $categories, array $options = []): array
    {
        $categoriesList = implode(', ', $categories);
        $prompt = "Classify the following text into one of these categories: {$categoriesList}. Return ONLY a JSON object with keys 'category' and 'confidence' (0 to 1). Text: \"{$text}\"";

        $response = $this->generateText($prompt, array_merge($options, [
            'system' => 'You are a text classification tool. Always respond with valid JSON only, no additional text.',
        ]));

        return $this->parseJsonResponse($response, [
            'category' => $categories[0] ?? '',
            'confidence' => 0,
        ]);
    }

    public function generateImage(string $prompt, array $options = []): string
    {
        throw AIException::missingCapability(Provider::Gemini->value, 'image_generation',
            'Gemini image generation is not supported through this bridge. Use OpenAI or HuggingFace for image generation.'
        );
    }

    public function extractEntities(string $text, array $options = []): array
    {
        $prompt = "Extract all named entities (people, organizations, locations, dates, etc.) from the following text. Return ONLY a JSON array of objects with 'entity', 'type', and 'count' properties. Text: \"{$text}\"";

        $response = $this->generateText($prompt, array_merge($options, [
            'system' => 'You are a named entity recognition tool. Always respond with valid JSON only, no additional text.',
        ]));

        $result = json_decode($response, true);

        return is_array($result) ? $result : [];
    }

    public function getClient(): mixed
    {
        return $this->client;
    }
}
