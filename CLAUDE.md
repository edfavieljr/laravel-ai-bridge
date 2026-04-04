# CLAUDE.md — Laravel AI Bridge

## What is this?

A Laravel package that provides a unified API for multiple AI providers (OpenAI, Anthropic, Gemini, HuggingFace). One facade, four providers, switchable at runtime.

**Not** an AI application — it's a **library** consumed via `composer require`.

## Quick reference

```bash
composer test        # Run all tests (200 tests, 370 assertions)
composer analyse     # PHPStan level 5
```

## Architecture

```
src/
├── Contracts/AIServiceInterface.php    # The contract — all providers implement this
├── Enums/Provider.php                  # Provider enum (no magic strings)
├── Services/
│   ├── AIService.php                   # Orchestrator: fallback, rate limiting, model switching
│   ├── OpenAIService.php               # OpenAI implementation
│   ├── AnthropicService.php            # Anthropic Claude implementation
│   ├── GeminiService.php               # Google Gemini implementation
│   ├── HuggingFaceService.php          # HuggingFace implementation
│   ├── ProviderFactory.php             # Resolves Provider enum → service instance
│   └── Concerns/HasAIInstrumentation.php  # Shared trait: cache, logging, events, redaction
├── Support/
│   ├── Conversation.php                # Multi-turn chat with native messages
│   └── StructuredOutput.php            # JSON schema validation on responses
├── Testing/AIFake.php                  # AI::fake() for consumer testing
├── Facades/AI.php                      # Primary entry point
├── Events/                             # AIRequestCompleted, AIRequestFailed
├── Listeners/StoreAICompletion.php     # DB storage via events (decoupled)
└── AIBridgeServiceProvider.php         # Deferred provider
```

## Key conventions

- **`declare(strict_types=1)`** in every PHP file — src and tests
- **`Provider` enum** — never use `'openai'` strings, use `Provider::OpenAI`
- **`HasAIInstrumentation` trait** — all services use it for cache, logging, events, and data redaction. Don't duplicate that logic.
- **Events over direct coupling** — services dispatch `AIRequestCompleted`/`AIRequestFailed`. The listener handles DB storage. Don't call `AICompletion::create*()` directly from services.
- **`generateChat()`** for conversations — takes native `messages` array. Each provider formats it for their API. Don't concatenate messages into a single string.
- **`generateText()`** for single prompts — wraps the prompt in a single user message internally.
- **Sensitive data is redacted** before storage via `redactSensitiveData()` in the trait. Both `request_data` and `response_data` are redacted in `storeSuccess()` and `storeError()`.
- **Gemini API key** goes in the `x-goog-api-key` header, never in the URL.

## Adding a new provider

1. Add a case to `src/Enums/Provider.php`
2. Create `src/Services/YourProviderService.php` implementing `AIServiceInterface` and using `HasAIInstrumentation`
3. Implement `providerName(): Provider`
4. Add to `ProviderFactory::make()` match statement
5. Register singleton in `AIBridgeServiceProvider::register()` and add to `provides()`
6. Add config in `config/ai.php` under `providers`
7. Write tests — use `Http::fake()` with `https://` prefix in URL patterns

## Testing patterns

```php
// Unit test for a service — create instance AFTER Http::fake()
Http::fake(['https://api.openai.com/*' => Http::response([...], 200)]);
$service = new OpenAIService('key', null, ['default_model' => 'gpt-4o']);
$result = $service->generateText('test');

// Integration test via facade — use AI::fake()
AI::fake(['generateText' => 'mocked']);
$result = AI::generateText('prompt');
AI::assertCalled('generateText');

// DB integration tests use RefreshDatabase + inline Schema::create
// (see tests/Integration/DatabaseStorageTest.php)
```

**Important**: Services are registered as singletons. In tests that use `Http::fake()`, create service instances manually (`new OpenAIService(...)`) AFTER the fake — otherwise the HTTP client won't be faked.

## What NOT to do

- Don't call `config()` in service constructors — use the injected `$options` array
- Don't call `auth()->id()` directly — use `$this->resolveUserId()` from the trait
- Don't call `AICompletion::createSuccess/Error()` from services — use `$this->storeSuccess/Error()` which dispatches events
- Don't add provider-specific facades — they're deprecated. Use `AI::provider('name')`
- Don't add helper functions — they're deprecated. Use the `AI` facade
- Don't use old trait method names (`completeText`, `analyzeSentimentOf`, etc.) — use `ai*()` methods

## Deprecations (will be removed in v2.0)

- Facades: `OpenAI::`, `Anthropic::`, `Gemini::`, `HuggingFace::` → use `AI::provider('name')`
- Helpers: `ai()`, `ai_embed()`, `ai_sentiment()`, etc. → use `AI::` facade
- Trait methods: `completeText()`, `analyzeSentimentOf()`, etc. → use `aiGenerateText()`, `aiAnalyzeSentiment()`, etc.

## Dependencies

- PHP 8.1+
- Laravel 10, 11, or 12 (`illuminate/support`, `illuminate/http`)
- No external AI SDKs — all providers use Laravel's HTTP client directly
