<?php

declare(strict_types=1);

namespace Edfavieljr\LaravelAIBridge\Listeners;

use Edfavieljr\LaravelAIBridge\Events\AIRequestCompleted;
use Edfavieljr\LaravelAIBridge\Events\AIRequestFailed;
use Edfavieljr\LaravelAIBridge\Models\AICompletion;

final class StoreAICompletion
{
    public function handle(AIRequestCompleted|AIRequestFailed $event): void
    {
        if (!config('ai.storage.enabled', false)) {
            return;
        }

        if ($event instanceof AIRequestCompleted) {
            AICompletion::createSuccess(
                $event->provider->value,
                $event->model,
                $event->prompt,
                $event->completion,
                $event->metadata,
            );
        } else {
            AICompletion::createError(
                $event->provider->value,
                $event->model,
                $event->prompt,
                $event->error,
                $event->metadata,
            );
        }
    }
}
