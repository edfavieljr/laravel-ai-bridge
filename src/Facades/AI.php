<?php

declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Facades;

use Illuminate\Support\Facades\Facade;
use Edfavieljr\LaravelAIBridge\Services\AIService;
use Edfavieljr\LaravelAIBridge\Testing\AIFake;

/**
 * @method static string generateText(string $prompt, array $options = [])
 * @method static string generateChat(array $messages, array $options = [])
 * @method static array generateEmbeddings(string|array $input, array $options = [])
 * @method static array analyzeSentiment(string $text, array $options = [])
 * @method static array classifyText(string $text, array $categories, array $options = [])
 * @method static string generateImage(string $prompt, array $options = [])
 * @method static array extractEntities(string $text, array $options = [])
 * @method static \Generator streamText(string $prompt, array $options = [])
 * @method static \Edfavieljr\LaravelAIBridge\Support\Conversation conversation()
 * @method static \Edfavieljr\LaravelAIBridge\Support\StructuredOutput structured(string $prompt, array $schema)
 * @method static mixed getClient()
 * @method static self provider(string $provider)
 * @method static self model(string $model)
 *
 * @see \Edfavieljr\LaravelAIBridge\Services\AIService
 */
class AI extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AIService::class;
    }

    /**
     * Replace the bound instance with a fake for testing.
     */
    public static function fake(array $responses = []): AIFake
    {
        $fake = new AIFake($responses);

        static::swap($fake);

        return $fake;
    }

    /**
     * Assert a method was called on the fake.
     */
    public static function assertCalled(string $method, ?int $times = null): void
    {
        static::getFacadeRoot()->assertCalled($method, $times);
    }

    /**
     * Assert a method was called with specific arguments.
     */
    public static function assertCalledWith(string $method, \Closure $callback): void
    {
        static::getFacadeRoot()->assertCalledWith($method, $callback);
    }

    /**
     * Assert a method was not called.
     */
    public static function assertNotCalled(string $method): void
    {
        static::getFacadeRoot()->assertNotCalled($method);
    }

    /**
     * Assert nothing was called.
     */
    public static function assertNothingCalled(): void
    {
        static::getFacadeRoot()->assertNothingCalled();
    }
}
