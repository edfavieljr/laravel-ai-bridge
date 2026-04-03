<?php

declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Edfavieljr\LaravelAIBridge\Commands\AISetupCommand;
use Edfavieljr\LaravelAIBridge\Commands\AIPurgeCommand;
use Edfavieljr\LaravelAIBridge\Contracts\AIServiceInterface;
use Edfavieljr\LaravelAIBridge\Enums\Provider;
use Edfavieljr\LaravelAIBridge\Events\AIRequestCompleted;
use Edfavieljr\LaravelAIBridge\Events\AIRequestFailed;
use Edfavieljr\LaravelAIBridge\Listeners\StoreAICompletion;
use Edfavieljr\LaravelAIBridge\Services\AIService;
use Edfavieljr\LaravelAIBridge\Services\AnthropicService;
use Edfavieljr\LaravelAIBridge\Services\GeminiService;
use Edfavieljr\LaravelAIBridge\Services\HuggingFaceService;
use Edfavieljr\LaravelAIBridge\Services\OpenAIService;
use Edfavieljr\LaravelAIBridge\Services\ProviderFactory;

class AIBridgeServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ai.php', 'ai');

        $this->app->singleton(ProviderFactory::class);

        $this->app->singleton(AIService::class, function ($app) {
            return new AIService($app['config']['ai']);
        });

        $this->app->singleton(OpenAIService::class, function ($app) {
            return new OpenAIService(
                $app['config']['ai.providers.openai.api_key'] ?? '',
                $app['config']['ai.providers.openai.organization'] ?? null,
                $app['config']['ai.providers.openai.options'] ?? [],
            );
        });

        $this->app->singleton(HuggingFaceService::class, function ($app) {
            return new HuggingFaceService(
                $app['config']['ai.providers.huggingface.api_key'] ?? '',
                $app['config']['ai.providers.huggingface.options'] ?? [],
            );
        });

        $this->app->singleton(AnthropicService::class, function ($app) {
            return new AnthropicService(
                $app['config']['ai.providers.anthropic.api_key'] ?? '',
                $app['config']['ai.providers.anthropic.options'] ?? [],
            );
        });

        $this->app->singleton(GeminiService::class, function ($app) {
            return new GeminiService(
                $app['config']['ai.providers.gemini.api_key'] ?? '',
                $app['config']['ai.providers.gemini.options'] ?? [],
            );
        });

        $this->app->bind(AIServiceInterface::class, function ($app) {
            $defaultProvider = Provider::tryFrom($app['config']['ai.default'] ?? 'openai');

            if ($defaultProvider === null) {
                throw new \InvalidArgumentException(
                    "Unsupported AI provider: {$app['config']['ai.default']}"
                );
            }

            return $app->make(ProviderFactory::class)->make($defaultProvider);
        });
    }

    public function boot(): void
    {
        Event::listen(AIRequestCompleted::class, StoreAICompletion::class);
        Event::listen(AIRequestFailed::class, StoreAICompletion::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/ai.php' => config_path('ai.php'),
            ], 'ai-config');

            $this->publishes([
                __DIR__.'/../database/migrations/create_ai_completions_table.php.stub' => database_path(
                    'migrations/' . date('Y_m_d_His') . '_create_ai_completions_table.php'
                ),
            ], 'ai-migrations');

            $this->commands([
                AISetupCommand::class,
                AIPurgeCommand::class,
            ]);
        }

        $this->loadHelpers();
    }

    protected function loadHelpers(): void
    {
        require_once __DIR__.'/Helpers/ai_helpers.php';
    }

    /** @return array<int, string> */
    public function provides(): array
    {
        return [
            AIService::class,
            OpenAIService::class,
            AnthropicService::class,
            GeminiService::class,
            HuggingFaceService::class,
            ProviderFactory::class,
            AIServiceInterface::class,
        ];
    }
}
