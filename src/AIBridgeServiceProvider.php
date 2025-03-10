<?php

namespace Edfavieljr\LaravelAIBridge;

use Illuminate\Support\ServiceProvider;
use Edfavieljr\LaravelAIBridge\Commands\AISetupCommand;
use Edfavieljr\LaravelAIBridge\Services\AIService;
use Edfavieljr\LaravelAIBridge\Services\OpenAIService;
use Edfavieljr\LaravelAIBridge\Services\HuggingFaceService;
use Edfavieljr\LaravelAIBridge\Contracts\AIServiceInterface;

class AIBridgeServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__.'/../config/ai.php', 'ai'
        );
        
        // Register the main AI service
        $this->app->singleton(AIService::class, function ($app) {
            return new AIService($app['config']['ai']);
        });
        
        // Register individual provider services
        $this->app->singleton(OpenAIService::class, function ($app) {
            return new OpenAIService(
                $app['config']['ai.providers.openai.api_key'],
                $app['config']['ai.providers.openai.organization'] ?? null,
                $app['config']['ai.providers.openai.options'] ?? []
            );
        });
        
        $this->app->singleton(HuggingFaceService::class, function ($app) {
            return new HuggingFaceService(
                $app['config']['ai.providers.huggingface.api_key'],
                $app['config']['ai.providers.huggingface.options'] ?? []
            );
        });
        
        // Register the interface binding - default to configured provider
        $this->app->bind(AIServiceInterface::class, function ($app) {
            $defaultProvider = $app['config']['ai.default'];
            
            return match ($defaultProvider) {
                'openai' => $app->make(OpenAIService::class),
                'huggingface' => $app->make(HuggingFaceService::class),
                default => throw new \Exception("Unsupported AI provider: {$defaultProvider}")
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish configuration
            $this->publishes([
                __DIR__.'/../config/ai.php' => config_path('ai.php'),
            ], 'ai-config');
            
            // Register commands
            $this->commands([
                AISetupCommand::class,
            ]);
        }
        
        // Load helpers
        $this->loadHelpers();
    }
    
    /**
     * Load helper functions.
     */
    protected function loadHelpers(): void
    {
        require_once __DIR__.'/Helpers/ai_helpers.php';
    }
}