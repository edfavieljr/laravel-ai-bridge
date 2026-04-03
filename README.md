# Laravel AI Bridge

A unified, testable API for integrating multiple AI providers into Laravel applications.

Supports **OpenAI**, **Anthropic Claude**, **Google Gemini**, and **HuggingFace** — with streaming, conversations, structured output, automatic fallback, caching, and built-in observability.

## Why This Package?

- **One API, four providers** — switch providers with one line, not a rewrite
- **Laravel 10, 11, and 12** — the official Laravel AI SDK requires 12+. This doesn't.
- **`AI::fake()` built in** — test your AI-powered code without HTTP mocking
- **Event-driven observability** — every AI call fires events you can listen to for logging, metrics, or alerting
- **Database logging** — track tokens, costs, and usage out of the box

## Installation

```bash
composer require edfavieljr/laravel-ai-bridge
```

Publish the config:

```bash
php artisan vendor:publish --tag=ai-config
```

Add your API key to `.env`:

```env
AI_PROVIDER=openai
OPENAI_API_KEY=your-key-here
```

That's it. You're ready.

## Quick Start

```php
use Edfavieljr\LaravelAIBridge\Facades\AI;

// Generate text
$response = AI::generateText('Explain quantum computing in simple terms');

// Switch provider on the fly
$response = AI::provider('anthropic')->generateText('Write a haiku about code');

// Switch model
$response = AI::provider('openai')->model('gpt-4o-mini')->generateText('Summarize this');
```

## Streaming

Real-time token streaming with PHP generators. Works with OpenAI, Anthropic, and Gemini.

```php
foreach (AI::streamText('Write a story about a robot') as $chunk) {
    echo $chunk; // Arrives in real time
    ob_flush();
}
```

## Conversations

Multi-turn chat with automatic history management.

```php
$chat = AI::conversation()
    ->system('You are a helpful math tutor.');

$chat->send('What is calculus?');
// "Calculus is a branch of mathematics..."

$chat->reply('Give me an example');
// "Sure! Consider the function f(x) = x²..."

// Full history available
$chat->messages(); // [['role' => 'user', ...], ['role' => 'assistant', ...], ...]
```

## Structured Output

Force JSON responses with automatic schema validation.

```php
$result = AI::structured('Extract info from: John Doe, 30 years old, lives in NYC', [
    'name' => 'string',
    'age'  => 'integer',
    'city' => 'string',
])->generate();

// ['name' => 'John Doe', 'age' => 30, 'city' => 'NYC']
```

Throws `AIException` if the response doesn't match your schema.

## All Methods

```php
// Text generation
AI::generateText('prompt', ['model' => 'gpt-4o', 'temperature' => 0.7]);

// Streaming
AI::streamText('prompt');

// Embeddings
AI::generateEmbeddings('text to embed');

// Sentiment analysis
AI::analyzeSentiment('I love this product!');
// ['score' => 0.9, 'category' => 'positive']

// Text classification
AI::classifyText('The battery drains fast', ['hardware', 'software', 'battery']);
// ['category' => 'battery', 'confidence' => 0.92]

// Entity extraction
AI::extractEntities('Apple CEO Tim Cook announced...');
// [['entity' => 'Apple', 'type' => 'ORG'], ['entity' => 'Tim Cook', 'type' => 'PERSON']]

// Image generation (OpenAI, HuggingFace)
AI::generateImage('A sunset over mountains');

// Conversations
AI::conversation()->system('...')->send('...');

// Structured output
AI::structured('prompt', ['key' => 'type'])->generate();
```

## Testing with `AI::fake()`

No HTTP mocking needed. No API keys required in tests.

```php
use Edfavieljr\LaravelAIBridge\Facades\AI;

// Simple fake — returns default empty values
AI::fake();

// Fake with configured responses
AI::fake([
    'generateText' => 'Hello world!',
    'analyzeSentiment' => ['score' => 0.9, 'category' => 'positive'],
]);

// Use closures for dynamic responses
AI::fake([
    'generateText' => fn (string $prompt) => "Echo: {$prompt}",
]);

// Assertions
AI::assertCalled('generateText');
AI::assertCalled('generateText', 2); // Called exactly 2 times
AI::assertCalledWith('generateText', fn ($prompt) => str_contains($prompt, 'hello'));
AI::assertNotCalled('generateImage');
AI::assertNothingCalled();

// Streaming fake
AI::fake([
    'streamText' => ['chunk 1', 'chunk 2', 'chunk 3'],
]);

// Conversations and structured output work with fakes too
AI::fake(['generateText' => '{"name": "John"}']);
$result = AI::structured('extract', ['name' => 'string'])->generate();
```

## Eloquent Integration

Add AI capabilities to any model:

```php
use Edfavieljr\LaravelAIBridge\Traits\HasAICapabilities;

class Article extends Model
{
    use HasAICapabilities;

    // Optional: configure default provider/model for this model
    protected function aiConfig(): array
    {
        return [
            'provider' => 'anthropic',
            'model' => 'claude-sonnet-4-20250514',
        ];
    }
}
```

```php
$article = Article::find(1);

$article->aiSummarize('body', 200);
$article->aiTranslate('title', 'Spanish');
$article->aiAnalyzeSentiment('content');
$article->aiClassify('body', ['tech', 'science', 'politics']);
$article->aiExtractEntities('body');
$article->aiGenerateImage('description');
$article->aiEmbed('body');
$article->aiGenerateText('body', 'Rewrite this to be more compelling: %s');
```

## Events

Every AI call dispatches events. Listen to them for custom logging, metrics, or alerting.

```php
// In a service provider or listener
use Edfavieljr\LaravelAIBridge\Events\AIRequestCompleted;
use Edfavieljr\LaravelAIBridge\Events\AIRequestFailed;

Event::listen(AIRequestCompleted::class, function (AIRequestCompleted $event) {
    // $event->provider  (Provider enum)
    // $event->model     (string)
    // $event->prompt    (string)
    // $event->completion (string)
    // $event->metadata  (array: tokens, execution_time, etc.)
});

Event::listen(AIRequestFailed::class, function (AIRequestFailed $event) {
    // $event->error (string)
    // Alert your team, track failure rates, etc.
});
```

## Configuration

### Fallback

If a provider fails, automatically try the next one:

```php
// config/ai.php
'fallback' => [
    'enabled' => true,
    'providers' => ['openai', 'anthropic', 'gemini', 'huggingface'],
],
```

### Caching

Cache identical requests to reduce costs:

```php
'cache' => [
    'enabled' => true,
    'ttl' => 60, // minutes
],
```

### Rate Limiting

```php
'rate_limiting' => [
    'enabled' => true,
    'max_requests_per_minute' => 60,
],
```

### Database Logging

Track all AI calls with token counts and execution time:

```bash
php artisan vendor:publish --tag=ai-migrations
php artisan migrate
```

```php
// config/ai.php
'storage' => [
    'enabled' => true,
    'purge_after_days' => 30,
],
```

```php
// Purge old records
php artisan ai:purge
php artisan ai:purge --days=7
```

```php
use Edfavieljr\LaravelAIBridge\Models\AICompletion;

AICompletion::fromProvider('openai')->get();
AICompletion::successful()->get();
AICompletion::failed()->get();
AICompletion::getTokenUsageSummary();
```

### Provider Configuration

```env
# OpenAI
OPENAI_API_KEY=sk-...
OPENAI_DEFAULT_MODEL=gpt-4o

# Anthropic
ANTHROPIC_API_KEY=sk-ant-...
ANTHROPIC_DEFAULT_MODEL=claude-sonnet-4-20250514

# Google Gemini
GEMINI_API_KEY=...
GEMINI_DEFAULT_MODEL=gemini-2.0-flash

# HuggingFace
HUGGINGFACE_API_KEY=hf_...
HUGGINGFACE_DEFAULT_MODEL=gpt2
```

## Provider Capabilities

| Feature | OpenAI | Anthropic | Gemini | HuggingFace |
|---------|--------|-----------|--------|-------------|
| Text generation | Yes | Yes | Yes | Yes |
| Streaming | Yes | Yes | Yes | Fallback |
| Embeddings | Yes | No | Yes | Yes |
| Image generation | Yes | No | No | Yes |
| Sentiment analysis | Yes | Yes | Yes | Yes |
| Classification | Yes | Yes | Yes | Yes |
| Entity extraction | Yes | Yes | Yes | Yes |

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

MIT. See [LICENSE.md](LICENSE.md).
