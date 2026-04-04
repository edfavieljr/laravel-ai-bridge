<?php
declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Tests\Unit\Services;

use Edfavieljr\LaravelAIBridge\Facades\AI;
use Edfavieljr\LaravelAIBridge\Testing\AIFake;
use Edfavieljr\LaravelAIBridge\Tests\TestCase;

class StreamingTest extends TestCase
{
    public function test_fake_stream_returns_generator(): void
    {
        AI::fake(['streamText' => 'Hello world']);

        $chunks = [];
        foreach (AI::streamText('prompt') as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertEquals(['Hello world'], $chunks);
    }

    public function test_fake_stream_with_multiple_chunks(): void
    {
        AI::fake([
            'streamText' => ['Hello ', 'beautiful ', 'world!'],
        ]);

        $chunks = [];
        foreach (AI::streamText('prompt') as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertEquals(['Hello ', 'beautiful ', 'world!'], $chunks);
    }

    public function test_fake_stream_records_call(): void
    {
        $fake = AI::fake(['streamText' => 'test']);

        iterator_to_array(AI::streamText('my prompt'));

        $fake->assertCalled('streamText');
        $fake->assertCalledWith('streamText', function (string $prompt) {
            return $prompt === 'my prompt';
        });
    }

    public function test_fake_stream_default_is_empty_string(): void
    {
        AI::fake();

        $chunks = iterator_to_array(AI::streamText('prompt'));

        $this->assertEquals([''], $chunks);
    }
}
