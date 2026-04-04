<?php
declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Tests\Unit\Services;

use Edfavieljr\LaravelAIBridge\Contracts\AIServiceInterface;
use Edfavieljr\LaravelAIBridge\Exceptions\AIException;
use Edfavieljr\LaravelAIBridge\Services\GeminiService;
use Edfavieljr\LaravelAIBridge\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class GeminiServiceTest extends TestCase
{
    protected function newService(): GeminiService
    {
        return new GeminiService('test-gemini-key', [
            'timeout' => 5,
            'default_model' => 'gemini-2.0-flash',
            'default_embedding_model' => 'text-embedding-004',
        ]);
    }

    public function test_it_implements_interface(): void
    {
        $this->assertInstanceOf(AIServiceInterface::class, $this->newService());
    }

    public function test_generate_text_success(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'Hello from Gemini']]]],
                ],
                'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 5, 'totalTokenCount' => 15],
            ], 200),
        ]);

        $result = $this->newService()->generateText('Say hello');

        $this->assertEquals('Hello from Gemini', $result);
    }

    public function test_generate_text_sends_api_key_as_header(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'ok']]]]],
            ], 200),
        ]);

        $this->newService()->generateText('test');

        Http::assertSent(function ($request) {
            return $request->hasHeader('x-goog-api-key', 'test-gemini-key')
                && !str_contains($request->url(), 'key=');
        });
    }

    public function test_generate_text_sends_correct_payload(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'ok']]]]],
            ], 200),
        ]);

        $this->newService()->generateText('test prompt', [
            'temperature' => 0.5,
            'max_tokens' => 200,
        ]);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);
            return $body['contents'][0]['parts'][0]['text'] === 'test prompt'
                && $body['generationConfig']['temperature'] === 0.5
                && $body['generationConfig']['maxOutputTokens'] === 200;
        });
    }

    public function test_generate_text_with_system_instruction(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'ok']]]]],
            ], 200),
        ]);

        $this->newService()->generateText('test', ['system' => 'You are helpful.']);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);
            return $body['systemInstruction']['parts'][0]['text'] === 'You are helpful.';
        });
    }

    public function test_generate_text_throws_on_api_error(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'error' => ['message' => 'Invalid API key'],
            ], 400),
        ]);

        $this->expectException(AIException::class);
        $this->expectExceptionMessage('Gemini API error');

        $this->newService()->generateText('test');
    }

    public function test_generate_embeddings_success(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'embedding' => ['values' => [0.1, 0.2, 0.3]],
            ], 200),
        ]);

        $result = $this->newService()->generateEmbeddings('test text');

        $this->assertIsArray($result);
        $this->assertEquals([0.1, 0.2, 0.3], $result[0]);
    }

    public function test_generate_embeddings_with_array_input(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'embedding' => ['values' => [0.1, 0.2]],
            ], 200),
        ]);

        $result = $this->newService()->generateEmbeddings(['text1', 'text2']);

        $this->assertCount(2, $result);
    }

    public function test_generate_image_throws_missing_capability(): void
    {
        $this->expectException(AIException::class);
        $this->expectExceptionMessage('not supported');

        $this->newService()->generateImage('test');
    }

    public function test_analyze_sentiment_returns_structured_result(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => '{"score": -0.5, "category": "negative"}']]]]],
            ], 200),
        ]);

        $result = $this->newService()->analyzeSentiment('I hate this');

        $this->assertEquals(-0.5, $result['score']);
        $this->assertEquals('negative', $result['category']);
    }

    public function test_classify_text_returns_structured_result(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => '{"category": "sports", "confidence": 0.85}']]]]],
            ], 200),
        ]);

        $result = $this->newService()->classifyText('The game was exciting', ['tech', 'sports']);

        $this->assertEquals('sports', $result['category']);
    }

    public function test_extract_entities_returns_array(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => '[{"entity": "Google", "type": "ORG", "count": 1}]']]]]],
            ], 200),
        ]);

        $result = $this->newService()->extractEntities('Google is a company');

        $this->assertIsArray($result);
        $this->assertEquals('Google', $result[0]['entity']);
    }

    public function test_model_method_sets_model(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'ok']]]]],
            ], 200),
        ]);

        $this->newService()->model('gemini-1.5-pro')->generateText('test');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'gemini-1.5-pro');
        });
    }

    public function test_get_client_returns_http_client(): void
    {
        $this->assertNotNull($this->newService()->getClient());
    }
}
