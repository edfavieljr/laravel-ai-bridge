# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **Streaming support** via `AI::streamText()` with PHP generators for OpenAI, Anthropic, and Gemini
- **Conversation history** via `AI::conversation()` for multi-turn chat with automatic context management
- **Structured output** via `AI::structured()` with JSON schema validation
- **Testing fakes** via `AI::fake()` with assertion methods (`assertCalled`, `assertCalledWith`, `assertNotCalled`, `assertNothingCalled`)
- **Event-driven architecture**: `AIRequestCompleted` and `AIRequestFailed` events dispatched on every AI call
- **`StoreAICompletion` listener** for database logging via events instead of direct coupling
- **`ProviderFactory`** for clean provider resolution through the container
- **`Provider` enum** replacing all magic strings for provider names
- **`HasAIInstrumentation` trait** extracting ~100 lines of duplicated cache/log/store logic from all services
- **Retry with backoff** via `retryRequest()` for transient errors (429, 503, 500)
- **`ai:purge` command** to clean old AI completion records from the database
- **Sensitive data redaction** in stored request/response data (API keys, tokens, etc.)
- **Injectable user resolver** replacing direct `auth()->id()` calls for queue compatibility
- `declare(strict_types=1)` in every PHP file (src and tests)
- PHPStan level 5 with zero errors
- GitHub Actions CI pipeline (PHP 8.2-8.4 x Laravel 11-12)
- `model()` fluent method to `OpenAIService` (was missing, other providers already had it)
- Cache support for `OpenAIService::generateEmbeddings()` (was missing)
- `storeError()` calls in `OpenAIService` catch blocks (was missing, events were not dispatched on failure)

### Changed
- **Default OpenAI model** from `gpt-4` to `gpt-4o`
- **Gemini API key** moved from URL query parameter to `x-goog-api-key` header (security fix)
- **Setup command models** updated to current versions (gpt-4o, claude-sonnet-4, gemini-2.0-flash)
- Trait `HasAICapabilities` refactored with consistent `ai*()` naming pattern
- All exceptions now include full context (errorType, provider, model)
- `AIServiceInterface::generateEmbeddings()` parameter typed as `string|array`
- `AIServiceInterface::getClient()` return type changed to `mixed`

### Deprecated
- Provider-specific facades (`OpenAI::`, `Anthropic::`, `Gemini::`, `HuggingFace::`) — use `AI::provider('name')` instead
- Helper functions (`ai()`, `ai_embed()`, `ai_sentiment()`, etc.) — use `AI::` facade instead
- Old trait method names (`completeText`, `analyzeSentimentOf`, `classifyAttribute`, etc.) — use `ai*()` methods instead

### Fixed
- Namespace mismatch: `YourVendor\LaravelAIBridge` replaced with `Edfavieljr\LaravelAIBridge` in 6 files
- Namespace case: `LaravelAiBridge` corrected to `LaravelAIBridge` in composer.json
- OpenAI cache key missing provider prefix (was `ai:method:hash`, now `ai:openai:method:hash`)
- `response_data` not redacted in `storeError()` (was only redacted in `storeSuccess()`)
- Unreferenced `$currentProvider` property in `AIService` causing potential runtime error

## [1.0.0] - 2025-01-01

### Added
- Initial release with OpenAI and HuggingFace support
- Basic text generation, embeddings, sentiment analysis, classification, entity extraction, image generation
- Facade, helpers, and Eloquent trait
- Configuration system with caching, logging, rate limiting, and storage options
