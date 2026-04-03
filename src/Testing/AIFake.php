<?php

declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Testing;

use Closure;
use PHPUnit\Framework\Assert;
use Edfavieljr\LaravelAIBridge\Contracts\AIServiceInterface;
use Edfavieljr\LaravelAIBridge\Support\Conversation;
use Edfavieljr\LaravelAIBridge\Support\StructuredOutput;

class AIFake implements AIServiceInterface
{
    /** @var array<string, mixed> */
    protected array $responses;

    /** @var array<string, list<array{args: array}>> */
    protected array $recorded = [];

    protected string $currentProvider = 'openai';
    protected ?string $currentModel = null;

    public function __construct(array $responses = [])
    {
        $this->responses = $responses;
    }

    public function provider(string $provider): self
    {
        $this->currentProvider = $provider;
        return $this;
    }

    public function model(string $model): self
    {
        $this->currentModel = $model;
        return $this;
    }

    public function generateText(string $prompt, array $options = []): string
    {
        return $this->record('generateText', [$prompt, $options], '');
    }

    public function generateEmbeddings(string|array $input, array $options = []): array
    {
        return $this->record('generateEmbeddings', [$input, $options], [[0.0]]);
    }

    public function analyzeSentiment(string $text, array $options = []): array
    {
        return $this->record('analyzeSentiment', [$text, $options], [
            'score' => 0,
            'category' => 'neutral',
        ]);
    }

    public function classifyText(string $text, array $categories, array $options = []): array
    {
        return $this->record('classifyText', [$text, $categories, $options], [
            'category' => $categories[0] ?? '',
            'confidence' => 0,
        ]);
    }

    public function generateImage(string $prompt, array $options = []): string
    {
        return $this->record('generateImage', [$prompt, $options], 'https://fake.test/image.png');
    }

    public function extractEntities(string $text, array $options = []): array
    {
        return $this->record('extractEntities', [$text, $options], []);
    }

    public function generateChat(array $messages, array $options = []): string
    {
        return $this->record('generateChat', [$messages, $options], '');
    }

    public function conversation(): Conversation
    {
        return new Conversation($this);
    }

    public function structured(string $prompt, array $schema): StructuredOutput
    {
        return new StructuredOutput($prompt, $schema, $this);
    }

    public function streamText(string $prompt, array $options = []): \Generator
    {
        $this->recorded['streamText'][] = ['args' => [$prompt, $options]];

        $response = $this->responses['streamText'] ?? '';

        if ($response instanceof Closure) {
            $response = $response($prompt, $options);
        }

        $chunks = is_array($response) ? $response : [$response];

        foreach ($chunks as $chunk) {
            yield $chunk;
        }
    }

    public function getClient(): mixed
    {
        return null;
    }

    // --- Recording & Assertions ---

    protected function record(string $method, array $args, mixed $default): mixed
    {
        $this->recorded[$method][] = ['args' => $args];

        $response = $this->responses[$method] ?? $default;

        if ($response instanceof Closure) {
            return $response(...$args);
        }

        if (is_array($response) && array_is_list($response) && isset($response[0])) {
            $index = count($this->recorded[$method]) - 1;
            return $response[$index] ?? $response[count($response) - 1];
        }

        return $response;
    }

    public function assertCalled(string $method, ?int $times = null): void
    {
        $count = count($this->recorded[$method] ?? []);

        if ($times !== null) {
            Assert::assertSame(
                $times,
                $count,
                "Expected [{$method}] to be called {$times} times, but was called {$count} times."
            );
        } else {
            Assert::assertTrue(
                $count > 0,
                "Expected [{$method}] to be called at least once, but it was not called."
            );
        }
    }

    public function assertCalledWith(string $method, Closure $callback): void
    {
        $calls = $this->recorded[$method] ?? [];

        Assert::assertTrue(
            count($calls) > 0,
            "Expected [{$method}] to be called at least once, but it was not called."
        );

        $matched = false;

        foreach ($calls as $call) {
            if ($callback(...$call['args'])) {
                $matched = true;
                break;
            }
        }

        Assert::assertTrue(
            $matched,
            "Expected [{$method}] to be called with matching arguments, but no matching call was found."
        );
    }

    public function assertNotCalled(string $method): void
    {
        $count = count($this->recorded[$method] ?? []);

        Assert::assertSame(
            0,
            $count,
            "Expected [{$method}] to not be called, but it was called {$count} times."
        );
    }

    public function assertNothingCalled(): void
    {
        $total = array_sum(array_map('count', $this->recorded));

        Assert::assertSame(
            0,
            $total,
            "Expected no AI methods to be called, but {$total} calls were recorded."
        );
    }

    /** @return array<string, list<array{args: array}>> */
    public function recorded(?string $method = null): array
    {
        if ($method !== null) {
            return [$method => $this->recorded[$method] ?? []];
        }

        return $this->recorded;
    }
}
