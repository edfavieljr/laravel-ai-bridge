<?php

declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Events;

use Edfavieljr\LaravelAIBridge\Enums\Provider;

final class AIRequestFailed
{
    public function __construct(
        public readonly Provider $provider,
        public readonly string $model,
        public readonly string $prompt,
        public readonly string $error,
        public readonly array $metadata = [],
    ) {}
}
