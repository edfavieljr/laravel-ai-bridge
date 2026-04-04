<?php

declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Services\Concerns;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Edfavieljr\LaravelAIBridge\Enums\Provider;
use Edfavieljr\LaravelAIBridge\Events\AIRequestCompleted;
use Edfavieljr\LaravelAIBridge\Events\AIRequestFailed;

trait HasAIInstrumentation
{
    protected ?Closure $userResolver = null;

    abstract protected function providerName(): Provider;

    public function setUserResolver(Closure $resolver): static
    {
        $this->userResolver = $resolver;

        return $this;
    }

    protected function getCacheKey(string $method, mixed $input, array $options): string
    {
        $inputHash = is_string($input) ? md5($input) : md5(json_encode($input));
        $optionsHash = md5(json_encode($options));

        return "ai:{$this->providerName()->value}:{$method}:{$inputHash}:{$optionsHash}";
    }

    protected function shouldUseCache(): bool
    {
        return (bool) config('ai.cache.enabled', true);
    }

    protected function shouldLog(): bool
    {
        return (bool) config('ai.logging.enabled', true);
    }

    protected function shouldStoreInDatabase(): bool
    {
        return (bool) config('ai.storage.enabled', false);
    }

    protected function loggingChannel(): string
    {
        return config('ai.logging.channel', 'stack');
    }

    protected function cacheTtlMinutes(): int
    {
        return (int) config('ai.cache.ttl', 60);
    }

    protected function resolveUserId(): ?int
    {
        if ($this->userResolver) {
            return ($this->userResolver)();
        }

        try {
            /** @var \Illuminate\Contracts\Auth\Guard $guard */
            $guard = auth();

            return $guard->id();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function logRequest(string $endpoint, string $model, string $prompt, array $options = []): void
    {
        if (!$this->shouldLog()) {
            return;
        }

        Log::channel($this->loggingChannel())
            ->info("{$this->providerName()->label()} API Request", [
                'endpoint' => $endpoint,
                'model' => $model,
                'prompt' => $prompt,
            ]);
    }

    protected function logError(string $message, string $prompt, string $model): void
    {
        if (!$this->shouldLog()) {
            return;
        }

        Log::channel($this->loggingChannel())
            ->error("{$this->providerName()->label()} API Error", [
                'message' => $message,
                'prompt' => $prompt,
                'model' => $model,
            ]);
    }

    protected function storeSuccess(
        string $model,
        string $prompt,
        string $completion,
        array $extra = []
    ): void {
        $extra['user_id'] = $extra['user_id'] ?? $this->resolveUserId();
        $extra['request_data'] = $this->redactSensitiveData($extra['request_data'] ?? []);
        $extra['response_data'] = $this->redactSensitiveData($extra['response_data'] ?? []);

        Event::dispatch(new AIRequestCompleted(
            provider: $this->providerName(),
            model: $model,
            prompt: $prompt,
            completion: $completion,
            metadata: $extra,
        ));
    }

    protected function storeError(
        string $model,
        string $prompt,
        string $error,
        array $extra = []
    ): void {
        $extra['user_id'] = $extra['user_id'] ?? $this->resolveUserId();
        $extra['request_data'] = $this->redactSensitiveData($extra['request_data'] ?? []);
        $extra['response_data'] = $this->redactSensitiveData($extra['response_data'] ?? []);

        Event::dispatch(new AIRequestFailed(
            provider: $this->providerName(),
            model: $model,
            prompt: $prompt,
            error: $error,
            metadata: $extra,
        ));
    }

    /**
     * Execute a callable with retry and exponential backoff for transient errors.
     *
     * @param callable $callback The API call to execute
     * @param int $maxAttempts Maximum retry attempts (default from config)
     * @return mixed The result of the callback
     */
    protected function retryRequest(callable $callback, int $maxAttempts = 3): mixed
    {
        return retry(
            times: $maxAttempts,
            callback: $callback,
            sleepMilliseconds: 500,
            when: function (\Throwable $e) {
                if ($e instanceof \Edfavieljr\LaravelAIBridge\Exceptions\AIException) {
                    return in_array($e->getCode(), [429, 503, 500], true);
                }
                return false;
            },
        );
    }

    protected function cacheResult(string $key, mixed $value): void
    {
        if ($this->shouldUseCache()) {
            Cache::put($key, $value, now()->addMinutes($this->cacheTtlMinutes()));
        }
    }

    protected function getCachedResult(string $key): mixed
    {
        if ($this->shouldUseCache() && Cache::has($key)) {
            return Cache::get($key);
        }

        return null;
    }

    protected function parseJsonResponse(string $response, array $defaults): array
    {
        $json = json_decode($response, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            return $json;
        }

        if (preg_match('/\{[^}]+\}/', $response, $matches)) {
            $json = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                return $json;
            }
        }

        return array_merge($defaults, ['raw_response' => $response]);
    }

    protected function redactSensitiveData(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        $sensitiveKeys = [
            'api_key', 'apiKey', 'api-key',
            'Authorization', 'authorization',
            'x-api-key', 'x-goog-api-key',
            'secret', 'password', 'token',
        ];

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array($key, $sensitiveKeys, true)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->redactSensitiveData($value);
            }
        }

        return $data;
    }
}
