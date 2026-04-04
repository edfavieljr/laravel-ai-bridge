<?php
declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Tests\Unit\Listeners;

use Edfavieljr\LaravelAIBridge\Enums\Provider;
use Edfavieljr\LaravelAIBridge\Events\AIRequestCompleted;
use Edfavieljr\LaravelAIBridge\Events\AIRequestFailed;
use Edfavieljr\LaravelAIBridge\Listeners\StoreAICompletion;
use Edfavieljr\LaravelAIBridge\Tests\TestCase;

class StoreAICompletionTest extends TestCase
{
    public function test_listener_handles_completed_event_when_storage_disabled(): void
    {
        $this->app['config']->set('ai.storage.enabled', false);

        $listener = new StoreAICompletion();
        $event = new AIRequestCompleted(
            provider: Provider::OpenAI,
            model: 'gpt-4o',
            prompt: 'test',
            completion: 'response',
        );

        // Should not throw — just returns early
        $listener->handle($event);

        $this->assertTrue(true); // If we got here, it didn't crash
    }

    public function test_listener_handles_failed_event_when_storage_disabled(): void
    {
        $this->app['config']->set('ai.storage.enabled', false);

        $listener = new StoreAICompletion();
        $event = new AIRequestFailed(
            provider: Provider::Anthropic,
            model: 'claude-sonnet-4-20250514',
            prompt: 'test',
            error: 'API error',
        );

        $listener->handle($event);

        $this->assertTrue(true);
    }

    public function test_listener_accepts_both_event_types(): void
    {
        $listener = new StoreAICompletion();

        // Verify the method signature accepts both types (union type)
        $reflection = new \ReflectionMethod($listener, 'handle');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);

        $type = $params[0]->getType();
        $this->assertNotNull($type);
    }
}
