<?php
declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Tests\Unit\Facades;

use Edfavieljr\LaravelAIBridge\Facades\AI;
use Edfavieljr\LaravelAIBridge\Facades\Anthropic;
use Edfavieljr\LaravelAIBridge\Facades\Gemini;
use Edfavieljr\LaravelAIBridge\Facades\HuggingFace;
use Edfavieljr\LaravelAIBridge\Facades\OpenAI;
use Edfavieljr\LaravelAIBridge\Services\AIService;
use Edfavieljr\LaravelAIBridge\Services\AnthropicService;
use Edfavieljr\LaravelAIBridge\Services\GeminiService;
use Edfavieljr\LaravelAIBridge\Services\HuggingFaceService;
use Edfavieljr\LaravelAIBridge\Services\OpenAIService;
use Edfavieljr\LaravelAIBridge\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class FacadesTest extends TestCase
{
    public function test_ai_facade_resolves_to_ai_service(): void
    {
        $instance = AI::getFacadeRoot();
        $this->assertInstanceOf(AIService::class, $instance);
    }

    public function test_openai_facade_resolves_to_openai_service(): void
    {
        $instance = OpenAI::getFacadeRoot();
        $this->assertInstanceOf(OpenAIService::class, $instance);
    }

    public function test_anthropic_facade_resolves_to_anthropic_service(): void
    {
        $instance = Anthropic::getFacadeRoot();
        $this->assertInstanceOf(AnthropicService::class, $instance);
    }

    public function test_gemini_facade_resolves_to_gemini_service(): void
    {
        $instance = Gemini::getFacadeRoot();
        $this->assertInstanceOf(GeminiService::class, $instance);
    }

    public function test_huggingface_facade_resolves_to_huggingface_service(): void
    {
        $instance = HuggingFace::getFacadeRoot();
        $this->assertInstanceOf(HuggingFaceService::class, $instance);
    }

    public function test_ai_facade_can_generate_text(): void
    {
        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'Facade response']]],
            ], 200),
        ]);

        $this->refreshProviderSingletons();

        $result = AI::generateText('test');
        $this->assertEquals('Facade response', $result);
    }

    public function test_ai_facade_can_switch_provider(): void
    {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'Anthropic via facade']],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);

        $this->refreshProviderSingletons();

        $result = AI::provider('anthropic')->generateText('test');
        $this->assertEquals('Anthropic via facade', $result);
    }

    public function test_openai_facade_can_generate_text(): void
    {
        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'Direct OpenAI']]],
            ], 200),
        ]);

        $this->refreshProviderSingletons();

        $result = OpenAI::generateText('test');
        $this->assertEquals('Direct OpenAI', $result);
    }

    protected function refreshProviderSingletons(): void
    {
        $this->app->forgetInstance(AIService::class);
        $this->app->forgetInstance(OpenAIService::class);
        $this->app->forgetInstance(AnthropicService::class);
        $this->app->forgetInstance(GeminiService::class);
        $this->app->forgetInstance(HuggingFaceService::class);
        AI::clearResolvedInstances();
        OpenAI::clearResolvedInstances();
        Anthropic::clearResolvedInstances();
        Gemini::clearResolvedInstances();
        HuggingFace::clearResolvedInstances();
    }
}
