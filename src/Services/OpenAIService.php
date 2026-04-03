<?php

declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Edfavieljr\LaravelAIBridge\Contracts\AIServiceInterface;
use Edfavieljr\LaravelAIBridge\Enums\Provider;
use Edfavieljr\LaravelAIBridge\Exceptions\AIException;
use Edfavieljr\LaravelAIBridge\Services\Concerns\HasAIInstrumentation;

class OpenAIService implements AIServiceInterface
{
    use HasAIInstrumentation;

    protected string $apiKey;
    protected ?string $organization;
    protected array $options;
    protected $client;
    protected ?string $currentModel = null;

    public function __construct(string $apiKey, ?string $organization = null, array $options = [])
    {
        $this->apiKey = $apiKey;
        $this->organization = $organization;
        $this->options = $options;

        $this->initializeHttpClient();
    }

    protected function providerName(): Provider
    {
        return Provider::OpenAI;
    }

    public function model(string $model): self
    {
        $this->currentModel = $model;
        return $this;
    }

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

        $this->client = $this->client->timeout($this->options['timeout'] ?? 30);
    }

    public function generateText(string $prompt, array $options = []): string
    {
        $startTime = microtime(true);
        $cacheKey = $this->getCacheKey('generateText', $prompt, $options);

        $cached = $this->getCachedResult($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $model = $this->currentModel ?? $options['model'] ?? $this->options['default_model'] ?? 'gpt-4o';
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 500,
        ];

        try {
            $baseUri = $this->options['base_uri'] ?? 'https://api.openai.com/v1';

            $this->logRequest('chat/completions', $model, $prompt);

            $response = $this->client->post("{$baseUri}/chat/completions", $payload);

            if ($response->failed()) {
                throw new AIException(
                    'OpenAI API error: ' . ($response->json('error.message') ?? $response->reason()),
                    $response->status(),
                    null,
                    'api_error',
                    Provider::OpenAI->value,
                    $model
                );
            }

            $result = $response->json();
            $generatedText = $result['choices'][0]['message']['content'] ?? '';
            $executionTime = microtime(true) - $startTime;

            $this->cacheResult($cacheKey, $generatedText);

            $this->storeSuccess($model, $prompt, $generatedText, [
                'prompt_tokens' => $result['usage']['prompt_tokens'] ?? null,
                'completion_tokens' => $result['usage']['completion_tokens'] ?? null,
                'total_tokens' => $result['usage']['total_tokens'] ?? null,
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
                'OpenAI API error: ' . $e->getMessage(),
                0,
                $e,
                'api_error',
                Provider::OpenAI->value,
                $model
            );
        }
    }

    public function generateEmbeddings(string|array $input, array $options = []): array
    {
        $model = $this->currentModel ?? $options['model'] ?? $this->options['default_embedding_model'] ?? 'text-embedding-3-large';
        $inputArray = is_array($input) ? $input : [$input];

        $cacheKey = $this->getCacheKey('generateEmbeddings', $input, $options);
        $cached = $this->getCachedResult($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $baseUri = $this->options['base_uri'] ?? 'https://api.openai.com/v1';
            $response = $this->client->post("{$baseUri}/embeddings", [
                'model' => $model,
                'input' => $inputArray,
            ]);

            if ($response->failed()) {
                throw new AIException(
                    'OpenAI API error: ' . ($response->json('error.message') ?? $response->reason()),
                    $response->status(),
                    null,
                    'api_error',
                    Provider::OpenAI->value,
                    $model
                );
            }

            $result = array_map(fn ($item) => $item['embedding'], $response->json('data'));

            $this->cacheResult($cacheKey, $result);

            return $result;
        } catch (AIException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new AIException(
                'OpenAI embeddings error: ' . $e->getMessage(),
                0,
                $e,
                'api_error',
                Provider::OpenAI->value,
                $model
            );
        }
    }

    public function generateChat(array $messages, array $options = []): string
    {
        $model = $this->currentModel ?? $options['model'] ?? $this->options['default_model'] ?? 'gpt-4o';

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 500,
        ];

        if (isset($options['system'])) {
            array_unshift($payload['messages'], ['role' => 'system', 'content' => $options['system']]);
        }

        try {
            $baseUri = $this->options['base_uri'] ?? 'https://api.openai.com/v1';

            $this->logRequest('chat/completions', $model, '[chat]');

            $response = $this->client->post("{$baseUri}/chat/completions", $payload);

            if ($response->failed()) {
                throw new AIException(
                    'OpenAI API error: ' . ($response->json('error.message') ?? $response->reason()),
                    $response->status(),
                    null,
                    'api_error',
                    Provider::OpenAI->value,
                    $model
                );
            }

            return $response->json('choices.0.message.content') ?? '';
        } catch (AIException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new AIException(
                'OpenAI API error: ' . $e->getMessage(),
                0,
                $e,
                'api_error',
                Provider::OpenAI->value,
                $model
            );
        }
    }

    public function streamText(string $prompt, array $options = []): \Generator
    {
        $model = $this->currentModel ?? $options['model'] ?? $this->options['default_model'] ?? 'gpt-4o';
        $baseUri = $this->options['base_uri'] ?? 'https://api.openai.com/v1';

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 500,
            'stream' => true,
        ];

        $this->logRequest('chat/completions (stream)', $model, $prompt);

        $response = $this->client
            ->withOptions(['stream' => true])
            ->post("{$baseUri}/chat/completions", $payload);

        if ($response->failed()) {
            throw new AIException(
                'OpenAI API error: ' . ($response->json('error.message') ?? $response->reason()),
                $response->status(),
                null,
                'api_error',
                Provider::OpenAI->value,
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
                if ($line === '' || $line === 'data: [DONE]') {
                    continue;
                }
                if (!str_starts_with($line, 'data: ')) {
                    continue;
                }

                $json = json_decode(substr($line, 6), true);
                $chunk = $json['choices'][0]['delta']['content'] ?? '';

                if ($chunk !== '') {
                    yield $chunk;
                }
            }
        }
    }

    public function analyzeSentiment(string $text, array $options = []): array
    {
        $prompt = "Analyze the sentiment of the following text and provide a score from -1 (very negative) to 1 (very positive), and categorize as 'positive', 'negative', or 'neutral'. Return JSON format with keys 'score' and 'category'. Text: \"{$text}\"";

        $response = $this->generateText($prompt, $options);

        return $this->parseJsonResponse($response, [
            'score' => 0,
            'category' => 'neutral',
        ]);
    }

    public function classifyText(string $text, array $categories, array $options = []): array
    {
        $categoriesList = implode(', ', $categories);
        $prompt = "Classify the following text into one of these categories: {$categoriesList}. Return JSON format with keys 'category' and 'confidence' (a number between 0 and 1). Text: \"{$text}\"";

        $response = $this->generateText($prompt, $options);

        return $this->parseJsonResponse($response, [
            'category' => $categories[0] ?? '',
            'confidence' => 0,
        ]);
    }

    public function generateImage(string $prompt, array $options = []): string
    {
        $model = $this->currentModel ?? $options['model'] ?? $this->options['default_image_model'] ?? 'dall-e-3';
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
                    $response->status(),
                    null,
                    'api_error',
                    Provider::OpenAI->value,
                    $model
                );
            }

            return $response->json("data.0.{$responseFormat}");
        } catch (AIException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new AIException(
                'OpenAI image generation error: ' . $e->getMessage(),
                0,
                $e,
                'api_error',
                Provider::OpenAI->value,
                $model
            );
        }
    }

    public function extractEntities(string $text, array $options = []): array
    {
        $prompt = "Extract all named entities (people, organizations, locations, dates, etc.) from the following text. For each entity, identify its type. Return as a JSON array of objects with 'entity', 'type', and 'count' properties. Text: \"{$text}\"";

        $response = $this->generateText($prompt, $options);
        $result = json_decode($response, true);

        return is_array($result) ? $result : [];
    }

    public function getClient(): mixed
    {
        return $this->client;
    }
}
