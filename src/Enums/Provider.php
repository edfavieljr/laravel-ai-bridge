<?php

declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Enums;

enum Provider: string
{
    case OpenAI = 'openai';
    case Anthropic = 'anthropic';
    case Gemini = 'gemini';
    case HuggingFace = 'huggingface';

    public function label(): string
    {
        return match ($this) {
            self::OpenAI => 'OpenAI',
            self::Anthropic => 'Anthropic',
            self::Gemini => 'Gemini',
            self::HuggingFace => 'HuggingFace',
        };
    }
}
