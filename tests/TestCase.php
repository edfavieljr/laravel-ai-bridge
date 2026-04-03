<?php
declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Tests;

use Edfavieljr\LaravelAIBridge\AIBridgeServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            AIBridgeServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'AI' => \Edfavieljr\LaravelAIBridge\Facades\AI::class,
            'OpenAI' => \Edfavieljr\LaravelAIBridge\Facades\OpenAI::class,
            'Anthropic' => \Edfavieljr\LaravelAIBridge\Facades\Anthropic::class,
            'Gemini' => \Edfavieljr\LaravelAIBridge\Facades\Gemini::class,
            'HuggingFace' => \Edfavieljr\LaravelAIBridge\Facades\HuggingFace::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('ai.default', 'openai');
        $app['config']->set('ai.cache.enabled', false);
        $app['config']->set('ai.logging.enabled', false);
        $app['config']->set('ai.storage.enabled', false);
        $app['config']->set('ai.rate_limiting.enabled', false);
        $app['config']->set('ai.fallback.enabled', false);
        $app['config']->set('ai.fallback.providers', ['openai', 'anthropic', 'gemini', 'huggingface']);

        $app['config']->set('ai.providers.openai.api_key', 'test-openai-key');
        $app['config']->set('ai.providers.openai.options', [
            'timeout' => 5,
            'default_model' => 'gpt-4',
            'default_embedding_model' => 'text-embedding-3-large',
            'default_image_model' => 'dall-e-3',
        ]);

        $app['config']->set('ai.providers.anthropic.api_key', 'test-anthropic-key');
        $app['config']->set('ai.providers.anthropic.options', [
            'timeout' => 5,
            'default_model' => 'claude-sonnet-4-20250514',
        ]);

        $app['config']->set('ai.providers.gemini.api_key', 'test-gemini-key');
        $app['config']->set('ai.providers.gemini.options', [
            'timeout' => 5,
            'default_model' => 'gemini-2.0-flash',
            'default_embedding_model' => 'text-embedding-004',
        ]);

        $app['config']->set('ai.providers.huggingface.api_key', 'test-hf-key');
        $app['config']->set('ai.providers.huggingface.options', [
            'timeout' => 5,
            'default_model' => 'gpt2',
            'default_embedding_model' => 'sentence-transformers/all-mpnet-base-v2',
        ]);
    }
}
