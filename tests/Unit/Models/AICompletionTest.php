<?php
declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Tests\Unit\Models;

use Edfavieljr\LaravelAIBridge\Models\AICompletion;
use Edfavieljr\LaravelAIBridge\Tests\TestCase;

class AICompletionTest extends TestCase
{
    public function test_fillable_attributes(): void
    {
        $model = new AICompletion();
        $fillable = $model->getFillable();

        $this->assertContains('provider', $fillable);
        $this->assertContains('model', $fillable);
        $this->assertContains('prompt', $fillable);
        $this->assertContains('completion', $fillable);
        $this->assertContains('prompt_tokens', $fillable);
        $this->assertContains('completion_tokens', $fillable);
        $this->assertContains('total_tokens', $fillable);
        $this->assertContains('user_id', $fillable);
        $this->assertContains('request_data', $fillable);
        $this->assertContains('response_data', $fillable);
        $this->assertContains('execution_time', $fillable);
        $this->assertContains('status', $fillable);
        $this->assertContains('error', $fillable);
        $this->assertContains('metadata', $fillable);
    }

    public function test_casts_are_correct(): void
    {
        $model = new AICompletion();
        $casts = $model->getCasts();

        $this->assertEquals('integer', $casts['prompt_tokens']);
        $this->assertEquals('integer', $casts['completion_tokens']);
        $this->assertEquals('integer', $casts['total_tokens']);
        $this->assertEquals('float', $casts['execution_time']);
        $this->assertEquals('array', $casts['request_data']);
        $this->assertEquals('array', $casts['response_data']);
        $this->assertEquals('array', $casts['metadata']);
    }

    public function test_table_name(): void
    {
        $model = new AICompletion();
        $this->assertEquals('ai_completions', $model->getTable());
    }

    public function test_get_migration_returns_valid_php(): void
    {
        $migration = AICompletion::getMigration();

        $this->assertStringContainsString('Schema::create', $migration);
        $this->assertStringContainsString('ai_completions', $migration);
        $this->assertStringContainsString('provider', $migration);
        $this->assertStringContainsString('prompt', $migration);
        $this->assertStringContainsString('completion', $migration);
    }

    public function test_from_provider_returns_builder(): void
    {
        $builder = AICompletion::fromProvider('openai');
        $this->assertNotNull($builder);

        $sql = $builder->toSql();
        $this->assertStringContainsString('provider', $sql);
    }

    public function test_using_model_returns_builder(): void
    {
        $builder = AICompletion::usingModel('gpt-4');
        $sql = $builder->toSql();
        $this->assertStringContainsString('model', $sql);
    }

    public function test_successful_scope_returns_builder(): void
    {
        $builder = AICompletion::successful();
        $sql = $builder->toSql();
        $this->assertStringContainsString('status', $sql);
    }

    public function test_failed_scope_returns_builder(): void
    {
        $builder = AICompletion::failed();
        $sql = $builder->toSql();
        $this->assertStringContainsString('status', $sql);
    }
}
