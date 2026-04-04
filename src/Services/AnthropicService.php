<?php

declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Edfavieljr\LaravelAIBridge\Contracts\AIServiceInterface;
use Edfavieljr\LaravelAIBridge\Enums\Provider;
use Edfavieljr\LaravelAIBridge\Exceptions\AIException;
use Edfavieljr\LaravelAIBridge\Services\Concerns\HasAIInstrumentation;

class AnthropicService implements AIServiceInterface
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
        return Provider::Anthropic;
    }

    protected function initializeHttpClient(): void
    {
        $this->client = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => $this->options['api_version'] ?? '2023-06-01',
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

        $model = $this->currentModel ?? $options['model'] ?? $this->options['default_model'] ?? 'claude-sonnet-4-20250514';
        $payload = [
            'model' => $model,
            'max_tokens' => $options['max_tokens'] ?? 1024,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ];

        if (isset($options['temperature'])) {
            $payload['temperature'] = $options['temperature'];
        }
        if (isset($options['system'])) {
            $payload['system'] = $options['system'];
        }
        if (isset($options['top_p'])) {
            $payload['top_p'] = $options['top_p'];
        }

        try {
            $baseUri = $this->options['base_uri'] ?? 'https://api.anthropic.com/v1';

            $this->logRequest('messages', $model, $prompt);

            $response = $this->client->post("{$baseUri}/messages", $payload);

            if ($response->failed()) {
                throw new AIException(
                    'Anthropic API error: ' . ($response->json('error.message') ?? $response->reason()),
                    $response->status(),
                    null,
                    'api_error',
                    Provider::Anthropic->value,
                    $model
                );
            }

            $result = $response->json();
            $generatedText = '';

            foreach ($result['content'] ?? [] as $block) {
                if ($block['type'] === 'text') {
                    $generatedText .= $block['text'];
                }
            }

            $executionTime = microtime(true) - $startTime;

            $this->cacheResult($cacheKey, $generatedText);

            $this->storeSuccess($model, $prompt, $generatedText, [
                'prompt_tokens' => $result['usage']['input_tokens'] ?? null,
                'completion_tokens' => $result['usage']['output_tokens'] ?? null,
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
                'Anthropic API error: ' . $e->getMessage(),
                0,
                $e,
                'api_error',
                Provider::Anthropic->value,
                $model
            );
        }
    }

    public function generateChat(array $messages, array $options = []): string
    {
        $model = $this->currentModel ?? $options['model'] ?? $this->options['default_model'] ?? 'claude-sonnet-4-20250514';

        $payload = [
            'model' => $model,
            'max_tokens' => $options['max_tokens'] ?? 1024,
            'messages' => $messages,
        ];

        if (isset($options['system'])) {
            $payload['system'] = $options['system'];
        }
        if (isset($options['temperature'])) {
            $payload['temperature'] = $options['temperature'];
        }

        try {
            $baseUri = $this->options['base_uri'] ?? 'https://api.anthropic.com/v1';

            $this->logRequest('messages', $model, '[chat]');

            $response = $this->client->post("{$baseUri}/messages", $payload);

            if ($response->failed()) {
                throw new AIException(
                    'Anthropic API error: ' . ($response->json('error.message') ?? $response->reason()),
                    $response->status(),
                    null,
                    'api_error',
                    Provider::Anthropic->value,
                    $model
                );
            }

            $result = $response->json();
            $text = '';
            foreach ($result['content'] ?? [] as $block) {
                if ($block['type'] === 'text') {
                    $text .= $block['text'];
                }
            }

            return $text;
        } catch (AIException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new AIException(
                'Anthropic API error: ' . $e->getMessage(),
                0,
                $e,
                'api_error',
                Provider::Anthropic->value,
                $model
            );
        }
    }

    public function streamText(string $prompt, array $options = []): \Generator
    {
        $model = $this->currentModel ?? $options['model'] ?? $this->options['default_model'] ?? 'claude-sonnet-4-20250514';
        $baseUri = $this->options['base_uri'] ?? 'https://api.anthropic.com/v1';

        $payload = [
            'model' => $model,
            'max_tokens' => $options['max_tokens'] ?? 1024,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'stream' => true,
        ];

        if (isset($options['temperature'])) {
            $payload['temperature'] = $options['temperature'];
        }
        if (isset($options['system'])) {
            $payload['system'] = $options['system'];
        }

        $this->logRequest('messages (stream)', $model, $prompt);

        $response = $this->client
            ->withOptions(['stream' => true])
            ->post("{$baseUri}/messages", $payload);

        if ($response->failed()) {
            throw new AIException(
                'Anthropic API error: ' . ($response->json('error.message') ?? $response->reason()),
                $response->status(),
                null,
                'api_error',
                Provider::Anthropic->value,
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

                if (($json['type'] ?? '') === 'content_block_delta') {
                    $chunk = $json['delta']['text'] ?? '';
                    if ($chunk !== '') {
                        yield $chunk;
                    }
                }
            }
        }
    }

    public function generateEmbeddings(string|array $input, array $options = []): array
    {
        throw AIException::missingCapability(Provider::Anthropic->value, 'embeddings',
            'Anthropic does not provide an embeddings API. Use OpenAI or HuggingFace for embeddings.'
        );
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
        throw AIException::missingCapability(Provider::Anthropic->value, 'image_generation',
            'Anthropic does not provide an image generation API. Use OpenAI or HuggingFace for image generation.'
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
