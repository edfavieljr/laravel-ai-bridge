<?php
declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Tests\Unit\Support;

use Edfavieljr\LaravelAIBridge\Exceptions\AIException;
use Edfavieljr\LaravelAIBridge\Facades\AI;
use Edfavieljr\LaravelAIBridge\Support\StructuredOutput;
use Edfavieljr\LaravelAIBridge\Tests\TestCase;

class StructuredOutputTest extends TestCase
{
    public function test_generates_structured_json(): void
    {
        AI::fake([
            'generateText' => '{"name": "John", "age": 30}',
        ]);

        $result = AI::structured('Extract name and age', [
            'name' => 'string',
            'age' => 'integer',
        ])->generate();

        $this->assertEquals('John', $result['name']);
        $this->assertEquals(30, $result['age']);
    }

    public function test_strips_markdown_code_fences(): void
    {
        AI::fake([
            'generateText' => "```json\n{\"name\": \"Alice\"}\n```",
        ]);

        $result = AI::structured('Extract name', [
            'name' => 'string',
        ])->generate();

        $this->assertEquals('Alice', $result['name']);
    }

    public function test_extracts_json_from_mixed_response(): void
    {
        AI::fake([
            'generateText' => 'Here is the result: {"name": "Bob", "score": 0.9} hope this helps!',
        ]);

        $result = AI::structured('Get name and score', [
            'name' => 'string',
            'score' => 'number',
        ])->generate();

        $this->assertEquals('Bob', $result['name']);
    }

    public function test_throws_on_invalid_json(): void
    {
        AI::fake([
            'generateText' => 'This is not JSON at all',
        ]);

        $this->expectException(AIException::class);
        $this->expectExceptionMessage('Failed to parse');

        AI::structured('Extract data', [
            'name' => 'string',
        ])->generate();
    }

    public function test_throws_on_missing_required_key(): void
    {
        AI::fake([
            'generateText' => '{"name": "John"}',
        ]);

        $this->expectException(AIException::class);
        $this->expectExceptionMessage('missing required key: age');

        AI::structured('Extract data', [
            'name' => 'string',
            'age' => 'integer',
        ])->generate();
    }

    public function test_throws_on_wrong_type(): void
    {
        AI::fake([
            'generateText' => '{"name": 123}',
        ]);

        $this->expectException(AIException::class);
        $this->expectExceptionMessage("expected type 'string'");

        AI::structured('Extract data', [
            'name' => 'string',
        ])->generate();
    }

    public function test_with_options(): void
    {
        AI::fake([
            'generateText' => '{"result": "ok"}',
        ]);

        $result = AI::structured('Test', ['result' => 'string'])
            ->withOptions(['model' => 'gpt-4o'])
            ->generate();

        $this->assertEquals('ok', $result['result']);
    }

    public function test_accepts_mixed_type(): void
    {
        AI::fake([
            'generateText' => '{"data": null}',
        ]);

        $result = AI::structured('Test', ['data' => 'mixed'])->generate();

        $this->assertNull($result['data']);
    }
}
