<?php
declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Tests\Unit\Services;

use Edfavieljr\LaravelAIBridge\Contracts\AIServiceInterface;
use Edfavieljr\LaravelAIBridge\Exceptions\AIException;
use Edfavieljr\LaravelAIBridge\Services\OpenAIService;
use Edfavieljr\LaravelAIBridge\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class OpenAIServiceTest extends TestCase
{
    protected function newService(array $options = []): OpenAIService
    {
        return new OpenAIService(
            'test-openai-key',
            null,
            array_merge(['default_model' => 'gpt-4', 'timeout' => 5], $options)
        );
    }

    public function test_it_implements_interface(): void
    {
        $this->assertInstanceOf(AIServiceInterface::class, $this->newService());
    }

    public function test_generate_text_success(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Generated text response']],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
            ], 200),
        ]);

        $result = $this->newService()->generateText('Hello');

        $this->assertEquals('Generated text response', $result);
    }

    public function test_generate_text_sends_correct_payload(): void
    {
        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'ok']]],
            ], 200),
        ]);

        $this->newService()->generateText('Test prompt', [
            'model' => 'gpt-3.5-turbo',
            'temperature' => 0.5,
            'max_tokens' => 100,
        ]);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);
            return $body['model'] === 'gpt-3.5-turbo'
                && $body['temperature'] === 0.5
                && $body['max_tokens'] === 100
                && $body['messages'][0]['content'] === 'Test prompt';
        });
    }

    public function test_generate_text_throws_on_api_error(): void
    {
        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'error' => ['message' => 'Invalid API key'],
            ], 401),
        ]);

        $this->expectException(AIException::class);
        $this->expectExceptionMessage('OpenAI API error');

        $this->newService()->generateText('test');
    }

    public function test_generate_text_uses_cache(): void
    {
        $this->app['config']->set('ai.cache.enabled', true);

        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'cached response']]],
            ], 200),
        ]);

        $service = $this->newService();

        $result1 = $service->generateText('cached prompt');
        $result2 = $service->generateText('cached prompt');

        $this->assertEquals('cached response', $result1);
        $this->assertEquals('cached response', $result2);

        Http::assertSentCount(1);
    }

    public function test_generate_embeddings_success(): void
    {
        Http::fake([
            'https://api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    ['embedding' => [0.1, 0.2, 0.3, 0.4]],
                ],
            ], 200),
        ]);

        $result = $this->newService()->generateEmbeddings('test text');

        $this->assertCount(1, $result);
        $this->assertEquals([0.1, 0.2, 0.3, 0.4], $result[0]);
    }

    public function test_generate_embeddings_with_array_input(): void
    {
        Http::fake([
            'https://api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    ['embedding' => [0.1, 0.2]],
                    ['embedding' => [0.3, 0.4]],
                ],
            ], 200),
        ]);

        $result = $this->newService()->generateEmbeddings(['text1', 'text2']);

        $this->assertCount(2, $result);
    }

    public function test_generate_embeddings_throws_on_error(): void
    {
        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'error' => ['message' => 'Error'],
            ], 500),
        ]);

        $this->expectException(AIException::class);
        $this->newService()->generateEmbeddings('test');
    }

    public function test_analyze_sentiment_returns_structured_result(): void
    {
        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => '{"score": 0.9, "category": "positive"}']],
                ],
            ], 200),
        ]);

        $result = $this->newService()->analyzeSentiment('I love this product!');

        $this->assertEquals(0.9, $result['score']);
        $this->assertEquals('positive', $result['category']);
    }

    public function test_classify_text_returns_structured_result(): void
    {
        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => '{"category": "technology", "confidence": 0.95}']],
                ],
            ], 200),
        ]);

        $result = $this->newService()->classifyText('AI is changing the world', ['technology', 'sports', 'politics']);

        $this->assertEquals('technology', $result['category']);
        $this->assertEquals(0.95, $result['confidence']);
    }

    public function test_generate_image_success(): void
    {
        Http::fake([
            'https://api.openai.com/v1/images/generations' => Http::response([
                'data' => [
                    ['url' => 'https://example.com/generated-image.png'],
                ],
            ], 200),
        ]);

        $result = $this->newService()->generateImage('A beautiful sunset');

        $this->assertEquals('https://example.com/generated-image.png', $result);
    }

    public function test_generate_image_with_options(): void
    {
        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'data' => [['url' => 'https://example.com/img.png']],
            ], 200),
        ]);

        $this->newService()->generateImage('test', [
            'size' => '512x512',
            'quality' => 'hd',
        ]);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);
            return $body['size'] === '512x512' && $body['quality'] === 'hd';
        });
    }

    public function test_generate_image_throws_on_error(): void
    {
        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'error' => ['message' => 'Content policy violation'],
            ], 400),
        ]);

        $this->expectException(AIException::class);
        $this->newService()->generateImage('test');
    }

    public function test_extract_entities_returns_array(): void
    {
        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => '[{"entity": "Microsoft", "type": "ORG", "count": 1}]']],
                ],
            ], 200),
        ]);

        $result = $this->newService()->extractEntities('Microsoft released a new product');

        $this->assertIsArray($result);
        $this->assertEquals('Microsoft', $result[0]['entity']);
    }

    public function test_get_client_returns_http_client(): void
    {
        $this->assertNotNull($this->newService()->getClient());
    }
}
