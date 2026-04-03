<?php

declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Edfavieljr\LaravelAIBridge\Contracts\AIServiceInterface;
use Edfavieljr\LaravelAIBridge\Enums\Provider;
use Edfavieljr\LaravelAIBridge\Exceptions\AIException;
use Edfavieljr\LaravelAIBridge\Services\ProviderFactory;
use Edfavieljr\LaravelAIBridge\Support\Conversation;
use Edfavieljr\LaravelAIBridge\Support\StructuredOutput;

class AIService implements AIServiceInterface
{
    /**
     * The configuration array.
     *
     * @var array
     */
    protected array $config;
    protected ?ProviderFactory $factory = null;
    protected string $currentProviderName;
    protected ?string $currentModel = null;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->currentProviderName = $config['default'] ?? Provider::OpenAI->value;
    }

    protected function getFactory(): ProviderFactory
    {
        return $this->factory ??= app(ProviderFactory::class);
    }

    /**
     * Switch to a specific provider.
     *
     * @param string $provider
     * @return $this
     */
    public function provider(string $provider): self
    {
        $this->currentProviderName = $provider;

        return $this;
    }

    /**
     * Set the model to use.
     *
     * @param string $model
     * @return $this
     */
    public function model(string $model): self
    {
        $this->currentModel = $model;

        return $this;
    }

    /**
     * Generate text using completion/chat models.
     *
     * @param string $prompt
     * @param array $options
     * @return string
     * @throws AIException
     */
    public function generateText(string $prompt, array $options = []): string
    {
        return $this->executeWithFallback('generateText', [$prompt, $this->mergeModelOption($options)]);
    }

    /**
     * Generate text embeddings.
     *
     * @param string|array $input
     * @param array $options
     * @return array
     * @throws AIException
     */
    public function generateEmbeddings(string|array $input, array $options = []): array
    {
        return $this->executeWithFallback('generateEmbeddings', [$input, $this->mergeModelOption($options)]);
    }

    /**
     * Analyze sentiment of the provided text.
     *
     * @param string $text
     * @param array $options
     * @return array
     * @throws AIException
     */
    public function analyzeSentiment(string $text, array $options = []): array
    {
        return $this->executeWithFallback('analyzeSentiment', [$text, $this->mergeModelOption($options)]);
    }

    /**
     * Classify text into categories.
     *
     * @param string $text
     * @param array $categories
     * @param array $options
     * @return array
     * @throws AIException
     */
    public function classifyText(string $text, array $categories, array $options = []): array
    {
        return $this->executeWithFallback('classifyText', [$text, $categories, $this->mergeModelOption($options)]);
    }

    /**
     * Generate an image from a text prompt.
     *
     * @param string $prompt
     * @param array $options
     * @return string
     * @throws AIException
     */
    public function generateImage(string $prompt, array $options = []): string
    {
        return $this->executeWithFallback('generateImage', [$prompt, $this->mergeModelOption($options)]);
    }

    /**
     * Extract entities from text.
     *
     * @param string $text
     * @param array $options
     * @return array
     * @throws AIException
     */
    public function extractEntities(string $text, array $options = []): array
    {
        return $this->executeWithFallback('extractEntities', [$text, $this->mergeModelOption($options)]);
    }

    /**
     * Generate text from a multi-turn conversation using native message arrays.
     */
    public function generateChat(array $messages, array $options = []): string
    {
        return $this->executeWithFallback('generateChat', [$messages, $this->mergeModelOption($options)]);
    }

    /**
     * Start a new conversation.
     */
    public function conversation(): Conversation
    {
        return new Conversation($this);
    }

    /**
     * Create a structured output request with schema validation.
     *
     * @param string $prompt
     * @param array<string, string> $schema Map of key => type ('string', 'int', 'float', 'bool', 'array')
     */
    public function structured(string $prompt, array $schema): StructuredOutput
    {
        return new StructuredOutput($prompt, $schema, $this);
    }

    /**
     * Stream text generation, yielding chunks as they arrive.
     *
     * @param string $prompt
     * @param array $options
     * @return \Generator<int, string, mixed, void>
     */
    public function streamText(string $prompt, array $options = []): \Generator
    {
        $provider = $this->resolveProvider($this->currentProviderName);

        $this->currentModel = null;

        return $provider->streamText($prompt, $this->mergeModelOption($options));
    }

    /**
     * Get the underlying provider client.
     *
     * @return mixed
     */
    public function getClient(): mixed
    {
        return $this->resolveProvider($this->currentProviderName)->getClient();
    }

    /**
     * Execute a method with fallback support.
     *
     * @param string $method
     * @param array $args
     * @return mixed
     * @throws AIException
     */
    protected function executeWithFallback(string $method, array $args): mixed
    {
        $this->checkRateLimit();

        $providers = $this->getFallbackProviders();
        $lastException = null;

        foreach ($providers as $providerName) {
            try {
                $provider = $this->resolveProvider($providerName);
                $result = $provider->{$method}(...$args);

                $this->currentModel = null;

                return $result;
            } catch (Exception $e) {
                $lastException = $e;

                if ($this->shouldLog()) {
                    Log::channel($this->config['logging']['channel'] ?? 'stack')
                        ->warning("AI provider '{$providerName}' failed for {$method}", [
                            'error' => $e->getMessage(),
                        ]);
                }

                if (!$this->isFallbackEnabled()) {
                    break;
                }
            }
        }

        $this->currentModel = null;

        throw new AIException(
            "All AI providers failed for {$method}: " . ($lastException ? $lastException->getMessage() : 'Unknown error'),
            0,
            $lastException,
            'all_providers_failed'
        );
    }

    /**
     * Get the list of providers to try (current + fallbacks).
     *
     * @return array
     */
    protected function getFallbackProviders(): array
    {
        if (!$this->isFallbackEnabled()) {
            return [$this->currentProviderName];
        }

        $fallbackProviders = $this->config['fallback']['providers'] ?? [];
        $providers = [$this->currentProviderName];

        foreach ($fallbackProviders as $provider) {
            if ($provider !== $this->currentProviderName) {
                $providers[] = $provider;
            }
        }

        return $providers;
    }

    /**
     * Resolve a provider instance by name.
     *
     * @param string $name
     * @return AIServiceInterface
     * @throws AIException
     */
    protected function resolveProvider(string $name): AIServiceInterface
    {
        return $this->getFactory()->makeFromString($name);
    }

    /**
     * Check rate limiting before making a request.
     *
     * @return void
     * @throws AIException
     */
    protected function checkRateLimit(): void
    {
        if (!($this->config['rate_limiting']['enabled'] ?? false)) {
            return;
        }

        $maxAttempts = $this->config['rate_limiting']['max_requests_per_minute'] ?? 60;
        $key = 'ai_rate_limit:' . ($this->currentProviderName);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);

            throw AIException::rateLimitExceeded($this->currentProviderName, $retryAfter);
        }

        RateLimiter::hit($key, 60);
    }

    /**
     * Merge the current model override into options.
     *
     * @param array $options
     * @return array
     */
    protected function mergeModelOption(array $options): array
    {
        if ($this->currentModel && !isset($options['model'])) {
            $options['model'] = $this->currentModel;
        }

        return $options;
    }

    /**
     * Determine if fallback is enabled.
     *
     * @return bool
     */
    protected function isFallbackEnabled(): bool
    {
        return $this->config['fallback']['enabled'] ?? false;
    }

    /**
     * Determine if logging is enabled.
     *
     * @return bool
     */
    protected function shouldLog(): bool
    {
        return $this->config['logging']['enabled'] ?? true;
    }
}
