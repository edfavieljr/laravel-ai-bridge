# Contributing to Laravel AI Bridge

Thank you for considering contributing to Laravel AI Bridge! This document outlines the process for contributing and the standards we follow.

## Code of Conduct

By participating in this project, you agree to abide by the [Code of Conduct](CODE_OF_CONDUCT.md). Please read it before contributing.

## Getting Started

1. **Fork the repository** on GitHub

2. **Clone your fork**
   ```bash
   git clone https://github.com/YOUR-USERNAME/laravel-ai-bridge.git
   cd laravel-ai-bridge
   ```

3. **Install dependencies**
   ```bash
   composer install
   ```

4. **Create a branch**
   ```bash
   git checkout -b feature/your-feature-name
   # or
   git checkout -b fix/your-bugfix-name
   ```

5. **Verify everything works**
   ```bash
   ./vendor/bin/phpunit
   ./vendor/bin/phpstan analyse
   ```

## Development Standards

### Code Style

- `declare(strict_types=1)` in every PHP file — no exceptions
- Return types on all public methods
- Use the `Provider` enum (`src/Enums/Provider.php`) instead of magic strings
- Use the `HasAIInstrumentation` trait for shared service logic (caching, logging, storage, error handling)
- Classes that are not designed for extension should be marked `final`

### Testing

Every feature or bug fix must include tests. We use PHPUnit with Orchestra Testbench:

```bash
./vendor/bin/phpunit
```

When testing AI service calls, use `Http::fake()` for direct service tests and `AI::fake()` for integration tests:

```php
// Direct service test
Http::fake([
    'https://api.openai.com/*' => Http::response([...], 200),
]);
$service = new OpenAIService('key', null, ['default_model' => 'gpt-4o']);
$result = $service->generateText('test');

// Integration test using the Fake system
AI::fake(['generateText' => 'mocked response']);
// ... run your application code ...
AI::assertCalled('generateText');
```

### Static Analysis

We use PHPStan at level 5:

```bash
./vendor/bin/phpstan analyse
```

All contributions must pass with zero errors.

### Documentation

- Update README.md (and README.es.md) if you change the public API
- Update CHANGELOG.md following [Keep a Changelog](https://keepachangelog.com/) format
- Add PHPDoc blocks to public methods

## Adding a New AI Provider

This is the most common type of contribution. Follow these steps:

1. **Create the service class** at `src/Services/YourProviderService.php`
   - Implement `AIServiceInterface`
   - Use the `HasAIInstrumentation` trait (gives you caching, logging, events, error storage for free)
   - Implement `providerName(): Provider` returning the enum value
   - Handle provider-specific API quirks in your methods
   - Throw `AIException::missingCapability()` for features the provider doesn't support

2. **Add a case to the `Provider` enum** at `src/Enums/Provider.php`

3. **Register the service** in `AIBridgeServiceProvider::register()`

4. **Add the provider to `ProviderFactory`** at `src/Services/ProviderFactory.php`

5. **Add configuration** in `config/ai.php` under the `providers` key

6. **Write tests** covering at minimum:
   - Text generation (success + error)
   - All supported capabilities
   - `missingCapability` exceptions for unsupported features
   - Header/auth verification
   - Model switching

7. **Update documentation**: README, README.es.md, CHANGELOG.md, and the provider capabilities table

### Example: Minimal provider service

```php
<?php

declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Services;

use Edfavieljr\LaravelAIBridge\Contracts\AIServiceInterface;
use Edfavieljr\LaravelAIBridge\Enums\Provider;
use Edfavieljr\LaravelAIBridge\Services\Concerns\HasAIInstrumentation;

class YourProviderService implements AIServiceInterface
{
    use HasAIInstrumentation;

    protected function providerName(): Provider
    {
        return Provider::YourProvider;
    }

    // ... implement interface methods
}
```

## Pull Request Process

1. **Ensure all tests pass** and PHPStan reports zero errors
2. **Update your fork** with the latest `main` branch
3. **Submit a PR** with a clear description of what changed and why
4. **One reviewer** will review your code — address any feedback
5. Once approved, a maintainer will merge

## Reporting Bugs

Use GitHub Issues. Include:
- PHP and Laravel version
- Steps to reproduce
- Expected vs actual behavior
- Error messages or stack traces

## Versioning

We follow [Semantic Versioning](https://semver.org/):

- **MAJOR** — incompatible API changes
- **MINOR** — backward-compatible new features
- **PATCH** — backward-compatible bug fixes

## License

By contributing, you agree that your contributions will be licensed under the project's [MIT License](LICENSE.md).
