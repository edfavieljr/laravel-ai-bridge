<?php
declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Tests\Unit\Events;

use Edfavieljr\LaravelAIBridge\Enums\Provider;
use Edfavieljr\LaravelAIBridge\Events\AIRequestCompleted;
use Edfavieljr\LaravelAIBridge\Events\AIRequestFailed;
use Edfavieljr\LaravelAIBridge\Services\OpenAIService;
use Edfavieljr\LaravelAIBridge\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

class AIEventsTest extends TestCase
{
    public function test_completed_event_has_correct_properties(): void
    {
        $event = new AIRequestCompleted(
            provider: Provider::OpenAI,
            model: 'gpt-4o',
            prompt: 'Hello',
            completion: 'World',
            metadata: ['tokens' => 10],
        );

        $this->assertEquals(Provider::OpenAI, $event->provider);
        $this->assertEquals('gpt-4o', $event->model);
        $this->assertEquals('Hello', $event->prompt);
        $this->assertEquals('World', $event->completion);
        $this->assertEquals(['tokens' => 10], $event->metadata);
    }

    public function test_failed_event_has_correct_properties(): void
    {
        $event = new AIRequestFailed(
            provider: Provider::Anthropic,
            model: 'claude-sonnet-4-20250514',
            prompt: 'Hello',
            error: 'API down',
            metadata: ['retry' => true],
        );

        $this->assertEquals(Provider::Anthropic, $event->provider);
        $this->assertEquals('claude-sonnet-4-20250514', $event->model);
        $this->assertEquals('Hello', $event->prompt);
        $this->assertEquals('API down', $event->error);
        $this->assertEquals(['retry' => true], $event->metadata);
    }

    public function test_completed_event_dispatched_on_successful_api_call(): void
    {
        Event::fake([AIRequestCompleted::class]);

        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'response']]],
                'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 3, 'total_tokens' => 8],
            ], 200),
        ]);

        $service = new OpenAIService('test-key', null, ['default_model' => 'gpt-4o']);
        $service->generateText('Hello');

        Event::assertDispatched(AIRequestCompleted::class, function (AIRequestCompleted $event) {
            return $event->provider === Provider::OpenAI
                && $event->completion === 'response';
        });
    }

    public function test_failed_event_not_dispatched_by_openai_on_error(): void
    {
        Event::fake([AIRequestFailed::class]);

        Http::fake([
            'https://api.openai.com/*' => Http::response(['error' => ['message' => 'fail']], 500),
        ]);

        $service = new OpenAIService('test-key', null, ['default_model' => 'gpt-4o']);

        try {
            $service->generateText('Hello');
        } catch (\Throwable) {
            // Expected — OpenAI throws, doesn't dispatch failed event
        }

        // OpenAI re-throws without calling storeError — by design
        Event::assertNotDispatched(AIRequestFailed::class);
    }
}
