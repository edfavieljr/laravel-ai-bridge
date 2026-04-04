<?php
declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Tests\Unit;

use Edfavieljr\LaravelAIBridge\Contracts\AIServiceInterface;
use Edfavieljr\LaravelAIBridge\Exceptions\AIException;
use Edfavieljr\LaravelAIBridge\Services\AIService;
use Edfavieljr\LaravelAIBridge\Services\AnthropicService;
use Edfavieljr\LaravelAIBridge\Services\GeminiService;
use Edfavieljr\LaravelAIBridge\Services\HuggingFaceService;
use Edfavieljr\LaravelAIBridge\Services\OpenAIService;
use Edfavieljr\LaravelAIBridge\Tests\TestCase;

class AIBridgeServiceProviderTest extends TestCase
{
    public function test_ai_service_is_registered(): void
    {
        $this->assertInstanceOf(AIService::class, app(AIService::class));
    }

    public function test_ai_service_is_singleton(): void
    {
        $instance1 = app(AIService::class);
        $instance2 = app(AIService::class);
        $this->assertSame($instance1, $instance2);
    }

    public function test_openai_service_is_registered(): void
    {
        $this->assertInstanceOf(OpenAIService::class, app(OpenAIService::class));
    }

    public function test_anthropic_service_is_registered(): void
    {
        $this->assertInstanceOf(AnthropicService::class, app(AnthropicService::class));
    }

    public function test_gemini_service_is_registered(): void
    {
        $this->assertInstanceOf(GeminiService::class, app(GeminiService::class));
    }

    public function test_huggingface_service_is_registered(): void
    {
        $this->assertInstanceOf(HuggingFaceService::class, app(HuggingFaceService::class));
    }

    public function test_interface_binds_to_default_provider(): void
    {
        $this->app['config']->set('ai.default', 'openai');
        $instance = app(AIServiceInterface::class);
        $this->assertInstanceOf(OpenAIService::class, $instance);
    }

    public function test_interface_binds_to_anthropic_when_configured(): void
    {
        $this->app['config']->set('ai.default', 'anthropic');
        $instance = app(AIServiceInterface::class);
        $this->assertInstanceOf(AnthropicService::class, $instance);
    }

    public function test_interface_binds_to_gemini_when_configured(): void
    {
        $this->app['config']->set('ai.default', 'gemini');
        $instance = app(AIServiceInterface::class);
        $this->assertInstanceOf(GeminiService::class, $instance);
    }

    public function test_interface_binds_to_huggingface_when_configured(): void
    {
        $this->app['config']->set('ai.default', 'huggingface');
        $instance = app(AIServiceInterface::class);
        $this->assertInstanceOf(HuggingFaceService::class, $instance);
    }

    public function test_unsupported_provider_throws_exception(): void
    {
        $this->app['config']->set('ai.default', 'unsupported');
        $this->expectException(\Exception::class);
        app(AIServiceInterface::class);
    }

    public function test_config_is_loaded(): void
    {
        $this->assertNotNull(config('ai'));
        $this->assertNotNull(config('ai.default'));
        $this->assertNotNull(config('ai.providers'));
    }

    public function test_helper_functions_are_loaded(): void
    {
        $this->assertTrue(function_exists('ai'));
        $this->assertTrue(function_exists('ai_embed'));
        $this->assertTrue(function_exists('ai_sentiment'));
        $this->assertTrue(function_exists('ai_classify'));
        $this->assertTrue(function_exists('ai_image'));
        $this->assertTrue(function_exists('ai_entities'));
        $this->assertTrue(function_exists('ai_provider'));
        $this->assertTrue(function_exists('ai_model'));
    }
}
