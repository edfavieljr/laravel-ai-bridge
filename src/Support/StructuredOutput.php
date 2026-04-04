<?php

declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Support;

use Edfavieljr\LaravelAIBridge\Contracts\AIServiceInterface;
use Edfavieljr\LaravelAIBridge\Exceptions\AIException;
use Edfavieljr\LaravelAIBridge\Services\AIService;

class StructuredOutput
{
    protected string $prompt;
    protected array $schema;
    protected array $options = [];
    protected AIServiceInterface $service;

    public function __construct(string $prompt, array $schema, ?AIServiceInterface $service = null)
    {
        $this->prompt = $prompt;
        $this->schema = $schema;
        $this->service = $service ?? app(AIService::class);
    }

    public function withOptions(array $options): self
    {
        $this->options = array_merge($this->options, $options);
        return $this;
    }

    /**
     * Generate structured output matching the schema.
     *
     * @return array The validated structured response
     * @throws AIException If response doesn't match schema after retries
     */
    public function generate(): array
    {
        $schemaDescription = $this->describeSchema($this->schema);

        $wrappedPrompt = "{$this->prompt}\n\nRespond with ONLY a valid JSON object matching this schema:\n{$schemaDescription}\n\nNo additional text, no markdown, just the JSON object.";

        $response = $this->service->generateText($wrappedPrompt, $this->options);

        $parsed = $this->parseResponse($response);

        $this->validateAgainstSchema($parsed);

        return $parsed;
    }

    protected function parseResponse(string $response): array
    {
        $cleaned = trim($response);

        // Strip markdown code fences if present
        if (str_starts_with($cleaned, '```')) {
            $cleaned = preg_replace('/^```(?:json)?\s*\n?/', '', $cleaned);
            $cleaned = preg_replace('/\n?```\s*$/', '', $cleaned);
        }

        $result = json_decode($cleaned, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Try to extract JSON from the response
            if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
                $result = json_decode($matches[0], true);
            }

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new AIException(
                    'Failed to parse structured output as JSON: ' . json_last_error_msg(),
                    0,
                    null,
                    'parse_error'
                );
            }
        }

        return $result;
    }

    protected function validateAgainstSchema(array $data): void
    {
        foreach ($this->schema as $key => $type) {
            if (!array_key_exists($key, $data)) {
                throw new AIException(
                    "Structured output missing required key: {$key}",
                    0,
                    null,
                    'validation_error'
                );
            }

            if ($type !== 'mixed' && !$this->matchesType($data[$key], $type)) {
                $actualType = gettype($data[$key]);
                throw new AIException(
                    "Structured output key '{$key}' expected type '{$type}', got '{$actualType}'",
                    0,
                    null,
                    'validation_error'
                );
            }
        }
    }

    protected function matchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'int', 'integer' => is_int($value),
            'float', 'double', 'number' => is_numeric($value),
            'bool', 'boolean' => is_bool($value),
            'array' => is_array($value),
            'null', 'nullable' => true,
            default => true,
        };
    }

    protected function describeSchema(array $schema): string
    {
        $lines = ['{'];

        foreach ($schema as $key => $type) {
            $lines[] = "  \"{$key}\": <{$type}>,";
        }

        $lines[] = '}';

        return implode("\n", $lines);
    }
}
