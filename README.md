# Laravel AI Bridge

A powerful, elegant library for integrating multiple AI providers into Laravel applications through a unified API.

## Introduction

Laravel AI Bridge provides seamless integration with leading AI providers (OpenAI, Anthropic Claude, Google Gemini, and Hugging Face) through a consistent, Laravel-style interface. The library abstracts away the complexities of working with different AI APIs, allowing developers to focus on building features rather than managing API implementations.

## Features

- **Unified API** for multiple AI providers
- **Provider-specific facades** for direct access to specialized features
- **Intelligent caching** to reduce API costs
- **Automatic fallback** between providers for increased reliability
- **Laravel-style syntax** with facades, helpers, and fluent interfaces
- **Eloquent integration** with model traits for AI capabilities
- **Comprehensive logging** and error handling

## Installation

### Requirements

- PHP 8.1 or higher
- Laravel 9.0 or higher
- Composer

### Via Composer

```bash
composer require edfavieljr/laravel-ai-bridge
```

### Publish Configuration

After installing the package, publish the configuration file:

```bash
php artisan vendor:publish --provider="edfavieljr\LaravelAIBridge\AIBridgeServiceProvider" --tag="ai-config"
```

## Quick Setup

The quickest way to get started is using the included setup command:

```bash
php artisan ai:setup
```

This interactive command will guide you through:
1. Selecting your preferred AI provider
2. Configuring your API keys
3. Setting default models
4. Updating your `.env` file automatically

## Manual Configuration

### Environment Variables

Add the following variables to your `.env` file:

```
# Default provider
AI_PROVIDER=openai

# OpenAI Configuration
OPENAI_API_KEY=your-openai-key
OPENAI_ORGANIZATION=your-organization-id  # Optional
OPENAI_DEFAULT_MODEL=gpt-4

# Anthropic Configuration
ANTHROPIC_API_KEY=your-anthropic-key
ANTHROPIC_DEFAULT_MODEL=claude-3-opus-20240229

# Google Gemini Configuration
GEMINI_API_KEY=your-gemini-key
GEMINI_PROJECT_ID=your-gcp-project-id  # Optional, for Vertex AI
GEMINI_DEFAULT_MODEL=gemini-1.5-pro

# Hugging Face Configuration
HUGGINGFACE_API_KEY=your-huggingface-key
HUGGINGFACE_DEFAULT_MODEL=gpt2
```

### Configuration Options

The `config/ai.php` file contains detailed settings for:

- Default provider
- Caching behavior
- Provider fallback options
- Rate limiting
- Logging
- Database storage for API calls
- Provider-specific configuration

## Basic Usage

### Using the Main Facade

```php
use edfavieljr\LaravelAIBridge\Facades\AI;

// Generate text with default provider
$response = AI::generateText('Explain quantum computing in simple terms');

// Analyze sentiment
$sentiment = AI::analyzeSentiment('I absolutely love this product!');

// Generate embeddings for semantic search
$embeddings = AI::generateEmbeddings('Text to convert to vector representation');

// Classify text into categories
$classification = AI::classifyText(
    'The battery drains too quickly on this phone',
    ['hardware_issue', 'software_issue', 'battery_problem', 'user_experience']
);

// Extract entities
$entities = AI::extractEntities('Apple announced their new iPhone yesterday in California');
```

### Using Global Helper Functions

```php
// Generate text
$explanation = ai('Explain how blockchain works in simple terms');

// Analyze sentiment
$sentiment = ai_sentiment('The customer service was terrible and I want a refund');

// Generate embeddings
$embeddings = ai_embed('Vector representation for semantic search');

// Classify text
$category = ai_classify(
    'The screen keeps freezing after the update',
    ['hardware_issue', 'software_bug', 'compatibility_problem', 'user_error']
);

// Extract entities
$entities = ai_entities('Microsoft CEO Satya Nadella announced a new partnership with OpenAI');
```

## Working with Specific Providers

### OpenAI

```php
use edfavieljr\LaravelAIBridge\Facades\OpenAI;

// Direct access via provider-specific facade
$completion = OpenAI::generateText('Write a poem about autumn');

// Generate an image
$imageUrl = OpenAI::generateImage('A futuristic city with flying cars');

// Using the main facade with provider specification
$completion = AI::provider('openai')
    ->model('gpt-4')
    ->generateText('Explain the theory of relativity');
```

### Anthropic (Claude)

```php
use edfavieljr\LaravelAIBridge\Facades\Anthropic;

// Generate text with Claude
$completion = Anthropic::generateText('Write a summary of the last climate report');

// Using the main facade with provider specification
$completion = AI::provider('anthropic')
    ->model('claude-3-opus-20240229')
    ->generateText('Compare and contrast quantum computing and classical computing');
```

### Google Gemini

```php
use edfavieljr\LaravelAIBridge\Facades\Gemini;

// Generate text with Gemini
$completion = Gemini::generateText('Create a tutorial for machine learning beginners');

// Using the main facade with provider specification
$completion = AI::provider('gemini')
    ->model('gemini-1.5-pro')
    ->generateText('Explain how neural networks work');
```

### Hugging Face

```php
use edfavieljr\LaravelAIBridge\Facades\HuggingFace;

// Generate text with HuggingFace models
$completion = HuggingFace::model('gpt2')->generateText('Continue this story: Once upon a time');

// Generate embeddings with a specific model
$embeddings = HuggingFace::model('sentence-transformers/all-mpnet-base-v2')
    ->generateEmbeddings('Semantic search vector');
```

## Integrating with Eloquent Models

Add AI capabilities directly to your models:

```php
use Illuminate\Database\Eloquent\Model;
use edfavieljr\LaravelAIBridge\Traits\HasAICapabilities;

class Product extends Model
{
    use HasAICapabilities;
    
    // Your model implementation...
}
```

Then use the AI capabilities on your model instances:

```php
$product = Product::find(1);

// Generate a marketing description
$marketingText = $product->completeText(
    'description',
    'Rewrite this product description to be more compelling: %s'
);

// Analyze customer review sentiment
$sentiment = $product->analyzeSentimentOf('customer_review');

// Categorize product based on description
$category = $product->classifyAttribute(
    'description',
    ['electronics', 'clothing', 'home', 'sports']
);

// Generate an image for the product
$imageUrl = $product->generateImageFrom('description');

// Summarize product description
$summary = $product->summarizeAttribute('description', 100);

// Translate product description
$translated = $product->translateAttribute('description', 'Spanish');
```

## Advanced Features

### Automatic Fallback Between Providers

Configure fallback behavior in `config/ai.php`:

```php
'fallback' => [
    'enabled' => true,
    'providers' => ['openai', 'anthropic', 'gemini', 'huggingface'],
],
```

With fallback enabled, if the primary provider fails, the library automatically tries the next provider:

```php
// Will try OpenAI first, then fall back to other providers if it fails
$result = AI::provider('openai')->generateText('Explain quantum physics');
```

### Intelligent Caching

Configure caching in `config/ai.php`:

```php
'cache' => [
    'enabled' => true,
    'ttl' => 60, // minutes
],
```

Identical requests will be cached to reduce API costs:

```php
// First call hits the API
$result1 = AI::generateText('What is machine learning?');

// Second identical call uses cached result
$result2 = AI::generateText('What is machine learning?');
```

### Database Logging

Enable database storage to track AI usage:

```php
'storage' => [
    'enabled' => true,
    'purge_after_days' => 30,
],
```

Then publish and run the migration:

```bash
php artisan vendor:publish --provider="edfavieljr\LaravelAIBridge\AIBridgeServiceProvider" --tag="ai-migrations"
php artisan migrate
```

Query the logs:

```php
use edfavieljr\LaravelAIBridge\Models\AICompletion;

// Get all completions
$completions = AICompletion::all();

// Get completions from a specific provider
$openaiCompletions = AICompletion::fromProvider('openai')->get();

// Get token usage summary
$usageSummary = AICompletion::getTokenUsageSummary();
```

## Troubleshooting

### Common Issues

1. **API Key Authentication Failures**
   - Verify your API keys are correctly set in the `.env` file
   - Check for whitespace or special characters in your keys

2. **Rate Limiting**
   - Configure rate limiting settings in `config/ai.php`
   - Implement queue-based processing for high-volume applications

3. **Model Availability**
   - Ensure you have access to the selected models in your provider accounts
   - Some models require specific permissions or subscriptions

### Debugging

Enable detailed logging:

```php
'logging' => [
    'enabled' => true,
    'channel' => 'ai-logs', // Create this channel in your config/logging.php
],
```

## Contributing

Contributions are welcome! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).