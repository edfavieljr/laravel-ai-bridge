<?php

declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Services;

use Illuminate\Contracts\Container\Container;
use Edfavieljr\LaravelAIBridge\Contracts\AIServiceInterface;
use Edfavieljr\LaravelAIBridge\Enums\Provider;
use Edfavieljr\LaravelAIBridge\Exceptions\AIException;

final class ProviderFactory
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function make(Provider $provider): AIServiceInterface
    {
        return match ($provider) {
            Provider::OpenAI => $this->container->make(OpenAIService::class),
            Provider::Anthropic => $this->container->make(AnthropicService::class),
            Provider::Gemini => $this->container->make(GeminiService::class),
            Provider::HuggingFace => $this->container->make(HuggingFaceService::class),
        };
    }

    public function makeFromString(string $name): AIServiceInterface
    {
        $provider = Provider::tryFrom($name);

        if ($provider === null) {
            throw new AIException(
                "Unsupported AI provider: {$name}",
                0,
                null,
                'invalid_provider'
            );
        }

        return $this->make($provider);
    }
}
