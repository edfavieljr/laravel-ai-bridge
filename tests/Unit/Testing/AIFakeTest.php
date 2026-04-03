<?php
declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Tests\Unit\Testing;

use Edfavieljr\LaravelAIBridge\Facades\AI;
use Edfavieljr\LaravelAIBridge\Testing\AIFake;
use Edfavieljr\LaravelAIBridge\Tests\TestCase;

class AIFakeTest extends TestCase
{
    public function test_fake_returns_ai_fake_instance(): void
    {
        $fake = AI::fake();

        $this->assertInstanceOf(AIFake::class, $fake);
    }

    public function test_fake_generate_text_returns_default(): void
    {
        AI::fake();

        $result = AI::generateText('hello');

        $this->assertEquals('', $result);
    }

    public function test_fake_generate_text_returns_configured_response(): void
    {
        AI::fake([
            'generateText' => 'Hello world!',
        ]);

        $result = AI::generateText('test prompt');

        $this->assertEquals('Hello world!', $result);
    }

    public function test_fake_with_closure_response(): void
    {
        AI::fake([
            'generateText' => fn (string $prompt) => "Echo: {$prompt}",
        ]);

        $result = AI::generateText('ping');

        $this->assertEquals('Echo: ping', $result);
    }

    public function test_fake_with_sequence_responses(): void
    {
        AI::fake([
            'generateText' => ['First', 'Second', 'Third'],
        ]);

        $this->assertEquals('First', AI::generateText('a'));
        $this->assertEquals('Second', AI::generateText('b'));
        $this->assertEquals('Third', AI::generateText('c'));
        // After exhausting, returns last
        $this->assertEquals('Third', AI::generateText('d'));
    }

    public function test_assert_called(): void
    {
        AI::fake();

        AI::generateText('hello');

        AI::assertCalled('generateText');
    }

    public function test_assert_called_with_count(): void
    {
        AI::fake();

        AI::generateText('one');
        AI::generateText('two');

        AI::assertCalled('generateText', 2);
    }

    public function test_assert_called_with_callback(): void
    {
        AI::fake();

        AI::generateText('find the bug', ['model' => 'gpt-4o']);

        AI::assertCalledWith('generateText', function (string $prompt, array $options) {
            return str_contains($prompt, 'bug') && $options['model'] === 'gpt-4o';
        });
    }

    public function test_assert_not_called(): void
    {
        AI::fake();

        AI::generateText('hello');

        AI::assertNotCalled('generateImage');
    }

    public function test_assert_nothing_called(): void
    {
        AI::fake();

        AI::assertNothingCalled();
    }

    public function test_fake_analyze_sentiment_returns_default(): void
    {
        AI::fake();

        $result = AI::analyzeSentiment('text');

        $this->assertEquals(0, $result['score']);
        $this->assertEquals('neutral', $result['category']);
    }

    public function test_fake_analyze_sentiment_returns_configured(): void
    {
        AI::fake([
            'analyzeSentiment' => ['score' => 0.9, 'category' => 'positive'],
        ]);

        $result = AI::analyzeSentiment('great!');

        $this->assertEquals(0.9, $result['score']);
        $this->assertEquals('positive', $result['category']);
    }

    public function test_fake_classify_text_returns_first_category(): void
    {
        AI::fake();

        $result = AI::classifyText('text', ['tech', 'sports']);

        $this->assertEquals('tech', $result['category']);
    }

    public function test_fake_generate_image_returns_default_url(): void
    {
        AI::fake();

        $result = AI::generateImage('a cat');

        $this->assertEquals('https://fake.test/image.png', $result);
    }

    public function test_fake_extract_entities_returns_empty_array(): void
    {
        AI::fake();

        $result = AI::extractEntities('text');

        $this->assertEquals([], $result);
    }

    public function test_fake_generate_embeddings_returns_default(): void
    {
        AI::fake();

        $result = AI::generateEmbeddings('text');

        $this->assertIsArray($result);
    }

    public function test_fake_get_client_returns_null(): void
    {
        AI::fake();

        $this->assertNull(AI::getClient());
    }

    public function test_fake_records_all_calls(): void
    {
        $fake = AI::fake();

        AI::generateText('hello');
        AI::analyzeSentiment('world');

        $recorded = $fake->recorded();

        $this->assertArrayHasKey('generateText', $recorded);
        $this->assertArrayHasKey('analyzeSentiment', $recorded);
        $this->assertCount(1, $recorded['generateText']);
        $this->assertCount(1, $recorded['analyzeSentiment']);
    }

    public function test_fake_provider_and_model_return_self(): void
    {
        $fake = AI::fake();

        $result = $fake->provider('anthropic')->model('claude-sonnet-4-20250514');

        $this->assertInstanceOf(AIFake::class, $result);
    }
}
