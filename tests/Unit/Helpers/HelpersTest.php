<?php
declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Tests\Unit\Helpers;

use Edfavieljr\LaravelAIBridge\Facades\AI;
use Edfavieljr\LaravelAIBridge\Services\AIService;
use Edfavieljr\LaravelAIBridge\Services\OpenAIService;
use Edfavieljr\LaravelAIBridge\Services\AnthropicService;
use Edfavieljr\LaravelAIBridge\Services\GeminiService;
use Edfavieljr\LaravelAIBridge\Services\HuggingFaceService;
use Edfavieljr\LaravelAIBridge\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class HelpersTest extends TestCase
{
    protected function refreshProviders(): void
    {
        $this->app->forgetInstance(AIService::class);
        $this->app->forgetInstance(OpenAIService::class);
        $this->app->forgetInstance(AnthropicService::class);
        $this->app->forgetInstance(GeminiService::class);
        $this->app->forgetInstance(HuggingFaceService::class);
        AI::clearResolvedInstances();
    }

    public function test_ai_helper_without_args_returns_service(): void
    {
        $result = ai();
        $this->assertInstanceOf(AIService::class, $result);
    }

    public function test_ai_helper_with_prompt_generates_text(): void
    {
        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'Helper response']]],
            ], 200),
        ]);
        $this->refreshProviders();

        $result = ai('Say hello');
        $this->assertEquals('Helper response', $result);
    }

    public function test_ai_embed_helper(): void
    {
        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'data' => [['embedding' => [0.1, 0.2]]],
            ], 200),
        ]);
        $this->refreshProviders();

        $result = ai_embed('test text');
        $this->assertIsArray($result);
    }

    public function test_ai_sentiment_helper(): void
    {
        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => '{"score": 0.5, "category": "positive"}']]],
            ], 200),
        ]);
        $this->refreshProviders();

        $result = ai_sentiment('Great!');
        $this->assertArrayHasKey('score', $result);
    }

    public function test_ai_classify_helper(): void
    {
        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => '{"category": "tech", "confidence": 0.9}']]],
            ], 200),
        ]);
        $this->refreshProviders();

        $result = ai_classify('AI news', ['tech', 'sports']);
        $this->assertArrayHasKey('category', $result);
    }

    public function test_ai_image_helper(): void
    {
        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'data' => [['url' => 'https://example.com/img.png']],
            ], 200),
        ]);
        $this->refreshProviders();

        $result = ai_image('A sunset');
        $this->assertEquals('https://example.com/img.png', $result);
    }

    public function test_ai_entities_helper(): void
    {
        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => '[{"entity": "Google", "type": "ORG", "count": 1}]']]],
            ], 200),
        ]);
        $this->refreshProviders();

        $result = ai_entities('Google is great');
        $this->assertIsArray($result);
    }
}
