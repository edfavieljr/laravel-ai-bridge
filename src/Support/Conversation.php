<?php

declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Support;

use Edfavieljr\LaravelAIBridge\Contracts\AIServiceInterface;
use Edfavieljr\LaravelAIBridge\Services\AIService;

class Conversation
{
    /** @var list<array{role: string, content: string}> */
    protected array $messages = [];

    protected ?string $systemPrompt = null;
    protected array $options = [];
    protected AIServiceInterface $service;

    public function __construct(?AIServiceInterface $service = null)
    {
        $this->service = $service ?? app(AIService::class);
    }

    public function system(string $prompt): self
    {
        $this->systemPrompt = $prompt;
        return $this;
    }

    public function withOptions(array $options): self
    {
        $this->options = array_merge($this->options, $options);
        return $this;
    }

    public function user(string $message): self
    {
        $this->messages[] = ['role' => 'user', 'content' => $message];
        return $this;
    }

    public function assistant(string $message): self
    {
        $this->messages[] = ['role' => 'assistant', 'content' => $message];
        return $this;
    }

    public function send(string $message): string
    {
        $this->user($message);

        $options = $this->options;
        if ($this->systemPrompt) {
            $options['system'] = $this->systemPrompt;
        }

        $response = $this->service->generateChat($this->messages, $options);

        $this->assistant($response);

        return $response;
    }

    public function reply(string $message): string
    {
        return $this->send($message);
    }

    /** @return list<array{role: string, content: string}> */
    public function messages(): array
    {
        return $this->messages;
    }

    public function getSystemPrompt(): ?string
    {
        return $this->systemPrompt;
    }

    public function reset(): self
    {
        $this->messages = [];
        return $this;
    }
}
