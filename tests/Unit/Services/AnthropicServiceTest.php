<?php
declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Tests\Unit\Services;

use Edfavieljr\LaravelAIBridge\Contracts\AIServiceInterface;
use Edfavieljr\LaravelAIBridge\Exceptions\AIException;
use Edfavieljr\LaravelAIBridge\Services\AnthropicService;
use Edfavieljr\LaravelAIBridge\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class AnthropicServiceTest extends TestCase
{
    protected function newService(): AnthropicService
    {
        return new AnthropicService('test-anthropic-key', [
            'timeout' => 5,
            'default_model' => 'claude-sonnet-4-20250514',
        ]);
    }

    public function test_it_implements_interface(): void
    {
        $this->assertInstanceOf(AIServiceInterface::class, $this->newService());
    }

    public function test_generate_text_success(): void
    {
        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => 'Hello from Claude'],
                ],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
            ], 200),
        ]);

        $result = $this->newService()->generateText('Say hello');

        $this->assertEquals('Hello from Claude', $result);
    }

    public function test_generate_text_sends_correct_headers(): void
    {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'ok']],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);

        $this->newService()->generateText('test');

        Http::assertSent(function ($request) {
            return $request->hasHeader('x-api-key', 'test-anthropic-key')
                && $request->hasHeader('anthropic-version');
        });
    }

    public function test_generate_text_sends_correct_payload(): void
    {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'ok']],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);

        $this->newService()->generateText('test prompt', [
            'temperature' => 0.5,
            'max_tokens' => 200,
            'system' => 'You are helpful.',
        ]);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);
            return $body['messages'][0]['content'] === 'test prompt'
                && $body['temperature'] === 0.5
                && $body['max_tokens'] === 200
                && $body['system'] === 'You are helpful.';
        });
    }

    public function test_generate_text_concatenates_multiple_text_blocks(): void
    {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => 'Part 1. '],
                    ['type' => 'text', 'text' => 'Part 2.'],
                ],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
            ], 200),
        ]);

        $result = $this->newService()->generateText('test');

        $this->assertEquals('Part 1. Part 2.', $result);
    }

    public function test_generate_text_throws_on_api_error(): void
    {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'error' => ['message' => 'Invalid API key'],
            ], 401),
        ]);

        $this->expectException(AIException::class);
        $this->expectExceptionMessage('Anthropic API error');

        $this->newService()->generateText('test');
    }

    public function test_generate_embeddings_throws_missing_capability(): void
    {
        $this->expectException(AIException::class);
        $this->expectExceptionMessage('does not provide an embeddings API');

        $this->newService()->generateEmbeddings('test');
    }

    public function test_generate_image_throws_missing_capability(): void
    {
        $this->expectException(AIException::class);
        $this->expectExceptionMessage('does not provide an image generation API');

        $this->newService()->generateImage('test');
    }

    public function test_analyze_sentiment_returns_structured_result(): void
    {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => '{"score": 0.8, "category": "positive"}'],
                ],
                'usage' => ['input_tokens' => 20, 'output_tokens' => 10],
            ], 200),
        ]);

        $result = $this->newService()->analyzeSentiment('I love this!');

        $this->assertEquals(0.8, $result['score']);
        $this->assertEquals('positive', $result['category']);
    }

    public function test_classify_text_returns_structured_result(): void
    {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => '{"category": "tech", "confidence": 0.95}'],
                ],
                'usage' => ['input_tokens' => 20, 'output_tokens' => 10],
            ], 200),
        ]);

        $result = $this->newService()->classifyText('AI is great', ['tech', 'sports']);

        $this->assertEquals('tech', $result['category']);
        $this->assertEquals(0.95, $result['confidence']);
    }

    public function test_extract_entities_returns_array(): void
    {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => '[{"entity": "Anthropic", "type": "ORG", "count": 1}]'],
                ],
                'usage' => ['input_tokens' => 20, 'output_tokens' => 15],
            ], 200),
        ]);

        $result = $this->newService()->extractEntities('Anthropic builds Claude');

        $this->assertIsArray($result);
        $this->assertEquals('Anthropic', $result[0]['entity']);
    }

    public function test_model_method_sets_model(): void
    {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'ok']],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);

        $this->newService()->model('claude-opus-4-20250514')->generateText('test');

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);
            return $body['model'] === 'claude-opus-4-20250514';
        });
    }

    public function test_get_client_returns_http_client(): void
    {
        $this->assertNotNull($this->newService()->getClient());
    }
}
