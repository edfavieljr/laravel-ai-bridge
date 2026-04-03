<?php

declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Traits;

use Edfavieljr\LaravelAIBridge\Contracts\AIServiceInterface;
use Edfavieljr\LaravelAIBridge\Services\AIService;

trait HasAICapabilities
{
    /**
     * Override in your model to configure provider/model defaults.
     */
    protected function aiConfig(): array
    {
        return [
            'provider' => null,
            'model' => null,
        ];
    }

    protected function aiService(): AIServiceInterface
    {
        $service = app(AIService::class);
        $config = $this->aiConfig();

        if ($config['provider'] ?? null) {
            $service = $service->provider($config['provider']);
        }
        if ($config['model'] ?? null) {
            $service = $service->model($config['model']);
        }

        return $service;
    }

    public function aiGenerateText(string $attribute, ?string $template = null, array $options = []): string
    {
        $value = $this->{$attribute} ?? null;

        if (!$value) {
            return '';
        }

        $prompt = $template ? sprintf($template, $value) : $value;

        return $this->aiService()->generateText($prompt, $options);
    }

    public function aiEmbed(string $attribute, array $options = []): array
    {
        $value = $this->{$attribute} ?? null;

        if (!$value) {
            return [];
        }

        return $this->aiService()->generateEmbeddings($value, $options);
    }

    public function aiAnalyzeSentiment(string $attribute, array $options = []): array
    {
        $value = $this->{$attribute} ?? null;

        if (!$value) {
            return ['score' => 0, 'category' => 'neutral'];
        }

        return $this->aiService()->analyzeSentiment($value, $options);
    }

    public function aiClassify(string $attribute, array $categories, array $options = []): array
    {
        $value = $this->{$attribute} ?? null;

        if (!$value) {
            return ['category' => $categories[0] ?? 'unknown', 'confidence' => 0];
        }

        return $this->aiService()->classifyText($value, $categories, $options);
    }

    public function aiExtractEntities(string $attribute, array $options = []): array
    {
        $value = $this->{$attribute} ?? null;

        if (!$value) {
            return [];
        }

        return $this->aiService()->extractEntities($value, $options);
    }

    public function aiGenerateImage(string $attribute, array $options = []): string
    {
        $value = $this->{$attribute} ?? null;

        if (!$value) {
            return '';
        }

        return $this->aiService()->generateImage($value, $options);
    }

    public function aiSummarize(string $attribute, int $maxLength = 100, array $options = []): string
    {
        $value = $this->{$attribute} ?? null;

        if (!$value) {
            return '';
        }

        $prompt = "Summarize the following text in {$maxLength} characters or less: {$value}";

        return $this->aiService()->generateText($prompt, $options);
    }

    public function aiTranslate(string $attribute, string $targetLanguage, array $options = []): string
    {
        $value = $this->{$attribute} ?? null;

        if (!$value) {
            return '';
        }

        $prompt = "Translate the following text to {$targetLanguage}: {$value}";

        return $this->aiService()->generateText($prompt, $options);
    }

    // --- Deprecated methods (backward compatibility) ---

    /** @deprecated Use aiGenerateText() */
    public function completeText(string $attribute, ?string $promptTemplate = null, array $options = []): string
    {
        return $this->aiGenerateText($attribute, $promptTemplate, $options);
    }

    /** @deprecated Use aiEmbed() */
    public function embedAttribute(string $attribute, array $options = []): array
    {
        return $this->aiEmbed($attribute, $options);
    }

    /** @deprecated Use aiAnalyzeSentiment() */
    public function analyzeSentimentOf(string $attribute, array $options = []): array
    {
        return $this->aiAnalyzeSentiment($attribute, $options);
    }

    /** @deprecated Use aiClassify() */
    public function classifyAttribute(string $attribute, array $categories, array $options = []): array
    {
        return $this->aiClassify($attribute, $categories, $options);
    }

    /** @deprecated Use aiExtractEntities() */
    public function extractEntitiesFrom(string $attribute, array $options = []): array
    {
        return $this->aiExtractEntities($attribute, $options);
    }

    /** @deprecated Use aiGenerateImage() */
    public function generateImageFrom(string $attribute, array $options = []): string
    {
        return $this->aiGenerateImage($attribute, $options);
    }

    /** @deprecated Use aiSummarize() */
    public function summarizeAttribute(string $attribute, int $maxLength = 100, array $options = []): string
    {
        return $this->aiSummarize($attribute, $maxLength, $options);
    }

    /** @deprecated Use aiTranslate() */
    public function translateAttribute(string $attribute, string $targetLanguage, array $options = []): string
    {
        return $this->aiTranslate($attribute, $targetLanguage, $options);
    }
}
