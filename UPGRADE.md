# Upgrade Guide

## Upgrading to v2.0 (from v1.x)

Version 2.0 removes all features that were deprecated in v1.x. This guide covers every breaking change and the exact replacement for each.

### Provider-Specific Facades Removed

The `OpenAI::`, `Anthropic::`, `Gemini::`, and `HuggingFace::` facades have been removed. Use `AI::provider()` instead.

| Before (v1.x) | After (v2.0) |
|----------------|--------------|
| `OpenAI::generateText('prompt')` | `AI::provider('openai')->generateText('prompt')` |
| `Anthropic::generateText('prompt')` | `AI::provider('anthropic')->generateText('prompt')` |
| `Gemini::generateText('prompt')` | `AI::provider('gemini')->generateText('prompt')` |
| `HuggingFace::generateText('prompt')` | `AI::provider('huggingface')->generateText('prompt')` |
| `OpenAI::model('gpt-4o')->generateText('prompt')` | `AI::provider('openai')->model('gpt-4o')->generateText('prompt')` |

**Search and replace in your codebase:**

```
OpenAI::    → AI::provider('openai')->
Anthropic:: → AI::provider('anthropic')->
Gemini::    → AI::provider('gemini')->
HuggingFace:: → AI::provider('huggingface')->
```

Also remove the facade aliases from your `config/app.php` if you registered them manually.

### Helper Functions Removed

All global helper functions have been removed. Use the `AI` facade directly.

| Before (v1.x) | After (v2.0) |
|----------------|--------------|
| `ai('prompt')` | `AI::generateText('prompt')` |
| `ai()` (no args) | `app(AIService::class)` |
| `ai_embed($input)` | `AI::generateEmbeddings($input)` |
| `ai_sentiment($text)` | `AI::analyzeSentiment($text)` |
| `ai_classify($text, $cats)` | `AI::classifyText($text, $cats)` |
| `ai_image($prompt)` | `AI::generateImage($prompt)` |
| `ai_entities($text)` | `AI::extractEntities($text)` |
| `ai_provider($name)` | `AI::provider($name)` |
| `ai_model($name)` | `AI::model($name)` |

### Eloquent Trait Method Names Changed

The `HasAICapabilities` trait methods have been renamed for consistency. Old method names have been removed.

| Before (v1.x) | After (v2.0) |
|----------------|--------------|
| `$model->completeText('attr')` | `$model->aiGenerateText('attr')` |
| `$model->embedAttribute('attr')` | `$model->aiEmbed('attr')` |
| `$model->analyzeSentimentOf('attr')` | `$model->aiAnalyzeSentiment('attr')` |
| `$model->classifyAttribute('attr', $cats)` | `$model->aiClassify('attr', $cats)` |
| `$model->extractEntitiesFrom('attr')` | `$model->aiExtractEntities('attr')` |
| `$model->generateImageFrom('attr')` | `$model->aiGenerateImage('attr')` |
| `$model->summarizeAttribute('attr')` | `$model->aiSummarize('attr')` |
| `$model->translateAttribute('attr', 'es')` | `$model->aiTranslate('attr', 'es')` |

The trait also now supports `aiConfig()` for per-model provider/model defaults:

```php
protected function aiConfig(): array
{
    return [
        'provider' => 'anthropic',
        'model' => 'claude-sonnet-4-20250514',
    ];
}
```

### Automated Migration

You can use search-and-replace across your project to handle most changes:

```bash
# Facades (if using IDE with regex replace)
# Search:  (OpenAI|Anthropic|Gemini|HuggingFace)::
# Replace: AI::provider('$1')->

# Helpers
grep -rn "ai_sentiment\|ai_embed\|ai_classify\|ai_image\|ai_entities\|ai_provider\|ai_model" app/

# Trait methods
grep -rn "completeText\|embedAttribute\|analyzeSentimentOf\|classifyAttribute\|extractEntitiesFrom\|generateImageFrom\|summarizeAttribute\|translateAttribute" app/
```
