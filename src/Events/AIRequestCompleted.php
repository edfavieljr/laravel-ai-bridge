<?php

declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Events;

use Edfavieljr\LaravelAIBridge\Enums\Provider;

final class AIRequestCompleted
{
    public function __construct(
        public readonly Provider $provider,
        public readonly string $model,
        public readonly string $prompt,
        public readonly string $completion,
        public readonly array $metadata = [],
    ) {}
}
