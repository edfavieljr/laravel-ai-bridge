<?php
declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Tests\Integration;

use Edfavieljr\LaravelAIBridge\Enums\Provider;
use Edfavieljr\LaravelAIBridge\Events\AIRequestCompleted;
use Edfavieljr\LaravelAIBridge\Events\AIRequestFailed;
use Edfavieljr\LaravelAIBridge\Listeners\StoreAICompletion;
use Edfavieljr\LaravelAIBridge\Models\AICompletion;
use Edfavieljr\LaravelAIBridge\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

class DatabaseStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('ai_completions', function ($table) {
            $table->id();
            $table->string('provider')->index();
            $table->string('model')->index();
            $table->text('prompt');
            $table->text('completion')->nullable();
            $table->integer('prompt_tokens')->nullable();
            $table->integer('completion_tokens')->nullable();
            $table->integer('total_tokens')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->json('request_data')->nullable();
            $table->json('response_data')->nullable();
            $table->float('execution_time')->nullable();
            $table->string('status')->default('success')->index();
            $table->text('error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    // --- createSuccess ---

    public function test_create_success_persists_all_fields(): void
    {
        $record = AICompletion::createSuccess(
            'openai',
            'gpt-4o',
            'What is AI?',
            'AI is artificial intelligence.',
            [
                'prompt_tokens' => 10,
                'completion_tokens' => 8,
                'total_tokens' => 18,
                'user_id' => 42,
                'execution_time' => 1.5,
                'request_data' => ['model' => 'gpt-4o'],
                'response_data' => ['id' => 'chatcmpl-123'],
                'metadata' => ['source' => 'test'],
            ]
        );

        $this->assertDatabaseHas('ai_completions', [
            'id' => $record->id,
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'prompt' => 'What is AI?',
            'completion' => 'AI is artificial intelligence.',
            'prompt_tokens' => 10,
            'completion_tokens' => 8,
            'total_tokens' => 18,
            'user_id' => 42,
            'status' => 'success',
        ]);

        $fresh = AICompletion::find($record->id);
        $this->assertEquals(1.5, $fresh->execution_time);
        $this->assertEquals(['model' => 'gpt-4o'], $fresh->request_data);
        $this->assertEquals(['id' => 'chatcmpl-123'], $fresh->response_data);
        $this->assertEquals(['source' => 'test'], $fresh->metadata);
    }

    public function test_create_success_calculates_total_tokens(): void
    {
        $record = AICompletion::createSuccess(
            'anthropic',
            'claude-sonnet-4-20250514',
            'prompt',
            'response',
            [
                'prompt_tokens' => 25,
                'completion_tokens' => 15,
            ]
        );

        $this->assertEquals(40, AICompletion::find($record->id)->total_tokens);
    }

    // --- createError ---

    public function test_create_error_persists_correctly(): void
    {
        $record = AICompletion::createError(
            'gemini',
            'gemini-2.0-flash',
            'broken prompt',
            'API rate limit exceeded',
            [
                'user_id' => 7,
                'execution_time' => 0.3,
            ]
        );

        $this->assertDatabaseHas('ai_completions', [
            'id' => $record->id,
            'provider' => 'gemini',
            'status' => 'error',
            'error' => 'API rate limit exceeded',
            'user_id' => 7,
        ]);
    }

    // --- Scopes ---

    public function test_from_provider_scope(): void
    {
        AICompletion::createSuccess('openai', 'gpt-4o', 'p1', 'r1');
        AICompletion::createSuccess('anthropic', 'claude', 'p2', 'r2');
        AICompletion::createSuccess('openai', 'gpt-4o', 'p3', 'r3');

        $this->assertCount(2, AICompletion::fromProvider('openai')->get());
        $this->assertCount(1, AICompletion::fromProvider('anthropic')->get());
    }

    public function test_successful_and_failed_scopes(): void
    {
        AICompletion::createSuccess('openai', 'gpt-4o', 'p1', 'r1');
        AICompletion::createSuccess('openai', 'gpt-4o', 'p2', 'r2');
        AICompletion::createError('openai', 'gpt-4o', 'p3', 'error');

        $this->assertCount(2, AICompletion::successful()->get());
        $this->assertCount(1, AICompletion::failed()->get());
    }

    public function test_using_model_scope(): void
    {
        AICompletion::createSuccess('openai', 'gpt-4o', 'p1', 'r1');
        AICompletion::createSuccess('openai', 'gpt-4o-mini', 'p2', 'r2');

        $this->assertCount(1, AICompletion::usingModel('gpt-4o')->get());
        $this->assertCount(1, AICompletion::usingModel('gpt-4o-mini')->get());
    }

    public function test_created_between_scope(): void
    {
        $old = AICompletion::createSuccess('openai', 'gpt-4o', 'old', 'r');
        $old->created_at = now()->subDays(10);
        $old->save();

        $recent = AICompletion::createSuccess('openai', 'gpt-4o', 'recent', 'r');

        $results = AICompletion::query()->createdBetween(now()->subDays(2))->get();

        $this->assertCount(1, $results);
        $this->assertEquals('recent', $results->first()->prompt);
    }

    public function test_for_user_scope(): void
    {
        AICompletion::createSuccess('openai', 'gpt-4o', 'p1', 'r1', ['user_id' => 1]);
        AICompletion::createSuccess('openai', 'gpt-4o', 'p2', 'r2', ['user_id' => 2]);
        AICompletion::createSuccess('openai', 'gpt-4o', 'p3', 'r3', ['user_id' => 1]);

        $this->assertCount(2, AICompletion::query()->forUser(1)->get());
        $this->assertCount(1, AICompletion::query()->forUser(2)->get());
    }

    // --- Token Usage Summary ---

    public function test_get_token_usage_summary(): void
    {
        AICompletion::createSuccess('openai', 'gpt-4o', 'p1', 'r1', [
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'total_tokens' => 150,
        ]);
        AICompletion::createSuccess('openai', 'gpt-4o', 'p2', 'r2', [
            'prompt_tokens' => 200,
            'completion_tokens' => 100,
            'total_tokens' => 300,
        ]);

        $summary = AICompletion::getTokenUsageSummary();

        $this->assertEquals(300, $summary['total_prompt_tokens']);
        $this->assertEquals(150, $summary['total_completion_tokens']);
        $this->assertEquals(450, $summary['total_tokens']);
        $this->assertEquals(2, $summary['request_count']);
        $this->assertEquals(225, $summary['average_tokens_per_request']);
    }

    // --- Purge Command ---

    public function test_purge_command_deletes_old_records(): void
    {
        $old = AICompletion::createSuccess('openai', 'gpt-4o', 'old', 'r');
        $old->created_at = now()->subDays(60);
        $old->save();

        $recent = AICompletion::createSuccess('openai', 'gpt-4o', 'recent', 'r');

        $this->artisan('ai:purge', ['--days' => 30])
            ->expectsOutputToContain('Purged 1')
            ->assertSuccessful();

        $this->assertDatabaseMissing('ai_completions', ['id' => $old->id]);
        $this->assertDatabaseHas('ai_completions', ['id' => $recent->id]);
    }

    public function test_purge_command_with_no_old_records(): void
    {
        AICompletion::createSuccess('openai', 'gpt-4o', 'recent', 'r');

        $this->artisan('ai:purge', ['--days' => 30])
            ->expectsOutputToContain('Purged 0')
            ->assertSuccessful();
    }

    // --- Listener Integration ---

    public function test_listener_stores_completed_event(): void
    {
        $this->app['config']->set('ai.storage.enabled', true);

        $listener = new StoreAICompletion();
        $listener->handle(new AIRequestCompleted(
            provider: Provider::OpenAI,
            model: 'gpt-4o',
            prompt: 'Hello',
            completion: 'World',
            metadata: [
                'prompt_tokens' => 5,
                'completion_tokens' => 3,
                'total_tokens' => 8,
                'execution_time' => 0.5,
            ],
        ));

        $this->assertDatabaseHas('ai_completions', [
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'prompt' => 'Hello',
            'completion' => 'World',
            'status' => 'success',
        ]);
    }

    public function test_listener_stores_failed_event(): void
    {
        $this->app['config']->set('ai.storage.enabled', true);

        $listener = new StoreAICompletion();
        $listener->handle(new AIRequestFailed(
            provider: Provider::Anthropic,
            model: 'claude-sonnet-4-20250514',
            prompt: 'test prompt',
            error: 'Rate limit exceeded',
            metadata: ['execution_time' => 0.1],
        ));

        $this->assertDatabaseHas('ai_completions', [
            'provider' => 'anthropic',
            'status' => 'error',
            'error' => 'Rate limit exceeded',
        ]);
    }

    public function test_listener_skips_when_storage_disabled(): void
    {
        $this->app['config']->set('ai.storage.enabled', false);

        $listener = new StoreAICompletion();
        $listener->handle(new AIRequestCompleted(
            provider: Provider::OpenAI,
            model: 'gpt-4o',
            prompt: 'ignored',
            completion: 'ignored',
        ));

        $this->assertDatabaseCount('ai_completions', 0);
    }
}
