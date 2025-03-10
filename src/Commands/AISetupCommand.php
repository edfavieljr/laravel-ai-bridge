<?php

namespace YourVendor\LaravelAIBridge\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AISetupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:setup 
                            {--provider= : The AI provider to set up (openai, huggingface, anthropic, gemini)}
                            {--key= : The API key for the provider}
                            {--model= : The default model to use}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set up AI integration for your Laravel application';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Setting up AI integration...');

        // Get or prompt for provider
        $provider = $this->option('provider');
        if (!$provider) {
            $provider = $this->choice(
                'Which AI provider would you like to set up?',
                ['openai', 'huggingface', 'anthropic', 'gemini'],
                0
            );
        }

        // Get or prompt for API key
        $key = $this->option('key');
        if (!$key) {
            $key = $this->secret("Enter your {$provider} API key");
            
            if (!$key) {
                $this->error('API key is required.');
                return 1;
            }
        }

        // Update .env file
        $this->updateEnvFile($provider, $key);

        // Set provider-specific options
        $this->setupProviderOptions($provider);

        $this->info('AI integration set up successfully!');
        $this->info('Remember to publish the configuration with:');
        $this->line('php artisan vendor:publish --provider="YourVendor\\LaravelAIBridge\\AIBridgeServiceProvider" --tag="ai-config"');

        return 0;
    }

    /**
     * Update the .env file with the API key.
     *
     * @param string $provider
     * @param string $key
     * @return void
     */
    protected function updateEnvFile(string $provider, string $key): void
    {
        $envFile = $this->laravel->environmentFilePath();
        $envContents = File::get($envFile);

        $keyVarName = strtoupper($provider) . '_API_KEY';

        // Check if the key already exists in the .env file
        if (preg_match("/^{$keyVarName}=/m", $envContents)) {
            // Update existing key
            $envContents = preg_replace(
                "/^{$keyVarName}=.*/m",
                "{$keyVarName}={$key}",
                $envContents
            );
        } else {
            // Add new key
            $envContents .= PHP_EOL . "{$keyVarName}={$key}";
        }

        // Also set as default provider if user confirms
        if ($this->confirm("Would you like to set {$provider} as your default AI provider?", true)) {
            if (preg_match("/^AI_PROVIDER=/m", $envContents)) {
                $envContents = preg_replace(
                    "/^AI_PROVIDER=.*/m",
                    "AI_PROVIDER={$provider}",
                    $envContents
                );
            } else {
                $envContents .= PHP_EOL . "AI_PROVIDER={$provider}";
            }
        }

        File::put($envFile, $envContents);
        
        $this->info("{$keyVarName} added to your .env file.");
    }

    /**
     * Set up additional options for the specified provider.
     *
     * @param string $provider
     * @return void
     */
    protected function setupProviderOptions(string $provider): void
    {
        switch ($provider) {
            case 'openai':
                $this->setupOpenAIOptions();
                break;
            case 'anthropic':
                $this->setupAnthropicOptions();
                break;
            case 'gemini':
                $this->setupGeminiOptions();
                break;
            case 'huggingface':
                $this->setupHuggingFaceOptions();
                break;
        }
    }

    /**
     * Set up OpenAI-specific options.
     *
     * @return void
     */
    protected function setupOpenAIOptions(): void
    {
        if ($this->confirm('Would you like to set an organization ID for OpenAI?', false)) {
            $org = $this->ask('Enter your OpenAI organization ID');
            
            $envFile = $this->laravel->environmentFilePath();
            $envContents = File::get($envFile);
            
            if (preg_match("/^OPENAI_ORGANIZATION=/m", $envContents)) {
                $envContents = preg_replace(
                    "/^OPENAI_ORGANIZATION=.*/m",
                    "OPENAI_ORGANIZATION={$org}",
                    $envContents
                );
            } else {
                $envContents .= PHP_EOL . "OPENAI_ORGANIZATION={$org}";
            }
            
            File::put($envFile, $envContents);
        }
        
        $model = $this->option('model') ?: $this->choice(
            'Which default model would you like to use?',
            ['gpt-4', 'gpt-4-turbo', 'gpt-3.5-turbo', 'gpt-3.5-turbo-16k'],
            0
        );
        
        $envFile = $this->laravel->environmentFilePath();
        $envContents = File::get($envFile);
        
        if (preg_match("/^OPENAI_DEFAULT_MODEL=/m", $envContents)) {
            $envContents = preg_replace(
                "/^OPENAI_DEFAULT_MODEL=.*/m",
                "OPENAI_DEFAULT_MODEL={$model}",
                $envContents
            );
        } else {
            $envContents .= PHP_EOL . "OPENAI_DEFAULT_MODEL={$model}";
        }
        
        File::put($envFile, $envContents);
    }

    /**
     * Set up Anthropic-specific options.
     *
     * @return void
     */
    protected function setupAnthropicOptions(): void
    {
        $model = $this->option('model') ?: $this->choice(
            'Which default Claude model would you like to use?',
            ['claude-3-opus-20240229', 'claude-3-sonnet-20240229', 'claude-3-haiku-20240307'],
            0
        );
        
        $envFile = $this->laravel->environmentFilePath();
        $envContents = File::get($envFile);
        
        if (preg_match("/^ANTHROPIC_DEFAULT_MODEL=/m", $envContents)) {
            $envContents = preg_replace(
                "/^ANTHROPIC_DEFAULT_MODEL=.*/m",
                "ANTHROPIC_DEFAULT_MODEL={$model}",
                $envContents
            );
        } else {
            $envContents .= PHP_EOL . "ANTHROPIC_DEFAULT_MODEL={$model}";
        }
        
        File::put($envFile, $envContents);
    }

    /**
     * Set up Gemini-specific options.
     *
     * @return void
     */
    protected function setupGeminiOptions(): void
    {
        if ($this->confirm('Would you like to set a Google Cloud Project ID for Gemini?', false)) {
            $projectId = $this->ask('Enter your Google Cloud Project ID');
            
            $envFile = $this->laravel->environmentFilePath();
            $envContents = File::get($envFile);
            
            if (preg_match("/^GEMINI_PROJECT_ID=/m", $envContents)) {
                $envContents = preg_replace(
                    "/^GEMINI_PROJECT_ID=.*/m",
                    "GEMINI_PROJECT_ID={$projectId}",
                    $envContents
                );
            } else {
                $envContents .= PHP_EOL . "GEMINI_PROJECT_ID={$projectId}";
            }
            
            File::put($envFile, $envContents);
        }
        
        $model = $this->option('model') ?: $this->choice(
            'Which default Gemini model would you like to use?',
            ['gemini-1.5-pro', 'gemini-1.5-flash', 'gemini-1.0-pro'],
            0
        );
        
        $envFile = $this->laravel->environmentFilePath();
        $envContents = File::get($envFile);
        
        if (preg_match("/^GEMINI_DEFAULT_MODEL=/m", $envContents)) {
            $envContents = preg_replace(
                "/^GEMINI_DEFAULT_MODEL=.*/m",
                "GEMINI_DEFAULT_MODEL={$model}",
                $envContents
            );
        } else {
            $envContents .= PHP_EOL . "GEMINI_DEFAULT_MODEL={$model}";
        }
        
        File::put($envFile, $envContents);
    }

    /**
     * Set up HuggingFace-specific options.
     *
     * @return void
     */
    protected function setupHuggingFaceOptions(): void
    {
        $model = $this->option('model') ?: $this->ask(
            'Enter the default HuggingFace model you would like to use (e.g., gpt2, t5-base)',
            'gpt2'
        );
        
        $envFile = $this->laravel->environmentFilePath();
        $envContents = File::get($envFile);
        
        if (preg_match("/^HUGGINGFACE_DEFAULT_MODEL=/m", $envContents)) {
            $envContents = preg_replace(
                "/^HUGGINGFACE_DEFAULT_MODEL=.*/m",
                "HUGGINGFACE_DEFAULT_MODEL={$model}",
                $envContents
            );
        } else {
            $envContents .= PHP_EOL . "HUGGINGFACE_DEFAULT_MODEL={$model}";
        }
        
        File::put($envFile, $envContents);
    }
}