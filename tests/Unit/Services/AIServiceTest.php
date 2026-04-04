<?php
declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Tests\Unit\Services;

use Edfavieljr\LaravelAIBridge\Contracts\AIServiceInterface;
use Edfavieljr\LaravelAIBridge\Exceptions\AIException;
use Edfavieljr\LaravelAIBridge\Services\AIService;
use Edfavieljr\LaravelAIBridge\Services\OpenAIService;
use Edfavieljr\LaravelAIBridge\Services\AnthropicService;
use Edfavieljr\LaravelAIBridge\Services\GeminiService;
use Edfavieljr\LaravelAIBridge\Services\HuggingFaceService;
use Edfavieljr\LaravelAIBridge\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

class AIServiceTest extends TestCase
{
    protected AIService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AIService::class);
    }

    /**
     * Reset provider singletons so they pick up Http::fake().
     */
    protected function resetProviders(): void
    {
        $this->app->forgetInstance(OpenAIService::class);
        $this->app->forgetInstance(AnthropicService::class);
        $this->app->forgetInstance(GeminiService::class);
        $this->app->forgetInstance(HuggingFaceService::class);
        $this->service = new AIService($this->app['config']['ai']);
    }

    public function test_it_can_be_instantiated(): void
    {
        $this->assertInstanceOf(AIService::class, $this->service);
    }

    public function test_it_implements_ai_service_interface(): void
    {
        $this->assertInstanceOf(AIServiceInterface::class, $this->service);
    }

    public function test_provider_method_returns_self(): void
    {
        $result = $this->service->provider('openai');
        $this->assertSame($this->service, $result);
    }

    public function test_model_method_returns_self(): void
    {
        $result = $this->service->model('gpt-4');
        $this->assertSame($this->service, $result);
    }

    public function test_generate_text_delegates_to_provider(): void
    {
        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Hello from OpenAI']],
                ],
                'usage' => [
                    'prompt_tokens' => 10,
                    'completion_tokens' => 5,
                    'total_tokens' => 15,
                ],
            ], 200),
        ]);

        $this->resetProviders();

        $result = $this->service->generateText('Say hello');

        $this->assertEquals('Hello from OpenAI', $result);
    }

    public function test_provider_switching(): void
    {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => 'Hello from Anthropic'],
                ],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
            ], 200),
        ]);

        $this->resetProviders();

        $result = $this->service->provider('anthropic')->generateText('Say hello');

        $this->assertEquals('Hello from Anthropic', $result);
    }

    public function test_model_override_is_passed_to_provider(): void
    {
        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'response']],
                ],
            ], 200),
        ]);

        $this->resetProviders();

        $this->service->model('gpt-3.5-turbo')->generateText('test');

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);
            return $body['model'] === 'gpt-3.5-turbo';
        });
    }

    public function test_fallback_works_when_primary_fails(): void
    {
        $this->app['config']->set('ai.fallback.enabled', true);
        $this->app['config']->set('ai.fallback.providers', ['openai', 'anthropic']);

        Http::fake([
            'https://api.openai.com/*' => Http::response(['error' => ['message' => 'Server error']], 500),
            'https://api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => 'Fallback response'],
                ],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
            ], 200),
        ]);

        $this->resetProviders();

        $result = $this->service->generateText('test');

        $this->assertEquals('Fallback response', $result);
    }

    public function test_throws_exception_when_all_providers_fail(): void
    {
        $this->app['config']->set('ai.fallback.enabled', true);
        $this->app['config']->set('ai.fallback.providers', ['openai', 'anthropic']);

        Http::fake([
            'https://api.openai.com/*' => Http::response(['error' => ['message' => 'Error']], 500),
            'https://api.anthropic.com/*' => Http::response(['error' => ['message' => 'Error']], 500),
        ]);

        $this->resetProviders();

        $this->expectException(AIException::class);
        $this->expectExceptionMessage('All AI providers failed');

        $this->service->generateText('test');
    }

    public function test_rate_limiting_throws_exception(): void
    {
        $this->app['config']->set('ai.rate_limiting.enabled', true);
        $this->app['config']->set('ai.rate_limiting.max_requests_per_minute', 1);

        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'ok']],
                ],
            ], 200),
        ]);

        $this->resetProviders();
        RateLimiter::clear('ai_rate_limit:openai');

        $this->service->generateText('first request');

        $this->expectException(AIException::class);
        $this->service->generateText('second request');
    }

    public function test_unsupported_provider_throws_exception(): void
    {
        $this->expectException(AIException::class);
        $this->expectExceptionMessage('Unsupported AI provider');

        $this->service->provider('invalid_provider')->generateText('test');
    }

    public function test_get_client_returns_provider_client(): void
    {
        $client = $this->service->getClient();
        $this->assertNotNull($client);
    }

    public function test_generate_embeddings_delegates_to_provider(): void
    {
        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'data' => [
                    ['embedding' => [0.1, 0.2, 0.3]],
                ],
            ], 200),
        ]);

        $this->resetProviders();

        $result = $this->service->generateEmbeddings('test text');

        $this->assertIsArray($result);
        $this->assertEquals([0.1, 0.2, 0.3], $result[0]);
    }

    public function test_analyze_sentiment_delegates_to_provider(): void
    {
        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => '{"score": 0.8, "category": "positive"}']],
                ],
            ], 200),
        ]);

        $this->resetProviders();

        $result = $this->service->analyzeSentiment('I love this!');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('category', $result);
    }

    public function test_classify_text_delegates_to_provider(): void
    {
        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => '{"category": "tech", "confidence": 0.9}']],
                ],
            ], 200),
        ]);

        $this->resetProviders();

        $result = $this->service->classifyText('AI is amazing', ['tech', 'sports', 'politics']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('category', $result);
    }

    public function test_generate_image_delegates_to_provider(): void
    {
        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'data' => [
                    ['url' => 'https://example.com/image.png'],
                ],
            ], 200),
        ]);

        $this->resetProviders();

        $result = $this->service->generateImage('A cat');

        $this->assertEquals('https://example.com/image.png', $result);
    }

    public function test_extract_entities_delegates_to_provider(): void
    {
        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => '[{"entity": "Google", "type": "ORG", "count": 1}]']],
                ],
            ], 200),
        ]);

        $this->resetProviders();

        $result = $this->service->extractEntities('Google is a tech company');

        $this->assertIsArray($result);
    }

    public function test_without_fallback_does_not_try_other_providers(): void
    {
        $this->app['config']->set('ai.fallback.enabled', false);

        Http::fake([
            'https://api.openai.com/*' => Http::response(['error' => ['message' => 'Error']], 500),
            'https://api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'Should not reach']],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);

        $this->resetProviders();

        $this->expectException(AIException::class);
        $this->service->generateText('test');
    }
}
